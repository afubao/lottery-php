<?php
declare(strict_types=1);

namespace Tests\Unit\Common;

use PHPUnit\Framework\TestCase;
use Leo\Lottery\Common\CacheKeyBuilder;

class CacheKeyBuilderTest extends TestCase
{
    private CacheKeyBuilder $builder;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new CacheKeyBuilder('lottery:');
    }
    
    public function testRulesList(): void
    {
        $key = $this->builder->rulesList();
        $this->assertStringStartsWith('lottery:rules:', $key);
        
        $date = date('ymd');
        $expectedKey = 'lottery:rules:' . $date;
        $this->assertEquals($expectedKey, $key);
    }
    
    public function testRulesListWithDate(): void
    {
        $date = '250201';
        $key = $this->builder->rulesList($date);
        $this->assertEquals('lottery:rules:' . $date, $key);
    }
    
    public function testRuleDetail(): void
    {
        $ruleId = 123;
        $key = $this->builder->ruleDetail($ruleId);
        $this->assertEquals('lottery:rule:123', $key);
    }
    
    public function testPrizesList(): void
    {
        $key = $this->builder->prizesList();
        $this->assertEquals('lottery:prizes', $key);
    }
    
    public function testPrizeDetail(): void
    {
        $prizeId = 456;
        $key = $this->builder->prizeDetail($prizeId);
        $this->assertEquals('lottery:prize:456', $key);
    }
    
    public function testUserDraws(): void
    {
        $openid = 'test_openid';
        $key = $this->builder->userDraws($openid);
        $this->assertEquals('lottery:user:test_openid:draws', $key);
    }
    
    public function testLock(): void
    {
        $openid = 'test_openid';
        $key = $this->builder->lock($openid);
        $this->assertEquals('lottery:lock:test_openid', $key);
    }
    
    public function testSetPrefixKey(): void
    {
        $this->builder->setPrefixKey('custom:');
        $key = $this->builder->rulesList();
        $this->assertStringStartsWith('custom:rules:', $key);
    }
    
    public function testGetPrefixKey(): void
    {
        $prefix = $this->builder->getPrefixKey();
        $this->assertEquals('lottery:', $prefix);
    }
}
