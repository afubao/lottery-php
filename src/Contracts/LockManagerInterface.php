<?php
declare(strict_types=1);

namespace Leo\Lottery\Contracts;

/**
 * 分布式锁管理器接口
 */
interface LockManagerInterface
{
    /**
     * 获取分布式锁
     * @param string $key 锁的键名
     * @param string $value 锁的值（用于安全释放）
     * @param int $timeout 超时时间（秒）
     * @return bool 是否成功获取锁
     */
    public function acquire(string $key, string $value, int $timeout): bool;

    /**
     * 释放分布式锁
     * @param string $key 锁的键名
     * @param string $value 锁的值（必须匹配才能释放）
     * @return bool 是否成功释放
     */
    public function release(string $key, string $value): bool;
}
