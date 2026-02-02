<?php
declare(strict_types=1);

namespace Leo\Lottery\Service;

use Leo\Lottery\Models\LotteryDraw;
use Leo\Lottery\Security\AntiCheatManager;
use Leo\Lottery\Exceptions\LotteryException;

/**
 * 抽奖验证服务
 * 用于客服验证用户是否真实中奖，防止用户通过抓包修改前端结果后申诉
 */
class VerificationService
{
    private ?AntiCheatManager $antiCheatManager;

    public function __construct(?AntiCheatManager $antiCheatManager = null)
    {
        $this->antiCheatManager = $antiCheatManager;
    }

    /**
     * 验证抽奖记录
     * @param string $drawId 抽奖ID
     * @param string $openid 用户标识
     * @param string|null $signature 签名（可选，如果启用防作弊功能）
     * @return array 验证结果
     * @throws LotteryException
     */
    public function verifyDraw(string $drawId, string $openid, ?string $signature = null): array
    {
        // 查询抽奖记录（使用findByDrawsId，自动处理编码/解码）
        $draw = LotteryDraw::with('prize')->findByDrawsId($drawId);
        
        if ($draw === null) {
            throw new LotteryException(
                LotteryException::RULE_NOT_FOUND,
                '抽奖记录不存在',
                null,
                ['draw_id' => $drawId, 'openid' => $openid]
            );
        }
        
        // 验证 openid 是否匹配
        if ($draw->openid !== $openid) {
            throw new LotteryException(
                LotteryException::LOTTERY_FAIL,
                '抽奖记录与用户不匹配',
                null,
                ['draw_id' => $drawId, 'openid' => $openid, 'record_openid' => $draw->openid]
            );
        }
        
        // 判断是否中奖
        $isWin = $draw->isWin();
        
        // 构建验证结果
        $result = [
            'draw_id' => $draw->draws_id,
            'openid' => $draw->openid,
            'is_win' => $isWin,
            'prize_id' => $draw->prize_id,
            'prize_type' => $draw->type,
            'rule_id' => $draw->rule_id,
            'create_time' => $draw->create_time,
            'ip' => $draw->ip,
        ];
        
        // 如果有奖品信息，添加到结果中
        if ($draw->prize) {
            $prizeInfo = $draw->prize->toArray();
            unset($prizeInfo['id'], $prizeInfo['total'], $prizeInfo['remaining_quantity'], $prizeInfo['weight']);
            $result['prize'] = $prizeInfo;
            $result['prize_name'] = $draw->prize->name ?? '';
            $result['prize_image_url'] = $draw->prize->image_url ?? '';
            $result['prize_url'] = $draw->prize->url ?? '';
        } else {
            // 如果没有关联奖品，可能是"谢谢参与"或奖品已删除
            $result['prize'] = null;
            $result['prize_name'] = $isWin ? '奖品信息不存在（可能已删除）' : '谢谢参与';
            $result['prize_image_url'] = '';
            $result['prize_url'] = '';
        }
        
        // 如果提供了签名，验证签名
        if ($signature !== null && $this->antiCheatManager !== null) {
            $prizeForSign = $result['prize'] ?? [
                'id' => $draw->prize_id,
                'name' => $result['prize_name'],
                'type' => $draw->type,
            ];
            
            $isValidSignature = $this->antiCheatManager->verifySignature(
                $drawId,
                $openid,
                $prizeForSign,
                $signature
            );
            
            $result['signature_valid'] = $isValidSignature;
            
            if (!$isValidSignature) {
                // 签名验证失败，记录警告日志
                \think\facade\Log::warning('[Lottery] Signature verification failed', [
                    'draw_id' => $drawId,
                    'openid' => $openid,
                    'provided_signature' => $signature
                ]);
            }
        }
        
        return $result;
    }

    /**
     * 批量验证抽奖记录
     * @param array $drawIds 抽奖ID数组
     * @param string $openid 用户标识
     * @return array 验证结果数组
     */
    public function verifyDraws(array $drawIds, string $openid): array
    {
        $results = [];
        
        foreach ($drawIds as $drawId) {
            try {
                $result = $this->verifyDraw($drawId, $openid);
                $results[] = [
                    'draw_id' => $drawId,
                    'success' => true,
                    'data' => $result,
                ];
            } catch (LotteryException $e) {
                $results[] = [
                    'draw_id' => $drawId,
                    'success' => false,
                    'error' => $e->getMessage(),
                    'code' => $e->getCode(),
                ];
            }
        }
        
        return $results;
    }

    /**
     * 验证用户是否在指定时间范围内中奖
     * @param string $openid 用户标识
     * @param string|null $startTime 开始时间
     * @param string|null $endTime 结束时间
     * @return array 中奖记录列表
     */
    public function verifyUserWins(string $openid, ?string $startTime = null, ?string $endTime = null): array
    {
        $query = LotteryDraw::with('prize')
            ->where('openid', $openid)
            ->where('prize_id', '>', 0) // 只查询中奖记录
            ->order('create_time', 'desc');
        
        if ($startTime !== null) {
            $query->whereTime('create_time', '>=', $startTime);
        }
        
        if ($endTime !== null) {
            $query->whereTime('create_time', '<=', $endTime);
        }
        
        $draws = $query->select();
        
        $results = [];
        foreach ($draws as $draw) {
            $prizeInfo = null;
            if ($draw->prize) {
                $prizeInfo = $draw->prize->toArray();
                unset($prizeInfo['id'], $prizeInfo['total'], $prizeInfo['remaining_quantity'], $prizeInfo['weight']);
            }
            
            $results[] = [
                'draw_id' => $draw->draws_id,
                'is_win' => true,
                'prize' => $prizeInfo,
                'prize_name' => $draw->prize->name ?? '',
                'prize_type' => $draw->type,
                'create_time' => $draw->create_time,
            ];
        }
        
        return $results;
    }
}
