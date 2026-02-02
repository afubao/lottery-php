<?php
declare(strict_types=1);

namespace Leo\Lottery\Common;

/**
 * 常量类
 */
class Constants
{
    /**
     * Redis 前缀键（可通过配置覆盖）
     */
    const REDIS_PREFIX_KEY = 'lottery:';

    /**
     * 抽奖锁键名
     */
    const LOTTERY_LOCK_KEY = self::REDIS_PREFIX_KEY . 'lock:';

    /**
     * 抽奖次数键名
     */
    const DRAW_COUNT_KEY = self::REDIS_PREFIX_KEY . 'draw:count:';

    /**
     * 奖品规则缓存键名
     */
    const PRIZE_RULE_KEY = self::REDIS_PREFIX_KEY . 'prize:';

    /**
     * IP 中奖集合键名
     */
    const WIN_IP_SET_KEY = self::REDIS_PREFIX_KEY . 'set:win:ip';
}
