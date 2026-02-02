<?php
declare(strict_types=1);

namespace Leo\Lottery\Contracts;

/**
 * Redis 接口
 */
interface RedisInterface
{
    /**
     * 获取值
     * @param string $key
     * @return mixed
     */
    public function get(string $key);

    /**
     * 设置值
     * @param string $key
     * @param mixed $value
     * @param int|null $ttl 过期时间（秒）
     * @return mixed
     */
    public function set(string $key, $value, ?int $ttl = null);

    /**
     * 自增
     * @param string $key
     * @return int
     */
    public function incr(string $key): int;

    /**
     * Hash 字段自增
     * @param string $key
     * @param string $field
     * @param int $value
     * @return int
     */
    public function hincrby(string $key, string $field, int $value): int;

    /**
     * 获取 Hash 所有字段
     * @param string $key
     * @return array
     */
    public function hgetall(string $key): array;

    /**
     * 设置 Hash 多个字段
     * @param string $key
     * @param array $data
     * @return mixed
     */
    public function hmset(string $key, array $data);

    /**
     * 判断集合成员是否存在
     * @param string $key
     * @param string $member
     * @return bool
     */
    public function sismember(string $key, string $member): bool;

    /**
     * 添加集合成员
     * @param string $key
     * @param array $members
     * @return int
     */
    public function sadd(string $key, array $members): int;

    /**
     * 执行 Lua 脚本
     * @param string $script
     * @param int $numKeys
     * @param mixed ...$args
     * @return mixed
     */
    public function eval(string $script, int $numKeys, ...$args);

    /**
     * 创建管道
     * @return PipelineInterface
     */
    public function pipeline(): PipelineInterface;
}
