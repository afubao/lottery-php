<?php
declare(strict_types=1);

namespace Leo\Lottery\Exceptions;

use Exception;

/**
 * 抽奖异常类
 */
class LotteryException extends Exception
{
    /**
     * 抽奖失败（通用）
     */
    const LOTTERY_FAIL = 30006;

    /**
     * 库存不足（规则库存）
     */
    const INSUFFICIENT_STOCK = 30007;

    /**
     * 奖品库存不足
     */
    const PRIZE_STOCK_INSUFFICIENT = 30011;

    /**
     * 奖品不存在
     */
    const PRIZE_NOT_FOUND = 30008;

    /**
     * 规则不存在
     */
    const RULE_NOT_FOUND = 30009;

    /**
     * Redis 操作失败
     */
    const REDIS_OPERATION_FAILED = 30010;

    /**
     * 获取分布式锁失败
     */
    const LOCK_ACQUIRE_FAILED = 30012;

    /**
     * 用户标识格式错误
     */
    const INVALID_OPENID = 30013;

    /**
     * IP 地址格式错误
     */
    const INVALID_IP = 30014;

    /**
     * 规则列表为空
     */
    const NO_RULES_AVAILABLE = 30015;

    /**
     * 发放策略拒绝
     */
    const DISTRIBUTION_REJECTED = 30016;

    /**
     * 上下文信息
     */
    private array $context = [];

    /**
     * 错误消息映射
     */
    private static array $messages = [
        self::LOTTERY_FAIL => '抽奖失败，请稍后重试',
        self::INSUFFICIENT_STOCK => '奖品库存不足',
        self::PRIZE_STOCK_INSUFFICIENT => '奖品库存不足',
        self::PRIZE_NOT_FOUND => '奖品不存在',
        self::RULE_NOT_FOUND => '抽奖规则不存在',
        self::REDIS_OPERATION_FAILED => 'Redis 操作失败',
        self::LOCK_ACQUIRE_FAILED => '获取分布式锁失败',
        self::INVALID_OPENID => '用户标识格式错误',
        self::INVALID_IP => 'IP 地址格式错误',
        self::NO_RULES_AVAILABLE => '当前没有可用的抽奖规则',
        self::DISTRIBUTION_REJECTED => '发放策略拒绝，当前时段无法发放该奖品',
    ];

    /**
     * 构造函数
     * @param int $code 错误码
     * @param string|null $message 错误消息
     * @param Exception|null $previous 前置异常
     * @param array $context 上下文信息（如 rule_id, prize_id, openid 等）
     */
    public function __construct(
        int $code = self::LOTTERY_FAIL,
        string $message = null,
        Exception $previous = null,
        array $context = []
    ) {
        if (is_null($message)) {
            $message = self::$messages[$code] ?? '未知错误';
        }
        
        // 如果有上下文信息，追加到消息中
        if (!empty($context)) {
            $this->context = $context;
            $contextStr = $this->formatContext($context);
            if ($contextStr) {
                $message .= ' (' . $contextStr . ')';
            }
        }
        
        parent::__construct($message, $code, $previous);
    }

    /**
     * 获取上下文信息
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * 格式化上下文信息为字符串
     * @param array $context
     * @return string
     */
    private function formatContext(array $context): string
    {
        $parts = [];
        
        if (isset($context['rule_id'])) {
            $parts[] = '规则ID: ' . $context['rule_id'];
        }
        
        if (isset($context['prize_id'])) {
            $parts[] = '奖品ID: ' . $context['prize_id'];
        }
        
        if (isset($context['openid'])) {
            $parts[] = '用户: ' . substr($context['openid'], 0, 8) . '...';
        }
        
        if (isset($context['lock_key'])) {
            $parts[] = '锁键: ' . $context['lock_key'];
        }
        
        // 其他上下文信息
        foreach ($context as $key => $value) {
            if (!in_array($key, ['rule_id', 'prize_id', 'openid', 'lock_key'])) {
                $parts[] = $key . ': ' . (is_scalar($value) ? $value : json_encode($value));
            }
        }
        
        return implode(', ', $parts);
    }

    /**
     * 创建库存不足异常
     * @param int|null $ruleId 规则ID
     * @param int|null $prizeId 奖品ID
     * @return self
     */
    public static function insufficientStock(?int $ruleId = null, ?int $prizeId = null): self
    {
        $context = [];
        if ($ruleId !== null) {
            $context['rule_id'] = $ruleId;
        }
        if ($prizeId !== null) {
            $context['prize_id'] = $prizeId;
        }
        
        return new self(
            $prizeId !== null ? self::PRIZE_STOCK_INSUFFICIENT : self::INSUFFICIENT_STOCK,
            null,
            null,
            $context
        );
    }

    /**
     * 创建奖品不存在异常
     * @param int $prizeId 奖品ID
     * @return self
     */
    public static function prizeNotFound(int $prizeId): self
    {
        return new self(
            self::PRIZE_NOT_FOUND,
            null,
            null,
            ['prize_id' => $prizeId]
        );
    }

    /**
     * 创建规则不存在异常
     * @param int $ruleId 规则ID
     * @return self
     */
    public static function ruleNotFound(int $ruleId): self
    {
        return new self(
            self::RULE_NOT_FOUND,
            null,
            null,
            ['rule_id' => $ruleId]
        );
    }
}
