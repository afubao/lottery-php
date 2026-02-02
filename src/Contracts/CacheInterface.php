<?php
declare(strict_types=1);

namespace Leo\Lottery\Contracts;

/**
 * 缓存接口
 */
interface CacheInterface
{
    /**
     * 获取缓存
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null);

    /**
     * 设置缓存
     * @param string $key
     * @param mixed $value
     * @param int|null $ttl 过期时间（秒）
     * @return bool
     */
    public function set(string $key, $value, ?int $ttl = null): bool;

    /**
     * 删除缓存
     * @param string $key
     * @return bool
     */
    public function delete(string $key): bool;

    /**
     * 判断缓存是否存在
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * 推送值到列表
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    public function push(string $key, $value);
}
