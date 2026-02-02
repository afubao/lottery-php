<?php
declare(strict_types=1);

namespace Leo\Lottery\Facades;

use think\Facade;
use Leo\Lottery\Service\LotteryService;

/**
 * 抽奖服务 Facade
 * 
 * 使用示例：
 * ```php
 * use Leo\Lottery\Facades\Lottery;
 * 
 * // 执行抽奖
 * $result = Lottery::draw($openid, $ip, $nonce);
 * 
 * // 清除奖品缓存
 * Lottery::clearPrizeCache();
 * ```
 */
class Lottery extends Facade
{
    /**
     * 获取当前 Facade 对应的类名（或者服务标识）
     * @return string
     */
    protected static function getFacadeClass(): string
    {
        return LotteryService::class;
    }
}
