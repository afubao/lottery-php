<?php
declare(strict_types=1);

namespace Leo\Lottery\Events;

/**
 * 抽奖完成后事件
 * 
 * 在抽奖流程完成后触发（无论成功或失败），可以用于：
 * - 记录日志
 * - 发送通知
 * - 更新统计
 */
class DrawAfterEvent
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
     * 抽奖结果
     * @var array|null
     */
    public ?array $result;

    /**
     * 是否成功
     * @var bool
     */
    public bool $success;

    /**
     * 错误信息（如果失败）
     * @var string|null
     */
    public ?string $error;

    /**
     * 构造函数
     * @param string $openid
     * @param string $ip
     * @param array|null $result
     * @param bool $success
     * @param string|null $error
     */
    public function __construct(
        string $openid,
        string $ip,
        ?array $result = null,
        bool $success = true,
        ?string $error = null
    ) {
        $this->openid = $openid;
        $this->ip = $ip;
        $this->result = $result;
        $this->success = $success;
        $this->error = $error;
    }
}
