<?php
declare(strict_types=1);

namespace Leo\Lottery\Selector;

use Leo\Lottery\Contracts\PrizeSelectorInterface;
use Leo\Lottery\Models\PrizeRule;
use Exception;

/**
 * 基于权重的奖品选择器
 */
class WeightedPrizeSelector implements PrizeSelectorInterface
{
    private int $noPrizeWeight;

    /**
     * 构造函数
     * @param int $noPrizeWeight 不中奖权重，0表示不启用
     */
    public function __construct(int $noPrizeWeight = 0)
    {
        $this->noPrizeWeight = $noPrizeWeight;
    }

    /**
     * 从规则列表中选择一个奖品规则
     * @param array $rules 奖品规则列表
     * @return PrizeRule|null 选中的规则，如果返回null则表示不中奖（会触发兜底逻辑）
     */
    public function select(array $rules): ?PrizeRule
    {
        if (empty($rules)) {
            return null;
        }

        // 获取最大因子（用于处理小数权重）
        $maxFactor = $this->getMaxFactor(array_column($rules, 'weight'));
        $arrWeight = array_column($rules, 'weight', 'id');
        
        // 计算权重列表
        if ($maxFactor == 1) {
            $weightList = $arrWeight;
            $maxRand = 100;
        } else {
            $weightList = array_map(function ($v) use ($maxFactor) {
                return $v * $maxFactor;
            }, $arrWeight);
            $maxRand = $maxFactor * 100;
        }
        
        $weightSum = array_sum($weightList);
        
        // 如果启用了不中奖权重，将其加入总权重
        $totalWeight = $weightSum;
        if ($this->noPrizeWeight > 0) {
            $noPrizeWeightAdjusted = $this->noPrizeWeight;
            if ($maxFactor > 1) {
                $noPrizeWeightAdjusted = $this->noPrizeWeight * $maxFactor;
            }
            $totalWeight += $noPrizeWeightAdjusted;
        }
        
        $maxRand = max($maxRand, $totalWeight);
        
        // 生成随机数
        try {
            $rand = random_int(1, $maxRand);
        } catch (Exception) {
            $rand = mt_rand(1, $maxRand);
        }
        
        // 匹配奖品
        $tpm = 0;
        $prizeRuleId = 0;
        foreach ($weightList as $key => $value) {
            $tpm += $value;
            if ($tpm >= $rand) {
                $prizeRuleId = $key;
                break;
            }
        }
        
        // 如果随机数落在"不中奖"区间（在规则权重之后），返回 null
        if ($prizeRuleId == 0 && $this->noPrizeWeight > 0 && $rand > $weightSum) {
            return null; // 不中奖，触发兜底逻辑
        }
        
        if ($prizeRuleId == 0) {
            return null;
        }
        
        // 查找规则对象
        $allRule = array_column($rules, null, 'id');
        $ruleInfo = $allRule[$prizeRuleId] ?? null;
        
        if ($ruleInfo === null) {
            return null;
        }
        
        // 转换为模型对象
        $rule = new PrizeRule();
        $rule->data($ruleInfo);
        $rule->exists(true);
        
        return $rule;
    }

    /**
     * 获取最大因子（用于处理小数权重）
     * @param array $list
     * @return int
     */
    private function getMaxFactor(array $list): int
    {
        $maxFactor = 1;
        foreach ($list as $value) {
            $temp = explode('.', (string)$value);
            $decimal = end($temp);
            $count = strlen($decimal);
            for ($i = $count - 1; $i >= 0; $i--) {
                if ($decimal[$i] != '0') {
                    break;
                }
                $count--;
            }
            $maxFactor = max((int)pow(10, $count), $maxFactor);
        }
        return $maxFactor;
    }
}
