<?php
declare(strict_types=1);

namespace Tests\Unit\Manager;

use PHPUnit\Framework\TestCase;
use Tests\Mocks\MockRedis;
use Tests\Mocks\MockCache;
use Leo\Lottery\Manager\StockManager;
use Leo\Lottery\Models\PrizeRule;
use Leo\Lottery\Models\LotteryPrize;

class StockManagerTest extends TestCase
{
    private StockManager $stockManager;
    private MockRedis $redis;
    private MockCache $cache;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->redis = new MockRedis();
        $this->cache = new MockCache();
        $this->stockManager = new StockManager($this->redis, $this->cache, 'lottery:');
    }
    
    public function testCheckStockWithRedis(): void
    {
        $ruleId = 1;
        $ruleDetailKey = 'lottery:rule:' . $ruleId;
        
        // 设置 Redis Hash
        $this->redis->hmset($ruleDetailKey, [
            'surplus_num' => 10,
            'id' => $ruleId,
        ]);
        
        $result = $this->stockManager->checkStock($ruleId);
        
        $this->assertTrue($result);
    }
    
    public function testCheckStockWithZeroStock(): void
    {
        $ruleId = 1;
        $ruleDetailKey = 'lottery:rule:' . $ruleId;
        
        // 设置 Redis Hash，库存为0
        $this->redis->hmset($ruleDetailKey, [
            'surplus_num' => 0,
            'id' => $ruleId,
        ]);
        
        $result = $this->stockManager->checkStock($ruleId);
        
        $this->assertFalse($result);
    }
    
    public function testCheckStockWithRedisFailure(): void
    {
        // 创建一个会抛出异常的 Redis Mock
        $failingRedis = $this->createMock(\Leo\Lottery\Contracts\RedisInterface::class);
        $failingRedis->method('hgetall')
            ->willThrowException(new \Exception('Redis connection failed'));
        
        $stockManager = new StockManager($failingRedis, $this->cache, 'lottery:');
        
        // 由于没有数据库，这个测试需要实际的数据库支持
        // 暂时跳过
        $this->markTestSkipped('需要数据库支持');
    }
    
    public function testDecrementStock(): void
    {
        $ruleId = 1;
        $ruleDetailKey = 'lottery:rule:' . $ruleId;
        
        // 设置 Redis Hash
        $this->redis->hmset($ruleDetailKey, [
            'surplus_num' => 10,
            'id' => $ruleId,
        ]);
        
        // 由于 decrementStock 需要数据库操作，这个测试需要实际的数据库
        // 暂时跳过
        $this->markTestSkipped('需要数据库支持');
    }
    
    public function testCheckPrizeStock(): void
    {
        // 需要数据库支持
        $this->markTestSkipped('需要数据库支持');
    }
}
