<?php
declare(strict_types=1);

namespace Leo\Lottery\Contracts;

use Leo\Lottery\Models\LotteryPrize;
use Leo\Lottery\Models\PrizeRule;

/**
 * 缓存管理器接口
 */
interface CacheManagerInterface
{
    /**
     * 获取当前有效的奖品规则列表
     * @return array 规则列表
     */
    public function getRules(): array;

    /**
     * 获取奖品信息
     * @param int $prizeId 奖品ID
     * @return array|null 奖品信息，不存在返回null
     */
    public function getPrize(int $prizeId): ?array;

    /**
     * 清除规则缓存
     * @param int|null $ruleId 规则ID，为null时清除所有规则缓存
     * @return void
     */
    public function clearRules(?int $ruleId = null): void;

    /**
     * 清除奖品缓存
     * @param int|null $prizeId 奖品ID，为null时清除所有奖品缓存
     * @return void
     */
    public function clearPrize(?int $prizeId = null): void;

    /**
     * 清除所有缓存
     * @return void
     */
    public function clearAll(): void;
}
