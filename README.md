# Leo Lottery Package

一个基于 ThinkPHP 8 的抽奖模块包，提供核心抽奖功能。本包专注于抽奖算法和奖品管理，业务逻辑（如用户抽奖次数管理、IP限制等）由使用者自行实现。

## 架构设计

本包采用**模块化架构设计**，遵循单一职责原则和开闭原则，各组件职责清晰，易于扩展和维护：

- **LotteryService** - 协调器，编排抽奖流程
- **PrizeSelector** - 奖品选择算法（支持权重算法，可扩展其他算法）
- **StockManager** - 统一库存管理（以数据库为准，Redis作为缓存）
- **DistributionStrategy** - 发放策略（支持峰值小时策略，可扩展其他策略）
- **LockManager** - 分布式锁管理
- **CacheManager** - 统一缓存管理（防止缓存击穿）
- **FallbackPrizeProvider** - 兜底奖品提供器
- **DrawResultBuilder** - 抽奖结果构建器

## 功能特性

- ✅ **模块化架构** - 职责清晰，易于扩展和维护
- ✅ **权重概率抽奖算法** - 支持小数权重，自动计算最大因子
- ✅ **可插拔算法** - 支持自定义奖品选择算法（如 Alias 算法）
- ✅ **可插拔策略** - 支持自定义发放策略
- ✅ **奖品规则管理** - 支持时间段、数量限制、权重配置
- ✅ **奖品库存管理** - 自动扣减库存，支持剩余数量控制
- ✅ **抽奖记录管理** - 记录每次抽奖结果，支持关联查询
- ✅ **分布式锁支持** - 使用 Redis 分布式锁防止并发抽奖
- ✅ **奖品发放上限控制** - 支持峰值/非峰值时段不同的发放策略
- ✅ **兜底奖品机制** - 当无实物奖品时自动发放兜底奖品（支持空奖品）
- ✅ **配置化** - 所有关键参数均可通过配置文件调整

## 环境要求

- PHP >= 8.0.0
- ThinkPHP >= 8.0
- ThinkORM >= 3.0
- Redis（用于分布式锁和缓存）
- MySQL/MariaDB（用于数据存储）

## 安装

### 1. 使用 Composer 安装

```bash
composer require leo/lottery
```

### 2. 发布配置文件

配置文件会自动发布到 `config/lottery.php`，如果没有自动发布，可以手动复制：

```bash
php think vendor:publish leo/lottery
```

或者手动复制：
```bash
cp vendor/leo/lottery/config/lottery.php config/lottery.php
```

### 3. 绑定接口实现（可选）

**重要更新**：组件现在提供了 ThinkPHP 默认的 Redis 和 Cache 适配器，如果你使用的是 ThinkPHP 的标准 Redis 和 Cache 配置，**无需手动绑定接口**！

组件会自动检测并使用 ThinkPHP 的 Redis 和 Cache Facade。

#### 3.0 使用默认适配器（推荐）

如果你使用的是 ThinkPHP 的标准配置，无需任何操作，组件会自动使用默认适配器。

只需确保你的 `config/cache.php` 和 `config/redis.php`（如果使用）已正确配置。

#### 3.1 自定义适配器（可选）

如果你需要自定义 Redis 或 Cache 实现，可以手动绑定接口。

#### 3.1 创建 Redis 适配器

创建文件 `app/common/LotteryRedisAdapter.php`：

```php
<?php
declare(strict_types=1);

namespace app\common;

use Leo\Lottery\Contracts\RedisInterface;
use Leo\Lottery\Contracts\PipelineInterface;
use Predis\Client;
use Predis\Pipeline\Pipeline;

class LotteryRedisAdapter implements RedisInterface
{
    private Client $redis;

    public function __construct()
    {
        $this->redis = Redis::getInstance(); // 你的 Redis 单例
    }

    public function get(string $key)
    {
        return $this->redis->get($key);
    }

    public function set(string $key, $value, ?int $ttl = null)
    {
        if ($ttl !== null) {
            return $this->redis->setex($key, $ttl, $value);
        }
        return $this->redis->set($key, $value);
    }

    public function incr(string $key): int
    {
        return $this->redis->incr($key);
    }

    public function hincrby(string $key, string $field, int $value): int
    {
        return $this->redis->hincrby($key, $field, $value);
    }

    public function hgetall(string $key): array
    {
        return $this->redis->hgetall($key);
    }

    public function hmset(string $key, array $data)
    {
        return $this->redis->hmset($key, $data);
    }

    public function sismember(string $key, string $member): bool
    {
        return (bool)$this->redis->sismember($key, $member);
    }

    public function sadd(string $key, array $members): int
    {
        return $this->redis->sadd($key, $members);
    }

    public function eval(string $script, int $numKeys, ...$args)
    {
        return $this->redis->eval($script, $numKeys, ...$args);
    }

    public function pipeline(): PipelineInterface
    {
        $pipeline = $this->redis->pipeline();
        return new LotteryPipelineAdapter($pipeline);
    }
}

class LotteryPipelineAdapter implements PipelineInterface
{
    private Pipeline $pipeline;

    public function __construct(Pipeline $pipeline)
    {
        $this->pipeline = $pipeline;
    }

    public function __call(string $method, array $args)
    {
        $this->pipeline->{$method}(...$args);
        return $this;
    }

    public function execute(): array
    {
        return $this->pipeline->execute();
    }
}
```

#### 3.2 创建 Cache 适配器

创建文件 `app/common/LotteryCacheAdapter.php`：

```php
<?php
declare(strict_types=1);

namespace app\common;

use Leo\Lottery\Contracts\CacheInterface;
use think\facade\Cache as ThinkCache;

class LotteryCacheAdapter implements CacheInterface
{
    public function get(string $key, $default = null)
    {
        return ThinkCache::get($key, $default);
    }

    public function set(string $key, $value, ?int $ttl = null): bool
    {
        if ($ttl !== null) {
            return ThinkCache::set($key, $value, $ttl);
        }
        return ThinkCache::set($key, $value);
    }

    public function delete(string $key): bool
    {
        return ThinkCache::delete($key);
    }

    public function has(string $key): bool
    {
        return ThinkCache::has($key);
    }

    public function push(string $key, $value)
    {
        return ThinkCache::push($key, $value);
    }
}
```

#### 3.3 在服务提供者中绑定接口

在 `app/AppService.php` 中绑定：

```php
<?php
declare(strict_types=1);

namespace app;

use app\common\LotteryRedisAdapter;
use app\common\LotteryCacheAdapter;
use Leo\Lottery\Contracts\RedisInterface;
use Leo\Lottery\Contracts\CacheInterface;
use think\Service;

class AppService extends Service
{
    public function register()
    {
        // 绑定抽奖包的接口实现
        $this->app->bind(RedisInterface::class, LotteryRedisAdapter::class);
        $this->app->bind(CacheInterface::class, LotteryCacheAdapter::class);
    }

    public function boot()
    {
        // 其他启动逻辑
    }
}
```

### 4. 运行数据库迁移

数据库迁移文件位于 `vendor/leo/lottery/database/migrations/`，需要手动复制到项目的 `database/migrations/` 目录，然后运行：

```bash
php think migrate:run
```

或者手动执行 SQL 创建表（参考下面的数据库表结构）。

## 配置说明

