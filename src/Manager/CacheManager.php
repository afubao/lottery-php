<?php
declare(strict_types=1);

namespace Leo\Lottery\Manager;

use Leo\Lottery\Contracts\CacheManagerInterface;
use Leo\Lottery\Contracts\RedisInterface;
use Leo\Lottery\Contracts\CacheInterface;
use Leo\Lottery\Contracts\LockManagerInterface;
use Leo\Lottery\Common\CacheKeyBuilder;
use Leo\Lottery\Models\PrizeRule;
use Leo\Lottery\Models\LotteryPrize;
use Exception;
use think\facade\Log;

/**
 * 缓存管理器
 * 统一管理所有缓存，防止缓存击穿
 */
class CacheManager implements CacheManagerInterface
{
    private RedisInterface $redis;
    private CacheInterface $cache;
    private LockManagerInterface $lockManager;
    private CacheKeyBuilder $keyBuilder;
    
    private const RULES_CACHE_TTL = 86400; // 24小时（规则缓存到当天结束）
    private const PRIZES_CACHE_TTL = 300; // 5分钟

    public function __construct(
        RedisInterface $redis,
        CacheInterface $cache,
        LockManagerInterface $lockManager,
        string $prefixKey = 'lottery:'
    ) {
        $this->redis = $redis;
        $this->cache = $cache;
        $this->lockManager = $lockManager;
        $this->keyBuilder = new CacheKeyBuilder($prefixKey);
    }

    /**
     * 获取当前有效的奖品规则列表
     * @return array
     */
    public function getRules(): array
    {
        $cacheKey = $this->keyBuilder->rulesList();
        
        // 尝试从缓存获取
        if ($this->cache->has($cacheKey)) {
            $ruleIds = $this->cache->get($cacheKey);
            if (empty($ruleIds)) {
                return [];
            }
            
            // 从 Redis Hash 获取规则详情
            $rules = [];
            try {
                $pipeline = $this->redis->pipeline();
                foreach ($ruleIds as $ruleId) {
                    $ruleDetailKey = $this->keyBuilder->ruleDetail($ruleId);
                    $pipeline->hgetall($ruleDetailKey);
                }
                $ruleCache = $pipeline->execute();
                
                foreach ($ruleCache as $ruleInfo) {
                    if (isset($ruleInfo['surplus_num']) && (int)$ruleInfo['surplus_num'] > 0) {
                        $rules[] = $ruleInfo;
                    }
                }
            } catch (Exception $e) {
                Log::warning('[Lottery] Failed to get rules from Redis, querying database', [
                    'error' => $e->getMessage()
                ]);
                return $this->loadRulesFromDatabase($cacheKey);
            }
            
            return $rules;
        }
        
        // 缓存不存在，从数据库加载
        return $this->loadRulesFromDatabase($cacheKey);
    }

    /**
     * 从数据库加载规则并缓存
     * @param string $cacheKey
     * @return array
     */
    private function loadRulesFromDatabase(string $cacheKey): array
    {
        // 使用分布式锁防止缓存击穿
        $mutexKey = $this->keyBuilder->mutex('rules');
        $mutexValue = 'lock_' . uniqid('', true);
        $lockAcquired = $this->lockManager->acquire($mutexKey, $mutexValue, 5);
        
        try {
            // 双重检查
            if ($this->cache->has($cacheKey)) {
                return $this->getRules();
            }
            
            // 从数据库查询
            $currentTime = date('Y-m-d H:i:s');
            $rules = PrizeRule::field(['id', 'prize_id', 'total_num', 'surplus_num', 'weight', 'start_time', 'end_time'])
                ->whereTime('start_time', '<=', $currentTime)
                ->whereTime('end_time', '>', $currentTime)
                ->where('surplus_num', '>', 0)
                ->where('weight', '>', 0)
                ->select()
                ->toArray();
            
            if (!empty($rules)) {
                // 缓存到 Redis Hash
                $ruleIds = [];
                $pipeline = $this->redis->pipeline();
                foreach ($rules as $rule) {
                    $ruleDetailKey = $this->keyBuilder->ruleDetail($rule['id']);
                    $pipeline->hmset($ruleDetailKey, [
                        'id' => $rule['id'],
                        'weight' => $rule['weight'],
                        'prize_id' => $rule['prize_id'],
                        'total_num' => $rule['total_num'],
                        'surplus_num' => $rule['surplus_num'],
                    ]);
                    $ruleIds[] = $rule['id'];
                }
                $pipeline->execute();
                
                // 缓存规则ID列表
                $ttl = strtotime('tomorrow') - time();
                $this->cache->set($cacheKey, $ruleIds, $ttl);
            }
            
            return $rules;
        } catch (Exception $e) {
            Log::error('[Lottery] Failed to load rules from database', [
                'error' => $e->getMessage()
            ]);
            return [];
        } finally {
            if ($lockAcquired) {
                $this->lockManager->release($mutexKey, $mutexValue);
            }
        }
    }

    /**
     * 获取奖品信息
     * @param int $prizeId 奖品ID
     * @return array|null
     */
    public function getPrize(int $prizeId): ?array
    {
        // 尝试从缓存获取奖品列表
        $prizesCacheKey = $this->keyBuilder->prizesList();
        $prizeList = $this->cache->get($prizesCacheKey);
        
        if (empty($prizeList)) {
            // 使用分布式锁防止缓存击穿
            $mutexKey = $this->keyBuilder->mutex('prizes');
            $mutexValue = 'lock_' . uniqid('', true);
            $lockAcquired = $this->lockManager->acquire($mutexKey, $mutexValue, 5);
            
            try {
                // 双重检查
                $prizeList = $this->cache->get($prizesCacheKey);
                if (empty($prizeList)) {
                    $prizeList = LotteryPrize::getActivePrizes();
                    $this->cache->set($prizesCacheKey, $prizeList, self::PRIZES_CACHE_TTL);
                }
            } catch (Exception $e) {
                Log::error('[Lottery] Failed to load prizes from database', [
                    'error' => $e->getMessage()
                ]);
                $prizeList = LotteryPrize::getActivePrizes();
            } finally {
                if ($lockAcquired) {
                    $this->lockManager->release($mutexKey, $mutexValue);
                }
            }
        }
        
        // 查找指定奖品
        foreach ($prizeList as $prize) {
            if ($prize['id'] == $prizeId) {
                return $prize;
            }
        }
        
        return null;
    }

    /**
     * 清除规则缓存
     * @param int|null $ruleId 规则ID
     * @return void
     */
    public function clearRules(?int $ruleId = null): void
    {
        $cacheKey = $this->keyBuilder->rulesList();
        $this->cache->delete($cacheKey);
        
        if ($ruleId !== null) {
            try {
                $ruleDetailKey = $this->keyBuilder->ruleDetail($ruleId);
                $this->redis->eval("return redis.call('del', KEYS[1])", 1, $ruleDetailKey);
            } catch (Exception $e) {
                Log::warning('[Lottery] Failed to delete rule Redis cache', [
                    'error' => $e->getMessage(),
                    'rule_id' => $ruleId
                ]);
            }
        }
    }

    /**
     * 清除奖品缓存
     * @param int|null $prizeId 奖品ID
     * @return void
     */
    public function clearPrize(?int $prizeId = null): void
    {
        $prizesCacheKey = $this->keyBuilder->prizesList();
        $this->cache->delete($prizesCacheKey);
    }

    /**
     * 清除所有缓存
     * @return void
     */
    public function clearAll(): void
    {
        $this->clearRules();
        $this->clearPrize();
    }
}
