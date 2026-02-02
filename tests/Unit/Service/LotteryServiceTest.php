<?php
declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Leo\Lottery\Service\LotteryService;
use Leo\Lottery\Service\LoggerService;
use Leo\Lottery\Contracts\LockManagerInterface;
use Leo\Lottery\Contracts\PrizeSelectorInterface;
use Leo\Lottery\Contracts\StockManagerInterface;
use Leo\Lottery\Contracts\DistributionStrategyInterface;
use Leo\Lottery\Contracts\CacheManagerInterface;
use Leo\Lottery\Contracts\FallbackPrizeProviderInterface;
use Leo\Lottery\Builder\DrawResultBuilder;
use Leo\Lottery\Exceptions\LotteryException;
use Leo\Lottery\Models\PrizeRule;
use Leo\Lottery\Models\LotteryPrize;
use think\facade\Event;

class LotteryServiceTest extends TestCase
{
    private LotteryService $service;
    private MockObject $lockManager;
    private MockObject $prizeSelector;
    private MockObject $stockManager;
    private MockObject $distributionStrategy;
    private MockObject $cacheManager;
    private MockObject $fallbackPrizeProvider;
    private MockObject $resultBuilder;
    private MockObject $logger;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock Event facade 以避免实际触发事件监听器
        // 注意：如果 ThinkPHP Event facade 未初始化，这里可能会失败
        // 在实际测试中，Event::trigger 通常不会抛出异常，只是调用监听器
        // 如果测试环境没有配置 Event，可以忽略事件触发
        
        $this->lockManager = $this->createMock(LockManagerInterface::class);
        $this->prizeSelector = $this->createMock(PrizeSelectorInterface::class);
        $this->stockManager = $this->createMock(StockManagerInterface::class);
        $this->distributionStrategy = $this->createMock(DistributionStrategyInterface::class);
        $this->cacheManager = $this->createMock(CacheManagerInterface::class);
        $this->fallbackPrizeProvider = $this->createMock(FallbackPrizeProviderInterface::class);
        $this->resultBuilder = $this->createMock(DrawResultBuilder::class);
        $this->logger = $this->createMock(LoggerService::class);
        
        $this->service = new LotteryService(
            $this->lockManager,
            $this->prizeSelector,
            $this->stockManager,
            $this->distributionStrategy,
            $this->cacheManager,
            $this->fallbackPrizeProvider,
            $this->resultBuilder,
            false, // isTest
            'lottery:',
            null, // antiCheatManager
            $this->logger
        );
    }
    
    public function testDrawWithInvalidOpenid(): void
    {
        $this->expectException(LotteryException::class);
        $this->expectExceptionCode(LotteryException::INVALID_OPENID);
        
        $this->service->draw('', '192.168.1.1');
    }
    
    public function testDrawWithInvalidIp(): void
    {
        $this->expectException(LotteryException::class);
        $this->expectExceptionCode(LotteryException::INVALID_IP);
        
        $this->service->draw('valid_openid', 'invalid_ip_address');
    }
    
    public function testDrawWithLockFailed(): void
    {
        $this->lockManager->expects($this->once())
            ->method('acquire')
            ->willReturn(false);
        
        $this->lockManager->expects($this->once())
            ->method('release');
        
        $this->fallbackPrizeProvider->expects($this->once())
            ->method('getFallbackPrize')
            ->willReturn(['id' => 0, 'name' => '谢谢参与']);
        
        $this->resultBuilder->expects($this->once())
            ->method('buildFallback')
            ->willReturn(['draw_id' => 'test', 'is_win' => false, 'prize' => ['id' => 0]]);
        
        $result = $this->service->draw('test_openid', '192.168.1.1');
        
        $this->assertArrayHasKey('draw_id', $result);
        $this->assertFalse($result['is_win']);
    }
    
    public function testDrawWithNoRules(): void
    {
        $this->lockManager->expects($this->once())
            ->method('acquire')
            ->willReturn(true);
        
        $this->lockManager->expects($this->once())
            ->method('release');
        
        $this->cacheManager->expects($this->once())
            ->method('getRules')
            ->willReturn([]);
        
        $this->fallbackPrizeProvider->expects($this->once())
            ->method('getFallbackPrize')
            ->willReturn(['id' => 0, 'name' => '谢谢参与']);
        
        $this->resultBuilder->expects($this->once())
            ->method('buildFallback')
            ->willReturn(['draw_id' => 'test', 'is_win' => false, 'prize' => ['id' => 0]]);
        
        $result = $this->service->draw('test_openid', '192.168.1.1');
        
        $this->assertArrayHasKey('draw_id', $result);
    }
    
    public function testDrawWithInsufficientStock(): void
    {
        $rule = new PrizeRule();
        $rule->data([
            'id' => 1,
            'prize_id' => 100,
            'weight' => 10,
            'total_num' => 100,
            'surplus_num' => 0, // 库存为0
        ]);
        $rule->exists(true);
        
        $this->lockManager->expects($this->once())
            ->method('acquire')
            ->willReturn(true);
        
        $this->lockManager->expects($this->once())
            ->method('release');
        
        $this->cacheManager->expects($this->once())
            ->method('getRules')
            ->willReturn([$rule->toArray()]);
        
        $this->prizeSelector->expects($this->once())
            ->method('select')
            ->willReturn($rule);
        
        $this->stockManager->expects($this->once())
            ->method('checkStock')
            ->willReturn(false);
        
        $this->fallbackPrizeProvider->expects($this->once())
            ->method('getFallbackPrize')
            ->willReturn(['id' => 0, 'name' => '谢谢参与']);
        
        $this->resultBuilder->expects($this->once())
            ->method('buildFallback')
            ->willReturn(['draw_id' => 'test', 'is_win' => false, 'prize' => ['id' => 0]]);
        
        $result = $this->service->draw('test_openid', '192.168.1.1');
        
        $this->assertArrayHasKey('draw_id', $result);
        $this->assertFalse($result['is_win']);
    }
}
