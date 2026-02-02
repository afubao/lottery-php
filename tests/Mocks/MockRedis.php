<?php
declare(strict_types=1);

namespace Tests\Mocks;

use Leo\Lottery\Contracts\RedisInterface;
use Leo\Lottery\Contracts\PipelineInterface;

/**
 * Redis 接口 Mock 类
 */
class MockRedis implements RedisInterface
{
    private array $data = [];
    private array $hashes = [];
    private array $expires = [];
    
    public function get(string $key)
    {
        if (isset($this->expires[$key]) && $this->expires[$key] < time()) {
            unset($this->data[$key], $this->expires[$key]);
            return null;
        }
        return $this->data[$key] ?? null;
    }
    
    public function set(string $key, $value, ?int $ttl = null): bool
    {
        $this->data[$key] = $value;
        if ($ttl !== null) {
            $this->expires[$key] = time() + $ttl;
        }
        return true;
    }
    
    public function del(string $key): bool
    {
        unset($this->data[$key], $this->expires[$key], $this->hashes[$key]);
        return true;
    }
    
    public function exists(string $key): bool
    {
        if (isset($this->expires[$key]) && $this->expires[$key] < time()) {
            unset($this->data[$key], $this->expires[$key]);
            return false;
        }
        return isset($this->data[$key]);
    }
    
    public function expire(string $key, int $ttl): bool
    {
        if (isset($this->data[$key])) {
            $this->expires[$key] = time() + $ttl;
            return true;
        }
        return false;
    }
    
    public function incr(string $key): int
    {
        $value = (int)($this->data[$key] ?? 0);
        $value++;
        $this->data[$key] = $value;
        return $value;
    }
    
    public function decr(string $key): int
    {
        $value = (int)($this->data[$key] ?? 0);
        $value--;
        $this->data[$key] = $value;
        return $value;
    }
    
    public function hget(string $key, string $field)
    {
        return $this->hashes[$key][$field] ?? null;
    }
    
    public function hset(string $key, string $field, $value): bool
    {
        if (!isset($this->hashes[$key])) {
            $this->hashes[$key] = [];
        }
        $this->hashes[$key][$field] = $value;
        return true;
    }
    
    public function hgetall(string $key): array
    {
        return $this->hashes[$key] ?? [];
    }
    
    public function hmset(string $key, array $values): bool
    {
        $this->hashes[$key] = $values;
        return true;
    }
    
    public function hincrby(string $key, string $field, int $increment): int
    {
        if (!isset($this->hashes[$key])) {
            $this->hashes[$key] = [];
        }
        $value = (int)($this->hashes[$key][$field] ?? 0);
        $value += $increment;
        $this->hashes[$key][$field] = $value;
        return $value;
    }
    
    public function eval(string $script, int $numKeys, ...$args)
    {
        // 简单的 Lua 脚本模拟（仅用于测试）
        // 实际测试中可能需要更复杂的实现
        return [1, 0];
    }
    
    public function pipeline(): PipelineInterface
    {
        return new MockPipeline($this);
    }
    
    public function clear(): void
    {
        $this->data = [];
        $this->hashes = [];
        $this->expires = [];
    }
}

/**
 * Pipeline Mock 类
 */
class MockPipeline implements PipelineInterface
{
    private MockRedis $redis;
    private array $commands = [];
    
    public function __construct(MockRedis $redis)
    {
        $this->redis = $redis;
    }
    
    public function get(string $key): PipelineInterface
    {
        $this->commands[] = ['method' => 'get', 'args' => [$key]];
        return $this;
    }
    
    public function hgetall(string $key): PipelineInterface
    {
        $this->commands[] = ['method' => 'hgetall', 'args' => [$key]];
        return $this;
    }
    
    public function execute(): array
    {
        $results = [];
        foreach ($this->commands as $command) {
            $method = $command['method'];
            $args = $command['args'];
            $results[] = $this->redis->$method(...$args);
        }
        $this->commands = [];
        return $results;
    }
}