配置文件 `config/lottery.php` 包含以下配置项：

### prefix_key

Redis 前缀键，用于区分不同项目的 Redis 键。

```php
'prefix_key' => 'lottery:',
```

### is_test

是否测试环境。测试环境会跳过奖品发放上限检查，方便测试。

```php
'is_test' => false,
```

### record_thanks_prize

是否记录"谢谢参与"到数据库。

```php
'record_thanks_prize' => true,  // 默认 true，保持向后兼容
```

**说明**：
- `true`（默认）：记录"谢谢参与"到数据库，可以通过数据库查询统计未中奖次数
- `false`：仍然会创建数据库记录（用于防作弊验证），但会使用 Redis 计数器进行统计

**重要变更**：
- **为了防作弊验证，所有抽奖结果（包括"谢谢参与"）都会创建数据库记录**
- 即使 `record_thanks_prize=false`，也会创建记录，确保可以通过 `draw_id` 验证
- 此配置现在主要用于控制是否使用 Redis 计数器进行统计
- 配置的兜底奖品（id>0）总是会记录到数据库，不受此配置影响
- 设置为 `false` 时，系统会自动使用 Redis 计数器统计"谢谢参与"次数（如果 `enable_thanks_statistics` 为 `true`）

**使用场景**：
- 如果业务需要统计未中奖次数，设置为 `true`（通过数据库统计）
- 如果业务希望减少数据库查询压力，设置为 `false`（使用 Redis 统计，但记录仍会创建）

### enable_thanks_statistics

是否启用"谢谢参与"统计（当 `record_thanks_prize=false` 时）。

```php
'enable_thanks_statistics' => true,  // 默认 true，启用统计
```

**说明**：
- `true`（默认）：当 `record_thanks_prize=false` 时，使用 Redis 计数器统计"谢谢参与"次数
- `false`：不统计"谢谢参与"次数
- 当 `record_thanks_prize=true` 时，此配置无效（因为已记录到数据库，可以直接查询）

**统计维度**：
- **用户每日统计**：`lottery:stats:thanks:{openid}:{ymd}`（例如：`lottery:stats:thanks:oXXX:250201`）
- **全局每日统计**：`lottery:stats:thanks:global:{ymd}`（例如：`lottery:stats:thanks:global:250201`）
- **用户累计统计**：`lottery:stats:thanks:user:{openid}`（不过期）

**过期时间**：
- 每日统计的 Redis Key 过期时间为 7 天
- 用户累计统计不过期

**使用统计数据**：

```php
use Leo\Lottery\Manager\StatisticsManager;

// 获取统计管理器
$statsManager = app(StatisticsManager::class);

// 获取用户今天的"谢谢参与"次数
$count = $statsManager->getUserThanksCount($openid, date('ymd'));

// 获取用户累计"谢谢参与"次数
$totalCount = $statsManager->getUserThanksCount($openid);

// 获取全局今天的"谢谢参与"次数
$globalCount = $statsManager->getGlobalThanksCount(date('ymd'));

// 获取用户总抽奖统计（中奖次数 + 谢谢参与次数）
$stats = $statsManager->getUserTotalDrawCount($openid, date('ymd'));
// 返回: ['win_count' => 5, 'thanks_count' => 10, 'total' => 15]

// 获取用户中奖率
$winRate = $statsManager->getUserWinRate($openid, date('ymd'));
// 返回: 0.3333 (33.33%)
```

**注意事项**：
- Redis 数据有过期时间（每日统计 7 天），长期统计需要定期持久化
- 如果 Redis 不可用，统计功能自动禁用，但不影响抽奖功能
- 统计数据与数据库记录可能不完全一致（如果 Redis 数据丢失）
- 建议在生产环境中定期备份 Redis 统计数据

### hot_hours

流量峰值小时数组（0-23）。峰值时段按照总量的100%发放，非峰值时段按照总量的20%发放。

```php
'hot_hours' => [9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21],
```

**说明**：
- 峰值时段：在 `hot_hours` 中的小时，按照总量的100%发放
- 非峰值时段：不在 `hot_hours` 中的小时，按照总量的20%发放
- 如果当前时间已过最后一个峰值小时，则直接放行

### fallback_prizes

兜底奖品配置数组。当无实物奖品可抽或未中奖时，会从兜底奖品中随机抽取。

**重要说明**：
- **如果不配置或配置为空数组 `[]`**：将自动返回空的"谢谢参与"（id=0, name='谢谢参与', type=4, url=''），这是默认的兜底行为
- **如果配置了兜底奖品**：会从配置中按权重随机选择
- **向后兼容**：仍支持 `virtual_prizes` 配置，但建议使用 `fallback_prizes`

**注意事项**：
- 即使不配置兜底奖品，系统也会自动返回"谢谢参与"，确保每次抽奖都有结果
- 空的"谢谢参与"会创建抽奖记录（draw_id=0），但不会记录到用户已中奖列表（因为 id=0）
- 如果需要完全自定义兜底行为，可以配置 `fallback_prizes` 数组，包含你想要的奖品

```php
'fallback_prizes' => [
    [
        'id' => 9,                    // 奖品 ID（唯一）
        'name' => '哈啰组合优惠券包',   // 奖品名称
        'url' => 'https://...',       // 兜底奖品跳转地址
        'weight' => 5,                 // 奖品权重，权重越大越容易中
        'type' => 100,                 // 奖品类型：100=优惠券，详见 PrizeType 类
    ],
    // ... 更多兜底奖品
],

// 或者配置为空数组，返回空的"谢谢参与"
'fallback_prizes' => [],  // 返回空的"谢谢参与"，没有任何内容
```

**说明**：
- `id`: 奖品唯一标识
- `name`: 奖品名称，会返回给前端
- `url`: 兜底奖品跳转地址（如果需要）
- `weight`: 权重值，权重越大越容易中奖
- `type`: 奖品类型，支持 0-255 的任意值，详见奖品类型扩展说明

## 使用示例

### 使用 Facade（推荐）

组件提供了 Facade 类，可以简化调用：

```php
<?php
namespace app\controller;

use Leo\Lottery\Facades\Lottery;
use Leo\Lottery\Facades\LotteryCache;
use Leo\Lottery\Exceptions\LotteryException;

class LotteryController extends BaseController
{
    /**
     * 抽奖接口
     */
    public function draw()
    {
        $openid = input('openid');
        $ip = request()->ip();
        
        try {
            // 使用 Facade 执行抽奖
            $result = Lottery::draw($openid, $ip);
            return json($result);
        } catch (LotteryException $e) {
            return json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * 清除缓存
     */
    public function clearCache()
    {
        // 清除规则缓存
        LotteryCache::clearRuleCache();
        
        // 或清除奖品缓存
        LotteryCache::clearPrizeCache();
        
        // 或清除所有缓存
        LotteryCache::clearAllCache();
        
        return json(['message' => '缓存已清除']);
    }
}
```

### 事件系统

组件提供了完整的事件系统，可以在抽奖流程的关键节点监听事件：

