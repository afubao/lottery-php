<?php
declare(strict_types=1);

namespace Leo\Lottery\Contracts;

/**
 * 库存管理器接口
 */
interface StockManagerInterface
{
    /**
     * 检查规则库存是否充足
     * @param int $ruleId 规则ID
     * @return bool 是否有库存
     */
    public function checkStock(int $ruleId): bool;

    /**
     * 扣减规则库存
     * @param int $ruleId 规则ID
     * @return bool 是否成功
     */
    public function decrementStock(int $ruleId): bool;

    /**
     * 获取规则剩余库存
     * @param int $ruleId 规则ID
     * @return int 剩余库存数量
     */
    public function getRemainingStock(int $ruleId): int;

    /**
     * 回滚规则库存（用于事务回滚）
     * @param int $ruleId 规则ID
     * @return bool 是否成功
     */
    public function rollbackStock(int $ruleId): bool;

    /**
     * 检查奖品库存是否充足
     * @param int $prizeId 奖品ID
     * @return bool 是否有库存
     */
    public function checkPrizeStock(int $prizeId): bool;

    /**
     * 扣减奖品库存
     * @param int $prizeId 奖品ID
     * @return bool 是否成功
     */
    public function decrementPrizeStock(int $prizeId): bool;

    /**
     * 回滚奖品库存（用于事务回滚）
     * @param int $prizeId 奖品ID
     * @return bool 是否成功
     */
    public function rollbackPrizeStock(int $prizeId): bool;
}
