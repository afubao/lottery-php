# 模型事件自动清除缓存

## 概述

抽奖组件提供了 `AutoClearCache` Trait，可以在模型更新、插入、删除后自动清除相关缓存，避免手动清除缓存的繁琐操作。

## 使用方法

### 方法一：使用 Trait（推荐）

在模型中使用 `AutoClearCache` Trait：

```php
<?php
namespace app\model;

use Leo\Lottery\Models\PrizeRule as BasePrizeRule;
use Leo\Lottery\Traits\AutoClearCache;
use Leo\Lottery\Service\CacheService;
use think\facade\App;

class PrizeRule extends BasePrizeRule
{
    use AutoClearCache;

    /**
     * 获取缓存服务实例
     * @return CacheService
     */
    protected function getCacheService(): CacheService
    {
        return App::make(CacheService::class);
    }
}
```

### 方法二：手动实现模型事件

如果不想使用 Trait，可以手动实现模型事件：

```php
<?php
namespace app\model;

use Leo\Lottery\Models\PrizeRule as BasePrizeRule;
use Leo\Lottery\Service\CacheService;
use think\facade\App;

class PrizeRule extends BasePrizeRule
{
    /**
     * 模型更新后事件
     */
    public function onAfterUpdate(): void
    {
        $cacheService = App::make(CacheService::class);
        $cacheService->clearRuleCache($this->id);
    }

    /**
     * 模型插入后事件
     */
    public function onAfterInsert(): void
    {
        $cacheService = App::make(CacheService::class);
        $cacheService->clearRuleCache();
    }

    /**
     * 模型删除后事件
     */
    public function onAfterDelete(): void
    {
        $cacheService = App::make(CacheService::class);
        $cacheService->clearRuleCache($this->id);
    }
}
```

## 支持的模型

### PrizeRule（奖品规则）

自动清除规则缓存：
- 更新规则后：清除指定规则的缓存
- 插入规则后：清除当天所有规则缓存
- 删除规则后：清除指定规则的缓存

### LotteryPrize（奖品）

自动清除奖品缓存：
- 更新奖品后：清除奖品缓存
- 插入奖品后：清除奖品缓存
- 删除奖品后：清除奖品缓存

## 注意事项

1. **性能影响**：自动清除缓存会在每次模型操作后执行，对性能有轻微影响。如果对性能要求极高，可以禁用自动清除，改为手动清除。

2. **错误处理**：自动清除缓存失败不会影响主流程，只会记录警告日志。

3. **批量操作**：批量更新时，建议手动清除缓存，避免多次清除：

```php
// 批量更新规则
PrizeRule::where('id', 'in', [1, 2, 3])->update(['weight' => 20]);

// 手动清除缓存（只清除一次）
$cacheService = App::make(CacheService::class);
$cacheService->clearRuleCache();
```

4. **事务处理**：如果在事务中更新模型，缓存会在事务提交后清除。如果需要立即清除，可以在事务提交后手动调用：

```php
Db::startTrans();
try {
    $rule = PrizeRule::find(1);
    $rule->weight = 20;
    $rule->save();
    
    Db::commit();
    
    // 事务提交后，自动清除缓存会执行
    // 如果需要立即清除，可以手动调用
    // $cacheService->clearRuleCache(1);
} catch (\Exception $e) {
    Db::rollback();
}
```

## 禁用自动清除

如果不想使用自动清除功能，可以不使用 Trait，或者重写相关方法：

```php
class PrizeRule extends BasePrizeRule
{
    use AutoClearCache;

    /**
     * 重写清除缓存方法，禁用自动清除
     */
    protected function clearRelatedCache(): void
    {
        // 不执行任何操作
    }
}
```

## 最佳实践

1. **开发环境**：建议启用自动清除，方便开发调试
2. **生产环境**：根据实际情况选择：
   - 如果更新频率低，可以启用自动清除
   - 如果更新频率高，建议禁用自动清除，改为定时清除或手动清除
3. **监控**：监控缓存清除操作的日志，确保缓存及时更新