```php
use think\facade\Event;
use Leo\Lottery\Events\DrawBeforeEvent;
use Leo\Lottery\Events\DrawAfterEvent;
use Leo\Lottery\Events\DrawSuccessEvent;
use Leo\Lottery\Events\DrawFailedEvent;
use Leo\Lottery\Events\PrizeSelectedEvent;

// 监听抽奖开始前事件
Event::listen(DrawBeforeEvent::class, function (DrawBeforeEvent $event) {
    // 记录日志
    \think\facade\Log::info('抽奖开始', [
        'openid' => $event->openid,
        'ip' => $event->ip,
    ]);
    
    // 检查用户资格
    // checkUserQualification($event->openid);
});

// 监听抽奖成功事件
Event::listen(DrawSuccessEvent::class, function (DrawSuccessEvent $event) {
    // 发送中奖通知
    sendNotification($event->openid, [
        'prize' => $event->result['prize']['name'],
        'draw_id' => $event->drawId,
    ]);
    
    // 更新用户积分
    updateUserPoints($event->openid, 100);
    
    // 记录中奖记录
    recordWinLog($event->openid, $event->prizeId, $event->drawId);
});

// 监听抽奖失败事件
Event::listen(DrawFailedEvent::class, function (DrawFailedEvent $event) {
    // 记录失败原因
    \think\facade\Log::warning('抽奖失败', [
        'openid' => $event->openid,
        'reason' => $event->reason,
        'error_code' => $event->errorCode,
    ]);
});

// 监听奖品选择事件
Event::listen(PrizeSelectedEvent::class, function (PrizeSelectedEvent $event) {
    // 记录选择过程
    \think\facade\Log::info('奖品已选择', [
        'openid' => $event->openid,
        'rule_id' => $event->rule->id,
        'prize_id' => $event->prizeInfo['id'],
    ]);
});

// 监听抽奖完成后事件（无论成功或失败）
Event::listen(DrawAfterEvent::class, function (DrawAfterEvent $event) {
    // 更新统计
    updateStatistics($event->openid, $event->success);
    
    // 发送通知
    if ($event->success) {
        sendSuccessNotification($event->openid);
    }
});
```

**可用事件列表**：

- `DrawBeforeEvent` - 抽奖开始前触发
- `PrizeSelectedEvent` - 奖品选择后触发（但还未扣减库存）
- `DrawSuccessEvent` - 抽奖成功时触发
- `DrawFailedEvent` - 抽奖失败时触发
- `DrawAfterEvent` - 抽奖完成后触发（无论成功或失败）

### 基础使用

在控制器中使用抽奖服务：

```php
<?php
declare(strict_types=1);

namespace app\controller;

use Leo\Lottery\Service\LotteryService;
use Leo\Lottery\Exceptions\LotteryException;
use think\Response;

class LotteryController extends BaseController
{
    /**
     * 抽奖接口
     */
    public function draw(): Response
    {
        // 1. 业务逻辑：检查用户抽奖资格（由使用者自行实现）
        if (!$this->currentUser->canLottery()) {
            return $this->error(20001, '暂无抽奖资格');
        }
        
        // 2. 获取抽奖服务
        $lotteryService = app(LotteryService::class);
        
        try {
            // 3. 执行抽奖
            // 注意：如果启用了防作弊功能，需要先获取 nonce（见下方防作弊示例）
            $result = $lotteryService->draw(
                $this->currentUser->openid,  // 用户标识
                get_real_ip(),               // IP 地址
                null                         // nonce（可选，防重放攻击）
            );
            
            // 4. 业务逻辑：减少用户抽奖次数（由使用者自行实现）
            $this->currentUser->reduceLotteryCount();
            
            // 5. 返回结果
            return $this->success($result);
            
        } catch (LotteryException $e) {
            // 处理抽奖异常
            return $this->error($e->getCode(), $e->getMessage());
        }
    }
}
```

### 防作弊使用（可选）

如果启用了防作弊功能，需要先获取 nonce：

```php
use Leo\Lottery\Security\AntiCheatManager;

class LotteryController extends BaseController
{
    /**
     * 获取 nonce（防重放攻击）
     */
    public function getNonce(): Response
    {
        $antiCheatManager = app(AntiCheatManager::class);
        $nonce = $antiCheatManager->generateNonce($this->currentUser->openid);
        return $this->success(['nonce' => $nonce]);
    }

    /**
     * 抽奖接口（带防作弊）
     */
    public function draw(): Response
    {
        $openid = $this->currentUser->openid;
        $ip = get_real_ip();
        $nonce = $this->request->param('nonce'); // 从前端获取
        
        $lotteryService = app(LotteryService::class);
        
        try {
            // 执行抽奖（传入 nonce）
            $result = $lotteryService->draw($openid, $ip, $nonce);
            
            // 可选：验证签名
            if (isset($result['signature'])) {
                $antiCheatManager = app(AntiCheatManager::class);
                $isValid = $antiCheatManager->verifySignature(
                    $result['draw_id'],
                    $openid,
                    $result['prize'],
                    $result['signature']
                );
                
                if (!$isValid) {
                    \think\facade\Log::error('抽奖结果签名验证失败', $result);
                    return $this->error(20002, '抽奖结果验证失败');
                }
            }
            
            return $this->success($result);
        } catch (LotteryException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        }
    }
}
```

**详细说明**：请查看 [ANTI_CHEAT.md](ANTI_CHEAT.md)，包含完整的防作弊功能说明和使用示例。

### 返回结果格式

```php
[
    'draw_id' => '202501011234567890',  // 抽奖记录ID（所有抽奖都有唯一ID，可用于验证）
    'is_win' => true,                   // 是否中奖（true=中奖，false=谢谢参与）
    'prize' => [
        'name' => '百度地图出行券包',    // 奖品名称
        'type' => 100,                   // 奖品类型：支持 0-255，详见奖品类型扩展说明
        'url' => 'https://...',         // 兜底奖品跳转地址（如果需要）
        'image_url' => 'https://...',    // 奖品图片（如果有）
    ],
    'signature' => 'abc123...'           // 签名（如果启用了防作弊功能）
]
```

**重要说明**：
- `draw_id`：所有抽奖结果都有唯一的 `draw_id`，可用于客服验证
- `is_win`：明确标识是否中奖，`true`=中奖，`false`=谢谢参与
- 即使 `record_thanks_prize=false`，所有抽奖也会创建数据库记录，确保可以通过 `draw_id` 验证

### 查询抽奖记录

```php
use Leo\Lottery\Models\LotteryDraw;

// 查询用户的抽奖记录
$draws = LotteryDraw::with('prize')
    ->where('openid', $this->currentUser->openid)
    ->order('create_time', 'desc')
    ->select();

// 根据抽奖ID查询
$draw = LotteryDraw::findByDrawsId('202501011234567890');
```

### 管理奖品

```php
use Leo\Lottery\Models\LotteryPrize;
use Leo\Lottery\Models\PrizeRule;

// 获取所有启用的奖品
$prizes = LotteryPrize::getActivePrizes();

// 根据类型获取奖品
$fallbackPrizes = LotteryPrize::getByType(PrizeType::VIRTUAL_THANKS);

// 创建奖品规则
PrizeRule::create([
    'prize_id' => 1,
    'total_num' => 100,        // 总发放数量
    'surplus_num' => 100,     // 剩余数量
    'weight' => 10,           // 权重
    'start_time' => '2025-01-01 00:00:00',
    'end_time' => '2025-12-31 23:59:59',
]);
```

## 架构说明

### 组件介绍

