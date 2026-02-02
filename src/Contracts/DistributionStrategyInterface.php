<?php
declare(strict_types=1);

namespace Leo\Lottery\Contracts;

/**
 * 发放策略接口
 */
interface DistributionStrategyInterface
{
    /**
     * 检查是否可以发放奖品
     * @param int $prizeId 奖品ID
     * @param int $total 总数量
     * @param array $context 上下文信息（如当前时间、已发放数量等）
     * @return bool 是否可以发放
     */
    public function canDistribute(int $prizeId, int $total, array $context = []): bool;

    /**
     * 记录发放数量（用于统计）
     * @param int $prizeId 奖品ID
     * @return void
     */
    public function recordDistribution(int $prizeId): void;
}
