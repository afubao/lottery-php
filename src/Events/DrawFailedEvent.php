<?php
declare(strict_types=1);

namespace Leo\Lottery\Events;

/**
 * 抽奖失败事件
 * 
 * 在抽奖失败时触发，可以用于：
 * - 记录失败原因
 * - 发送失败通知
 * - 统计失败率
 */
class DrawFailedEvent
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
     * 失败原因
     * @var string
     */
    public string $reason;

    /**
     * 错误代码
     * @var int|null
     */
    public ?int $errorCode;

    /**
     * 上下文信息
     * @var array
     */
    public array $context;

    /**
     * 构造函数
     * @param string $openid
     * @param string $ip
     * @param string $reason
     * @param int|null $errorCode
     * @param array $context
     */
    public function __construct(
        string $openid,
        string $ip,
        string $reason,
        ?int $errorCode = null,
        array $context = []
    ) {
        $this->openid = $openid;
        $this->ip = $ip;
        $this->reason = $reason;
        $this->errorCode = $errorCode;
        $this->context = $context;
    }
}
