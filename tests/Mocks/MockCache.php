<?php
declare(strict_types=1);

namespace Tests\Mocks;

use Leo\Lottery\Contracts\CacheInterface;

/**
 * Cache 接口 Mock 类
 */
class MockCache implements CacheInterface
{
    private array $data = [];
    private array $expires = [];
    
    public function get(string $key, $default = null)
    {
        if (isset($this->expires[$key]) && $this->expires[$key] < time()) {
            unset($this->data[$key], $this->expires[$key]);
            return $default;
        }
        return $this->data[$key] ?? $default;
    }
    
    public function set(string $key, $value, ?int $ttl = null): bool
    {
        $this->data[$key] = $value;
        if ($ttl !== null) {
            $this->expires[$key] = time() + $ttl;
        }
        return true;
    }
    
    public function delete(string $key): bool
    {
        unset($this->data[$key], $this->expires[$key]);
        return true;
    }
    
    public function has(string $key): bool
    {
        if (isset($this->expires[$key]) && $this->expires[$key] < time()) {
            unset($this->data[$key], $this->expires[$key]);
            return false;
        }
        return isset($this->data[$key]);
    }
    
    public function push(string $key, $value): bool
    {
        if (!isset($this->data[$key])) {
            $this->data[$key] = [];
        }
        if (!is_array($this->data[$key])) {
            $this->data[$key] = [];
        }
        $this->data[$key][] = $value;
        return true;
    }
    
    public function clear(): void
    {
        $this->data = [];
        $this->expires = [];
    }
}
