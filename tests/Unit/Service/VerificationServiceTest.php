<?php
declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Leo\Lottery\Service\VerificationService;
use Leo\Lottery\Security\AntiCheatManager;
use Leo\Lottery\Models\LotteryDraw;
use Leo\Lottery\Models\LotteryPrize;
use Leo\Lottery\Exceptions\LotteryException;

class VerificationServiceTest extends TestCase
{
    private VerificationService $service;
    private MockObject $antiCheatManager;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->antiCheatManager = $this->createMock(AntiCheatManager::class);
        $this->service = new VerificationService($this->antiCheatManager);
    }
    
    public function testVerifyDrawWithNonExistentDraw(): void
    {
        // Mock LotteryDraw::findByDrawsId 返回 null
        $this->expectException(LotteryException::class);
        $this->expectExceptionCode(LotteryException::RULE_NOT_FOUND);
        
        // 由于 LotteryDraw::findByDrawsId 是静态方法，我们需要使用其他方式测试
        // 这里先测试异常情况
        try {
            $this->service->verifyDraw('non_existent_id', 'test_openid');
        } catch (LotteryException $e) {
            $this->assertEquals(LotteryException::RULE_NOT_FOUND, $e->getCode());
            throw $e;
        }
    }
    
    public function testVerifyDrawWithMismatchedOpenid(): void
    {
        // 这个测试需要实际的数据库或更复杂的 Mock
        // 暂时跳过，在实际集成测试中验证
        $this->markTestSkipped('需要数据库支持或更复杂的 Mock');
    }
    
    public function testVerifyDraws(): void
    {
        $drawIds = ['id1', 'id2', 'id3'];
        $openid = 'test_openid';
        
        $results = $this->service->verifyDraws($drawIds, $openid);
        
        $this->assertIsArray($results);
        $this->assertCount(3, $results);
        $this->assertArrayHasKey('draw_id', $results[0]);
        $this->assertArrayHasKey('success', $results[0]);
    }
    
    public function testVerifyUserWins(): void
    {
        $openid = 'test_openid';
        $startTime = '2025-01-01 00:00:00';
        $endTime = '2025-01-31 23:59:59';
        
        $results = $this->service->verifyUserWins($openid, $startTime, $endTime);
        
        $this->assertIsArray($results);
    }
}
