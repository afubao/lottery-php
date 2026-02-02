<?php
declare(strict_types=1);

namespace Leo\Lottery\Contracts;

/**
 * 兜底奖品提供器接口
 */
interface FallbackPrizeProviderInterface
{
    /**
     * 获取兜底奖品
     * @param string $openid 用户标识
     * @param array $context 上下文信息（如已中奖列表等）
     * @return array 兜底奖品信息
     */
    public function getFallbackPrize(string $openid, array $context = []): array;
}
