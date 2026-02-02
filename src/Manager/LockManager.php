<?php
declare(strict_types=1);

namespace Leo\Lottery\Manager;

use Leo\Lottery\Contracts\LockManagerInterface;
use Leo\Lottery\Contracts\RedisInterface;
use Exception;
use think\facade\Log;

/**
 * Redis 分布式锁管理器
 */
class LockManager implements LockManagerInterface
{
    private RedisInterface $redis;
    private string $prefixKey;

    public function __construct(RedisInterface $redis, string $prefixKey = 'lottery:')
    {
        $this->redis = $redis;
        $this->prefixKey = $prefixKey;
    }

    /**
     * 获取分布式锁
     * @param string $key 锁的键名
     * @param string $value 锁的值
     * @param int $timeout 超时时间（秒）
     * @return bool
     */
    public function acquire(string $key, string $value, int $timeout): bool
    {
        try {
            $fullKey = $this->prefixKey . $key;
            $luaScript = "
                if redis.call('set', KEYS[1], ARGV[1], 'NX', 'EX', ARGV[2]) then
                    return 1
                else
                    return 0
                end
            ";
            $result = $this->redis->eval($luaScript, 1, $fullKey, $value, (string)$timeout);
            return $result === 1;
        } catch (Exception $e) {
            Log::warning('[Lottery] Redis lock operation failed', [
                'error' => $e->getMessage(),
                'key' => $key
            ]);
            // Redis 失败时返回 false，由调用方决定如何处理
            return false;
        }
    }

    /**
     * 释放分布式锁
     * @param string $key 锁的键名
     * @param string $value 锁的值
     * @return bool
     */
    public function release(string $key, string $value): bool
    {
        try {
            $fullKey = $this->prefixKey . $key;
            $luaScript = "
                if redis.call('get', KEYS[1]) == ARGV[1] then
                    return redis.call('del', KEYS[1])
                else
                    return 0
                end
            ";
            $result = $this->redis->eval($luaScript, 1, $fullKey, $value);
            return $result === 1;
        } catch (Exception $e) {
            Log::warning('[Lottery] Failed to release lock', [
                'error' => $e->getMessage(),
                'key' => $key
            ]);
            return false;
        }
    }
}