#### LotteryService（抽奖服务）
协调器，编排整个抽奖流程。主要方法：
- `draw(string $openid, string $ip): array` - 执行抽奖

#### PrizeSelector（奖品选择器）
负责从规则列表中选择一个奖品规则。默认实现：
- `WeightedPrizeSelector` - 基于权重的选择算法

**自定义选择器**：
```php
use Leo\Lottery\Contracts\PrizeSelectorInterface;
use Leo\Lottery\Models\PrizeRule;

class AliasPrizeSelector implements PrizeSelectorInterface
{
    public function select(array $rules): ?PrizeRule
    {
        // 实现 Alias 算法（O(1) 时间复杂度）
        // ...
    }
}

// 在服务提供者中注册
$this->app->bind(PrizeSelectorInterface::class, AliasPrizeSelector::class);
```

#### StockManager（库存管理器）
统一管理库存，以数据库为准，Redis 作为缓存。主要方法：
- `checkStock(int $ruleId): bool` - 检查库存
- `decrementStock(int $ruleId): bool` - 扣减库存
- `getRemainingStock(int $ruleId): int` - 获取剩余库存

#### DistributionStrategy（发放策略）
控制奖品的发放策略。默认实现：
- `PeakHoursStrategy` - 峰值小时策略（峰值时段100%，非峰值时段20%）
- `SimpleStrategy` - 简单策略（无限制或固定比例）

**自定义策略**：
```php
use Leo\Lottery\Contracts\DistributionStrategyInterface;

class CustomStrategy implements DistributionStrategyInterface
{
    public function canDistribute(int $prizeId, int $total, array $context = []): bool
    {
        // 自定义发放逻辑
        // ...
    }
    
    public function recordDistribution(int $prizeId): void
    {
        // 记录发放数量
        // ...
    }
}

// 在服务提供者中注册
$this->app->bind(DistributionStrategyInterface::class, CustomStrategy::class);
```

#### LockManager（锁管理器）
基于 Redis 的分布式锁管理。主要方法：
- `acquire(string $key, string $value, int $timeout): bool` - 获取锁
- `release(string $key, string $value): bool` - 释放锁

#### CacheManager（缓存管理器）
统一管理所有缓存，防止缓存击穿。主要方法：
- `getRules(): array` - 获取规则列表
- `getPrize(int $prizeId): ?array` - 获取奖品信息
- `clearRules(?int $ruleId = null): void` - 清除规则缓存
- `clearPrize(?int $prizeId = null): void` - 清除奖品缓存

#### FallbackPrizeProvider（兜底奖品提供器）
提供兜底奖品。主要方法：
- `getFallbackPrize(string $openid, array $context = []): array` - 获取兜底奖品

#### DrawResultBuilder（结果构建器）
构建抽奖结果，统一结果格式。主要方法：
- `build(string $openid, string $ip, PrizeRule $rule, LotteryPrize $prize): array` - 构建结果
- `buildFallback(string $openid, string $ip, array $fallbackPrize): array` - 构建兜底奖品结果

### 数据流

1. **用户请求** -> `LotteryService::draw()`
2. **获取锁** -> `LockManager::acquire()`
3. **获取规则** -> `CacheManager::getRules()`
4. **选择奖品** -> `PrizeSelector::select()`
5. **检查库存** -> `StockManager::checkStock()`
6. **检查策略** -> `DistributionStrategy::canDistribute()`
7. **扣减库存** -> `StockManager::decrementStock()`
8. **创建记录** -> `LotteryDraw::create()`
9. **构建结果** -> `DrawResultBuilder::build()`
10. **释放锁** -> `LockManager::release()`

## API 文档

### LotteryService::draw()

执行抽奖。

**参数**：
- `string $openid` - 用户标识（如微信 openid）
- `string $ip` - 用户 IP 地址

**返回**：
```php
[
    'draw_id' => string,  // 抽奖记录ID，兜底奖品为0
    'prize' => array      // 奖品信息
]
```

**异常**：
- `LotteryException` - 抽奖失败时抛出

### LotteryDraw 模型

**静态方法**：
- `findByDrawsId(string $drawsId): ?LotteryDraw` - 根据抽奖ID查找记录
- `verifyDraw(string $drawId, string $openid): ?array` - 验证抽奖记录（用于客服验证）
- `findByOpenid(string $openid, ?int $limit = null, string $order = 'desc')` - 根据用户标识获取抽奖记录
- `findByOpenidAndType(string $openid, int $type, ?int $limit = null)` - 根据用户标识和奖品类型获取记录
- `findByTimeRange(?string $startTime = null, ?string $endTime = null, ?int $limit = null)` - 根据时间范围获取记录
- `findByPrizeId(int $prizeId, ?int $limit = null)` - 根据奖品ID获取记录
- `findByRuleId(int $ruleId, ?int $limit = null)` - 根据规则ID获取记录
- `getUserWins(string $openid, ?int $limit = null)` - 获取用户的中奖记录（排除"谢谢参与"）
- `countByOpenid(string $openid, ?string $startTime = null, ?string $endTime = null): int` - 统计用户抽奖次数
- `countWinsByOpenid(string $openid, ?string $startTime = null, ?string $endTime = null): int` - 统计用户中奖次数
- `countByPrizeId(int $prizeId, ?string $startTime = null, ?string $endTime = null): int` - 统计指定奖品的发放数量
- `createDraw(string $openid, int $prizeRuleId, array $prizeInfo): LotteryDraw` - 创建抽奖记录

**实例方法**：
- `isWin(): bool` - 判断是否真实中奖（prize_id > 0）

**关联**：
- `prize()` - 关联奖品模型

**查询示例**：
```php
use Leo\Lottery\Models\LotteryDraw;

// 获取用户最近的10条抽奖记录
$draws = LotteryDraw::findByOpenid($openid, 10);

// 获取用户今天的中奖记录
$wins = LotteryDraw::getUserWins($openid);

// 统计用户今天抽奖次数
$count = LotteryDraw::countByOpenid($openid, date('Y-m-d 00:00:00'), date('Y-m-d 23:59:59'));

// 统计用户中奖次数
$winCount = LotteryDraw::countWinsByOpenid($openid);
```

### LotteryPrize 模型

**静态方法**：
- `getActivePrizes(): array` - 获取所有启用的奖品
- `getByType(int $type): Collection` - 根据类型获取奖品

### PrizeRule 模型

**方法**：
- `getRule(RedisInterface $redis, CacheInterface $cache, string $prefixKey): array` - 获取当前时间段内的抽奖规则

### CacheService（缓存管理服务）

**方法**：
- `clearRuleCache(?int $ruleId = null): void` - 清除规则缓存，传入规则ID只清除该规则，不传则清除当天所有规则
- `clearPrizeCache(): void` - 清除奖品缓存
- `clearAllCache(): void` - 清除所有抽奖相关缓存
- `clearRuleCacheByDate(string $date): void` - 清除指定日期的规则缓存（日期格式：ymd，如 250201）

### VerificationService（验证服务）

**方法**：
- `verifyDraw(string $drawId, string $openid, ?string $signature = null): array` - 验证抽奖记录（用于客服验证）
- `verifyDraws(array $drawIds, string $openid): array` - 批量验证抽奖记录
- `verifyUserWins(string $openid, ?string $startTime = null, ?string $endTime = null): array` - 验证用户在指定时间范围内的中奖记录

