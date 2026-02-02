<?php
declare(strict_types=1);

namespace Leo\Lottery\Service;

use Leo\Lottery\Contracts\CacheInterface;
use Leo\Lottery\Contracts\RedisInterface;
use Leo\Lottery\Common\Constants;
use Leo\Lottery\Common\CacheKeyBuilder;

/**
 * 缓存管理服务
 * 用于统一管理抽奖相关的缓存清除
 */
class CacheService
{
    private CacheInterface $cache;
    private RedisInterface $redis;
    private CacheKeyBuilder $keyBuilder;

    public function __construct(
        CacheInterface $cache,
        RedisInterface $redis,
        string $prefixKey = Constants::REDIS_PREFIX_KEY
    ) {
        $this->cache = $cache;
        $this->redis = $redis;
        $this->keyBuilder = new CacheKeyBuilder($prefixKey);
    }

    /**
     * 清除规则缓存（当规则更新时调用）
     * @param int|null $ruleId 规则ID，如果为null则清除当天所有规则缓存（只清除缓存键，Redis Hash会在下次查询时重建）
     * @param array|null $ruleIds 规则ID数组，用于批量清除多个规则的 Redis Hash
     */
    public function clearRuleCache(?int $ruleId = null, ?array $ruleIds = null): void
    {
        $cacheKey = $this->keyBuilder->rulesList();
        
        // 清除规则列表缓存
        $this->cache->delete($cacheKey);
        
        // 清除指定规则的 Redis Hash
        $rulesToClear = [];
        if ($ruleId !== null) {
            $rulesToClear[] = $ruleId;
        } elseif ($ruleIds !== null && !empty($ruleIds)) {
            $rulesToClear = $ruleIds;
        }
        
        // 清除指定规则的 Redis Hash
        foreach ($rulesToClear as $id) {
            $ruleDetailKey = $this->keyBuilder->ruleDetail($id);
            try {
                // 删除 Redis Hash
                $this->redis->eval("return redis.call('del', KEYS[1])", 1, $ruleDetailKey);
            } catch (\Exception $e) {
                // 忽略错误，继续执行
            }
        }
        
        // 注意：如果未指定规则ID，只清除缓存键，Redis Hash 会在下次查询时自动重建
    }

    /**
     * 清除奖品缓存（当奖品更新时调用）
     */
    public function clearPrizeCache(): void
    {
        $prizesCacheKey = $this->keyBuilder->prizesList();
        $this->cache->delete($prizesCacheKey);
    }

    /**
     * 清除所有抽奖相关缓存
     */
    public function clearAllCache(): void
    {
        $this->clearRuleCache();
        $this->clearPrizeCache();
    }

    /**
     * 清除指定日期的规则缓存（用于跨天场景）
     * @param string $date 日期格式：ymd，如 250201
     * @param array|null $ruleIds 规则ID数组，如果提供则只清除这些规则的 Redis Hash
     */
    public function clearRuleCacheByDate(string $date, ?array $ruleIds = null): void
    {
        $cacheKey = $this->keyBuilder->rulesList($date);
        $this->cache->delete($cacheKey);
        
        // 如果提供了规则ID列表，清除这些规则的 Redis Hash
        if ($ruleIds !== null && !empty($ruleIds)) {
            foreach ($ruleIds as $ruleId) {
                $ruleDetailKey = $this->keyBuilder->ruleDetail($ruleId, $date);
                try {
                    $this->redis->eval("return redis.call('del', KEYS[1])", 1, $ruleDetailKey);
                } catch (\Exception $e) {
                    // 忽略错误
                }
            }
        }
        // 注意：如果不提供规则ID列表，只清除缓存键，Redis Hash 会在下次查询时自动重建
    }
}
