<?php
declare(strict_types=1);

namespace Leo\Lottery\Strategy;

use Leo\Lottery\Contracts\DistributionStrategyInterface;

/**
 * 简单发放策略
 * 无限制或固定比例发放
 */
class SimpleStrategy implements DistributionStrategyInterface
{
    private ?float $maxRatio;

    /**
     * @param float|null $maxRatio 最大发放比例，null表示无限制
     */
    public function __construct(?float $maxRatio = null)
    {
        $this->maxRatio = $maxRatio;
    }

    /**
     * 检查是否可以发放奖品
     * @param int $prizeId 奖品ID
     * @param int $total 总数量
     * @param array $context 上下文信息
     * @return bool
     */
    public function canDistribute(int $prizeId, int $total, array $context = []): bool
    {
        if ($this->maxRatio === null) {
            return true; // 无限制
        }
        
        $distributed = $context['distributed'] ?? 0;
        return $distributed < ($total * $this->maxRatio);
    }

    /**
     * 记录发放数量
     * @param int $prizeId 奖品ID
     * @return void
     */
    public function recordDistribution(int $prizeId): void
    {
        // 简单策略不需要记录
    }
}