**使用示例**：
```php
use Leo\Lottery\Service\VerificationService;
use Leo\Lottery\Exceptions\LotteryException;

$verificationService = app(VerificationService::class);

try {
    // 验证单个抽奖记录
    $result = $verificationService->verifyDraw($drawId, $openid);
    
    if ($result['is_win']) {
        echo "用户确实中奖了，奖品：{$result['prize_name']}";
    } else {
        echo "用户未中奖，这是'谢谢参与'记录";
    }
} catch (LotteryException $e) {
    echo "验证失败：{$e->getMessage()}";
}
```

**使用示例**：
```php
use Leo\Lottery\Service\CacheService;

$cacheService = app(CacheService::class);

// 清除指定规则缓存
$cacheService->clearRuleCache(1);

// 清除当天所有规则缓存
$cacheService->clearRuleCache();

// 清除奖品缓存
$cacheService->clearPrizeCache();

// 清除所有缓存
$cacheService->clearAllCache();
```

## 数据库表结构

### lottery_draws（抽奖记录表）

| 字段 | 类型 | 说明 |
|------|------|------|
| id | int | 主键ID |
| draws_id | varchar(32) | 抽奖ID（唯一） |
| openid | varchar(32) | 用户标识 |
| prize_id | int | 奖品ID |
| type | tinyint | 奖品类型（0-255），详见下方奖品类型说明 |
| ip | varchar(20) | IP地址 |
| rule_id | int | 规则ID |
| create_time | datetime | 创建时间 |
| update_time | datetime | 更新时间 |

### lottery_prizes（奖品表）

| 字段 | 类型 | 说明 |
|------|------|------|
| id | int | 主键ID |
| type | tinyint | 奖品类型（0-255），详见下方奖品类型说明 |
| name | varchar(100) | 奖品名称 |
| image_url | varchar(255) | 奖品图片 |
| url | varchar(255) | 兜底奖品跳转地址 |
| total | int | 总数量 |
| remaining_quantity | int | 剩余数量 |
| weight | int | 中奖概率权重 |
| create_time | datetime | 创建时间 |
| update_time | datetime | 更新时间 |

### prize_rule（奖品规则表）

| 字段 | 类型 | 说明 |
|------|------|------|
| id | int | 主键ID |
| prize_id | int | 奖品ID |
| total_num | int | 总发放数量 |
| surplus_num | int | 剩余数量 |
| weight | int | 权重 |
| start_time | datetime | 开始时间 |
| end_time | datetime | 结束时间 |
| create_time | datetime | 创建时间 |
| update_time | datetime | 更新时间 |

## 奖品类型扩展

### 默认奖品类型

本包提供了 `PrizeType` 常量类，定义了默认的奖品类型：

```php
use Leo\Lottery\Common\PrizeType;

// 实物奖品类型
PrizeType::PHYSICAL_NORMAL = 1;      // 普通实物
PrizeType::PHYSICAL_LIMITED = 2;     // 限量实物

// 兜底奖品类型
PrizeType::VIRTUAL_THANKS = 4;       // 谢谢参与（兜底奖品，可能为空）
PrizeType::VIRTUAL_COUPON = 100;     // 优惠券
PrizeType::VIRTUAL_POINTS = 101;     // 积分
PrizeType::VIRTUAL_MEMBERSHIP = 102; // 会员权益
```

### 类型范围建议

- **1-99**: 实物奖品类型（如：1=普通实物，2=限量实物，3=特殊实物等）
- **100-199**: 兜底奖品类型（如：100=优惠券，101=积分，102=会员权益等）
- **200-255**: 自定义类型（根据业务需求定义）

### 扩展奖品类型

#### 方法一：扩展 PrizeType 类（推荐）

创建自定义的奖品类型类：

```php
<?php
declare(strict_types=1);

namespace app\common;

use Leo\Lottery\Common\PrizeType as BasePrizeType;

/**
 * 自定义奖品类型
 */
class PrizeType extends BasePrizeType
{
    /**
     * 实物奖品 - 特殊实物
     */
    const PHYSICAL_SPECIAL = 3;

    /**
     * 兜底奖品 - 现金红包
     */
    const VIRTUAL_CASH = 103;

    /**
     * 兜底奖品 - 游戏道具
     */
    const VIRTUAL_GAME_ITEM = 104;

    /**
     * 自定义类型 - 活动积分
     */
    const CUSTOM_ACTIVITY_POINTS = 200;

    /**
     * 重写兜底奖品判断逻辑
     */
    public static function isVirtual(int $type): bool
    {
        return parent::isVirtual($type) 
            || $type === self::VIRTUAL_CASH
            || $type === self::VIRTUAL_GAME_ITEM
            || $type === self::CUSTOM_ACTIVITY_POINTS;
    }

    /**
     * 重写类型名称映射
     */
    public static function getName(int $type): string
    {
        $names = [
            self::PHYSICAL_SPECIAL => '特殊实物',
            self::VIRTUAL_CASH => '现金红包',
            self::VIRTUAL_GAME_ITEM => '游戏道具',
            self::CUSTOM_ACTIVITY_POINTS => '活动积分',
        ];

        return $names[$type] ?? parent::getName($type);
    }
}
```

#### 方法二：直接使用数字类型

你也可以直接在数据库和配置中使用数字类型，无需扩展类：

```php
// 在配置文件中直接使用数字类型
'fallback_prizes' => [
    [
        'id' => 9,
        'name' => '现金红包',
        'type' => 103,  // 直接使用数字类型
        'url' => 'https://...',
        'weight' => 5,
    ],
    [
        'id' => 10,
        'name' => '游戏道具',
        'type' => 104,  // 直接使用数字类型
        'url' => 'https://...',
        'weight' => 3,
    ],
],
```

### 使用奖品类型工具方法

```php
use Leo\Lottery\Common\PrizeType;

// 检查是否为兜底奖品
if (PrizeType::isVirtual($prize->type)) {
    // 处理兜底奖品逻辑
}

// 检查是否为实物奖品
if (PrizeType::isPhysical($prize->type)) {
    // 处理实物奖品逻辑
}

// 获取类型名称（用于日志和调试）
$typeName = PrizeType::getName($prize->type);
echo "奖品类型：{$typeName}";
```

### 注意事项

1. **数据库字段类型**：`type` 字段是 `tinyint` 类型，支持 0-255 的值
2. **类型一致性**：确保数据库、配置文件和代码中使用的类型值一致
3. **兜底奖品判断**：如果需要自定义兜底奖品的判断逻辑，可以扩展 `PrizeType::isVirtual()` 方法
4. **类型文档**：建议在项目文档中记录所有使用的奖品类型及其含义

## 工作原理

### 抽奖流程

1. **获取分布式锁** - 防止同一用户并发抽奖
2. **获取奖品规则** - 查询当前时间段内有效的奖品规则
3. **权重算法计算** - 根据权重随机计算中奖规则
4. **检查发放上限** - 检查奖品是否超过发放上限（峰值/非峰值策略）
5. **创建抽奖记录** - 记录抽奖结果
6. **更新库存** - 扣减奖品和规则的剩余数量
7. **释放锁** - 释放分布式锁

### 权重算法

