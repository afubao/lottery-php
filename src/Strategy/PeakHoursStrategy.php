<?php
declare(strict_types=1);

namespace Leo\Lottery\Strategy;

use Leo\Lottery\Contracts\DistributionStrategyInterface;
use Leo\Lottery\Contracts\CacheInterface;
use Leo\Lottery\Common\CacheKeyBuilder;
use Exception;
use think\facade\Log;

/**
 * 峰值小时发放策略
 * 峰值时段按照总量的100%发放，非峰值时段按照总量的20%发放
 */
class PeakHoursStrategy implements DistributionStrategyInterface
{
    private CacheInterface $cache;
    private CacheKeyBuilder $keyBuilder;
    private array $hotHours;
    private float $peakRatio;
    private float $nonPeakRatio;

    public function __construct(
        CacheInterface $cache,
        array $hotHours = [],
        float $peakRatio = 1.0,
        float $nonPeakRatio = 0.2,
        string $prefixKey = 'lottery:'
    ) {
        $this->cache = $cache;
        $this->keyBuilder = new CacheKeyBuilder($prefixKey);
        $this->hotHours = empty($hotHours) ? [9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21] : $hotHours;
        $this->peakRatio = $peakRatio;
        $this->nonPeakRatio = $nonPeakRatio;
    }

    /**
     * 检查是否可以发放奖品
     * @param int $prizeId 奖品ID
     * @param int $total 总数量
     * @param array $context 上下文信息
     * @return bool
     */
    public function canDistribute(int $prizeId, int $total, array $context = []): bool
    {
        $cacheKey = $this->keyBuilder->prizeDistribution($prizeId);
        $curDay = $this->cache->get($cacheKey, []);
        $curHour = (int)date('G');
        $curDaySum = array_sum($curDay);
        
        // 已超过总量
        if ($curDaySum >= $total) {
            return false;
        }
        
        $residue = $total - $curDaySum;
        
        // 判断当前是否为峰值时段
        if (in_array($curHour, $this->hotHours)) {
            // 峰值时段
            if (!isset($curDay[$curHour])) {
                return true; // 峰值时段首次发放，直接放行
            }
            
            $hourKey = array_search($curHour, $this->hotHours);
            $residueHourNum = count($this->hotHours) - $hourKey;
            
            if ($residueHourNum == 1) {
                return true; // 最后一个峰值小时
            }
            
            // 峰值时段按照总量的100%发放
            $hourMaxNum = floor($residue / $residueHourNum);
        } else {
            // 非峰值时段
            if (!empty($this->hotHours) && $curHour > end($this->hotHours)) {
                return true; // 已过峰值时段，直接放行
            }
            
            // 计算非峰值时段的小时数
            $allHours = range(0, 23);
            $diffHours = array_diff($allHours, $this->hotHours);
            $diffKey = array_search($curHour, array_values($diffHours));
            $residueHourNum = 24 - count($this->hotHours) - $diffKey;
            
            // 非峰值时段按照总量的20%发放
            $residue *= $this->nonPeakRatio;
            $hourMaxNum = floor($residue / $residueHourNum);
        }
        
        if ($hourMaxNum <= 0) {
            return false;
        }
        
        // 检查当前小时是否超过限制
        if (isset($curDay[$curHour]) && $curDay[$curHour] >= $hourMaxNum) {
            return false;
        }
        
        return true;
    }

    /**
     * 记录发放数量
     * @param int $prizeId 奖品ID
     * @return void
     */
    public function recordDistribution(int $prizeId): void
    {
        try {
            $cacheKey = $this->keyBuilder->prizeDistribution($prizeId);
            $curHour = (int)date('G');
            $curDay = $this->cache->get($cacheKey, []);
            
            if (isset($curDay[$curHour])) {
                $curDay[$curHour]++;
            } else {
                $curDay[$curHour] = 1;
            }
            
            $this->cache->set($cacheKey, $curDay);
        } catch (Exception $e) {
            Log::warning('[Lottery] Failed to record distribution', [
                'error' => $e->getMessage(),
                'prize_id' => $prizeId
            ]);
        }
    }
}
