<?php
declare(strict_types=1);

namespace Leo\Lottery\Contracts;

use Leo\Lottery\Models\PrizeRule;

/**
 * 奖品选择器接口
 */
interface PrizeSelectorInterface
{
    /**
     * 从规则列表中选择一个奖品规则
     * @param array $rules 奖品规则列表
     * @return PrizeRule|null 选中的规则，如果没有选中则返回null
     */
    public function select(array $rules): ?PrizeRule;
}
