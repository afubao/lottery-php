<?php
declare(strict_types=1);

namespace Leo\Lottery\Models;

use Leo\Lottery\Contracts\CacheInterface;
use Leo\Lottery\Common\CacheKeyBuilder;
use think\Model;

/**
 * 抽奖奖品模型
 * @property int $id 主键ID
 * @property int $type 奖品类型（0-255），详见 PrizeType 类
 * @property string $name 奖品名称
 * @property string $image_url 奖品图片
 * @property string $url 兜底奖品跳转地址
 * @property int $total 总数量
 * @property int $remaining_quantity 剩余数量
 * @property int $weight 中奖概率权重
 * @property string $create_time 创建时间
 * @property string $update_time 更新时间
 */
class LotteryPrize extends Model
{
    // 设置表名
    protected $name = 'lottery_prizes';
    
    // 设置主键
    protected $pk = 'id';
    
    // 自动时间戳
    protected $autoWriteTimestamp = true;
    
    // 时间字段格式
    protected $dateFormat = 'Y-m-d H:i:s';
    
    // 字段类型转换
    protected $type = [
        'id' => 'integer',
        'type' => 'integer',
        'total' => 'integer',
        'remaining_quantity' => 'integer',
        'weight' => 'integer',
        'create_time' => 'timestamp',
        'update_time' => 'timestamp',
    ];
    
    // 隐藏字段
    protected $hidden = ['total', 'remaining_quantity', 'weight', 'create_time', 'update_time'];
    
    // 只读字段
    protected $readonly = ['id', 'create_time'];

    /**
     * 获取所有启用的奖品
     * @return array
     */
    public static function getActivePrizes(): array
    {
        return self::where('remaining_quantity', '>', 0)
            ->order('id', 'asc')
            ->select()
            ->toArray();
    }

    /**
     * 清除奖品缓存（当奖品信息更新时调用）
     * @param CacheInterface $cache
     * @param string $prefixKey 前缀键，默认为 'lottery:'
     * @deprecated 建议使用 CacheService::clearPrizeCache()
     */
    public static function clearCache(CacheInterface $cache, string $prefixKey = 'lottery:'): void
    {
        $keyBuilder = new CacheKeyBuilder($prefixKey);
        $cache->delete($keyBuilder->prizesList());
    }

    /**
     * 模型更新后事件（自动清除缓存）
     * 注意：需要在后台更新奖品后调用，或者通过模型事件触发
     */
    public function onAfterUpdate(): void
    {
        // 清除奖品缓存，使新奖品信息立即生效
        // 注意：这里需要注入 CacheService，但模型事件中无法直接获取
        // 建议在后台更新奖品后手动调用 CacheService::clearPrizeCache()
    }

    /**
     * 模型插入后事件
     */
    public function onAfterInsert(): void
    {
        // 新奖品插入后清除缓存
    }

    /**
     * 模型删除后事件
     */
    public function onAfterDelete(): void
    {
        // 奖品删除后清除缓存
    }
    
    /**
     * 根据类型获取奖品
     * @param int $type
     * @return \think\Collection
     */
    public static function getByType(int $type): \think\Collection
    {
        return self::where('type', $type)
            ->where('remaining_quantity', '>', 0)
            ->order('weight', 'desc')
            ->select();
    }
}
