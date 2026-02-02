<?php
declare(strict_types=1);

namespace Leo\Lottery\Events;

/**
 * 抽奖开始前事件
 * 
 * 在抽奖流程开始前触发，可以用于：
 * - 记录日志
 * - 检查用户资格
 * - 统计请求量
 */
class DrawBeforeEvent
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
     * Nonce（防重放攻击）
     * @var string|null
     */
    public ?string $nonce;

    /**
     * 构造函数
     * @param string $openid
     * @param string $ip
     * @param string|null $nonce
     */
    public function __construct(string $openid, string $ip, ?string $nonce = null)
    {
        $this->openid = $openid;
        $this->ip = $ip;
        $this->nonce = $nonce;
    }
}
