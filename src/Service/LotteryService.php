<?php
declare(strict_types=1);

namespace Leo\Lottery\Service;

use Leo\Lottery\Contracts\LockManagerInterface;
use Leo\Lottery\Contracts\PrizeSelectorInterface;
use Leo\Lottery\Contracts\StockManagerInterface;
use Leo\Lottery\Contracts\DistributionStrategyInterface;
use Leo\Lottery\Contracts\CacheManagerInterface;
use Leo\Lottery\Contracts\FallbackPrizeProviderInterface;
use Leo\Lottery\Builder\DrawResultBuilder;
use Leo\Lottery\Common\CacheKeyBuilder;
use Leo\Lottery\Security\AntiCheatManager;
use Leo\Lottery\Exceptions\LotteryException;
use Leo\Lottery\Models\LotteryPrize;
use Leo\Lottery\Events\DrawBeforeEvent;
use Leo\Lottery\Events\DrawAfterEvent;
use Leo\Lottery\Events\DrawSuccessEvent;
use Leo\Lottery\Events\DrawFailedEvent;
use Leo\Lottery\Events\PrizeSelectedEvent;
use Exception;
use think\facade\Db;
use think\facade\Event;

/**
 * 抽奖服务类（重构版）
 * 协调各个组件，编排抽奖流程
 */
class LotteryService
{
    private LockManagerInterface $lockManager;
    private PrizeSelectorInterface $prizeSelector;
    private StockManagerInterface $stockManager;
    private DistributionStrategyInterface $distributionStrategy;
    private CacheManagerInterface $cacheManager;
    private FallbackPrizeProviderInterface $fallbackPrizeProvider;
    private DrawResultBuilder $resultBuilder;
    private CacheKeyBuilder $keyBuilder;
    private ?AntiCheatManager $antiCheatManager;
    private bool $isTest;
    private LoggerService $logger;
    
    // 常量
    private const LOCK_TIMEOUT = 30; // 分布式锁超时时间（秒）

    public function __construct(
        LockManagerInterface $lockManager,
        PrizeSelectorInterface $prizeSelector,
        StockManagerInterface $stockManager,
        DistributionStrategyInterface $distributionStrategy,
        CacheManagerInterface $cacheManager,
        FallbackPrizeProviderInterface $fallbackPrizeProvider,
        DrawResultBuilder $resultBuilder,
        bool $isTest = false,
        string $prefixKey = 'lottery:',
        ?AntiCheatManager $antiCheatManager = null,
        ?LoggerService $logger = null
    ) {
        $this->lockManager = $lockManager;
        $this->prizeSelector = $prizeSelector;
        $this->stockManager = $stockManager;
        $this->distributionStrategy = $distributionStrategy;
        $this->cacheManager = $cacheManager;
        $this->fallbackPrizeProvider = $fallbackPrizeProvider;
        $this->resultBuilder = $resultBuilder;
        $this->keyBuilder = new CacheKeyBuilder($prefixKey);
        $this->antiCheatManager = $antiCheatManager;
        $this->isTest = $isTest;
        
        // 创建日志服务实例
        if ($logger === null) {
            $logConfig = config('lottery.logging', []);
            $this->logger = new LoggerService(
                $logConfig['enabled'] ?? true,
                $logConfig['log_performance'] ?? true,
                $logConfig['log_audit'] ?? true,
                $logConfig['performance_threshold'] ?? 100,
                $logConfig['log_level'] ?? 'info'
            );
        } else {
            $this->logger = $logger;
        }
    }

