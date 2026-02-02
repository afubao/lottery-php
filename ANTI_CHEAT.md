# 防作弊机制说明

## 概述

抽奖组件提供了完善的防作弊机制，包括防重放攻击、结果签名验证、频率限制统计等功能。

## 功能特性

### 1. 防重放攻击（Nonce 机制）

**原理**：每次抽奖请求需要携带一个唯一的 nonce 值，服务器验证 nonce 是否已使用过，防止重复提交。

**使用流程**：

1. **前端获取 nonce**：
```php
use Leo\Lottery\Security\AntiCheatManager;

$antiCheatManager = app(AntiCheatManager::class);
$nonce = $antiCheatManager->generateNonce($openid);
// 返回给前端，前端在抽奖时携带此 nonce
```

2. **前端调用抽奖接口**：
```php
$result = $lotteryService->draw($openid, $ip, $nonce);
```

3. **服务器验证 nonce**：
- 如果 nonce 已使用过或已过期，抛出异常
- 验证成功后，nonce 会被删除，确保只能使用一次

**配置**：

```php
// config/lottery.php
'anti_cheat' => [
    'enabled' => true,
    'nonce_ttl' => 300, // nonce 过期时间（秒），默认5分钟
],
```

### 2. 抽奖结果签名验证

**原理**：使用 HMAC-SHA256 对抽奖结果进行签名，前端可以验证结果是否被篡改。

**配置**：

```php
// config/lottery.php
'anti_cheat' => [
    'enabled' => true,
    'secret_key' => 'your-secret-key-at-least-32-characters-long', // 签名密钥
],
```

**生成签名**：

服务器会自动为抽奖结果生成签名：

```php
$result = $lotteryService->draw($openid, $ip, $nonce);
// 返回结果包含 signature
// [
//     'draw_id' => '202501011234567890',
//     'prize' => [...],
//     'signature' => 'abc123...' // HMAC-SHA256 签名
// ]
```

**验证签名**：

```php
use Leo\Lottery\Security\AntiCheatManager;

$antiCheatManager = app(AntiCheatManager::class);
$isValid = $antiCheatManager->verifySignature(
    $result['draw_id'],
    $openid,
    $result['prize'],
    $result['signature']
);

if (!$isValid) {
    // 签名验证失败，结果可能被篡改
    throw new Exception('抽奖结果验证失败');
}
```

### 3. 抽奖记录唯一性检查

**原理**：生成抽奖ID时检查是否已存在，如果存在则重新生成，确保每个抽奖记录的唯一性。

**自动处理**：系统会自动处理，无需手动调用。

### 4. 频率限制统计

**功能**：记录用户和IP的抽奖请求次数，可用于频率限制检查。

**使用示例**：

```php
use Leo\Lottery\Security\AntiCheatManager;

$antiCheatManager = app(AntiCheatManager::class);

// 获取用户今日抽奖请求次数
$userCount = $antiCheatManager->getUserRequestCount($openid);

// 获取IP今日抽奖请求次数
$ipCount = $antiCheatManager->getIpRequestCount($ip);

// 获取用户当前小时抽奖请求次数
$hourCount = $antiCheatManager->getUserHourRequestCount($openid);

// 在业务层实现频率限制
if ($userCount > 10) {
    return $this->error('今日抽奖次数已达上限');
}
```

**注意**：频率限制的具体逻辑需要业务层自行实现，组件只提供统计数据。

## 完整使用示例

### 1. 启用防作弊功能

```php
// config/lottery.php
return [
    'anti_cheat' => [
        'enabled' => true,
        'secret_key' => 'your-secret-key-at-least-32-characters-long',
        'nonce_ttl' => 300,
    ],
];
```

### 2. 前端获取 nonce

```php
// 控制器方法
public function getNonce()
{
    $openid = $this->currentUser->openid;
    $antiCheatManager = app(\Leo\Lottery\Security\AntiCheatManager::class);
    $nonce = $antiCheatManager->generateNonce($openid);
    
    return $this->success(['nonce' => $nonce]);
}
```

### 3. 执行抽奖

