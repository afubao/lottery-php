<?php
declare(strict_types=1);

namespace Leo\Lottery\Adapters;

use Leo\Lottery\Contracts\CacheInterface;
use think\facade\Cache;

/**
 * ThinkPHP Cache 适配器
 * 
 * 将 ThinkPHP 的 Cache Facade 适配为 CacheInterface
 * 
 * 使用示例：
 * ```php
 * // 在 LotteryServiceProvider 中注册
 * $this->app->bind(CacheInterface::class, ThinkCacheAdapter::class);
 * ```
 */
class ThinkCacheAdapter implements CacheInterface
{
    /**
     * 获取缓存
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        $value = Cache::get($key);
        return $value === false ? $default : $value;
    }

    /**
     * 设置缓存
     * @param string $key
     * @param mixed $value
     * @param int|null $ttl 过期时间（秒）
     * @return bool
     */
    public function set(string $key, $value, ?int $ttl = null): bool
    {
        if ($ttl !== null) {
            return Cache::set($key, $value, $ttl);
        }
        return Cache::set($key, $value);
    }

    /**
     * 删除缓存
     * @param string $key
     * @return bool
     */
    public function delete(string $key): bool
    {
        return Cache::delete($key);
    }

    /**
     * 判断缓存是否存在
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return Cache::has($key);
    }

    /**
     * 推送值到列表
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    public function push(string $key, $value)
    {
        // ThinkPHP Cache 不直接支持列表操作，使用 Redis 实现
        // 这里使用 Cache 的底层 Redis 连接
        try {
            $redis = Cache::store()->handler();
            if ($redis instanceof \Redis) {
                return $redis->lpush($key, is_string($value) ? $value : json_encode($value));
            }
        } catch (\Exception $e) {
            // 如果无法获取 Redis 实例，返回 false
        }
        return false;
    }
}
