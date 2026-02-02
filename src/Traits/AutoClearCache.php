<?php
declare(strict_types=1);

namespace Leo\Lottery\Traits;

use Leo\Lottery\Service\CacheService;
use think\facade\App;

/**
 * 自动清除缓存 Trait
 * 在模型更新、插入、删除后自动清除相关缓存
 * 
 * 使用方法：
 * 在模型中 use 这个 Trait，并实现 getCacheService() 方法
 * 
 * 示例：
 * ```php
 * class PrizeRule extends Model
 * {
 *     use AutoClearCache;
 *     
 *     protected function getCacheService(): CacheService
 *     {
 *         return App::make(CacheService::class);
 *     }
 * }
 * ```
 */
trait AutoClearCache
{
    /**
     * 获取缓存服务实例
     * @return CacheService
     */
    abstract protected function getCacheService(): CacheService;

    /**
     * 模型更新后事件
     */
    public function onAfterUpdate(): void
    {
        $this->clearRelatedCache();
    }

    /**
     * 模型插入后事件
     */
    public function onAfterInsert(): void
    {
        $this->clearRelatedCache();
    }

    /**
     * 模型删除后事件
     */
    public function onAfterDelete(): void
    {
        $this->clearRelatedCache();
    }

    /**
     * 清除相关缓存
     */
    protected function clearRelatedCache(): void
    {
        try {
            $cacheService = $this->getCacheService();
            
            // 根据模型类型清除不同的缓存
            if ($this instanceof \Leo\Lottery\Models\PrizeRule) {
                // 清除规则缓存
                $ruleId = $this->id ?? null;
                if ($ruleId) {
                    $cacheService->clearRuleCache($ruleId);
                } else {
                    $cacheService->clearRuleCache();
                }
            } elseif ($this instanceof \Leo\Lottery\Models\LotteryPrize) {
                // 清除奖品缓存
                $cacheService->clearPrizeCache();
            }
        } catch (\Exception $e) {
            // 静默失败，不影响主流程
            \think\facade\Log::warning('[Lottery] Failed to clear cache automatically', [
                'model' => get_class($this),
                'id' => $this->id ?? null,
                'error' => $e->getMessage()
            ]);
        }
    }
}