    /**
     * 执行抽奖
     * @param string $openid 用户标识
     * @param string $ip IP地址
     * @param string|null $nonce 防重放攻击的 nonce（可选）
     * @return array 抽奖结果 ['draw_id' => string, 'prize' => array, 'signature' => string]
     * @throws LotteryException
     */
    public function draw(string $openid, string $ip, ?string $nonce = null): array
    {
        $startTime = microtime(true);
        
        // 触发抽奖开始前事件
        Event::trigger(new DrawBeforeEvent($openid, $ip, $nonce));
        
        // 记录抽奖开始
        $this->logger->logDrawStart($openid, $ip, $nonce);
        
        // 参数验证
        try {
            $this->validateOpenid($openid);
            $this->validateIp($ip);
        } catch (LotteryException $e) {
            $this->logger->logDrawFailure($openid, 'validation_failed', ['error' => $e->getMessage()]);
            // 触发失败事件
            Event::trigger(new DrawFailedEvent($openid, $ip, 'validation_failed', $e->getCode(), ['error' => $e->getMessage()]));
            // 触发完成后事件
            Event::trigger(new DrawAfterEvent($openid, $ip, null, false, $e->getMessage()));
            throw $e;
        }

        // 防重放攻击验证
        if ($this->antiCheatManager !== null && $nonce !== null) {
            if (!$this->antiCheatManager->verifyNonce($openid, $nonce)) {
                $this->logger->logDrawFailure($openid, 'invalid_nonce', ['nonce' => substr($nonce, 0, 8) . '...']);
                $exception = new LotteryException(
                    LotteryException::LOTTERY_FAIL,
                    '无效的请求，请重新抽奖',
                    null,
                    ['openid' => $openid, 'reason' => 'invalid_nonce']
                );
                // 触发失败事件
                Event::trigger(new DrawFailedEvent($openid, $ip, 'invalid_nonce', $exception->getCode(), ['nonce' => substr($nonce, 0, 8) . '...']));
                // 触发完成后事件
                Event::trigger(new DrawAfterEvent($openid, $ip, null, false, $exception->getMessage()));
                throw $exception;
            }
        }

        $lockKey = $this->keyBuilder->lock($openid);
        $lockValue = 'lock_' . uniqid('', true);
        
        // 获取分布式锁
        $lockStartTime = microtime(true);
        $lockAcquired = $this->lockManager->acquire($lockKey, $lockValue, self::LOCK_TIMEOUT);
        $lockDuration = (microtime(true) - $lockStartTime) * 1000;
        $this->logger->logLockOperation('acquire', $lockKey, $lockAcquired, $lockDuration);
        
        if (!$lockAcquired) {
            $this->logger->logDrawFailure($openid, 'lock_failed', ['lock_key' => $lockKey]);
            // 没有获取到锁，发放兜底奖品
            $fallbackPrize = $this->fallbackPrizeProvider->getFallbackPrize($openid);
            $result = $this->resultBuilder->buildFallback($openid, $ip, $fallbackPrize);
            // 触发完成后事件（返回兜底奖品也算完成）
            Event::trigger(new DrawAfterEvent($openid, $ip, $result, true));
            return $result;
        }

        try {
            // 1. 获取奖品规则列表
            $rulesStartTime = microtime(true);
            $rules = $this->cacheManager->getRules();
            $rulesDuration = (microtime(true) - $rulesStartTime) * 1000;
            $this->logger->logCacheOperation('get_rules', 'rules', !empty($rules), $rulesDuration);
            
            if (empty($rules)) {
                $this->logger->logDrawFailure($openid, 'no_rules_available');
                $fallbackPrize = $this->fallbackPrizeProvider->getFallbackPrize($openid);
                $result = $this->resultBuilder->buildFallback($openid, $ip, $fallbackPrize);
                // 触发失败事件
                Event::trigger(new DrawFailedEvent($openid, $ip, 'no_rules_available', null, []));
                // 触发完成后事件
                Event::trigger(new DrawAfterEvent($openid, $ip, $result, true));
                return $result;
            }

            // 2. 选择奖品规则
            $selectedRule = $this->prizeSelector->select($rules);
            if ($selectedRule === null) {
                $this->logger->logDrawFailure($openid, 'no_rule_matched');
                $fallbackPrize = $this->fallbackPrizeProvider->getFallbackPrize($openid);
                $result = $this->resultBuilder->buildFallback($openid, $ip, $fallbackPrize);
                // 触发失败事件
                Event::trigger(new DrawFailedEvent($openid, $ip, 'no_rule_matched', null, []));
                // 触发完成后事件
                Event::trigger(new DrawAfterEvent($openid, $ip, $result, true));
                return $result;
            }

            // 3. 检查库存
            $stockCheckStartTime = microtime(true);
            $hasStock = $this->stockManager->checkStock($selectedRule->id);
            $stockCheckDuration = (microtime(true) - $stockCheckStartTime) * 1000;
            $this->logger->logStockOperation('check', $selectedRule->id, null, $hasStock, $stockCheckDuration);
            
            if (!$hasStock) {
                $this->logger->logDrawFailure($openid, 'insufficient_stock', ['rule_id' => $selectedRule->id]);
                $fallbackPrize = $this->fallbackPrizeProvider->getFallbackPrize($openid);
                $result = $this->resultBuilder->buildFallback($openid, $ip, $fallbackPrize);
                // 触发失败事件
                Event::trigger(new DrawFailedEvent($openid, $ip, 'insufficient_stock', null, ['rule_id' => $selectedRule->id]));
                // 触发完成后事件
                Event::trigger(new DrawAfterEvent($openid, $ip, $result, true));
                return $result;
            }

            // 4. 获取奖品信息
            $prizeInfo = $this->cacheManager->getPrize($selectedRule->prize_id);
            if ($prizeInfo === null) {
                $this->logger->logDrawFailure($openid, 'prize_not_found', [
                    'prize_id' => $selectedRule->prize_id,
                    'rule_id' => $selectedRule->id
                ]);
                // 注意：这里不抛异常，而是返回兜底奖品，保证用户体验
                $fallbackPrize = $this->fallbackPrizeProvider->getFallbackPrize($openid);
                $result = $this->resultBuilder->buildFallback($openid, $ip, $fallbackPrize);
                // 触发完成后事件（返回兜底奖品也算完成）
                Event::trigger(new DrawAfterEvent($openid, $ip, $result, true));
                return $result;
            }

            // 触发奖品选择事件
            Event::trigger(new PrizeSelectedEvent($openid, $selectedRule, $prizeInfo));

            // 5. 检查发放策略（测试模式跳过）
            if (!$this->isTest) {
                $canDistribute = $this->distributionStrategy->canDistribute(
                    $selectedRule->prize_id,
                    $selectedRule->total_num,
                    ['rule_id' => $selectedRule->id]
                );
                
                if (!$canDistribute) {
                    $this->logger->logDrawFailure($openid, 'distribution_rejected', [
                        'prize_id' => $selectedRule->prize_id,
                        'rule_id' => $selectedRule->id
                    ]);
                    $fallbackPrize = $this->fallbackPrizeProvider->getFallbackPrize($openid);
                    $result = $this->resultBuilder->buildFallback($openid, $ip, $fallbackPrize);
                    // 触发失败事件
                    Event::trigger(new DrawFailedEvent($openid, $ip, 'distribution_rejected', null, [
                        'prize_id' => $selectedRule->prize_id,
                        'rule_id' => $selectedRule->id
                    ]));
                    // 触发完成后事件
                    Event::trigger(new DrawAfterEvent($openid, $ip, $result, true));
                    return $result;
                }
            }

            // 6. 记录抽奖请求（用于频率限制统计）
            if ($this->antiCheatManager !== null) {
                $this->antiCheatManager->recordDrawRequest($openid, $ip);
            }

            // 7. 扣减库存并创建记录（事务）
            $result = $this->processDraw($openid, $ip, $selectedRule, $prizeInfo);
            
            // 记录成功日志
            $duration = microtime(true) - $startTime;
            $this->logger->logDrawSuccess($openid, $result['draw_id'], $selectedRule->prize_id, $selectedRule->id, $duration);
            
            // 触发成功事件
            Event::trigger(new DrawSuccessEvent($openid, $ip, $result['draw_id'], $selectedRule->prize_id, $selectedRule->id, $result));
            
            // 触发完成后事件
            Event::trigger(new DrawAfterEvent($openid, $ip, $result, true));
            
            return $result;

        } catch (LotteryException $e) {
            // 触发失败事件
            Event::trigger(new DrawFailedEvent($openid, $ip, 'lottery_exception', $e->getCode(), [
                'error' => $e->getMessage()
            ]));
            // 触发完成后事件
            Event::trigger(new DrawAfterEvent($openid, $ip, null, false, $e->getMessage()));
            throw $e;
        } catch (Exception $e) {
            // 触发失败事件
            Event::trigger(new DrawFailedEvent($openid, $ip, 'exception', null, [
                'error' => $e->getMessage()
            ]));
            // 触发完成后事件
            Event::trigger(new DrawAfterEvent($openid, $ip, null, false, $e->getMessage()));
            throw $e;
        } finally {
            // 释放锁
            $releaseStartTime = microtime(true);
            $releaseSuccess = $this->lockManager->release($lockKey, $lockValue);
            $releaseDuration = (microtime(true) - $releaseStartTime) * 1000;
            $this->logger->logLockOperation('release', $lockKey, $releaseSuccess, $releaseDuration);
        }
    }

