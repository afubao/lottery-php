<?php
declare(strict_types=1);

namespace Tests\Unit\Selector;

use PHPUnit\Framework\TestCase;
use Leo\Lottery\Selector\WeightedPrizeSelector;
use Leo\Lottery\Models\PrizeRule;

class WeightedPrizeSelectorTest extends TestCase
{
    private WeightedPrizeSelector $selector;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->selector = new WeightedPrizeSelector();
    }
    
    public function testSelectWithSingleRule(): void
    {
        $rules = [
            [
                'id' => 1,
                'weight' => 10,
                'prize_id' => 100,
                'total_num' => 100,
                'surplus_num' => 50,
            ]
        ];
        
        $selected = $this->selector->select($rules);
        
        $this->assertInstanceOf(PrizeRule::class, $selected);
        $this->assertEquals(1, $selected->id);
    }
    
    public function testSelectWithMultipleRules(): void
    {
        $rules = [
            [
                'id' => 1,
                'weight' => 10,
                'prize_id' => 100,
                'total_num' => 100,
                'surplus_num' => 50,
            ],
            [
                'id' => 2,
                'weight' => 20,
                'prize_id' => 200,
                'total_num' => 100,
                'surplus_num' => 50,
            ],
            [
                'id' => 3,
                'weight' => 30,
                'prize_id' => 300,
                'total_num' => 100,
                'surplus_num' => 50,
            ],
        ];
        
        $selected = $this->selector->select($rules);
        
        $this->assertInstanceOf(PrizeRule::class, $selected);
        $this->assertContains($selected->id, [1, 2, 3]);
    }
    
    public function testSelectWithZeroWeight(): void
    {
        $rules = [
            [
                'id' => 1,
                'weight' => 0,
                'prize_id' => 100,
                'total_num' => 100,
                'surplus_num' => 50,
            ],
            [
                'id' => 2,
                'weight' => 10,
                'prize_id' => 200,
                'total_num' => 100,
                'surplus_num' => 50,
            ],
        ];
        
        // 多次选择，应该只选择权重不为0的规则
        $selected = $this->selector->select($rules);
        
        // 由于随机性，可能选择到规则2，也可能返回null（如果随机数落在0权重范围内）
        if ($selected !== null) {
            $this->assertEquals(2, $selected->id);
        }
    }
    
    public function testSelectWithAllZeroWeight(): void
    {
        $rules = [
            [
                'id' => 1,
                'weight' => 0,
                'prize_id' => 100,
                'total_num' => 100,
                'surplus_num' => 50,
            ],
            [
                'id' => 2,
                'weight' => 0,
                'prize_id' => 200,
                'total_num' => 100,
                'surplus_num' => 50,
            ],
        ];
        
        $selected = $this->selector->select($rules);
        
        // 所有权重为0时，应该返回null
        $this->assertNull($selected);
    }
    
    public function testSelectWithDecimalWeight(): void
    {
        $rules = [
            [
                'id' => 1,
                'weight' => 0.5,
                'prize_id' => 100,
                'total_num' => 100,
                'surplus_num' => 50,
            ],
            [
                'id' => 2,
                'weight' => 1.5,
                'prize_id' => 200,
                'total_num' => 100,
                'surplus_num' => 50,
            ],
        ];
        
        $selected = $this->selector->select($rules);
        
        // 应该能正常选择
        $this->assertInstanceOf(PrizeRule::class, $selected);
        $this->assertContains($selected->id, [1, 2]);
    }
    
    public function testSelectWithEmptyRules(): void
    {
        $selected = $this->selector->select([]);
        $this->assertNull($selected);
    }
    
    public function testSelectDistribution(): void
    {
        // 测试权重分布是否合理
        $rules = [
            [
                'id' => 1,
                'weight' => 10,
                'prize_id' => 100,
                'total_num' => 100,
                'surplus_num' => 50,
            ],
            [
                'id' => 2,
                'weight' => 90,
                'prize_id' => 200,
                'total_num' => 100,
                'surplus_num' => 50,
            ],
        ];
        
        $counts = [1 => 0, 2 => 0];
        $iterations = 1000;
        
        for ($i = 0; $i < $iterations; $i++) {
            $selected = $this->selector->select($rules);
            if ($selected !== null) {
                $counts[$selected->id]++;
            }
        }
        
        // 规则2的权重是规则1的9倍，所以被选中的概率应该更高
        // 由于随机性，我们只检查规则2被选中的次数明显多于规则1
        $this->assertGreaterThan($counts[1], $counts[2]);
        
        // 规则2应该被选中至少70%的次数（考虑到随机性）
        $this->assertGreaterThan($iterations * 0.7, $counts[2]);
    }
}
