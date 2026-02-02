<?php
declare(strict_types=1);

namespace Leo\Lottery\Provider;

use Leo\Lottery\Contracts\FallbackPrizeProviderInterface;
use Leo\Lottery\Contracts\CacheInterface;
use Leo\Lottery\Common\PrizeType;
use Leo\Lottery\Common\CacheKeyBuilder;
use Exception;

/**
 * 兜底奖品提供器
 */
class FallbackPrizeProvider implements FallbackPrizeProviderInterface
{
    private CacheInterface $cache;
    private array $fallbackPrizes;
    private CacheKeyBuilder $keyBuilder;

    public function __construct(
        CacheInterface $cache,
        array $fallbackPrizes = [],
        string $prefixKey = 'lottery:'
    ) {
        $this->cache = $cache;
        $this->fallbackPrizes = $fallbackPrizes;
        $this->keyBuilder = new CacheKeyBuilder($prefixKey);
    }

    /**
     * 获取兜底奖品
     * @param string $openid 用户标识
     * @param array $context 上下文信息
     * @return array
     */
    public function getFallbackPrize(string $openid, array $context = []): array
    {
        // 如果配置为空数组，返回空的"谢谢参与"（没有任何内容）
        if (empty($this->fallbackPrizes)) {
            return $this->getDefaultFallbackPrize();
        }

        $userDrawPrizes = $this->cache->get($this->keyBuilder->userDraws($openid));
        
        // 计算总权重
        $totalWeight = 0;
        $prizeNew = [];
        foreach ($this->fallbackPrizes as $item) {
            if (is_array($userDrawPrizes) && in_array($item['id'], $userDrawPrizes)) {
                continue; // 已中过此奖品，跳过
            }
            $prizeNew[] = $item;
            $totalWeight += (int)($item['weight'] ?? 0);
        }
        
        if ($totalWeight <= 0 || empty($prizeNew)) {
            // 都中过奖了，返回第一个兜底奖品或默认的"谢谢参与"
            return $this->fallbackPrizes[0] ?? $this->getDefaultFallbackPrize();
        }
        
        // 使用权重随机选择
        try {
            $random = random_int(1, $totalWeight);
        } catch (Exception) {
            $random = mt_rand(1, $totalWeight);
        }
        
        $currentWeight = 0;
        foreach ($prizeNew as $item) {
            $currentWeight += (int)($item['weight'] ?? 0);
            if ($random <= $currentWeight) {
                return $item;
            }
        }
        
        // 最终兜底
        return $prizeNew[0];
    }

    /**
     * 获取默认兜底奖品（空的"谢谢参与"）
     * @return array
     */
    private function getDefaultFallbackPrize(): array
    {
        return [
            'id' => 0,
            'name' => '谢谢参与',
            'type' => PrizeType::VIRTUAL_THANKS,
            'url' => '',
        ];
    }
}
