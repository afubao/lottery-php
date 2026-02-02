# 后台管理使用指南

## 规则和奖品更新后清除缓存

### 问题说明

抽奖组件使用了缓存机制来提升性能：
- **规则缓存**：规则信息会缓存到当天结束
- **奖品缓存**：奖品信息缓存5分钟

**重要**：如果在后台修改了规则或奖品，**必须清除缓存**，否则修改不会立即生效。

### 解决方案

#### 方案1：在后台更新时自动清除（推荐）

在后台管理系统的更新方法中，更新后立即清除缓存：

```php
<?php
namespace app\admin\controller;

use Leo\Lottery\Service\CacheService;
use Leo\Lottery\Models\PrizeRule;
use Leo\Lottery\Models\LotteryPrize;

class LotteryAdminController
{
    /**
     * 更新规则
     */
    public function updateRule($id, $data)
    {
        // 更新规则
        PrizeRule::update($data, ['id' => $id]);
        
        // 立即清除缓存，使新规则生效
        $cacheService = app(CacheService::class);
        $cacheService->clearRuleCache($id);
        
        return $this->success('规则更新成功，已清除缓存');
    }

    /**
     * 创建规则
     */
    public function createRule($data)
    {
        $rule = PrizeRule::create($data);
        
        // 清除缓存，使新规则生效
        $cacheService = app(CacheService::class);
        $cacheService->clearRuleCache();
        
        return $this->success('规则创建成功，已清除缓存');
    }

    /**
     * 删除规则
     */
    public function deleteRule($id)
    {
        PrizeRule::destroy($id);
        
        // 清除缓存
        $cacheService = app(CacheService::class);
        $cacheService->clearRuleCache($id);
        
        return $this->success('规则删除成功，已清除缓存');
    }

    /**
     * 更新奖品
     */
    public function updatePrize($id, $data)
    {
        // 更新奖品
        LotteryPrize::update($data, ['id' => $id]);
        
        // 立即清除缓存，使新奖品信息生效
        $cacheService = app(CacheService::class);
        $cacheService->clearPrizeCache();
        
        return $this->success('奖品更新成功，已清除缓存');
    }

    /**
     * 创建奖品
     */
    public function createPrize($data)
    {
        LotteryPrize::create($data);
        
        // 清除缓存
        $cacheService = app(CacheService::class);
        $cacheService->clearPrizeCache();
        
        return $this->success('奖品创建成功，已清除缓存');
    }

    /**
     * 删除奖品
     */
    public function deletePrize($id)
    {
        LotteryPrize::destroy($id);
        
        // 清除缓存
        $cacheService = app(CacheService::class);
        $cacheService->clearPrizeCache();
        
        return $this->success('奖品删除成功，已清除缓存');
    }
}
```

#### 方案2：使用模型事件（需要配置）

如果使用 ThinkPHP 的模型事件，可以在模型中添加事件监听：

```php
// 在后台管理系统中配置事件监听
use think\facade\Event;
use Leo\Lottery\Models\PrizeRule;
use Leo\Lottery\Models\LotteryPrize;
use Leo\Lottery\Service\CacheService;

// 监听规则更新事件
Event::listen('PrizeRule.after_update', function($rule) {
    $cacheService = app(CacheService::class);
    $cacheService->clearRuleCache($rule->id);
});

// 监听奖品更新事件
Event::listen('LotteryPrize.after_update', function($prize) {
    $cacheService = app(CacheService::class);
    $cacheService->clearPrizeCache();
});
```

#### 方案3：手动清除缓存接口

提供后台清除缓存的接口：

```php
/**
 * 清除规则缓存
 */
public function clearRuleCache($ruleId = null)
{
    $cacheService = app(CacheService::class);
    if ($ruleId) {
        $cacheService->clearRuleCache($ruleId);
        return $this->success("规则 {$ruleId} 的缓存已清除");
    } else {
        $cacheService->clearRuleCache();
        return $this->success('当天所有规则缓存已清除');
    }
}

/**
 * 清除奖品缓存
 */
public function clearPrizeCache()
{
    $cacheService = app(CacheService::class);
    $cacheService->clearPrizeCache();
    return $this->success('奖品缓存已清除');
}

/**
 * 清除所有缓存
 */
public function clearAllCache()
{
    $cacheService = app(CacheService::class);
    $cacheService->clearAllCache();
    return $this->success('所有缓存已清除');
}
```

### 批量更新场景

如果批量更新多个规则，可以传入规则ID数组：

```php
// 批量更新规则
$ruleIds = [1, 2, 3];
foreach ($ruleIds as $id) {
    PrizeRule::update($data, ['id' => $id]);
}

// 批量清除缓存
$cacheService = app(CacheService::class);
$cacheService->clearRuleCache(null, $ruleIds);
```

### 运营检查清单

在后台更新规则或奖品后，请确认：

- [ ] 已调用 `CacheService::clearRuleCache()` 或 `CacheService::clearPrizeCache()`
- [ ] 测试抽奖功能，确认新规则/奖品已生效
- [ ] 检查日志，确认缓存清除成功
- [ ] 如发现异常，立即清除所有缓存：`$cacheService->clearAllCache()`

### 常见问题

**Q: 更新规则后忘记清除缓存怎么办？**

A: 立即调用 `clearRuleCache()` 清除缓存。规则缓存会在当天结束（00:00）后自动失效，但可能影响当天的运营活动。

**Q: 如何确认缓存已清除？**

A: 清除缓存后，下次抽奖时会从数据库重新加载规则，新规则会立即生效。可以通过日志或测试抽奖功能来验证。

**Q: 可以设置定时任务自动清除缓存吗？**

A: 可以，但不推荐。建议在更新时立即清除，确保规则立即生效。如果确实需要，可以设置每天凌晨清除前一天的缓存。