1. 计算所有规则权重的最大因子（用于处理小数权重）
2. 生成随机数（1 到 max(权重总和, 100)）
3. 遍历权重列表，累加权重，找到第一个大于等于随机数的规则
4. 返回中奖规则

### 发放上限控制

- **峰值时段**（`hot_hours` 中）：按照总量的100%发放
- **非峰值时段**：按照总量的20%发放
- **已过峰值时段**：直接放行

## 注意事项

### 业务逻辑分离

本包只提供核心抽奖功能，以下业务逻辑需要使用者自行实现：
- 用户抽奖次数检查和管理
- IP 限制检查
- 抽奖资格验证
- 特殊奖励逻辑（如第100000次抽奖）

### 缓存机制

**重要：规则和奖品更新后需要清除缓存**

本包使用了缓存机制来提升性能：
- **规则缓存**：规则信息会缓存到当天结束，提高查询性能
- **奖品缓存**：奖品信息缓存5分钟

**风险说明**：
- 如果在后台修改了奖品规则（`prize_rule` 表）或奖品信息（`lottery_prizes` 表），**缓存不会自动更新**
- 修改后的规则/奖品信息**不会立即生效**，需要手动清除缓存
- 缓存会在以下情况自动失效：
  - 规则缓存：当天结束（00:00）后自动失效
  - 奖品缓存：5分钟后自动失效

**解决方案**：

在后台更新规则或奖品后，必须调用缓存清除方法：

```php
use Leo\Lottery\Service\CacheService;

// 获取缓存服务
$cacheService = app(CacheService::class);

// 更新规则后，清除规则缓存
PrizeRule::update(['weight' => 20], ['id' => 1]);
$cacheService->clearRuleCache(1); // 传入规则ID，只清除该规则缓存
// 或清除当天所有规则缓存
$cacheService->clearRuleCache();

// 更新奖品后，清除奖品缓存
LotteryPrize::update(['name' => '新奖品'], ['id' => 1]);
$cacheService->clearPrizeCache();

// 清除所有缓存
$cacheService->clearAllCache();
```

**推荐做法**：

在后台管理系统中，更新规则或奖品后自动清除缓存：

```php
// 在后台更新规则的控制器中
public function updateRule($id, $data)
{
    PrizeRule::update($data, ['id' => $id]);
    
    // 立即清除缓存，使新规则生效
    $cacheService = app(CacheService::class);
    $cacheService->clearRuleCache($id);
    
    return $this->success('更新成功');
}
```

### 其他注意事项

1. **分布式锁**：使用 Redis 分布式锁防止并发，锁的过期时间为30秒

2. **事务处理**：抽奖过程使用数据库事务，确保数据一致性

3. **兜底奖品机制**：当无实物奖品或未中奖时，会自动从兜底奖品中随机抽取。如果 `fallback_prizes` 不配置或配置为空数组，将自动返回空的"谢谢参与"（id=0, name='谢谢参与', type=4, url=''）。这是默认的安全行为，确保每次抽奖都有结果。

4. **记录控制**：为了防作弊验证，所有抽奖结果（包括"谢谢参与"）都会创建数据库记录。通过 `record_thanks_prize` 配置项可以控制统计方式：设置为 `false` 时，会使用 Redis 计数器统计"谢谢参与"次数（如果 `enable_thanks_statistics` 为 `true`），可以通过 `StatisticsManager` 查询统计信息。

4. **配置优先级**：配置文件中的 `hot_hours` 会覆盖代码中的默认值

## 常见问题

### Q: 如何自定义兜底奖品？

A: 修改 `config/lottery.php` 中的 `fallback_prizes` 配置项即可。

### Q: 如何返回空的"谢谢参与"（没有任何内容）？

A: 有两种方式：
1. **不配置 `fallback_prizes`**：系统会自动返回空的"谢谢参与"
2. **配置为空数组**：`'fallback_prizes' => []`，效果相同

系统会自动返回空的"谢谢参与"（id=0, name='谢谢参与', type=4, url=''）。

### Q: 如果不配置兜底奖品会有什么问题吗？

A: **没有问题**。如果不配置 `fallback_prizes`，系统会自动返回空的"谢谢参与"作为兜底，确保每次抽奖都有结果。这是默认的安全行为。

**行为说明**：
- 不配置或配置为空数组 `[]`：返回空的"谢谢参与"（id=0）
- 配置了兜底奖品：从配置中按权重随机选择
- **所有抽奖结果都会创建数据库记录**（用于防作弊验证），`record_thanks_prize` 配置主要影响统计方式

**建议**：
- 如果希望用户未中奖时得到一些安慰奖（如优惠券），可以配置 `fallback_prizes`
- 如果希望用户未中奖时只显示"谢谢参与"（没有任何内容），可以不配置或配置为空数组
- 如果希望使用 Redis 统计而不是数据库统计，可以设置 `record_thanks_prize => false`

### Q: 如何避免产生大量"谢谢参与"的数据库记录？

A: **注意**：为了防作弊验证，所有抽奖结果（包括"谢谢参与"）都会创建数据库记录，确保可以通过 `draw_id` 验证。

设置 `record_thanks_prize => false` 后：
- 仍然会创建数据库记录（用于防作弊验证）
- 会使用 Redis 计数器进行统计（如果 `enable_thanks_statistics` 为 `true`）
- 可以通过 `StatisticsManager` 查询统计信息，包括用户抽奖次数、中奖率等

**说明**：
- 配置的兜底奖品（id>0）总是会记录到数据库
- 所有抽奖都有唯一的 `draw_id`，可用于客服验证

**统计查询示例**：

```php
use Leo\Lottery\Manager\StatisticsManager;

$statsManager = app(StatisticsManager::class);

// 获取用户总抽奖统计
$stats = $statsManager->getUserTotalDrawCount($openid);
// 返回: ['win_count' => 5, 'thanks_count' => 10, 'total' => 15]

// 获取用户中奖率
$winRate = $statsManager->getUserWinRate($openid);
// 返回: 0.3333 (33.33%)
```

### Q: 如何调整峰值小时？

A: 修改 `config/lottery.php` 中的 `hot_hours` 配置项，例如设置为 `[10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20]`。

### Q: 如何跳过奖品发放上限检查？

A: 设置 `config/lottery.php` 中的 `is_test` 为 `true`。

### Q: 如何查询用户的抽奖记录？

A: 使用 `LotteryDraw` 模型查询：
```php
$draws = LotteryDraw::where('openid', $openid)->select();
```

### Q: 如何手动创建奖品规则？

A: 使用 `PrizeRule` 模型创建：
```php
PrizeRule::create([
    'prize_id' => 1,
    'total_num' => 100,
    'surplus_num' => 100,
    'weight' => 10,
    'start_time' => '2025-01-01 00:00:00',
    'end_time' => '2025-12-31 23:59:59',
]);

// 创建后清除缓存，使新规则立即生效
$cacheService = app(\Leo\Lottery\Service\CacheService::class);
$cacheService->clearRuleCache();
```

### Q: 后台更新规则后，为什么新规则不生效？

A: **这是因为缓存机制**。规则信息会被缓存到当天结束，更新规则后需要手动清除缓存：

```php
use Leo\Lottery\Service\CacheService;

// 更新规则
PrizeRule::update(['weight' => 20], ['id' => 1]);

// 清除缓存，使新规则立即生效
$cacheService = app(CacheService::class);
$cacheService->clearRuleCache(1); // 传入规则ID
```

