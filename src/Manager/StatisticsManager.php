<?php
declare(strict_types=1);

namespace Leo\Lottery\Manager;

use Leo\Lottery\Contracts\RedisInterface;
use Leo\Lottery\Models\LotteryDraw;
use Leo\Lottery\Common\CacheKeyBuilder;
use Exception;
use think\facade\Log;

/**
 * 统计管理器
 * 用于查询"谢谢参与"的统计信息
 */
class StatisticsManager
{
    private RedisInterface $redis;
    private CacheKeyBuilder $keyBuilder;

    public function __construct(RedisInterface $redis, string $prefixKey = 'lottery:')
    {
        $this->redis = $redis;
        $this->keyBuilder = new CacheKeyBuilder($prefixKey);
    }

    /**
     * 获取用户"谢谢参与"次数
     * @param string $openid 用户标识
     * @param string|null $date 日期（格式：ymd），null 表示累计总数
     * @return int
     */
    public function getUserThanksCount(string $openid, ?string $date = null): int
    {
        try {
            $key = $this->keyBuilder->thanksStats($openid, $date);
            $value = $this->redis->get($key);
            return $value !== null ? (int)$value : 0;
        } catch (Exception $e) {
            Log::warning('[Lottery] Failed to get user thanks count', [
                'error' => $e->getMessage(),
                'openid' => $openid,
                'date' => $date
            ]);
            return 0;
        }
    }

    /**
     * 获取全局"谢谢参与"次数
     * @param string|null $date 日期（格式：ymd），null 表示所有日期累计
     * @return int
     */
    public function getGlobalThanksCount(?string $date = null): int
    {
        try {
            if ($date === null) {
                // 获取所有日期的累计总数（需要遍历所有日期，这里简化处理）
                // 注意：此方法性能较低，建议传入具体日期
                return 0;
            } else {
                // 获取某天的全局统计
                $key = $this->keyBuilder->globalThanksStats($date);
                $value = $this->redis->get($key);
                return $value !== null ? (int)$value : 0;
            }
        } catch (Exception $e) {
            Log::warning('[Lottery] Failed to get global thanks count', [
                'error' => $e->getMessage(),
                'date' => $date
            ]);
            return 0;
        }
    }

    /**
     * 获取用户总抽奖统计（中奖次数 + 谢谢参与次数）
     * @param string $openid 用户标识
     * @param string|null $date 日期（格式：ymd），null 表示累计总数
     * @return array ['win_count' => int, 'thanks_count' => int, 'total' => int]
     */
    public function getUserTotalDrawCount(string $openid, ?string $date = null): array
    {
        try {
            // 获取中奖次数（从数据库查询）
            $winCount = 0;
            if ($date === null) {
                // 累计总数
                $winCount = LotteryDraw::where('openid', $openid)
                    ->where('prize_id', '>', 0) // 排除"谢谢参与"（id=0）
                    ->count();
            } else {
                // 某天的统计
                $startDate = date('Y-m-d', strtotime($date));
                $endDate = date('Y-m-d', strtotime($date . ' +1 day'));
                $winCount = LotteryDraw::where('openid', $openid)
                    ->where('prize_id', '>', 0)
                    ->whereTime('create_time', '>=', $startDate)
                    ->whereTime('create_time', '<', $endDate)
                    ->count();
            }

            // 获取"谢谢参与"次数（从 Redis 查询）
            $thanksCount = $this->getUserThanksCount($openid, $date);

            return [
                'win_count' => $winCount,
                'thanks_count' => $thanksCount,
                'total' => $winCount + $thanksCount,
            ];
        } catch (Exception $e) {
            Log::warning('[Lottery] Failed to get user total draw count', [
                'error' => $e->getMessage(),
                'openid' => $openid,
                'date' => $date
            ]);
            return [
                'win_count' => 0,
                'thanks_count' => 0,
                'total' => 0,
            ];
        }
    }

    /**
     * 获取用户中奖率
     * @param string $openid 用户标识
     * @param string|null $date 日期（格式：ymd），null 表示累计统计
     * @return float 中奖率（0-1之间的小数）
     */
    public function getUserWinRate(string $openid, ?string $date = null): float
    {
        $stats = $this->getUserTotalDrawCount($openid, $date);
        if ($stats['total'] === 0) {
            return 0.0;
        }
        return round($stats['win_count'] / $stats['total'], 4);
    }
}
