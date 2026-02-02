<?php
declare(strict_types=1);

namespace Leo\Lottery\Models;

use Leo\Lottery\Contracts\CacheInterface;
use Leo\Lottery\Contracts\RedisInterface;
use Leo\Lottery\Common\Constants;
use Exception;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Model;

/**
 * 奖品规则模型
 * @property int $id 主键ID
 * @property int $prize_id 奖品ID
 * @property int $total_num 发放数量
 * @property int $surplus_num 剩余数量
 * @property int $weight 权重
 * @property string $start_time 开始时间
 * @property string $end_time 结束时间
 */
class PrizeRule extends Model
{
    // 设置表名
    protected $name = 'prize_rule';
    
    // 设置主键
    protected $pk = 'id';
    
    // 自动时间戳
    protected $autoWriteTimestamp = true;
    
    // 时间字段格式
    protected $dateFormat = 'Y-m-d H:i:s';
    
    // 字段类型转换
    protected $type = [
        'id' => 'integer',
        'prize_id' => 'integer',
        'total_num' => 'integer',
        'surplus_num' => 'integer',
        'weight' => 'integer',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'create_time' => 'timestamp',
        'update_time' => 'timestamp',
    ];
    
    /**
     * 关联奖品
     * @return \think\model\relation\BelongsTo
     */
    public function prize()
    {
        return $this->belongsTo(LotteryPrize::class, 'prize_id', 'id');
    }

    /**
     * 获取当前时间段内的抽奖规则
     * @param RedisInterface $redis
     * @param CacheInterface $cache
     * @param string $prefixKey Redis前缀键
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @throws Exception
     * @deprecated 请使用 CacheManager::getRules() 替代
     */
    public function getRule(RedisInterface $redis, CacheInterface $cache, string $prefixKey = Constants::REDIS_PREFIX_KEY): array 
    {
        $currentTime = date('Y-m-d H:i:s');
        $cacheKey = $prefixKey . 'prize' . date('ymd', strtotime($currentTime));
        if ($cache->has($cacheKey)) {
            $prizeRuleIds = $cache->get($cacheKey);
            $rule = [];
            $ruleIds = [];
            $pipeline = $redis->pipeline();
            foreach ($prizeRuleIds as $ruleId) {
                $pipeline->hgetall($cacheKey . ':' . $ruleId);
            }
            $ruleCache = $pipeline->execute();
            foreach ($ruleCache as $ruleInfo) {
                if (isset($ruleInfo['surplus_num']) && (int)$ruleInfo['surplus_num'] > 0) {
                    $rule[] = $ruleInfo;
                    $ruleIds[] = $ruleInfo['id'];
                }
            }
            // 只有在有规则时才更新缓存
            if (!empty($ruleIds)) {
                $cache->set($cacheKey, $ruleIds);
            }
            return $rule;
        }
        $list = $this
            ->field(['id', 'prize_id', 'total_num', 'surplus_num', 'weight', 'start_time', 'end_time'])
            ->whereTime('start_time', '<=', $currentTime)
            ->whereTime('end_time', '>', $currentTime)
            ->where('surplus_num', '>', 0)
            ->where('weight', '>', 0)
            ->select()
            ->toArray();
        if (!empty($list)) {
            $ruleIds = [];
            $pipeline = $redis->pipeline();
            foreach ($list as $item) {
                $pipeline->hmset($cacheKey . ':' . $item['id'], [
                    'id' => $item['id'],
                    'weight' => $item['weight'],
                    'prize_id' => $item['prize_id'],
                    'total_num' => $item['total_num'],
                    'surplus_num' => $item['surplus_num'],
                ]);
                $ruleIds[] = $item['id'];
            }
            $pipeline->execute();
            // 设置缓存过期时间为当天结束
            $ttl = strtotime('tomorrow') - time();
            $cache->set($cacheKey, $ruleIds, $ttl);
        }
        return $list;
    }

    /**
     * 清除规则缓存（当规则更新时调用）
     * @param CacheInterface $cache
     * @param string $prefixKey
     * @deprecated 建议使用 CacheService::clearRuleCache()
     */
    public static function clearRuleCache(CacheInterface $cache, string $prefixKey = Constants::REDIS_PREFIX_KEY): void
    {
        $today = date('ymd');
        $cacheKey = $prefixKey . 'prize' . $today;
        $cache->delete($cacheKey);
    }

    /**
     * 模型更新后事件（自动清除缓存）
     * 注意：需要在后台更新规则后调用，或者通过模型事件触发
     */
    public function onAfterUpdate(): void
    {
        // 清除规则缓存，使新规则立即生效
        // 注意：这里需要注入 CacheService，但模型事件中无法直接获取
        // 建议在后台更新规则后手动调用 CacheService::clearRuleCache()
    }

    /**
     * 模型插入后事件
     */
    public function onAfterInsert(): void
    {
        // 新规则插入后清除缓存
    }

    /**
     * 模型删除后事件
     */
    public function onAfterDelete(): void
    {
        // 规则删除后清除缓存
    }
}
