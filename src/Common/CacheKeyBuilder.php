<?php
declare(strict_types=1);

namespace Leo\Lottery\Common;

/**
 * 缓存键构建器
 * 统一管理所有缓存键的生成逻辑
 */
class CacheKeyBuilder
{
    private string $prefixKey;

    public function __construct(string $prefixKey = 'lottery:')
    {
        $this->prefixKey = rtrim($prefixKey, ':') . ':';
    }

    /**
     * 构建规则列表缓存键
     * @param string|null $date 日期（格式：ymd），null 表示今天
     * @return string
     */
    public function rulesList(?string $date = null): string
    {
        $date = $date ?? date('ymd');
        return $this->prefixKey . 'rules:' . $date;
    }

    /**
     * 构建规则详情缓存键（Redis Hash）
     * @param int $ruleId 规则ID
     * @param string|null $date 日期（格式：ymd），null 表示今天
     * @return string
     */
    public function ruleDetail(int $ruleId, ?string $date = null): string
    {
        $date = $date ?? date('ymd');
        return $this->prefixKey . 'rules:' . $date . ':' . $ruleId;
    }

    /**
     * 构建奖品列表缓存键
     * @return string
     */
    public function prizesList(): string
    {
        return $this->prefixKey . 'prizes:active';
    }

    /**
     * 构建奖品详情缓存键
     * @param int $prizeId 奖品ID
     * @return string
     */
    public function prizeDetail(int $prizeId): string
    {
        return $this->prefixKey . 'prize:' . $prizeId;
    }

    /**
     * 构建用户抽奖记录缓存键
     * @param string $openid 用户标识
     * @param string|null $date 日期（格式：ymd），null 表示今天
     * @return string
     */
    public function userDraws(string $openid, ?string $date = null): string
    {
        $date = $date ?? date('ymd');
        return $this->prefixKey . 'draws:' . $openid . ':' . $date;
    }

    /**
     * 构建奖品发放统计缓存键（用于峰值小时策略）
     * @param int $prizeId 奖品ID
     * @param string|null $date 日期（格式：ymd），null 表示今天
     * @return string
     */
    public function prizeDistribution(int $prizeId, ?string $date = null): string
    {
        $date = $date ?? date('ymd');
        return $this->prefixKey . 'distribution:' . $prizeId . ':' . $date;
    }

    /**
     * 构建分布式锁键
     * @param string $key 锁的标识
     * @return string
     */
    public function lock(string $key): string
    {
        return $this->prefixKey . 'lock:' . $key;
    }

    /**
     * 构建互斥锁键（用于防止缓存击穿）
     * @param string $type 类型（如 rules, prizes）
     * @param string|null $date 日期（格式：ymd），null 表示今天
     * @return string
     */
    public function mutex(string $type, ?string $date = null): string
    {
        $date = $date ?? date('ymd');
        return $this->prefixKey . 'mutex:' . $type . ':' . $date;
    }

    /**
     * 构建"谢谢参与"统计键
     * @param string $openid 用户标识
     * @param string|null $date 日期（格式：ymd），null 表示累计总数
     * @return string
     */
    public function thanksStats(string $openid, ?string $date = null): string
    {
        if ($date === null) {
            return $this->prefixKey . 'stats:thanks:user:' . $openid;
        }
        return $this->prefixKey . 'stats:thanks:' . $openid . ':' . $date;
    }

    /**
     * 构建全局"谢谢参与"统计键
     * @param string|null $date 日期（格式：ymd），null 表示所有日期累计
     * @return string
     */
    public function globalThanksStats(?string $date = null): string
    {
        if ($date === null) {
            return $this->prefixKey . 'stats:thanks:global:total';
        }
        return $this->prefixKey . 'stats:thanks:global:' . $date;
    }

    /**
     * 获取前缀键
     * @return string
     */
    public function getPrefixKey(): string
    {
        return $this->prefixKey;
    }

    /**
     * 设置前缀键
     * @param string $prefixKey
     * @return self
     */
    public function setPrefixKey(string $prefixKey): self
    {
        $this->prefixKey = rtrim($prefixKey, ':') . ':';
        return $this;
    }
}
