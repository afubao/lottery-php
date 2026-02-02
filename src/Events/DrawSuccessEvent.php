<?php
declare(strict_types=1);

namespace Leo\Lottery\Events;

/**
 * 抽奖成功事件
 * 
 * 在抽奖成功时触发，可以用于：
 * - 发送中奖通知
 * - 更新用户积分
 * - 记录中奖记录
 */
class DrawSuccessEvent
{
    /**
     * 用户标识
     * @var string
     */
    public string $openid;

    /**
     * IP地址
     * @var string
     */
    public string $ip;

    /**
     * 抽奖ID
     * @var string
     */
    public string $drawId;

    /**
     * 奖品ID
     * @var int
     */
    public int $prizeId;

    /**
     * 规则ID
     * @var int
     */
    public int $ruleId;

    /**
     * 抽奖结果
     * @var array
     */
    public array $result;

    /**
     * 构造函数
     * @param string $openid
     * @param string $ip
     * @param string $drawId
     * @param int $prizeId
     * @param int $ruleId
     * @param array $result
     */
    public function __construct(
        string $openid,
        string $ip,
        string $drawId,
        int $prizeId,
        int $ruleId,
        array $result
    ) {
        $this->openid = $openid;
        $this->ip = $ip;
        $this->drawId = $drawId;
        $this->prizeId = $prizeId;
        $this->ruleId = $ruleId;
        $this->result = $result;
    }
}