### Q: 如何清除所有缓存？

A: 使用 `CacheService`：
```php
$cacheService = app(\Leo\Lottery\Service\CacheService::class);
$cacheService->clearAllCache(); // 清除所有抽奖相关缓存
```

### Q: 规则缓存什么时候会自动失效？

A: 规则缓存会在**当天结束（00:00）后自动失效**。如果需要立即生效，必须手动清除缓存。

### Q: 如何自动清除缓存？

A: 可以使用模型事件自动清除缓存功能。详情请查看 [MODEL_EVENTS.md](MODEL_EVENTS.md)。

### Q: 如何处理抽奖异常？

A: 抽奖组件提供了详细的错误码和上下文信息。详情请查看 [ERROR_CODES.md](ERROR_CODES.md)。

### Q: Redis 不可用时系统还能正常工作吗？

A: 可以。系统有完善的降级策略，会自动降级到数据库操作。详情请查看 [DEGRADATION_STRATEGY.md](DEGRADATION_STRATEGY.md)。

### Q: 如何防止用户通过抓包修改前端结果后找客服申诉？

A: 使用 `VerificationService` 验证接口。所有抽奖结果都有唯一的 `draw_id` 并记录到数据库，客服可以通过 `draw_id` 和 `openid` 验证用户是否真实中奖：

```php
use Leo\Lottery\Service\VerificationService;

$verificationService = app(VerificationService::class);
$result = $verificationService->verifyDraw($drawId, $openid);

if (!$result['is_win']) {
    // 用户未中奖，这是"谢谢参与"记录
    return $this->error('该记录为"谢谢参与"，未中奖');
}
```

详情请查看 README 中的"客服验证"章节。

### Q: draw_id 是趋势递增的，会不会有安全风险？

A: **已修复**。新版本的 `draw_id` 使用时间戳前缀 + 随机字符串生成，平衡了安全性和性能：

- **标准格式（32位）**：时间戳编码（8位）+ 随机字符串（24位），约 7.92e28 种可能
- **扩展格式（48位）**：时间戳编码（8位）+ 随机字符串（40位），约 1.46e48 种可能

**安全特性**：
- ✅ 不可预测：使用密码学安全的随机数生成器
- ✅ 不可枚举：随机部分足够长，枚举空间巨大
- ✅ 时间隐藏：时间戳使用偏移量编码，不完全暴露真实时间

**性能优化**：
- ✅ 插入性能：时间戳前缀使插入相对有序，减少索引页分裂
- ✅ 查询性能：不受影响（B+ 树支持随机查找）

详情请查看 [DRAW_ID_ENCODER.md](DRAW_ID_ENCODER.md)。

### Q: 随机 ID 加上索引会不会有数据库性能问题？

A: **已优化**。推荐使用 **ID编码方案**（默认启用），这是性能、安全性、维护成本的最佳平衡：

**ID编码方案（推荐）**：
- ✅ **数据库性能最优**：使用自增ID作为主键，插入性能最优（无索引页分裂）
- ✅ **查询性能最优**：使用主键查询，性能最优（O(1)）
- ✅ **安全性高**：用户看到的ID是编码后的随机字符串，无法推断真实ID
- ✅ **无索引碎片**：自增ID无碎片化问题，无需定期重建索引

**工作原理**：
1. 数据库存储自增ID（如：12345）
2. 编码算法将ID编码为随机字符串（如：`a1b2c3d4`）
3. 用户看到的是编码后的ID，验证时解码得到真实ID查询

**配置**：
```php
// config/lottery.php
'draw_id_encoder' => [
    'enabled' => true,  // 启用ID编码（推荐）
    'key' => 0x12345678,  // 编码密钥（生产环境建议使用随机整数）
    'min_length' => 8,  // 编码后的最小长度
],
```

**其他方案**：
- **时间戳前缀+随机**：性能较好，但不如自增ID编码方案
- **完全随机字符串**：安全性最高，但性能较差，需要定期重建索引

详情请查看 [DRAW_ID_ENCODER.md](DRAW_ID_ENCODER.md)。

## 运营注意事项

### ⚠️ 重要：规则和奖品更新后必须清除缓存

**风险说明**：
- 规则信息会缓存到当天结束，奖品信息缓存5分钟
- **后台更新规则或奖品后，如果不清除缓存，修改不会立即生效**
- 这可能导致运营活动无法按预期进行

### 规则和奖品更新流程

1. **更新前**：确认当前没有正在进行的抽奖活动
2. **更新操作**：在后台更新规则或奖品信息
3. **清除缓存**：**必须**调用 `CacheService::clearRuleCache()` 或 `CacheService::clearPrizeCache()`
4. **验证**：测试抽奖功能，确认新规则/奖品已生效

### 缓存管理最佳实践

1. **后台更新时自动清除**（推荐）：在后台管理系统中，每次更新规则或奖品后自动清除对应缓存
2. **提供手动清除接口**：在后台提供清除缓存的按钮，方便运营人员操作
3. **监控告警**：监控缓存清除操作，确保更新后缓存被正确清除

### 详细使用指南

**后台管理使用指南**：请查看 [ADMIN_USAGE.md](ADMIN_USAGE.md)，包含完整的后台更新示例代码。

**错误码对照表**：请查看 [ERROR_CODES.md](ERROR_CODES.md)，包含所有错误码的详细说明和处理建议。

**降级策略说明**：请查看 [DEGRADATION_STRATEGY.md](DEGRADATION_STRATEGY.md)，了解 Redis 降级机制和监控建议。

**模型事件自动清除缓存**：请查看 [MODEL_EVENTS.md](MODEL_EVENTS.md)，了解如何使用自动清除缓存功能。

**防作弊机制**：请查看 [ANTI_CHEAT.md](ANTI_CHEAT.md)，了解防重放攻击、结果签名验证等防作弊功能。

**Draw ID 编码方案（推荐）**：请查看 [DRAW_ID_ENCODER.md](DRAW_ID_ENCODER.md)，了解使用自增ID编码的方案，这是性能、安全性、维护成本的最佳平衡方案。

**日志系统**：请查看 [LOGGING.md](LOGGING.md)，了解统一的日志服务使用说明，包括结构化日志、性能日志、审计日志。

**单元测试**：请查看 [TESTING.md](TESTING.md)，了解如何运行和编写单元测试。

**快速开始**：请查看 [QUICKSTART.md](QUICKSTART.md)，5 分钟快速集成指南。

## 文档索引

- **[README.md](README.md)** - 主文档，包含完整的使用说明
- **[QUICKSTART.md](QUICKSTART.md)** - 快速开始指南，5 分钟快速集成
- **[ADMIN_USAGE.md](ADMIN_USAGE.md)** - 后台管理使用指南
- **[ERROR_CODES.md](ERROR_CODES.md)** - 错误码对照表
- **[ANTI_CHEAT.md](ANTI_CHEAT.md)** - 防作弊机制说明
- **[DRAW_ID_ENCODER.md](DRAW_ID_ENCODER.md)** - Draw ID 编码方案（推荐）
- **[DEGRADATION_STRATEGY.md](DEGRADATION_STRATEGY.md)** - Redis 降级策略说明
- **[MODEL_EVENTS.md](MODEL_EVENTS.md)** - 模型事件自动清除缓存
- **[LOGGING.md](LOGGING.md)** - 日志系统使用说明
- **[TESTING.md](TESTING.md)** - 单元测试指南

