<?php
declare(strict_types=1);

namespace Leo\Lottery\Security;

use Leo\Lottery\Contracts\RedisInterface;
use Leo\Lottery\Contracts\CacheInterface;
use Leo\Lottery\Common\CacheKeyBuilder;
use Leo\Lottery\Models\LotteryDraw;
use Exception;
use think\facade\Log;

/**
 * 防作弊管理器
 * 提供防重放攻击、结果签名验证等功能
 */
class AntiCheatManager
{
    private RedisInterface $redis;
    private CacheInterface $cache;
    private CacheKeyBuilder $keyBuilder;
    private ?string $secretKey;
    private int $nonceTtl;

    public function __construct(
        RedisInterface $redis,
        CacheInterface $cache,
        string $prefixKey = 'lottery:',
        ?string $secretKey = null,
        int $nonceTtl = 300 // 5分钟
    ) {
        $this->redis = $redis;
        $this->cache = $cache;
        $this->keyBuilder = new CacheKeyBuilder($prefixKey);
        $this->secretKey = $secretKey;
        $this->nonceTtl = $nonceTtl;
    }

    /**
     * 生成防重放攻击的 nonce
     * @param string $openid 用户标识
     * @return string nonce 值
     */
    public function generateNonce(string $openid): string
    {
        $nonce = bin2hex(random_bytes(16));
        $prefixKey = rtrim($this->keyBuilder->getPrefixKey(), ':');
        $nonceKey = $prefixKey . ':nonce:' . $openid . ':' . $nonce;
        
        try {
            // 存储 nonce，5分钟过期
            $this->redis->set($nonceKey, time(), $this->nonceTtl);
        } catch (Exception $e) {
            Log::warning('[Lottery] Failed to store nonce', [
                'error' => $e->getMessage(),
                'openid' => $openid
            ]);
        }
        
        return $nonce;
    }

    /**
     * 验证 nonce 是否有效（防重放攻击）
     * @param string $openid 用户标识
     * @param string $nonce nonce 值
     * @return bool
     */
    public function verifyNonce(string $openid, string $nonce): bool
    {
        if (empty($nonce)) {
            return false;
        }

        $prefixKey = rtrim($this->keyBuilder->getPrefixKey(), ':');
        $nonceKey = $prefixKey . ':nonce:' . $openid . ':' . $nonce;
        
        try {
            $value = $this->redis->get($nonceKey);
            if ($value === null) {
                // nonce 不存在或已过期，可能是重放攻击
                return false;
            }
            
            // 验证后删除 nonce，确保只能使用一次
            $this->redis->eval("return redis.call('del', KEYS[1])", 1, $nonceKey);
            return true;
        } catch (Exception $e) {
            Log::warning('[Lottery] Failed to verify nonce', [
                'error' => $e->getMessage(),
                'openid' => $openid,
                'nonce' => $nonce
            ]);
            // Redis 失败时，如果配置了 secretKey，允许继续（降级）
            return $this->secretKey !== null;
        }
    }

    /**
     * 生成抽奖结果签名
     * @param string $drawId 抽奖ID
     * @param string $openid 用户标识
     * @param array $prize 奖品信息
     * @return string 签名
     */
    public function signResult(string $drawId, string $openid, array $prize): string
    {
        if ($this->secretKey === null) {
            return '';
        }

        // 构建签名字符串
        $signString = sprintf(
            '%s|%s|%s|%s|%s',
            $drawId,
            $openid,
            $prize['id'] ?? '',
            $prize['name'] ?? '',
            $prize['type'] ?? ''
        );

        return hash_hmac('sha256', $signString, $this->secretKey);
    }

