<?php
declare(strict_types=1);

namespace Leo\Lottery\Events;

/**
 * 奖品选择后事件
 * 
 * 在奖品选择后触发（但还未扣减库存），可以用于：
 * - 记录选择过程
 * - 统计选择概率
 * - 自定义奖品选择逻辑
 */
class PrizeSelectedEvent
{
    /**
     * 用户标识
     * @var string
     */
    public string $openid;

    /**
     * 选中的规则
     * @var object
     */
    public object $rule;

    /**
     * 奖品信息
     * @var array
     */
    public array $prizeInfo;

    /**
     * 构造函数
     * @param string $openid
     * @param object $rule
     * @param array $prizeInfo
     */
    public function __construct(string $openid, object $rule, array $prizeInfo)
    {
        $this->openid = $openid;
        $this->rule = $rule;
        $this->prizeInfo = $prizeInfo;
    }
}