    /**
     * 处理抽奖（扣减库存、创建记录）
     * @param string $openid
     * @param string $ip
     * @param \Leo\Lottery\Models\PrizeRule $rule
     * @param array $prizeInfo
     * @return array
     * @throws LotteryException
     */
    private function processDraw(string $openid, string $ip, $rule, array $prizeInfo): array
    {
        Db::startTrans();
        try {
            // 扣减规则库存
            $decrementStartTime = microtime(true);
            $decrementSuccess = $this->stockManager->decrementStock($rule->id);
            $decrementDuration = (microtime(true) - $decrementStartTime) * 1000;
            $this->logger->logStockOperation('decrement', $rule->id, null, $decrementSuccess, $decrementDuration);
            
            if (!$decrementSuccess) {
                Db::rollback();
                $this->logger->logDrawFailure($openid, 'decrement_rule_stock_failed', ['rule_id' => $rule->id]);
                throw LotteryException::insufficientStock($rule->id, null);
            }

            // 扣减奖品库存
            $prizeDecrementStartTime = microtime(true);
            $prizeDecrementSuccess = $this->stockManager->decrementPrizeStock($rule->prize_id);
            $prizeDecrementDuration = (microtime(true) - $prizeDecrementStartTime) * 1000;
            $this->logger->logStockOperation('decrement_prize', $rule->id, $rule->prize_id, $prizeDecrementSuccess, $prizeDecrementDuration);
            
            if (!$prizeDecrementSuccess) {
                Db::rollback();
                // 回滚规则库存
                $this->stockManager->rollbackStock($rule->id);
                $this->logger->logDrawFailure($openid, 'decrement_prize_stock_failed', [
                    'rule_id' => $rule->id,
                    'prize_id' => $rule->prize_id
                ]);
                throw LotteryException::insufficientStock($rule->id, $rule->prize_id);
            }

            // 创建奖品模型对象
            $prize = new LotteryPrize();
            $prize->data($prizeInfo);
            $prize->exists(true);

            // 记录发放数量
            $this->distributionStrategy->recordDistribution($rule->prize_id);

            // 构建结果
            $result = $this->resultBuilder->build($openid, $ip, $rule, $prize);

            Db::commit();

            return $result;

        } catch (LotteryException $e) {
            Db::rollback();
            $this->logger->logDrawFailure($openid, 'lottery_exception', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'rule_id' => $rule->id ?? null,
                'prize_id' => $rule->prize_id ?? null
            ]);
            // 触发失败事件
            Event::trigger(new DrawFailedEvent($openid, $ip, 'lottery_exception', $e->getCode(), [
                'error' => $e->getMessage(),
                'rule_id' => $rule->id ?? null,
                'prize_id' => $rule->prize_id ?? null
            ]));
            // 触发完成后事件
            Event::trigger(new DrawAfterEvent($openid, $ip, null, false, $e->getMessage()));
            throw $e;
        } catch (Exception $e) {
            Db::rollback();
            $this->logger->logError('Draw failed', [
                'openid' => $openid,
                'rule_id' => $rule->id ?? null,
                'prize_id' => $rule->prize_id ?? null
            ], $e);
            $exception = new LotteryException(
                LotteryException::LOTTERY_FAIL,
                '抽奖失败，请稍后重试',
                $e,
                ['openid' => $openid, 'rule_id' => $rule->id ?? null, 'prize_id' => $rule->prize_id ?? null]
            );
            // 触发失败事件
            Event::trigger(new DrawFailedEvent($openid, $ip, 'exception', $exception->getCode(), [
                'error' => $e->getMessage(),
                'rule_id' => $rule->id ?? null,
                'prize_id' => $rule->prize_id ?? null
            ]));
            // 触发完成后事件
            Event::trigger(new DrawAfterEvent($openid, $ip, null, false, $exception->getMessage()));
            throw $exception;
        }
    }

    /**
     * 清除奖品缓存（供外部调用）
     */
    public function clearPrizeCache(): void
    {
        $this->cacheManager->clearPrize();
    }

    /**
     * 验证 openid 格式
     * @param string $openid
     * @throws LotteryException
     */
    private function validateOpenid(string $openid): void
    {
        if (empty($openid)) {
            throw new LotteryException(
                LotteryException::INVALID_OPENID,
                '用户标识不能为空',
                null,
                ['openid' => $openid]
            );
        }
        
        $length = mb_strlen($openid, 'UTF-8');
        if ($length < 1 || $length > 32) {
            throw new LotteryException(
                LotteryException::INVALID_OPENID,
                '用户标识长度必须在1-32字符之间',
                null,
                ['openid' => $openid, 'length' => $length]
            );
        }
        
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $openid)) {
            throw new LotteryException(
                LotteryException::INVALID_OPENID,
                '用户标识格式不正确，只能包含字母、数字、下划线和连字符',
                null,
                ['openid' => $openid]
            );
        }
    }

    /**
     * 验证 IP 地址格式
     * @param string $ip
     * @throws LotteryException
     */
    private function validateIp(string $ip): void
    {
        if (empty($ip)) {
            throw new LotteryException(
                LotteryException::INVALID_IP,
                'IP地址不能为空',
                null,
                ['ip' => $ip]
            );
        }
        
        $length = strlen($ip);
        if ($length > 45) {
            throw new LotteryException(
                LotteryException::INVALID_IP,
                'IP地址长度超出限制',
                null,
                ['ip' => $ip, 'length' => $length]
            );
        }
        
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6)) {
            throw new LotteryException(
                LotteryException::INVALID_IP,
                'IP地址格式不正确',
                null,
                ['ip' => $ip]
            );
        }
    }
}
