<?php
declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Leo\Lottery\Service\HealthCheckService;
use Leo\Lottery\Contracts\RedisInterface;
use Leo\Lottery\Contracts\CacheInterface;
use Tests\Mocks\MockRedis;
use Tests\Mocks\MockCache;

class HealthCheckServiceTest extends TestCase
{
    private HealthCheckService $service;
    private MockObject $redis;
    private MockObject $cache;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->redis = $this->createMock(RedisInterface::class);
        $this->cache = $this->createMock(CacheInterface::class);
        
        $this->service = new HealthCheckService($this->redis, $this->cache);
    }

    public function testCheckRedisSuccess(): void
    {
        $this->redis->expects($this->once())
            ->method('set')
            ->willReturn(true);
        
        $this->redis->expects($this->once())
            ->method('get')
            ->willReturn('test');

        $result = $this->service->checkRedis();
        
        $this->assertTrue($result['ok']);
        $this->assertEquals('Redis 连接正常', $result['message']);
    }

    public function testCheckRedisFailure(): void
    {
        $this->redis->expects($this->once())
            ->method('set')
            ->willThrowException(new \Exception('Connection failed'));

        $result = $this->service->checkRedis();
        
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Redis 连接失败', $result['message']);
    }

    public function testCheckCacheSuccess(): void
    {
        $this->cache->expects($this->once())
            ->method('set')
            ->willReturn(true);
        
        $this->cache->expects($this->once())
            ->method('get')
            ->willReturn('test');
        
        $this->cache->expects($this->once())
            ->method('delete')
            ->willReturn(true);

        $result = $this->service->checkCache();
        
        $this->assertTrue($result['ok']);
        $this->assertEquals('Cache 连接正常', $result['message']);
    }

    public function testCheckCacheFailure(): void
    {
        $this->cache->expects($this->once())
            ->method('set')
            ->willThrowException(new \Exception('Cache failed'));

        $result = $this->service->checkCache();
        
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Cache 连接失败', $result['message']);
    }

    public function testCheckDatabase(): void
    {
        // 这个测试需要实际的数据库连接
        // 如果没有数据库，会失败，这是预期的
        $result = $this->service->checkDatabase();
        
        $this->assertArrayHasKey('ok', $result);
        $this->assertArrayHasKey('message', $result);
    }

    public function testCheckConfig(): void
    {
        $result = $this->service->checkConfig();
        
        $this->assertArrayHasKey('ok', $result);
        $this->assertArrayHasKey('message', $result);
    }

    public function testCheck(): void
    {
        $this->redis->expects($this->once())
            ->method('set')
            ->willReturn(true);
        
        $this->redis->expects($this->once())
            ->method('get')
            ->willReturn('test');
        
        $this->cache->expects($this->once())
            ->method('set')
            ->willReturn(true);
        
        $this->cache->expects($this->once())
            ->method('get')
            ->willReturn('test');
        
        $this->cache->expects($this->once())
            ->method('delete')
            ->willReturn(true);

        $result = $this->service->check();
        
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('checks', $result);
        $this->assertArrayHasKey('timestamp', $result);
        $this->assertArrayHasKey('redis', $result['checks']);
        $this->assertArrayHasKey('cache', $result['checks']);
        $this->assertArrayHasKey('database', $result['checks']);
        $this->assertArrayHasKey('config', $result['checks']);
    }

    public function testQuickCheck(): void
    {
        $this->redis->expects($this->once())
            ->method('set')
            ->willReturn(true);
        
        $this->redis->expects($this->once())
            ->method('get')
            ->willReturn('test');
        
        $this->cache->expects($this->once())
            ->method('set')
            ->willReturn(true);
        
        $this->cache->expects($this->once())
            ->method('get')
            ->willReturn('test');
        
        $this->cache->expects($this->once())
            ->method('delete')
            ->willReturn(true);

        $result = $this->service->quickCheck();
        
        $this->assertIsBool($result);
    }

    public function testCheckWithNullRedis(): void
    {
        $service = new HealthCheckService(null, $this->cache);
        
        $result = $service->checkRedis();
        
        $this->assertFalse($result['ok']);
        $this->assertEquals('Redis 未配置', $result['message']);
    }
}
