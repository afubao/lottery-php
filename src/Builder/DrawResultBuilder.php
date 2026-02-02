<?php
declare(strict_types=1);

namespace Leo\Lottery\Builder;

use Leo\Lottery\Models\LotteryDraw;
use Leo\Lottery\Models\LotteryPrize;
use Leo\Lottery\Models\PrizeRule;
use Leo\Lottery\Contracts\CacheInterface;
use Leo\Lottery\Contracts\RedisInterface;
use Leo\Lottery\Common\PrizeType;
use Leo\Lottery\Common\CacheKeyBuilder;
use Leo\Lottery\Security\AntiCheatManager;
use Exception;
use think\facade\Log;

/**
 * 抽奖结果构建器
 */
class DrawResultBuilder
{
    private const STATS_TTL = 7 * 24 * 3600; // 7天过期时间
    
    private CacheInterface $cache;
    private ?RedisInterface $redis;
    private CacheKeyBuilder $keyBuilder;
    private ?AntiCheatManager $antiCheatManager;
    private bool $recordThanksPrize;
    private bool $enableStatistics;

    public function __construct(
        CacheInterface $cache,
        string $prefixKey = 'lottery:',
        bool $recordThanksPrize = true,
        ?RedisInterface $redis = null,
        bool $enableStatistics = true,
        ?AntiCheatManager $antiCheatManager = null
    ) {
        $this->cache = $cache;
        $this->redis = $redis;
        $this->keyBuilder = new CacheKeyBuilder($prefixKey);
        $this->antiCheatManager = $antiCheatManager;
        $this->recordThanksPrize = $recordThanksPrize;
        $this->enableStatistics = $enableStatistics;
    }

    /**
     * 构建抽奖结果
     * @param string $openid 用户标识
     * @param string $ip IP地址
     * @param PrizeRule $rule 奖品规则
     * @param LotteryPrize $prize 奖品信息
     * @return array 抽奖结果 ['draw_id' => string, 'prize' => array]
     */
    public function build(string $openid, string $ip, PrizeRule $rule, LotteryPrize $prize): array
    {
        // 创建抽奖记录
        $prizeInfo = $prize->toArray();
        $prizeInfo['ip'] = $ip;
        $drawData = LotteryDraw::createDraw($openid, $rule->id, $prizeInfo);
        
        // 记录用户已中奖的奖品（用于兜底奖品去重）
        $this->recordUserPrize($openid, $prize->id);
        
        // 构建返回结果
        $prizeInfo = $prize->toArray();
        unset($prizeInfo['id'], $prizeInfo['total'], $prizeInfo['remaining_quantity'], $prizeInfo['weight']);
        
        $result = [
            'draw_id' => $drawData->draws_id,
            'is_win' => true, // 真实中奖
            'prize' => $prizeInfo,
        ];
        
        // 如果启用了防作弊管理器，添加签名
        if ($this->antiCheatManager !== null) {
            $signature = $this->antiCheatManager->signResult(
                $drawData->draws_id,
                $openid,
                $prizeInfo
            );
            if (!empty($signature)) {
                $result['signature'] = $signature;
            }
        }
        
        return $result;
    }

    /**
     * 构建兜底奖品结果
     * @param string $openid 用户标识
     * @param string $ip IP地址
     * @param array $fallbackPrize 兜底奖品信息
     * @return array
     */
    public function buildFallback(string $openid, string $ip, array $fallbackPrize): array
    {
        $fallbackPrize['ip'] = $ip;
        $prizeId = $fallbackPrize['id'] ?? 0;
        
        // 重要：为了防作弊验证，所有抽奖结果都必须有数据库记录
        // 即使 record_thanks_prize=false，也要创建记录，确保可以通过 draw_id 验证
        $drawData = LotteryDraw::createDraw($openid, 0, $fallbackPrize);
        $drawId = $drawData->draws_id;
        
        // 如果配置不记录"谢谢参与"到数据库，使用 Redis 计数器统计（用于统计）
        // 但数据库记录仍然创建，用于防作弊验证
        if ($prizeId === 0 && !$this->recordThanksPrize && $this->enableStatistics) {
            $this->incrementThanksStatistics($openid);
        }
        
        // 记录用户已中奖的兜底奖品（用于兜底奖品去重）
        // 只有配置的兜底奖品（id>0）才记录，空的"谢谢参与"不记录
        if ($prizeId > 0) {
            $this->recordUserPrize($openid, $prizeId);
        }
        
        // 判断是否中奖：配置的兜底奖品（id>0）算中奖，空的"谢谢参与"（id=0）不算中奖
        $isWin = $prizeId > 0;
        
        $result = [
            'draw_id' => $drawId,
            'is_win' => $isWin, // 明确标识是否中奖
            'prize' => $fallbackPrize,
        ];
        
        // 如果启用了防作弊管理器，添加签名
        if ($this->antiCheatManager !== null) {
            $signature = $this->antiCheatManager->signResult(
                $drawId,
                $openid,
                $fallbackPrize
            );
            if (!empty($signature)) {
                $result['signature'] = $signature;
            }
        }
        
        return $result;
    }

    /**
     * 增加"谢谢参与"统计计数
     * @param string $openid 用户标识
     * @return void
     */
    private function incrementThanksStatistics(string $openid): void
    {
        if ($this->redis === null) {
            return;
        }

        try {
            $today = date('ymd');
            
            // 使用 Lua 脚本原子性地执行 INCR 和设置过期时间
            // 如果 key 不存在，先设置为 1 并设置过期时间；如果存在，则 INCR
            $luaScript = "
                local key = KEYS[1]
                local ttl = tonumber(ARGV[1])
                local exists = redis.call('EXISTS', key)
                if exists == 0 then
                    redis.call('SET', key, 1)
                    redis.call('EXPIRE', key, ttl)
                    return 1
                else
                    return redis.call('INCR', key)
                end
            ";
            
            // 用户每日统计
            $userKey = $this->keyBuilder->thanksStats($openid, $today);
            $this->redis->eval($luaScript, 1, $userKey, self::STATS_TTL);
            
            // 全局每日统计
            $globalKey = $this->keyBuilder->globalThanksStats($today);
            $this->redis->eval($luaScript, 1, $globalKey, self::STATS_TTL);
            
            // 用户累计统计（不过期）
            $userTotalKey = $this->keyBuilder->thanksStats($openid);
            $this->redis->incr($userTotalKey);
        } catch (Exception $e) {
            // 静默失败，不影响抽奖功能
            Log::warning('[Lottery] Failed to increment thanks statistics', [
                'error' => $e->getMessage(),
                'openid' => $openid
            ]);
        }
    }

    /**
     * 记录用户已中奖的奖品
     * @param string $openid
     * @param int $prizeId
     * @return void
     */
    private function recordUserPrize(string $openid, int $prizeId): void
    {
        try {
            $cacheKey = $this->keyBuilder->userDraws($openid);
            $this->cache->push($cacheKey, $prizeId);
        } catch (Exception $e) {
            Log::warning('[Lottery] Failed to record user prize', [
                'error' => $e->getMessage(),
                'openid' => $openid,
                'prize_id' => $prizeId
            ]);
        }
    }
}
