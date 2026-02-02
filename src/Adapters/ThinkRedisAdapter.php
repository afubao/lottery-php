<?php
declare(strict_types=1);

namespace Leo\Lottery\Adapters;

use Leo\Lottery\Contracts\RedisInterface;
use Leo\Lottery\Contracts\PipelineInterface;
use think\facade\Redis;

/**
 * ThinkPHP Redis 适配器
 * 
 * 将 ThinkPHP 的 Redis Facade 适配为 RedisInterface
 * 
 * 使用示例：
 * ```php
 * // 在 LotteryServiceProvider 中注册
 * $this->app->bind(RedisInterface::class, ThinkRedisAdapter::class);
 * ```
 */
class ThinkRedisAdapter implements RedisInterface
{
    /**
     * 获取值
     * @param string $key
     * @return mixed
     */
    public function get(string $key)
    {
        return Redis::get($key);
    }

    /**
     * 设置值
     * @param string $key
     * @param mixed $value
     * @param int|null $ttl 过期时间（秒）
     * @return mixed
     */
    public function set(string $key, $value, ?int $ttl = null)
    {
        if ($ttl !== null) {
            return Redis::setex($key, $ttl, $value);
        }
        return Redis::set($key, $value);
    }

    /**
     * 自增
     * @param string $key
     * @return int
     */
    public function incr(string $key): int
    {
        return (int) Redis::incr($key);
    }

    /**
     * Hash 字段自增
     * @param string $key
     * @param string $field
     * @param int $value
     * @return int
     */
    public function hincrby(string $key, string $field, int $value): int
    {
        return (int) Redis::hincrby($key, $field, $value);
    }

    /**
     * 获取 Hash 所有字段
     * @param string $key
     * @return array
     */
    public function hgetall(string $key): array
    {
        $result = Redis::hgetall($key);
        return is_array($result) ? $result : [];
    }

    /**
     * 设置 Hash 多个字段
     * @param string $key
     * @param array $data
     * @return mixed
     */
    public function hmset(string $key, array $data)
    {
        return Redis::hmset($key, ...$this->flattenHashData($data));
    }

    /**
     * 判断集合成员是否存在
     * @param string $key
     * @param string $member
     * @return bool
     */
    public function sismember(string $key, string $member): bool
    {
        return (bool) Redis::sismember($key, $member);
    }

    /**
     * 添加集合成员
     * @param string $key
     * @param array $members
     * @return int
     */
    public function sadd(string $key, array $members): int
    {
        return (int) Redis::sadd($key, ...$members);
    }

    /**
     * 执行 Lua 脚本
     * @param string $script
     * @param int $numKeys
     * @param mixed ...$args
     * @return mixed
     */
    public function eval(string $script, int $numKeys, ...$args)
    {
        return Redis::eval($script, $args, $numKeys);
    }

    /**
     * 创建管道
     * @return PipelineInterface
     */
    public function pipeline(): PipelineInterface
    {
        return new ThinkRedisPipelineAdapter(Redis::pipeline());
    }

    /**
     * 将 Hash 数据扁平化为键值对数组
     * @param array $data
     * @return array
     */
    private function flattenHashData(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $result[] = $key;
            $result[] = $value;
        }
        return $result;
    }
}

/**
 * ThinkPHP Redis Pipeline 适配器
 */
class ThinkRedisPipelineAdapter implements PipelineInterface
{
    private $pipeline;

    public function __construct($pipeline)
    {
        $this->pipeline = $pipeline;
    }

    /**
     * 添加命令到管道
     * @param string $method
     * @param array $args
     * @return $this
     */
    public function __call(string $method, array $args)
    {
        $this->pipeline->$method(...$args);
        return $this;
    }

    /**
     * 执行管道
     * @return array
     */
    public function execute(): array
    {
        return $this->pipeline->exec();
    }
}
