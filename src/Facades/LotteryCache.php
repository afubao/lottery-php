<?php
declare(strict_types=1);

namespace Leo\Lottery\Facades;

use think\Facade;
use Leo\Lottery\Service\CacheService;

/**
 * 缓存服务 Facade
 * 
 * 使用示例：
 * ```php
 * use Leo\Lottery\Facades\LotteryCache;
 * 
 * // 清除规则缓存
 * LotteryCache::clearRuleCache($ruleId);
 * 
 * // 清除奖品缓存
 * LotteryCache::clearPrizeCache();
 * 
 * // 清除所有缓存
 * LotteryCache::clearAllCache();
 * ```
 */
class LotteryCache extends Facade
{
    /**
     * 获取当前 Facade 对应的类名（或者服务标识）
     * @return string
     */
    protected static function getFacadeClass(): string
    {
        return CacheService::class;
    }
}
