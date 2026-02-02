<?php
declare(strict_types=1);

namespace Leo\Lottery\Manager;

use Leo\Lottery\Contracts\StockManagerInterface;
use Leo\Lottery\Contracts\RedisInterface;
use Leo\Lottery\Contracts\CacheInterface;
use Leo\Lottery\Common\CacheKeyBuilder;
use Leo\Lottery\Models\PrizeRule;
use Leo\Lottery\Models\LotteryPrize;
use Exception;
use think\facade\Log;
use think\facade\Db;

/**
 * 库存管理器
 * 统一管理库存，以数据库为准，Redis作为缓存
 */
class StockManager implements StockManagerInterface
{
    private RedisInterface $redis;
    private CacheInterface $cache;
    private CacheKeyBuilder $keyBuilder;

    public function __construct(
        RedisInterface $redis,
        CacheInterface $cache,
        string $prefixKey = 'lottery:'
    ) {
        $this->redis = $redis;
        $this->cache = $cache;
        $this->keyBuilder = new CacheKeyBuilder($prefixKey);
    }

    /**
     * 检查规则库存是否充足
     * @param int $ruleId 规则ID
     * @return bool
     */
    public function checkStock(int $ruleId): bool
    {
        try {
            // 先检查 Redis 缓存
            $ruleDetailKey = $this->keyBuilder->ruleDetail($ruleId);
            
            try {
                $surplus = $this->redis->hgetall($ruleDetailKey);
                if (!empty($surplus) && isset($surplus['surplus_num'])) {
                    return (int)$surplus['surplus_num'] > 0;
                }
            } catch (Exception $e) {
                Log::warning('[Lottery] Redis check stock failed, fallback to database', [
                    'error' => $e->getMessage(),
                    'rule_id' => $ruleId
                ]);
            }

            // 从数据库检查
            $rule = PrizeRule::find($ruleId);
            return $rule && $rule->surplus_num > 0;
        } catch (Exception $e) {
            Log::error('[Lottery] Failed to check stock', [
                'error' => $e->getMessage(),
                'rule_id' => $ruleId
            ]);
            return false;
        }
    }

    /**
     * 扣减规则库存（原子性操作）
     * @param int $ruleId 规则ID
     * @return bool
     */
    public function decrementStock(int $ruleId): bool
    {
        $ruleDetailKey = $this->keyBuilder->ruleDetail($ruleId);

        try {
            // 先检查数据库库存
            $rule = PrizeRule::find($ruleId);
            if (!$rule || $rule->surplus_num <= 0) {
                return false;
            }

            // 使用 Lua 脚本原子性扣减 Redis 库存
            // 如果 Redis Hash 不存在，先初始化
            $luaScript = "
                local surplus = redis.call('hget', KEYS[1], 'surplus_num')
                if not surplus then
                    -- Redis Hash 不存在，从数据库初始化
                    return {2, 0}
                end
                if tonumber(surplus) <= 0 then
                    return {0, tonumber(surplus) or 0}
                end
                local newSurplus = redis.call('hincrby', KEYS[1], 'surplus_num', -1)
                return {1, newSurplus}
            ";
            $result = $this->redis->eval($luaScript, 1, $ruleDetailKey);
            
            // result[0] = 1: Redis 扣减成功
            // result[0] = 2: Redis Hash 不存在，需要初始化
            // result[0] = 0: Redis 库存不足
            if (is_array($result) && count($result) >= 2) {
                if ($result[0] === 1) {
                    // Redis 扣减成功，更新数据库
                    $affected = PrizeRule::where('id', $ruleId)
                        ->where('surplus_num', '>', 0)
                        ->dec('surplus_num')
                        ->update();
                    
                    if ($affected === 0) {
                        // 数据库扣减失败，回滚 Redis
                        $this->redis->hincrby($ruleDetailKey, 'surplus_num', 1);
                        return false;
                    }
                    
                    return true;
                } elseif ($result[0] === 2) {
                    // Redis Hash 不存在，直接使用数据库
                    $affected = PrizeRule::where('id', $ruleId)
                        ->where('surplus_num', '>', 0)
                        ->dec('surplus_num')
                        ->update();
                    
                    if ($affected > 0) {
                        // 初始化 Redis Hash
                        $this->redis->hmset($ruleDetailKey, [
                            'surplus_num' => $rule->surplus_num - 1,
                            'id' => $ruleId,
                            'prize_id' => $rule->prize_id,
                            'total_num' => $rule->total_num,
                            'weight' => $rule->weight,
                        ]);
                    }
                    
                    return $affected > 0;
                }
            }
            
            return false;
        } catch (Exception $e) {
            Log::warning('[Lottery] Redis decrement stock failed, using database only', [
                'error' => $e->getMessage(),
                'rule_id' => $ruleId
            ]);
            
            // Redis 失败，直接使用数据库
            $affected = PrizeRule::where('id', $ruleId)
                ->where('surplus_num', '>', 0)
                ->dec('surplus_num')
                ->update();
            
            return $affected > 0;
        }
    }