    /**
     * 验证抽奖结果签名
     * @param string $drawId 抽奖ID
     * @param string $openid 用户标识
     * @param array $prize 奖品信息
     * @param string $signature 签名
     * @return bool
     */
    public function verifySignature(string $drawId, string $openid, array $prize, string $signature): bool
    {
        if ($this->secretKey === null || empty($signature)) {
            // 未配置 secretKey 或签名为空，跳过验证
            return true;
        }

        $expectedSignature = $this->signResult($drawId, $openid, $prize);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * 检查抽奖记录是否已存在（防止重复提交）
     * @param string $drawId 抽奖ID
     * @return bool true=已存在，false=不存在
     */
    public function checkDrawExists(string $drawId): bool
    {
        if (empty($drawId) || $drawId === '0') {
            return false;
        }

        try {
            $draw = LotteryDraw::findByDrawsId($drawId);
            return $draw !== null;
        } catch (Exception $e) {
            Log::warning('[Lottery] Failed to check draw exists', [
                'error' => $e->getMessage(),
                'draw_id' => $drawId
            ]);
            return false;
        }
    }

    /**
     * 记录抽奖请求（用于频率限制检查）
     * @param string $openid 用户标识
     * @param string $ip IP地址
     * @return void
     */
    public function recordDrawRequest(string $openid, string $ip): void
    {
        try {
            $today = date('ymd');
            $hour = date('H');
            
            $prefixKey = rtrim($this->keyBuilder->getPrefixKey(), ':');
            
            // 记录用户今日抽奖次数
            $userKey = $prefixKey . ':requests:user:' . $openid . ':' . $today;
            $this->redis->incr($userKey);
            $this->redis->eval("redis.call('EXPIRE', KEYS[1], 86400)", 1, $userKey);
            
            // 记录IP今日抽奖次数
            $ipKey = $prefixKey . ':requests:ip:' . $ip . ':' . $today;
            $this->redis->incr($ipKey);
            $this->redis->eval("redis.call('EXPIRE', KEYS[1], 86400)", 1, $ipKey);
            
            // 记录用户当前小时抽奖次数（用于频率限制）
            $userHourKey = $prefixKey . ':requests:user:hour:' . $openid . ':' . $today . ':' . $hour;
            $this->redis->incr($userHourKey);
            $this->redis->eval("redis.call('EXPIRE', KEYS[1], 3600)", 1, $userHourKey);
        } catch (Exception $e) {
            Log::warning('[Lottery] Failed to record draw request', [
                'error' => $e->getMessage(),
                'openid' => $openid,
                'ip' => $ip
            ]);
        }
    }

    /**
     * 获取用户今日抽奖请求次数
     * @param string $openid 用户标识
     * @return int
     */
    public function getUserRequestCount(string $openid): int
    {
        try {
            $today = date('ymd');
            $prefixKey = rtrim($this->keyBuilder->getPrefixKey(), ':');
            $userKey = $prefixKey . ':requests:user:' . $openid . ':' . $today;
            $count = $this->redis->get($userKey);
            return $count !== null ? (int)$count : 0;
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * 获取IP今日抽奖请求次数
     * @param string $ip IP地址
     * @return int
     */
    public function getIpRequestCount(string $ip): int
    {
        try {
            $today = date('ymd');
            $prefixKey = rtrim($this->keyBuilder->getPrefixKey(), ':');
            $ipKey = $prefixKey . ':requests:ip:' . $ip . ':' . $today;
            $count = $this->redis->get($ipKey);
            return $count !== null ? (int)$count : 0;
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * 获取用户当前小时抽奖请求次数
     * @param string $openid 用户标识
     * @return int
     */
    public function getUserHourRequestCount(string $openid): int
    {
        try {
            $today = date('ymd');
            $hour = date('H');
            $prefixKey = rtrim($this->keyBuilder->getPrefixKey(), ':');
            $userHourKey = $prefixKey . ':requests:user:hour:' . $openid . ':' . $today . ':' . $hour;
            $count = $this->redis->get($userHourKey);
            return $count !== null ? (int)$count : 0;
        } catch (Exception $e) {
            return 0;
        }
    }
}
