# 错误码对照表

本文档列出了抽奖组件中所有可能的错误码及其含义。

## 错误码列表

| 错误码 | 常量名 | 说明 | 解决方案 |
|--------|--------|------|----------|
| 30006 | `LOTTERY_FAIL` | 抽奖失败（通用错误） | 检查日志获取详细错误信息，稍后重试 |
| 30007 | `INSUFFICIENT_STOCK` | 规则库存不足 | 检查规则库存配置，或等待库存补充 |
| 30008 | `PRIZE_NOT_FOUND` | 奖品不存在 | 检查奖品配置，确认奖品ID是否正确 |
| 30009 | `RULE_NOT_FOUND` | 抽奖规则不存在 | 检查规则配置，确认规则ID是否正确 |
| 30010 | `REDIS_OPERATION_FAILED` | Redis 操作失败 | 检查 Redis 连接，系统会自动降级到数据库 |
| 30011 | `PRIZE_STOCK_INSUFFICIENT` | 奖品库存不足 | 检查奖品库存配置 |
| 30012 | `LOCK_ACQUIRE_FAILED` | 获取分布式锁失败 | 可能是并发过高，系统会自动返回兜底奖品 |
| 30013 | `INVALID_OPENID` | 用户标识格式错误 | 检查 openid 格式，必须是1-32字符的字母数字下划线连字符组合 |
| 30014 | `INVALID_IP` | IP 地址格式错误 | 检查 IP 地址格式，必须是有效的 IPv4 或 IPv6 地址 |
| 30015 | `NO_RULES_AVAILABLE` | 当前没有可用的抽奖规则 | 检查规则配置，确认是否有有效的规则 |
| 30016 | `DISTRIBUTION_REJECTED` | 发放策略拒绝 | 当前时段无法发放该奖品，可能是非峰值时段限制 |

## 异常上下文信息

`LotteryException` 异常会携带上下文信息，帮助定位问题：

```php
try {
    $result = $lotteryService->draw($openid, $ip);
} catch (LotteryException $e) {
    $code = $e->getCode();
    $message = $e->getMessage();
    $context = $e->getContext(); // 获取上下文信息
    
    // 上下文信息可能包含：
    // - rule_id: 规则ID
    // - prize_id: 奖品ID
    // - openid: 用户标识（部分隐藏）
    // - lock_key: 锁键
    // - 其他相关信息
}
```

## 错误处理示例

### 示例 1：处理库存不足

```php
use Leo\Lottery\Service\LotteryService;
use Leo\Lottery\Exceptions\LotteryException;

try {
    $result = $lotteryService->draw($openid, $ip);
} catch (LotteryException $e) {
    $context = $e->getContext();
    
    if ($e->getCode() === LotteryException::INSUFFICIENT_STOCK) {
        // 规则库存不足
        $ruleId = $context['rule_id'] ?? null;
        Log::warning("规则库存不足", ['rule_id' => $ruleId]);
        return $this->error(20001, '很抱歉，该奖品已被抽完');
    } elseif ($e->getCode() === LotteryException::PRIZE_STOCK_INSUFFICIENT) {
        // 奖品库存不足
        $prizeId = $context['prize_id'] ?? null;
        Log::warning("奖品库存不足", ['prize_id' => $prizeId]);
        return $this->error(20002, '很抱歉，该奖品库存不足');
    }
    
    // 其他错误
    Log::error("抽奖失败", [
        'code' => $e->getCode(),
        'message' => $e->getMessage(),
        'context' => $context
    ]);
    return $this->error(20000, '抽奖失败，请稍后重试');
}
```

### 示例 2：处理参数验证错误

```php
try {
    $result = $lotteryService->draw($openid, $ip);
} catch (LotteryException $e) {
    if ($e->getCode() === LotteryException::INVALID_OPENID) {
        return $this->error(20003, '用户标识格式错误');
    } elseif ($e->getCode() === LotteryException::INVALID_IP) {
        return $this->error(20004, 'IP地址格式错误');
    }
    
    throw $e; // 重新抛出其他异常
}
```

### 示例 3：记录详细错误日志

```php
try {
    $result = $lotteryService->draw($openid, $ip);
} catch (LotteryException $e) {
    Log::error("抽奖异常", [
        'code' => $e->getCode(),
        'message' => $e->getMessage(),
        'context' => $e->getContext(),
        'previous' => $e->getPrevious() ? $e->getPrevious()->getMessage() : null,
        'trace' => $e->getTraceAsString()
    ]);
    
    // 根据错误码返回不同的错误信息
    $errorMessages = [
        LotteryException::INSUFFICIENT_STOCK => '奖品已被抽完',
        LotteryException::PRIZE_NOT_FOUND => '奖品不存在',
        LotteryException::RULE_NOT_FOUND => '抽奖规则不存在',
        // ... 其他错误码
    ];
    
    $userMessage = $errorMessages[$e->getCode()] ?? '抽奖失败，请稍后重试';
    return $this->error(20000, $userMessage);
}
```

## 常见问题

### Q: 为什么有些错误会返回兜底奖品而不是抛出异常？

A: 为了保证用户体验，以下情况会返回兜底奖品而不是抛出异常：
- 获取分布式锁失败
- 没有可用的抽奖规则
- 库存不足
- 发放策略拒绝

这些情况下，系统会记录日志并返回兜底奖品，确保用户每次抽奖都有结果。

### Q: 如何区分不同类型的库存不足？

A: 使用错误码区分：
- `INSUFFICIENT_STOCK` (30007): 规则库存不足
- `PRIZE_STOCK_INSUFFICIENT` (30011): 奖品库存不足

异常上下文信息中会包含 `rule_id` 和 `prize_id` 帮助定位问题。

### Q: Redis 操作失败会影响抽奖吗？

A: 不会。系统有降级策略：
- Redis 失败时会自动降级到数据库操作
- 性能可能略有下降，但功能不受影响
- 建议监控 Redis 连接状态

## 监控建议

建议监控以下错误码的出现频率：
- `LOCK_ACQUIRE_FAILED`: 并发过高，可能需要优化
- `REDIS_OPERATION_FAILED`: Redis 连接问题，需要检查
- `INSUFFICIENT_STOCK`: 库存配置可能需要调整
- `DISTRIBUTION_REJECTED`: 发放策略可能需要优化