## 工具类

### Helper 工具类

组件提供了 `Helper` 工具类，包含常用的辅助方法：

```php
use Leo\Lottery\Common\Helper;

// 获取当前日期的 ymd 格式字符串（如：250201）
$date = Helper::getYmdDate();

// 将时间戳转换为 ymd 格式
$date = Helper::formatYmdDate(time());

// 验证 openid 格式
if (!Helper::validateOpenid($openid)) {
    return json(['error' => '无效的用户标识'], 400);
}

// 验证 IP 地址格式
if (!Helper::validateIp($ip)) {
    return json(['error' => '无效的 IP 地址'], 400);
}

// 判断奖品类型
if (Helper::isPhysicalPrize($prizeType)) {
    // 实物奖品处理逻辑
}

if (Helper::isVirtualPrize($prizeType)) {
    // 虚拟奖品处理逻辑
}

if (Helper::isThanksPrize($prizeType)) {
    // 谢谢参与处理逻辑
}

// 获取奖品类型名称
$typeName = Helper::getPrizeTypeName($prizeType);

// 验证规则数据格式
$validation = Helper::validateRule($ruleData);
if (!$validation['valid']) {
    foreach ($validation['errors'] as $error) {
        echo $error . "\n";
    }
}

// 验证奖品数据格式
$validation = Helper::validatePrize($prizeData);
if (!$validation['valid']) {
    foreach ($validation['errors'] as $error) {
        echo $error . "\n";
    }
}
```

## 命令行工具

组件提供了多个命令行工具，方便管理和维护：

### 清除缓存命令

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

### 查看统计命令

```bash
# 查看今天的统计
php think lottery:stats

# 查看指定日期的统计
php think lottery:stats --date=250201
```

### 检查配置命令

```bash
# 完整检查（检查 Redis、Cache、数据库、配置）
php think lottery:check

# 快速检查（只检查关键依赖）
php think lottery:check --quick
```

## 健康检查

组件提供了健康检查服务，可以检查各项依赖是否正常工作：

```php
use Leo\Lottery\Service\HealthCheckService;

$healthCheck = app(HealthCheckService::class);

// 完整检查
$result = $healthCheck->check();
/*
返回格式：
[
    'status' => 'ok' | 'error',
    'checks' => [
        'redis' => ['ok' => true, 'message' => 'Redis 连接正常'],
        'cache' => ['ok' => true, 'message' => 'Cache 连接正常'],
        'database' => ['ok' => true, 'message' => '数据库连接正常'],
        'config' => ['ok' => true, 'message' => '配置检查通过'],
    ],
    'timestamp' => '2025-02-02 10:00:00',
]
*/

// 快速检查（只检查关键依赖）
$ok = $healthCheck->quickCheck(); // 返回 true/false
```

## 客服验证（防作弊）

### 问题场景

用户通过抓包修改前端返回的抽奖结果，然后截图找客服说自己中奖了，但实际上没中奖。

### 解决方案

所有抽奖结果都有唯一的 `draw_id` 并记录到数据库，客服可以通过 `draw_id` 和 `openid` 验证用户是否真实中奖。

### 使用示例

#### 1. 客服验证接口

```php
<?php
namespace app\admin\controller;

use Leo\Lottery\Service\VerificationService;
use Leo\Lottery\Exceptions\LotteryException;

class CustomerServiceController
{
    /**
     * 验证用户中奖记录
     */
    public function verifyDraw()
    {
        $drawId = $this->request->param('draw_id');
        $openid = $this->request->param('openid');
        $signature = $this->request->param('signature'); // 可选
        
        if (empty($drawId) || empty($openid)) {
            return $this->error('参数不完整');
        }
        
        try {
            $verificationService = app(VerificationService::class);
            $result = $verificationService->verifyDraw($drawId, $openid, $signature);
            
            // 返回验证结果
            return $this->success([
                'draw_id' => $result['draw_id'],
                'is_win' => $result['is_win'],
                'prize_name' => $result['prize_name'],
                'create_time' => $result['create_time'],
                'message' => $result['is_win'] 
                    ? "用户确实中奖了，奖品：{$result['prize_name']}" 
                    : "用户未中奖，这是'谢谢参与'记录"
            ]);
            
        } catch (LotteryException $e) {
            if ($e->getCode() === LotteryException::RULE_NOT_FOUND) {
                return $this->error('抽奖记录不存在，可能是伪造的 draw_id');
            }
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * 查询用户的所有中奖记录
     */
    public function getUserWins()
    {
        $openid = $this->request->param('openid');
        $startTime = $this->request->param('start_time');
        $endTime = $this->request->param('end_time');
        
        $verificationService = app(VerificationService::class);
        $wins = $verificationService->verifyUserWins($openid, $startTime, $endTime);
        
        return $this->success($wins);
    }
}
```

#### 2. 前端调用示例

```javascript
// 用户提供 draw_id 和 openid 给客服
// 客服在后台验证
const verifyResult = await api.post('/admin/verify-draw', {
    draw_id: '202501011234567890',
    openid: 'user_openid_123',
    signature: 'abc123...' // 可选
});

if (verifyResult.is_win) {
    // 用户确实中奖了
    console.log('中奖记录有效');
} else {
    // 用户未中奖
    console.log('这是"谢谢参与"记录，未中奖');
}
```

### 验证结果说明

验证成功时返回：
```php
[
    'draw_id' => '202501011234567890',
    'openid' => 'user_openid_123',
    'is_win' => true,  // true=中奖，false=谢谢参与
    'prize_id' => 1,
    'prize_name' => '百度地图出行券包',
    'prize_type' => 100,
    'prize' => [
        'name' => '百度地图出行券包',
        'type' => 100,
        'url' => 'https://...',
        'image_url' => 'https://...',
    ],
    'create_time' => '2025-01-01 12:34:56',
    'signature_valid' => true  // 如果提供了签名
]
```

### 注意事项

1. **所有抽奖都有记录**：即使 `record_thanks_prize=false`，所有抽奖也会创建数据库记录，确保可以验证
2. **draw_id 唯一性**：每个 `draw_id` 都是唯一的，可以通过数据库查询验证
3. **openid 验证**：验证时会检查 `draw_id` 对应的 `openid` 是否匹配，防止用户使用他人的 `draw_id`
4. **签名验证**：如果启用了防作弊功能，可以提供签名进行额外验证

### 最佳实践

1. **客服工作流程**：
   - 用户提供截图和 `draw_id`
   - 客服调用验证接口验证
   - 根据 `is_win` 字段判断是否真实中奖

2. **权限控制**：
   - 验证接口应该限制为管理员/客服使用
   - 不要暴露给普通用户

3. **日志记录**：
   - 记录所有验证请求
   - 对于验证失败的记录，记录详细信息用于分析

### 风险提示

- ⚠️ **规则更新后不清除缓存，新规则不会立即生效**（最严重）
- ⚠️ **奖品信息更新后不清除缓存，新奖品信息不会立即生效**
- ⚠️ **缓存会在指定时间自动失效，但可能影响当天的运营活动**
- ⚠️ **建议在后台更新时自动清除缓存，避免遗漏**

## License

MIT
