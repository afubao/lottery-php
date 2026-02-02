<?php
declare(strict_types=1);

namespace Leo\Lottery\Service;

use think\facade\Log;

/**
 * 统一的日志服务类
 * 
 * 功能：
 * - 统一日志格式和级别管理
 * - 结构化日志记录（包含关键字段：openid、draw_id、rule_id、prize_id等）
 * - 性能日志记录（记录关键操作的耗时）
 * - 审计日志记录（记录所有关键操作）
 */
class LoggerService
{
    private bool $enabled;
    private bool $logPerformance;
    private bool $logAudit;
    private int $performanceThreshold; // 毫秒
    private string $logLevel;
    
    // 日志前缀
    private const LOG_PREFIX = '[Lottery]';
    
    public function __construct(
        bool $enabled = true,
        bool $logPerformance = true,
        bool $logAudit = true,
        int $performanceThreshold = 100,
        string $logLevel = 'info'
    ) {
        $this->enabled = $enabled;
        $this->logPerformance = $logPerformance;
        $this->logAudit = $logAudit;
        $this->performanceThreshold = $performanceThreshold;
        $this->logLevel = $logLevel;
    }
    
    /**
     * 记录抽奖开始
     * @param string $openid 用户标识
     * @param string $ip IP地址
     * @param string|null $nonce 防重放攻击的nonce
     * @return void
     */
    public function logDrawStart(string $openid, string $ip, ?string $nonce = null): void
    {
        if (!$this->enabled) {
            return;
        }
        
        $context = [
            'action' => 'draw_start',
            'openid' => $this->maskOpenid($openid),
            'ip' => $ip,
        ];
        
        if ($nonce !== null) {
            $context['nonce'] = substr($nonce, 0, 8) . '...';
        }
        
        $this->logAudit('Draw started', $context);
    }
    
    /**
     * 记录抽奖成功
     * @param string $openid 用户标识
     * @param string $drawId 抽奖ID
     * @param int $prizeId 奖品ID
     * @param int $ruleId 规则ID
     * @param float $duration 耗时（秒）
     * @return void
     */
    public function logDrawSuccess(string $openid, string $drawId, int $prizeId, int $ruleId, float $duration): void
    {
        if (!$this->enabled) {
            return;
        }
        
        $context = [
            'action' => 'draw_success',
            'openid' => $this->maskOpenid($openid),
            'draw_id' => $drawId,
            'prize_id' => $prizeId,
            'rule_id' => $ruleId,
            'duration_ms' => round($duration * 1000, 2),
        ];
        
        Log::info(self::LOG_PREFIX . ' Draw success', $context);
        
        // 记录性能日志
        if ($this->logPerformance) {
            $this->logPerformance('draw', $duration * 1000, $context);
        }
        
        // 记录审计日志
        $this->logAudit('Draw success', $context);
    }
    
    /**
     * 记录抽奖失败
     * @param string $openid 用户标识
     * @param string $reason 失败原因
     * @param array $context 上下文信息
     * @return void
     */
    public function logDrawFailure(string $openid, string $reason, array $context = []): void
    {
        if (!$this->enabled) {
            return;
        }
        
        $logContext = array_merge([
            'action' => 'draw_failure',
            'openid' => $this->maskOpenid($openid),
            'reason' => $reason,
        ], $context);
        
        Log::warning(self::LOG_PREFIX . ' Draw failed: ' . $reason, $logContext);
        
        // 记录审计日志
        $this->logAudit('Draw failed', $logContext);
    }
    
    /**
     * 记录性能日志
     * @param string $operation 操作名称
     * @param float $duration 耗时（毫秒）
     * @param array $context 上下文信息
     * @return void
     */
    public function logPerformance(string $operation, float $duration, array $context = []): void
    {
        if (!$this->logPerformance || !$this->enabled) {
            return;
        }
        
        // 只记录超过阈值的操作
        if ($duration < $this->performanceThreshold) {
            return;
        }
        
        $logContext = array_merge([
            'operation' => $operation,
            'duration_ms' => round($duration, 2),
        ], $context);
        
        Log::warning(self::LOG_PREFIX . ' Performance: ' . $operation . ' took ' . round($duration, 2) . 'ms', $logContext);
    }
    
    /**
     * 记录审计日志
     * @param string $action 操作名称
     * @param array $context 上下文信息
     * @return void
     */
    public function logAudit(string $action, array $context = []): void
    {
        if (!$this->logAudit || !$this->enabled) {
            return;
        }
        
        $logContext = array_merge([
            'action' => $action,
            'timestamp' => date('Y-m-d H:i:s'),
        ], $context);
        
        Log::info(self::LOG_PREFIX . ' Audit: ' . $action, $logContext);
    }
    
    /**
     * 记录信息日志
     * @param string $message 日志消息
     * @param array $context 上下文信息
     * @return void
     */
    public function logInfo(string $message, array $context = []): void
    {
        if (!$this->enabled) {
            return;
        }
        
        $this->maskSensitiveData($context);
        Log::info(self::LOG_PREFIX . ' ' . $message, $context);
    }
    
    /**
     * 记录警告日志
     * @param string $message 日志消息
     * @param array $context 上下文信息
     * @return void
     */
    public function logWarning(string $message, array $context = []): void
    {
        if (!$this->enabled) {
            return;
        }
        
        $this->maskSensitiveData($context);
        Log::warning(self::LOG_PREFIX . ' ' . $message, $context);
    }
    
