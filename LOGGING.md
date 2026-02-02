# 日志系统使用说明

## 概述

抽奖组件提供了统一的日志服务 `LoggerService`，用于记录结构化日志、性能日志和审计日志。

## 配置

在 `config/lottery.php` 中配置日志：

```php
'logging' => [
    // 是否启用日志
    'enabled' => true,
    
    // 是否记录性能日志
    'log_performance' => true,
    
    // 是否记录审计日志
    'log_audit' => true,
    
    // 性能日志阈值（毫秒），超过此阈值的操作会记录性能日志
    'performance_threshold' => 100,
    
    // 默认日志级别（info、warning、error、debug）
    'log_level' => 'info',
],
```

## 日志类型

### 1. 结构化日志

结构化日志包含关键字段，便于分析和监控：

- `openid`: 用户标识（自动掩码处理）
- `draw_id`: 抽奖ID
- `prize_id`: 奖品ID
- `rule_id`: 规则ID
- `action`: 操作类型
- `duration_ms`: 耗时（毫秒）

### 2. 性能日志

性能日志记录关键操作的耗时，只记录超过阈值的操作：

- 抽奖操作耗时
- 库存操作耗时
- 缓存操作耗时
- 锁操作耗时

### 3. 审计日志

审计日志记录所有关键操作，用于审计和追溯：

- 抽奖开始
- 抽奖成功
- 抽奖失败
- 验证操作

## 使用示例

### 在服务中使用

```php
use Leo\Lottery\Service\LoggerService;

class YourService
{
    private LoggerService $logger;
    
    public function __construct(LoggerService $logger)
    {
        $this->logger = $logger;
    }
    
    public function someOperation(string $openid): void
    {
        $startTime = microtime(true);
        
        // 记录操作开始
        $this->logger->logAudit('Operation started', ['openid' => $openid]);
        
        try {
            // 执行操作
            // ...
            
            // 记录成功
            $duration = microtime(true) - $startTime;
            $this->logger->logInfo('Operation success', [
                'openid' => $openid,
                'duration_ms' => $duration * 1000
            ]);
            
        } catch (\Exception $e) {
            // 记录失败
            $this->logger->logError('Operation failed', ['openid' => $openid], $e);
            throw $e;
        }
    }
}
```

### 记录性能日志

```php
$startTime = microtime(true);
// 执行操作
$duration = (microtime(true) - $startTime) * 1000;

$this->logger->logPerformance('operation_name', $duration, [
    'context' => 'additional_info'
]);
```

### 记录审计日志

```php
$this->logger->logAudit('User action', [
    'openid' => $openid,
    'action' => 'draw',
    'result' => 'success'
]);
```

## 日志格式

### 抽奖成功日志

```
[Lottery] Draw success
{
    "action": "draw_success",
    "openid": "us****id",
    "draw_id": "a1b2c3d4",
    "prize_id": 100,
    "rule_id": 1,
    "duration_ms": 45.23
}
```

### 性能日志

```
[Lottery] Performance: draw took 150.5ms
{
    "operation": "draw",
    "duration_ms": 150.5,
    "openid": "us****id",
    "draw_id": "a1b2c3d4"
}
```

### 审计日志

```
[Lottery] Audit: Draw started
{
    "action": "Draw started",
    "timestamp": "2025-02-02 12:34:56",
    "openid": "us****id",
    "ip": "192.168.1.1"
}
```

## 敏感数据掩码

`LoggerService` 自动对敏感数据进行掩码处理：

- `openid`: 保留前2位和后2位，中间用 `*` 替代
  - 示例：`user_openid_123` → `us**********23`

## 日志级别

- **info**: 一般信息日志（抽奖成功、操作完成等）
- **warning**: 警告日志（库存不足、锁获取失败等）
- **error**: 错误日志（异常、失败等）
- **debug**: 调试日志（仅在 `log_level` 为 `debug` 时记录）

## 监控建议

建议监控以下关键日志：

1. **抽奖成功率**: `[Lottery] Draw success` 的数量
2. **抽奖失败率**: `[Lottery] Draw failed` 的数量
3. **性能问题**: `[Lottery] Performance` 日志的频率和耗时
4. **锁竞争**: `[Lottery] Lock operation failed` 的频率
5. **库存问题**: `[Lottery] Stock operation failed` 的频率

## 最佳实践

1. **结构化日志**: 使用 `logInfo`、`logWarning`、`logError` 等方法，传入结构化数据
2. **性能监控**: 对关键操作记录性能日志，设置合理的阈值
3. **审计追踪**: 对关键操作记录审计日志，便于追溯
4. **错误处理**: 记录异常信息，包含上下文数据
5. **日志级别**: 根据重要性选择合适的日志级别

## 注意事项

1. 日志服务会自动掩码敏感数据（如 `openid`）
2. 性能日志只记录超过阈值的操作
3. 审计日志可以通过配置开关控制
4. 日志格式统一，便于日志分析工具处理
