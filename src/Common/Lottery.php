<?php
declare(strict_types=1);

namespace Leo\Lottery\Common;

use Leo\Lottery\Contracts\CacheInterface;
use Leo\Lottery\Contracts\RedisInterface;
use Exception;
use think\facade\Log;

/**
 * 奖品发放控制类
 * @deprecated 此类的功能已被重构到以下组件：
 *   - PeakHoursStrategy: 发放策略控制
 *   - StockManager: 库存管理
 *   - CacheManager: 缓存管理
 *   请使用新的组件架构
 */
class Lottery
{
    /**
     * @var array
     */
    private static array $_instance = [];
    private int $prizeId = 0;
    private CacheInterface $cache;
    private ?RedisInterface $redis = null;

    private function __construct(int $prizeId, CacheInterface $cache, ?RedisInterface $redis = null) {
        $this->prizeId = $prizeId;
        $this->cache = $cache;
        $this->redis = $redis;
    }

    public static function getInstance(int $prizeId, CacheInterface $cache, ?RedisInterface $redis = null): Lottery
    {
        $instanceKey = $prizeId . '_' . ($redis ? 'redis' : 'cache');
        if (!isset(self::$_instance[$instanceKey])
            || !(self::$_instance[$instanceKey] instanceof Lottery)
        ) {
            self::$_instance[$instanceKey] = new Lottery($prizeId, $cache, $redis);
        }
        return self::$_instance[$instanceKey];
    }

    private function __clone() {
    }

    /**
     * 检测超量
     * @param int $total 总数量
     * @param array $hotHours 峰值小时数组（0-23），如果为空则使用默认值
     * @return bool
     */
    public function checkExcessive(int $total, array $hotHours = []): bool {
        if (empty($hotHours)) {
            // 默认峰值小时：9-21点
            $hotHours = [9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21];
        }
        // 当天的奖品放量
        $curDay = $this->cache->get($this->getCacheKey(), []);
        $curHour = (int)date('G');
        $curDaySum = array_sum($curDay);
        if ($curDaySum >= $total) {
            // 超量
            return false;
        }
        $residue = $total - $curDaySum;
        if (in_array($curHour, $hotHours)) {
            if (!isset($curDay[$curHour])) {
                // 峰值流量没放过量，直接放行
                return true;
            }
            $hourKey = array_search($curHour, $hotHours);
            // 峰值流量，按照总量的100%发放
            $residueHourNum = count($hotHours) - $hourKey;
            if ($residueHourNum == 1) {
                // 最后一个峰值流量小时内
                return true;
            }
        } else {
            // 过了峰值小时，直接放行
            // 确保 $hotHours 不为空后再调用 end()
            if (!empty($hotHours) && $curHour > end($hotHours)) {
                return true;
            }
            // 非峰值流量，按照总量的20%的比例发放
            $allHours = range(0, 23);
            $diffHours = array_diff($allHours, $hotHours);
            $diffKey = array_search($curHour, array_values($diffHours));
            $residueHourNum = 24 - count($hotHours) - $diffKey;
            $residue *= 0.2;
        }
        $hourMaxNum = floor($residue / $residueHourNum) * 2;
        if ($hourMaxNum <= 0) {
            return false;
        }
        if (isset($curDay[$curHour])) {
            if ($curDay[$curHour] >= $hourMaxNum) {
                return false;
            }
        }
        return true;
    }

    /**
     * 增加发放数量（原子性操作）
     * 
     * 优先使用 Redis HINCRBY 原子操作，如果 Redis 不可用则回退到缓存操作
     */
    public function add(): void {
        $curHour = (int)date('G');
        $cacheKey = $this->getCacheKey();
        
        // 优先使用 Redis HINCRBY 原子操作
        if ($this->redis !== null) {
            try {
                // 使用 Redis HINCRBY 原子操作
                $this->redis->hincrby($cacheKey, (string)$curHour, 1);
                
                // 从 Redis Hash 读取最新数据更新缓存，保持数据一致性
                $redisData = $this->redis->hgetall($cacheKey);
                if (!empty($redisData)) {
                    // 将 Redis Hash 数据转换为数组格式
                    $curDay = [];
                    foreach ($redisData as $hour => $count) {
                        if (is_numeric($hour)) {
                            $curDay[(int)$hour] = (int)$count;
                        }
                    }
                    $this->cache->set($cacheKey, $curDay);
                } else {
                    // 如果 Redis Hash 为空，使用当前小时的数据
                    $curDay = $this->cache->get($cacheKey, []);
                    if (isset($curDay[$curHour])) {
                        $curDay[$curHour]++;
                    } else {
                        $curDay[$curHour] = 1;
                    }
                    $this->cache->set($cacheKey, $curDay);
                }
                return;
            } catch (Exception $e) {
                // Redis 操作失败，回退到缓存操作
                Log::warning('[Lottery] Redis operation failed in add(), fallback to cache', [
                    'error' => $e->getMessage(),
                    'prize_id' => $this->prizeId,
                    'cache_key' => $cacheKey
                ]);
            }
        }
        
        // 回退方案：使用缓存操作（非原子性，但已通过分布式锁保护）
        // 注意：此操作在高并发下可能存在数据竞争，但已通过 LotteryService 的分布式锁保护
        $curDay = $this->cache->get($cacheKey, []);
        if (isset($curDay[$curHour])) {
            $curDay[$curHour]++;
        } else {
            $curDay[$curHour] = 1;
        }
        $this->cache->set($cacheKey, $curDay);
    }

    /**
     * 获取缓存键名
     * @return string
     */
    private function getCacheKey(): string {
        return 'prize_' . date('ymd') . '_' . $this->prizeId;
    }

    /**
     * 获取缓存
     * @return mixed
     */
    public function getCache() {
        return $this->cache->get($this->getCacheKey());
    }

    /**
     * 清除缓存
     * @return bool
     */
    public function clear(): bool {
        return $this->cache->delete($this->getCacheKey());
    }

}
