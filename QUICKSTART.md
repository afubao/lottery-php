# 快速开始指南

本指南将帮助你在 5 分钟内快速集成抽奖组件。

## 前置条件

- PHP >= 8.0.0
- ThinkPHP >= 8.0
- Redis（用于分布式锁和缓存）
- MySQL/MariaDB（用于数据存储）

## 步骤 1：安装组件

```bash
composer require leo/lottery
```

## 步骤 2：配置

配置文件会自动发布到 `config/lottery.php`。如果没有自动发布，可以手动复制：

```bash
cp vendor/leo/lottery/config/lottery.php config/lottery.php
```

### 最小化配置示例

```php
<?php
return [
    'prefix_key' => 'lottery:',
    'is_test' => false,
    'fallback_prizes' => [
        [
            'id' => 9,
            'name' => '优惠券',
            'url' => 'https://example.com/coupon',
            'weight' => 5,
            'type' => 100,
        ],
    ],
];
```

## 步骤 3：使用默认适配器（推荐）

组件现在提供了 ThinkPHP 默认的 Redis 和 Cache 适配器，无需手动绑定接口！

只需确保你的 ThinkPHP 项目已配置好 Redis 和 Cache：

```php
// config/cache.php
return [
    'default' => 'redis',
    'stores' => [
        'redis' => [
            'type' => 'redis',
            'host' => '127.0.0.1',
            'port' => 6379,
        ],
    ],
];
```

组件会自动使用 ThinkPHP 的 Redis 和 Cache Facade。

## 步骤 4：运行数据库迁移

复制迁移文件到项目：

```bash
cp vendor/leo/lottery/database/migrations/*.php database/migrations/
```

运行迁移：

```bash
php think migrate:run
```

或者手动执行 SQL（参考 README.md 中的数据库表结构）。

## 步骤 5：使用 Facade 进行抽奖

### 方式一：使用 Facade（推荐）

```php
<?php
namespace app\controller;

use Leo\Lottery\Facades\Lottery;

class LotteryController
{
    public function draw()
    {
        $openid = input('openid');
        $ip = request()->ip();
        
        try {
            $result = Lottery::draw($openid, $ip);
            return json($result);
        } catch (\Leo\Lottery\Exceptions\LotteryException $e) {
            return json(['error' => $e->getMessage()], 400);
        }
    }
}
```

### 方式二：使用容器

```php
use Leo\Lottery\Service\LotteryService;

$lotteryService = app(LotteryService::class);
$result = $lotteryService->draw($openid, $ip);
```

## 步骤 6：清除缓存（可选）

### 使用 Facade

```php
use Leo\Lottery\Facades\LotteryCache;

// 清除规则缓存
LotteryCache::clearRuleCache();

// 清除奖品缓存
LotteryCache::clearPrizeCache();

// 清除所有缓存
LotteryCache::clearAllCache();
```

### 使用命令行

```bash
# 清除所有缓存
php think lottery:clear-cache --all

# 清除规则缓存
php think lottery:clear-cache

# 清除奖品缓存
php think lottery:clear-cache --prize

# 清除指定规则缓存
php think lottery:clear-cache --rule=1
```

## 步骤 7：配置奖品和规则

### 创建奖品

```php
use Leo\Lottery\Models\LotteryPrize;

LotteryPrize::create([
    'id' => 1,
    'name' => 'iPhone 15',
    'type' => 1, // 实物奖品
    'remaining_quantity' => 10,
    'weight' => 1,
]);
```

### 创建规则

```php
use Leo\Lottery\Models\PrizeRule;

PrizeRule::create([
    'prize_id' => 1,
    'weight' => 10,
    'total_num' => 10,
    'remaining_num' => 10,
    'start_time' => '2025-02-01 00:00:00',
    'end_time' => '2025-02-28 23:59:59',
]);
```

**重要**：更新奖品或规则后，记得清除缓存！

## 常见场景示例

### 场景 1：监听抽奖事件

```php
use think\facade\Event;
use Leo\Lottery\Events\DrawSuccessEvent;

// 监听抽奖成功事件
Event::listen(DrawSuccessEvent::class, function (DrawSuccessEvent $event) {
    // 发送中奖通知
    sendNotification($event->openid, $event->result);
    
    // 更新用户积分
    updateUserPoints($event->openid, 100);
});
```

### 场景 2：使用 Helper 工具类

```php
use Leo\Lottery\Common\Helper;

// 获取当前日期（ymd格式）
$date = Helper::getYmdDate(); // 250201

// 验证 openid
if (!Helper::validateOpenid($openid)) {
    return json(['error' => '无效的用户标识'], 400);
}

// 判断奖品类型
if (Helper::isPhysicalPrize($prizeType)) {
    // 实物奖品处理逻辑
}
```

### 场景 3：健康检查

```php
use Leo\Lottery\Service\HealthCheckService;

$healthCheck = app(HealthCheckService::class);
$result = $healthCheck->check();

if ($result['status'] === 'ok') {
    echo '所有检查通过';
} else {
    foreach ($result['checks'] as $name => $check) {
        if (!$check['ok']) {
            echo "{$name}: {$check['message']}\n";
        }
    }
}
```

### 场景 4：使用命令行工具

```bash
# 检查配置和依赖
php think lottery:check

# 快速检查
php think lottery:check --quick

# 查看统计
php think lottery:stats

# 查看指定日期统计
php think lottery:stats --date=250201
```

## 下一步

- 阅读 [README.md](README.md) 了解完整功能
- 阅读 [ANTI_CHEAT.md](ANTI_CHEAT.md) 了解防作弊机制
- 阅读 [LOGGING.md](LOGGING.md) 了解日志系统
- 阅读 [TESTING.md](TESTING.md) 了解单元测试

## 常见问题

### Q: 如何自定义 Redis 和 Cache 适配器？

A: 在 `LotteryServiceProvider` 的 `register` 方法之前绑定：

```php
use Leo\Lottery\Contracts\RedisInterface;
use Leo\Lottery\Contracts\CacheInterface;

// 在 AppServiceProvider 中
$this->app->bind(RedisInterface::class, YourRedisAdapter::class);
$this->app->bind(CacheInterface::class, YourCacheAdapter::class);
```

### Q: 如何监听所有事件？

A: 使用 ThinkPHP 的事件系统：

```php
use think\facade\Event;
use Leo\Lottery\Events\DrawBeforeEvent;
use Leo\Lottery\Events\DrawAfterEvent;
use Leo\Lottery\Events\DrawSuccessEvent;
use Leo\Lottery\Events\DrawFailedEvent;

Event::listen(DrawBeforeEvent::class, function ($event) {
    // 处理逻辑
});
```

### Q: 更新奖品后缓存没有清除？

A: 使用 `AutoClearCache` Trait 或手动清除：

```php
use Leo\Lottery\Facades\LotteryCache;

LotteryCache::clearPrizeCache();
```

### Q: 如何检查组件是否正常工作？

A: 使用健康检查命令：

```bash
php think lottery:check
```