    /**
     * 记录错误日志
     * @param string $message 日志消息
     * @param array $context 上下文信息
     * @param \Throwable|null $exception 异常对象
     * @return void
     */
    public function logError(string $message, array $context = [], ?\Throwable $exception = null): void
    {
        if (!$this->enabled) {
            return;
        }
        
        $this->maskSensitiveData($context);
        
        if ($exception !== null) {
            $context['error'] = $exception->getMessage();
            $context['trace'] = $exception->getTraceAsString();
        }
        
        Log::error(self::LOG_PREFIX . ' ' . $message, $context);
    }
    
    /**
     * 记录调试日志
     * @param string $message 日志消息
     * @param array $context 上下文信息
     * @return void
     */
    public function logDebug(string $message, array $context = []): void
    {
        if (!$this->enabled || $this->logLevel !== 'debug') {
            return;
        }
        
        $this->maskSensitiveData($context);
        Log::debug(self::LOG_PREFIX . ' ' . $message, $context);
    }
    
    /**
     * 记录库存操作
     * @param string $operation 操作类型（check、decrement、rollback等）
     * @param int $ruleId 规则ID
     * @param int|null $prizeId 奖品ID
     * @param bool $success 是否成功
     * @param float|null $duration 耗时（毫秒）
     * @return void
     */
    public function logStockOperation(string $operation, int $ruleId, ?int $prizeId = null, bool $success = true, ?float $duration = null): void
    {
        if (!$this->enabled) {
            return;
        }
        
        $context = [
            'operation' => 'stock_' . $operation,
            'rule_id' => $ruleId,
            'success' => $success,
        ];
        
        if ($prizeId !== null) {
            $context['prize_id'] = $prizeId;
        }
        
        if ($duration !== null) {
            $context['duration_ms'] = round($duration, 2);
            if ($this->logPerformance) {
                $this->logPerformance('stock_' . $operation, $duration, $context);
            }
        }
        
        if ($success) {
            Log::info(self::LOG_PREFIX . ' Stock operation: ' . $operation, $context);
        } else {
            Log::warning(self::LOG_PREFIX . ' Stock operation failed: ' . $operation, $context);
        }
    }
    
    /**
     * 记录缓存操作
     * @param string $operation 操作类型（get、set、delete等）
     * @param string $key 缓存键
     * @param bool $success 是否成功
     * @param float|null $duration 耗时（毫秒）
     * @return void
     */
    public function logCacheOperation(string $operation, string $key, bool $success = true, ?float $duration = null): void
    {
        if (!$this->enabled) {
            return;
        }
        
        $context = [
            'operation' => 'cache_' . $operation,
            'key' => $key,
            'success' => $success,
        ];
        
        if ($duration !== null) {
            $context['duration_ms'] = round($duration, 2);
            if ($this->logPerformance) {
                $this->logPerformance('cache_' . $operation, $duration, $context);
            }
        }
        
        if ($success) {
            Log::debug(self::LOG_PREFIX . ' Cache operation: ' . $operation, $context);
        } else {
            Log::warning(self::LOG_PREFIX . ' Cache operation failed: ' . $operation, $context);
        }
    }
    
    /**
     * 记录锁操作
     * @param string $operation 操作类型（acquire、release）
     * @param string $key 锁键
     * @param bool $success 是否成功
     * @param float|null $duration 耗时（毫秒）
     * @return void
     */
    public function logLockOperation(string $operation, string $key, bool $success = true, ?float $duration = null): void
    {
        if (!$this->enabled) {
            return;
        }
        
        $context = [
            'operation' => 'lock_' . $operation,
            'key' => $key,
            'success' => $success,
        ];
        
        if ($duration !== null) {
            $context['duration_ms'] = round($duration, 2);
            if ($this->logPerformance) {
                $this->logPerformance('lock_' . $operation, $duration, $context);
            }
        }
        
        if ($success) {
            Log::debug(self::LOG_PREFIX . ' Lock operation: ' . $operation, $context);
        } else {
            Log::warning(self::LOG_PREFIX . ' Lock operation failed: ' . $operation, $context);
        }
    }
    
    /**
     * 记录验证操作
     * @param string $operation 操作类型（verify_draw、verify_signature等）
     * @param string $drawId 抽奖ID
     * @param string $openid 用户标识
     * @param bool $success 是否成功
     * @return void
     */
    public function logVerification(string $operation, string $drawId, string $openid, bool $success = true): void
    {
        if (!$this->enabled) {
            return;
        }
        
        $context = [
            'action' => 'verification',
            'operation' => $operation,
            'draw_id' => $drawId,
            'openid' => $this->maskOpenid($openid),
            'success' => $success,
        ];
        
        if ($success) {
            Log::info(self::LOG_PREFIX . ' Verification success: ' . $operation, $context);
        } else {
            Log::warning(self::LOG_PREFIX . ' Verification failed: ' . $operation, $context);
        }
        
        // 记录审计日志
        $this->logAudit('Verification: ' . $operation, $context);
    }
    
    /**
     * 掩码敏感数据（openid）
     * @param string $openid 原始openid
     * @return string 掩码后的openid
     */
    private function maskOpenid(string $openid): string
    {
        $length = strlen($openid);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }
        
        // 保留前2位和后2位，中间用*替代
        return substr($openid, 0, 2) . str_repeat('*', $length - 4) . substr($openid, -2);
    }
    
    /**
     * 掩码上下文中的敏感数据
     * @param array $context 上下文数组（引用传递）
     * @return void
     */
    private function maskSensitiveData(array &$context): void
    {
        if (isset($context['openid'])) {
            $context['openid'] = $this->maskOpenid($context['openid']);
        }
        
        // 可以添加其他敏感字段的掩码处理
    }
}
