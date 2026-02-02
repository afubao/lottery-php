<?php
declare(strict_types=1);

namespace Tests\Unit\Models;

use PHPUnit\Framework\TestCase;
use Leo\Lottery\Models\LotteryDraw;

class LotteryDrawTest extends TestCase
{
    public function testIsWinWithPrizeIdGreaterThanZero(): void
    {
        $draw = new LotteryDraw();
        $draw->prize_id = 100;
        
        $this->assertTrue($draw->isWin());
    }
    
    public function testIsWinWithPrizeIdZero(): void
    {
        $draw = new LotteryDraw();
        $draw->prize_id = 0;
        
        $this->assertFalse($draw->isWin());
    }
    
    public function testFindByDrawsId(): void
    {
        // 需要数据库支持
        $this->markTestSkipped('需要数据库支持');
    }
    
    public function testVerifyDraw(): void
    {
        // 需要数据库支持
        $this->markTestSkipped('需要数据库支持');
    }
    
    public function testCreateDraw(): void
    {
        // 需要数据库支持
        $this->markTestSkipped('需要数据库支持');
    }
}
