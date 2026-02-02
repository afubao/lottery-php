<?php
declare(strict_types=1);

namespace Leo\Lottery\Service;

use Leo\Lottery\Contracts\RedisInterface;
use Leo\Lottery\Contracts\CacheInterface;
use think\facade\Db;

/**
 * 健康检查服务
 * 
 * 用于检查抽奖组件的各项依赖是否正常工作
 */
class HealthCheckService
{
    private ?RedisInterface $redis;
    private CacheInterface $cache;

    public function __construct(?RedisInterface $redis, CacheInterface $cache)
    {
        $this->redis = $redis;
        $this->cache = $cache;
    }

    /**
     * 执行完整的健康检查
     * @return array 返回检查结果 ['status' => 'ok'|'error', 'checks' => array]
     */
    public function check(): array
    {
        $checks = [
            'redis' => $this->checkRedis(),
            'cache' => $this->checkCache(),
            'database' => $this->checkDatabase(),
            'config' => $this->checkConfig(),
        ];

        $allOk = true;
        foreach ($checks as $check) {
            if (!$check['ok']) {
                $allOk = false;
                break;
            }
        }

        return [
            'status' => $allOk ? 'ok' : 'error',
            'checks' => $checks,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * 检查 Redis 连接
     * @return array
     */
    public function checkRedis(): array
    {
        if ($this->redis === null) {
            return [
                'ok' => false,
                'message' => 'Redis 未配置',
            ];
        }

        try {
            $testKey = 'lottery:health_check:' . time();
            $this->redis->set($testKey, 'test', 10);
            $value = $this->redis->get($testKey);
            $this->redis->set($testKey, '', 1); // 删除测试键

            if ($value === 'test') {
                return [
                    'ok' => true,
                    'message' => 'Redis 连接正常',
                ];
            } else {
                return [
                    'ok' => false,
                    'message' => 'Redis 读写异常',
                ];
            }
        } catch (\Exception $e) {
            return [
                'ok' => false,
                'message' => 'Redis 连接失败: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * 检查 Cache 连接
     * @return array
     */
    public function checkCache(): array
    {
        try {
            $testKey = 'lottery:health_check:cache:' . time();
            $this->cache->set($testKey, 'test', 10);
            $value = $this->cache->get($testKey);
            $this->cache->delete($testKey);

            if ($value === 'test') {
                return [
                    'ok' => true,
                    'message' => 'Cache 连接正常',
                ];
            } else {
                return [
                    'ok' => false,
                    'message' => 'Cache 读写异常',
                ];
            }
        } catch (\Exception $e) {
            return [
                'ok' => false,
                'message' => 'Cache 连接失败: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * 检查数据库连接
     * @return array
     */
    public function checkDatabase(): array
    {
        try {
            Db::query('SELECT 1');
            return [
                'ok' => true,
                'message' => '数据库连接正常',
            ];
        } catch (\Exception $e) {
            return [
                'ok' => false,
                'message' => '数据库连接失败: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * 检查配置完整性
     * @return array
     */
    public function checkConfig(): array
    {
        $config = config('lottery', []);
        $errors = [];

        // 检查必要配置项
        if (!isset($config['prefix_key']) || empty($config['prefix_key'])) {
            $errors[] = 'prefix_key 未配置';
        }

        if (!isset($config['fallback_prizes']) && !isset($config['virtual_prizes'])) {
            $errors[] = 'fallback_prizes 或 virtual_prizes 未配置';
        }

        if (empty($errors)) {
            return [
                'ok' => true,
                'message' => '配置检查通过',
            ];
        } else {
            return [
                'ok' => false,
                'message' => '配置不完整: ' . implode(', ', $errors),
            ];
        }
    }

    /**
     * 快速检查（只检查关键依赖）
     * @return bool
     */
    public function quickCheck(): bool
    {
        $redisCheck = $this->checkRedis();
        $cacheCheck = $this->checkCache();
        $dbCheck = $this->checkDatabase();

        return $redisCheck['ok'] && $cacheCheck['ok'] && $dbCheck['ok'];
    }
}