```php
// 控制器方法
public function draw()
{
    $openid = $this->currentUser->openid;
    $ip = get_real_ip();
    $nonce = $this->request->param('nonce'); // 从前端获取
    
    try {
        $lotteryService = app(\Leo\Lottery\Service\LotteryService::class);
        $result = $lotteryService->draw($openid, $ip, $nonce);
        
        // 可选：验证签名
        $antiCheatManager = app(\Leo\Lottery\Security\AntiCheatManager::class);
        if (isset($result['signature'])) {
            $isValid = $antiCheatManager->verifySignature(
                $result['draw_id'],
                $openid,
                $result['prize'],
                $result['signature']
            );
            
            if (!$isValid) {
                \think\facade\Log::error('抽奖结果签名验证失败', $result);
                return $this->error('抽奖结果验证失败');
            }
        }
        
        return $this->success($result);
    } catch (\Leo\Lottery\Exceptions\LotteryException $e) {
        return $this->error($e->getCode(), $e->getMessage());
    }
}
```

### 4. 实现频率限制

```php
// 在抽奖前检查频率
public function draw()
{
    $openid = $this->currentUser->openid;
    $ip = get_real_ip();
    
    // 检查频率限制
    $antiCheatManager = app(\Leo\Lottery\Security\AntiCheatManager::class);
    
    // 检查用户今日抽奖次数
    $userCount = $antiCheatManager->getUserRequestCount($openid);
    if ($userCount >= 10) {
        return $this->error('今日抽奖次数已达上限');
    }
    
    // 检查用户当前小时抽奖次数
    $hourCount = $antiCheatManager->getUserHourRequestCount($openid);
    if ($hourCount >= 3) {
        return $this->error('当前小时抽奖次数已达上限');
    }
    
    // 检查IP抽奖次数
    $ipCount = $antiCheatManager->getIpRequestCount($ip);
    if ($ipCount >= 50) {
        return $this->error('IP抽奖次数过多，请稍后再试');
    }
    
    // 执行抽奖...
}
```

## 安全建议

### 1. 签名密钥管理

- **长度**：建议至少 32 字符
- **随机性**：使用随机字符串生成器生成
- **保密性**：不要提交到代码仓库，使用环境变量或配置中心管理
- **定期更换**：建议定期更换签名密钥

```php
// 推荐：使用环境变量
'secret_key' => env('LOTTERY_SECRET_KEY', ''),
```

### 2. Nonce 管理

- **过期时间**：根据业务需求调整，建议 5-10 分钟
- **唯一性**：确保每个 nonce 只使用一次
- **存储**：使用 Redis 存储，自动过期

### 3. 频率限制

- **用户限制**：根据业务需求设置每日/每小时限制
- **IP限制**：防止同一IP大量请求
- **动态调整**：根据实际情况动态调整限制值

### 4. 日志监控

建议监控以下指标：
- Nonce 验证失败次数（可能的重放攻击）
- 签名验证失败次数（可能的篡改）
- 频率限制触发次数
- 异常抽奖行为

## 注意事项

1. **性能影响**：启用防作弊功能会增加 Redis 操作，对性能有轻微影响
2. **Redis 依赖**：防作弊功能依赖 Redis，如果 Redis 不可用，功能会自动降级
3. **业务逻辑**：频率限制的具体逻辑需要业务层实现，组件只提供统计数据
4. **向后兼容**：默认不启用防作弊功能，不影响现有代码

## 常见问题

### Q: 不启用防作弊功能会影响抽奖吗？

A: 不会。防作弊功能是可选的，默认不启用。不启用时，抽奖功能正常工作。

### Q: Redis 不可用时防作弊功能还能用吗？

A: 不能。防作弊功能依赖 Redis。如果 Redis 不可用：
- Nonce 验证会失败（如果配置了 secretKey，会允许继续）
- 签名功能不受影响（不依赖 Redis）
- 频率统计功能不可用

### Q: 如何生成安全的签名密钥？

A: 使用以下方法生成：

```php
// PHP
$secretKey = bin2hex(random_bytes(32)); // 64字符的十六进制字符串

// 或使用命令行
// openssl rand -hex 32
```

### Q: 前端如何验证签名？

A: 前端可以使用相同的算法验证：

```javascript
// JavaScript 示例
const crypto = require('crypto');

function verifySignature(drawId, openid, prize, signature, secretKey) {
    const signString = `${drawId}|${openid}|${prize.id}|${prize.name}|${prize.type}`;
    const expectedSignature = crypto
        .createHmac('sha256', secretKey)
        .update(signString)
        .digest('hex');
    
    return expectedSignature === signature;
}
```
