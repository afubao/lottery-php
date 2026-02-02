<?php
declare(strict_types=1);

namespace Leo\Lottery\Models;

use think\Model;
use Leo\Lottery\Common\DrawIdEncoder;

/**
 * 抽奖记录模型
 * @property int $id 主键ID
 * @property string $draws_id 抽奖ID
 * @property string $openid 微信openid
 * @property int $prize_id 奖品ID
 * @property int $type 奖品类型（0-255），详见 PrizeType 类
 * @property string $ip IP地址
 * @property int $rule_id 规则ID
 * @property string $create_time 创建时间
 * @property string $update_time 更新时间
 */
class LotteryDraw extends Model
{
    // 设置表名
    protected $name = 'lottery_draws';
    
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
        'type' => 'integer',
        'rule_id' => 'integer',
        'create_time' => 'timestamp',
        'update_time' => 'timestamp',
    ];
    
    // 隐藏字段
    protected $hidden = ['id', 'openid', 'ip', 'create_time', 'update_time'];
    
    // 只读字段
    protected $readonly = ['id', 'create_time'];
    
    /**
     * 关联奖品
     * @return \think\model\relation\BelongsTo
     */
    public function prize()
    {
        return $this->belongsTo(LotteryPrize::class, 'prize_id', 'id');
    }
    
    /**
     * 根据抽奖ID获取记录
     * @param string $drawsId 编码后的draw_id或原始draw_id
     * @return LotteryDraw|null
     */
    public static function findByDrawsId(string $drawsId): ?LotteryDraw
    {
        // 检查是否启用ID编码
        $config = config('lottery.draw_id_encoder', []);
        $encoderEnabled = $config['enabled'] ?? true;
        
        if ($encoderEnabled) {
            // 尝试解码得到真实ID
            $encoder = self::getEncoder();
            $realId = $encoder->decode($drawsId);
            
            if ($realId !== null) {
                // 解码成功，使用主键查询（性能最优）
                return self::find($realId);
            }
        }
        
        // 解码失败或未启用编码，使用draws_id字段查询（向后兼容）
        return self::where('draws_id', $drawsId)->find();
    }

    /**
     * 验证抽奖记录（用于客服验证）
     * @param string $drawId 抽奖ID
     * @param string $openid 用户标识
     * @return array|null 返回验证结果，null表示记录不存在或openid不匹配
     *                    [
     *                        'draw_id' => string,
     *                        'openid' => string,
     *                        'is_win' => bool,  // 是否中奖
     *                        'prize_id' => int,
     *                        'prize_name' => string,
     *                        'prize_type' => int,
     *                        'create_time' => string,
     *                        'prize' => array  // 完整奖品信息（如果有关联）
     *                    ]
     */
    public static function verifyDraw(string $drawId, string $openid): ?array
    {
        // 使用findByDrawsId方法，自动处理编码/解码
        $draw = self::with('prize')->findByDrawsId($drawId);
        
        if ($draw === null) {
            return null;
        }
        
        // 验证 openid 是否匹配
        if ($draw->openid !== $openid) {
            return null;
        }
        
        // 判断是否中奖（prize_id > 0 表示中奖，prize_id = 0 表示"谢谢参与"）
        $isWin = $draw->prize_id > 0;
        
        $result = [
            'draw_id' => $draw->draws_id,
            'openid' => $draw->openid,
            'is_win' => $isWin,
            'prize_id' => $draw->prize_id,
            'prize_type' => $draw->type,
            'create_time' => $draw->create_time,
        ];
        
        // 如果有奖品信息，添加到结果中
        if ($draw->prize) {
            $prizeInfo = $draw->prize->toArray();
            unset($prizeInfo['id'], $prizeInfo['total'], $prizeInfo['remaining_quantity'], $prizeInfo['weight']);
            $result['prize'] = $prizeInfo;
            $result['prize_name'] = $draw->prize->name ?? '';
        } else {
            // 如果没有关联奖品，可能是"谢谢参与"或奖品已删除
            $result['prize'] = null;
            $result['prize_name'] = $isWin ? '奖品信息不存在' : '谢谢参与';
        }
        
        return $result;
    }

    /**
     * 判断是否真实中奖
     * @return bool true=中奖，false=未中奖（谢谢参与）
     */
    public function isWin(): bool
    {
        return $this->prize_id > 0;
    }

    /**
     * 根据用户标识获取抽奖记录
     * @param string $openid 用户标识
     * @param int|null $limit 限制数量
     * @param string $order 排序方式（desc 或 asc）
     * @return \think\Collection
     */
    public static function findByOpenid(string $openid, ?int $limit = null, string $order = 'desc')
    {
        $query = self::where('openid', $openid)->order('create_time', $order);
        if ($limit !== null) {
            $query->limit($limit);
        }
        return $query->select();
    }

    /**
     * 根据用户标识和奖品类型获取抽奖记录
     * @param string $openid 用户标识
     * @param int $type 奖品类型
     * @param int|null $limit 限制数量
     * @return \think\Collection
     */
    public static function findByOpenidAndType(string $openid, int $type, ?int $limit = null)
    {
        $query = self::where('openid', $openid)
            ->where('type', $type)
            ->order('create_time', 'desc');
        if ($limit !== null) {
            $query->limit($limit);
        }
        return $query->select();
    }

    /**
     * 根据时间范围获取抽奖记录
     * @param string|null $startTime 开始时间（格式：Y-m-d H:i:s）
     * @param string|null $endTime 结束时间（格式：Y-m-d H:i:s）
     * @param int|null $limit 限制数量
     * @return \think\Collection
     */
    public static function findByTimeRange(?string $startTime = null, ?string $endTime = null, ?int $limit = null)
    {
        $query = self::order('create_time', 'desc');
        
        if ($startTime !== null) {
            $query->whereTime('create_time', '>=', $startTime);
        }
        
        if ($endTime !== null) {
            $query->whereTime('create_time', '<=', $endTime);
        }
        
        if ($limit !== null) {
            $query->limit($limit);
        }
        
        return $query->select();
    }

    /**
     * 根据奖品ID获取抽奖记录
     * @param int $prizeId 奖品ID
     * @param int|null $limit 限制数量
     * @return \think\Collection
     */
    public static function findByPrizeId(int $prizeId, ?int $limit = null)
    {
        $query = self::where('prize_id', $prizeId)
            ->order('create_time', 'desc');
        if ($limit !== null) {
            $query->limit($limit);
        }
        return $query->select();
    }

    /**
     * 根据规则ID获取抽奖记录
     * @param int $ruleId 规则ID
     * @param int|null $limit 限制数量
     * @return \think\Collection
     */
    public static function findByRuleId(int $ruleId, ?int $limit = null)
    {
        $query = self::where('rule_id', $ruleId)
            ->order('create_time', 'desc');
        if ($limit !== null) {
            $query->limit($limit);
        }
        return $query->select();
    }

    /**
     * 获取用户的中奖记录（排除"谢谢参与"）
     * @param string $openid 用户标识
     * @param int|null $limit 限制数量
     * @return \think\Collection
     */
    public static function getUserWins(string $openid, ?int $limit = null)
    {
        $query = self::where('openid', $openid)
            ->where('prize_id', '>', 0) // 排除兜底奖品（id=0）
            ->order('create_time', 'desc');
        if ($limit !== null) {
            $query->limit($limit);
        }
        return $query->select();
    }

    /**
     * 统计用户抽奖次数
     * @param string $openid 用户标识
     * @param string|null $startTime 开始时间
     * @param string|null $endTime 结束时间
     * @return int
     */
    public static function countByOpenid(string $openid, ?string $startTime = null, ?string $endTime = null): int
    {
        $query = self::where('openid', $openid);
        
        if ($startTime !== null) {
            $query->whereTime('create_time', '>=', $startTime);
        }
        
        if ($endTime !== null) {
            $query->whereTime('create_time', '<=', $endTime);
        }
        
        return $query->count();
    }

    /**
     * 统计用户中奖次数（排除"谢谢参与"）
     * @param string $openid 用户标识
     * @param string|null $startTime 开始时间
     * @param string|null $endTime 结束时间
     * @return int
     */
    public static function countWinsByOpenid(string $openid, ?string $startTime = null, ?string $endTime = null): int
    {
        $query = self::where('openid', $openid)
            ->where('prize_id', '>', 0); // 排除兜底奖品（id=0）
        
        if ($startTime !== null) {
            $query->whereTime('create_time', '>=', $startTime);
        }
        
        if ($endTime !== null) {
            $query->whereTime('create_time', '<=', $endTime);
        }
        
        return $query->count();
    }

    /**
     * 统计指定奖品的发放数量
     * @param int $prizeId 奖品ID
     * @param string|null $startTime 开始时间
     * @param string|null $endTime 结束时间
     * @return int
     */
    public static function countByPrizeId(int $prizeId, ?string $startTime = null, ?string $endTime = null): int
    {
        $query = self::where('prize_id', $prizeId);
        
        if ($startTime !== null) {
            $query->whereTime('create_time', '>=', $startTime);
        }
        
        if ($endTime !== null) {
            $query->whereTime('create_time', '<=', $endTime);
        }
        
        return $query->count();
    }
    
    /**
     * 创建抽奖记录
     * @param string $openid
     * @param int $prizeRuleId
     * @param array $prizeInfo 奖品信息
     * @return LotteryDraw
     */
    public static function createDraw(string $openid, int $prizeRuleId, array $prizeInfo): LotteryDraw
    {
        // 检查是否启用ID编码
        $config = config('lottery.draw_id_encoder', []);
        $encoderEnabled = $config['enabled'] ?? true;
        
        if ($encoderEnabled) {
            // 方案1：使用ID编码（推荐）
            // 先创建记录获取自增ID，然后编码生成draws_id
            $draw = self::create([
                'draws_id' => '', // 临时值，稍后更新
                'openid' => $openid,
                'prize_id' => $prizeInfo['id'],
                'ip' => $prizeInfo['ip'] ?? '',
                'type' => $prizeInfo['type'],
                'rule_id' => $prizeRuleId,
            ]);
            
            // 编码自增ID生成draws_id
            $encoder = self::getEncoder();
            $drawsId = $encoder->encode($draw->id);
            
            // 更新draws_id
            $draw->draws_id = $drawsId;
            $draw->save();
            
            return $draw;
        } else {
            // 方案2：使用随机字符串（向后兼容）
            // 生成抽奖ID，确保唯一性
            $drawsId = self::generateDrawsId();
            
            // 检查抽奖ID是否已存在（防止重复）
            $maxRetries = 5;
            $retries = 0;
            while (self::findByDrawsId($drawsId) !== null && $retries < $maxRetries) {
                $drawsId = self::generateDrawsId();
                $retries++;
            }
            
            if ($retries >= $maxRetries) {
                // 如果重试5次仍然冲突，使用更长的ID
                $drawsId = self::generateDrawsId(true);
            }
            
            return self::create([
                'draws_id' => $drawsId,
                'openid' => $openid,
                'prize_id' => $prizeInfo['id'],
                'ip' => $prizeInfo['ip'] ?? '',
                'type' => $prizeInfo['type'],
                'rule_id' => $prizeRuleId,
            ]);
        }
    }
    
    /**
     * 获取ID编码器实例
     * @return DrawIdEncoder
     */
    private static function getEncoder(): DrawIdEncoder
    {
        static $encoder = null;
        
        if ($encoder === null) {
            $config = config('lottery.draw_id_encoder', []);
            $key = $config['key'] ?? null;
            $minLength = $config['min_length'] ?? 8;
            $encoder = new DrawIdEncoder($key, $minLength);
        }
        
        return $encoder;
    }
    
    /**
     * 生成抽奖ID（使用更安全的随机数生成）
     * @param bool $extended 是否使用扩展格式（更长，更安全）
     * @return string
     */
    /**
     * 生成抽奖ID
     * 
     * 安全与性能平衡设计：
     * 1. 使用时间戳前缀使插入相对有序，减少索引页分裂，提升插入性能
     * 2. 时间戳经过编码（偏移量），不完全暴露真实时间
     * 3. 随机部分足够长，保持高安全性
     * 4. 格式：时间戳编码（8位）+ 随机字符串（24-40位）
     * 
     * 性能优化：
     * - 时间戳前缀使插入相对有序，减少 B+ 树索引页分裂
     * - 查询性能不受影响（B+ 树支持随机查找）
     * 
     * 安全性：
     * - 时间戳使用偏移量编码，不完全暴露真实时间
     * - 随机部分足够长（24-40位），枚举空间巨大
     * - 无法通过时间戳推断下一个可能的 ID
     * 
     * @param bool $extended 是否使用扩展格式（更长，更安全）
     * @return string
     */
    private static function generateDrawsId(bool $extended = false): string
    {
        try {
            // 时间戳编码（8位十六进制 = 32位二进制，可表示约136年）
            // 使用 Unix 时间戳的偏移量，增加安全性（不完全暴露真实时间）
            $timestamp = time();
            $timestampOffset = $timestamp - 1700000000; // 2023-11-15 作为基准点
            $timestampEncoded = str_pad(dechex($timestampOffset), 8, '0', STR_PAD_LEFT);
            
            if ($extended) {
                // 扩展格式：48位 = 时间戳编码（8位）+ 随机字符串（40位）
                // 随机性：40位十六进制 = 160位二进制，约 1.46e48 种可能
                $randomBytes = random_bytes(20); // 20字节 = 40个十六进制字符
                $randomStr = bin2hex($randomBytes);
                
                return $timestampEncoded . $randomStr;
            } else {
                // 标准格式：32位 = 时间戳编码（8位）+ 随机字符串（24位）
                // 随机性：24位十六进制 = 96位二进制，约 7.92e28 种可能
                $randomBytes = random_bytes(12); // 12字节 = 24个十六进制字符
                $randomStr = bin2hex($randomBytes);
                
                return $timestampEncoded . $randomStr;
            }
        } catch (\Exception $e) {
            // 降级方案：如果 random_bytes 失败，使用时间戳 + 更多随机数
            // 但仍然比原来的方案更安全
            $timestamp = date('YmdHis');
            $microsecond = (int)(microtime(true) * 100000) % 100000;
            
            if ($extended) {
                // 扩展格式：时间戳 + 微秒 + 更多随机数
                $random1 = mt_rand(100000, 999999);
                $random2 = mt_rand(10000, 99999);
                $random3 = mt_rand(1000, 9999);
                return $timestamp . $microsecond . $random1 . $random2 . $random3;
            } else {
                // 标准格式：时间戳 + 微秒 + 随机数
                $random1 = mt_rand(100000, 999999);
                $random2 = mt_rand(10000, 99999);
                return $timestamp . $microsecond . $random1 . $random2;
            }
        }
    }
}
