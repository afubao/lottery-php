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
        
        // 收集需要清除发放统计缓存的奖品ID
        $prizeIdsToClear = [];
        
        // 清除指定规则的 Redis Hash，并收集奖品ID
        foreach ($rulesToClear as $id) {
            $ruleDetailKey = $this->keyBuilder->ruleDetail($id);
            try {
                // 在删除前，先获取规则信息中的 prize_id
                $ruleInfo = $this->redis->hgetall($ruleDetailKey);
                if (!empty($ruleInfo) && isset($ruleInfo['prize_id'])) {
                    $prizeIdsToClear[] = (int)$ruleInfo['prize_id'];
                }
                
                // 删除 Redis Hash
                $this->redis->eval("return redis.call('del', KEYS[1])", 1, $ruleDetailKey);
            } catch (\Exception $e) {
                // 忽略错误，继续执行
            }
        }
        
        // 清除这些规则关联奖品的发放统计缓存
        // 注意：同一个奖品可能被多个规则使用，所以需要去重
        $prizeIdsToClear = array_unique($prizeIdsToClear);
        foreach ($prizeIdsToClear as $prizeId) {
            $this->clearDistributionCache($prizeId);
        }
        
        // 注意：如果未指定规则ID，只清除缓存键，Redis Hash 会在下次查询时自动重建
        // 此时无法获取奖品ID，所以不会清除发放统计缓存
        // 建议：如果需要清除所有缓存，请使用 clearAllCache()
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
     * 清除奖品发放数量统计缓存（用于峰值小时策略）
     * @param int|null $prizeId 奖品ID，如果为null则清除当天所有奖品的发放统计缓存
     * @param string|null $date 日期格式：ymd，如 250201，null 表示今天
     */
    public function clearDistributionCache(?int $prizeId = null, ?string $date = null): void
    {
        $date = $date ?? date('ymd');
        
        if ($prizeId !== null) {
            // 清除指定奖品的发放统计缓存
            $distributionKey = $this->keyBuilder->prizeDistribution($prizeId, $date);
            $this->cache->delete($distributionKey);
        } else {
            // 清除当天所有奖品的发放统计缓存
            // 由于 CacheInterface 不提供模式匹配功能，这里使用 Redis 的 SCAN 命令
            // 注意：这要求底层缓存驱动是 Redis
            try {
                $pattern = $this->keyBuilder->getPrefixKey() . 'distribution:*:' . $date;
                // 使用 Lua 脚本通过 SCAN 命令查找并删除匹配的键（避免 KEYS 命令阻塞）
                $luaScript = "
                    local cursor = '0'
                    local deleted = 0
                    repeat
                        local result = redis.call('SCAN', cursor, 'MATCH', ARGV[1], 'COUNT', 100)
                        cursor = result[1]
                        local keys = result[2]
                        if #keys > 0 then
                            deleted = deleted + redis.call('DEL', unpack(keys))
                        end
                    until cursor == '0'
                    return deleted
                ";
                $this->redis->eval($luaScript, 0, $pattern);
            } catch (\Exception $e) {
                // 如果 SCAN 失败（可能是非 Redis 驱动或集群模式），尝试直接通过缓存接口删除
                // 由于无法获取所有奖品ID，这里只能清除已知的缓存键
                // 建议：如果需要清除所有 distribution 缓存，请指定具体的 prizeId 或使用数据库查询所有奖品ID
            }
        }
    }

    /**
     * 清除所有抽奖相关缓存
     */
    public function clearAllCache(): void
    {
        $this->clearRuleCache();
        $this->clearPrizeCache();
        $this->clearDistributionCache(); // 清除发放数量统计缓存
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