    /**
     * 获取规则剩余库存
     * @param int $ruleId 规则ID
     * @return int
     */
    public function getRemainingStock(int $ruleId): int
    {
        try {
            $ruleDetailKey = $this->keyBuilder->ruleDetail($ruleId);
            
            try {
                $surplus = $this->redis->hgetall($ruleDetailKey);
                if (!empty($surplus) && isset($surplus['surplus_num'])) {
                    return (int)$surplus['surplus_num'];
                }
            } catch (Exception $e) {
                // Redis 失败，从数据库获取
            }

            $rule = PrizeRule::find($ruleId);
            return $rule ? $rule->surplus_num : 0;
        } catch (Exception $e) {
            Log::error('[Lottery] Failed to get remaining stock', [
                'error' => $e->getMessage(),
                'rule_id' => $ruleId
            ]);
            return 0;
        }
    }

    /**
     * 回滚规则库存
     * @param int $ruleId 规则ID
     * @return bool
     */
    public function rollbackStock(int $ruleId): bool
    {
        try {
            $ruleDetailKey = $this->keyBuilder->ruleDetail($ruleId);
            
            // 回滚 Redis
            try {
                $this->redis->hincrby($ruleDetailKey, 'surplus_num', 1);
            } catch (Exception $e) {
                Log::warning('[Lottery] Failed to rollback Redis stock', [
                    'error' => $e->getMessage(),
                    'rule_id' => $ruleId
                ]);
            }
            
            // 回滚数据库
            PrizeRule::where('id', $ruleId)->inc('surplus_num')->update();
            return true;
        } catch (Exception $e) {
            Log::error('[Lottery] Failed to rollback stock', [
                'error' => $e->getMessage(),
                'rule_id' => $ruleId
            ]);
            return false;
        }
    }

    /**
     * 检查奖品库存是否充足
     * @param int $prizeId 奖品ID
     * @return bool
     */
    public function checkPrizeStock(int $prizeId): bool
    {
        try {
            $prize = LotteryPrize::find($prizeId);
            return $prize && $prize->remaining_quantity > 0;
        } catch (Exception $e) {
            Log::error('[Lottery] Failed to check prize stock', [
                'error' => $e->getMessage(),
                'prize_id' => $prizeId
            ]);
            return false;
        }
    }

    /**
     * 扣减奖品库存
     * @param int $prizeId 奖品ID
     * @return bool
     */
    public function decrementPrizeStock(int $prizeId): bool
    {
        try {
            $affected = LotteryPrize::where('id', $prizeId)
                ->where('remaining_quantity', '>', 0)
                ->dec('remaining_quantity')
                ->update();
            
            return $affected > 0;
        } catch (Exception $e) {
            Log::error('[Lottery] Failed to decrement prize stock', [
                'error' => $e->getMessage(),
                'prize_id' => $prizeId
            ]);
            return false;
        }
    }

    /**
     * 回滚奖品库存
     * @param int $prizeId 奖品ID
     * @return bool
     */
    public function rollbackPrizeStock(int $prizeId): bool
    {
        try {
            LotteryPrize::where('id', $prizeId)->inc('remaining_quantity')->update();
            return true;
        } catch (Exception $e) {
            Log::error('[Lottery] Failed to rollback prize stock', [
                'error' => $e->getMessage(),
                'prize_id' => $prizeId
            ]);
            return false;
        }
    }
}
