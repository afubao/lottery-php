# Redis 降级策略说明

## 概述

抽奖组件设计了完善的 Redis 降级策略，确保在 Redis 不可用时，系统仍能正常运行，只是性能会有所下降。

## 降级场景

### 1. 分布式锁降级

**场景**：获取分布式锁失败

**降级策略**：
- 不抛出异常，直接返回兜底奖品
- 记录警告日志
- 保证用户体验，确保每次抽奖都有结果

**影响**：
- 可能无法完全防止并发抽奖（依赖数据库事务）
- 用户体验不受影响

**代码位置**：`LotteryService::draw()`

```php
$lockAcquired = $this->lockManager->acquire($lockKey, $lockValue, self::LOCK_TIMEOUT);
if (!$lockAcquired) {
    // 降级：返回兜底奖品
    $fallbackPrize = $this->fallbackPrizeProvider->getFallbackPrize($openid);
    return $this->resultBuilder->buildFallback($openid, $ip, $fallbackPrize);
}
```

### 2. 库存检查降级

**场景**：Redis 库存检查失败

**降级策略**：
- 回退到数据库查询库存
- 记录警告日志
- 继续执行抽奖流程

**影响**：
- 性能下降（数据库查询比 Redis 慢）
- 功能不受影响

**代码位置**：`StockManager::checkStock()`

```php
try {
    // 尝试从 Redis 获取库存
    $surplus = $this->redis->hgetall($ruleRedisKey);
    // ...
} catch (Exception $e) {
    // 降级：从数据库查询
    Log::warning('[Lottery] Redis check stock failed, fallback to database');
    $rule = PrizeRule::find($ruleId);
    return $rule && $rule->surplus_num > 0;
}
```

### 3. 库存扣减降级

**场景**：Redis 库存扣减失败

**降级策略**：
- 直接使用数据库扣减库存
- 记录警告日志
- 使用数据库事务保证一致性

**影响**：
- 性能下降
- 功能不受影响
- 数据一致性由数据库事务保证

**代码位置**：`StockManager::decrementStock()`

```php
try {
    // 尝试使用 Redis Lua 脚本原子性扣减
    $result = $this->redis->eval($luaScript, 1, $ruleRedisKey);
    // ...
} catch (Exception $e) {
    // 降级：直接使用数据库
    Log::warning('[Lottery] Redis decrement stock failed, using database only');
    $affected = PrizeRule::where('id', $ruleId)
        ->where('surplus_num', '>', 0)
        ->dec('surplus_num')
        ->update();
    return $affected > 0;
}
```

### 4. 规则缓存降级

**场景**：从 Redis 获取规则缓存失败

**降级策略**：
- 从数据库查询规则
- 记录警告日志
- 继续执行抽奖流程

**影响**：
- 性能下降（每次都要查询数据库）
- 功能不受影响

**代码位置**：`CacheManager::getRules()`

```php
try {
    // 尝试从 Redis Hash 获取规则
    $ruleCache = $pipeline->execute();
    // ...
} catch (Exception $e) {
    // 降级：从数据库查询
    Log::warning('[Lottery] Failed to get rules from Redis, querying database');
    return $this->loadRulesFromDatabase($cacheKey);
}
```

### 5. 奖品缓存降级

**场景**：从缓存获取奖品信息失败

**降级策略**：
- 从数据库查询奖品
- 记录警告日志
- 继续执行抽奖流程

**影响**：
- 性能下降
- 功能不受影响

**代码位置**：`CacheManager::getPrize()`

## 降级监控

### 监控指标

建议监控以下指标：

1. **Redis 连接状态**
   - Redis 连接失败次数
   - Redis 操作失败率
   - Redis 响应时间

2. **降级触发频率**
   - 分布式锁获取失败次数
   - Redis 操作失败次数
   - 降级到数据库的次数

3. **性能影响**
   - 抽奖接口响应时间
   - 数据库查询次数
   - 数据库慢查询

### 告警建议

建议设置以下告警：

1. **Redis 连接失败告警**
   - 条件：连续 3 次连接失败
   - 级别：警告
   - 处理：检查 Redis 服务状态

2. **降级频率告警**
   - 条件：1 分钟内降级次数超过 100 次
   - 级别：警告
   - 处理：检查 Redis 性能

3. **性能下降告警**
   - 条件：平均响应时间超过 1 秒
   - 级别：警告
   - 处理：检查数据库和 Redis 性能

## 降级开关（可选）

如果需要完全禁用 Redis，可以修改配置：

```php
// config/lottery.php
return [
    // 禁用 Redis（如果不需要分布式锁和缓存）
    // 注意：这需要修改代码，不是配置项
];
```

**注意**：当前版本没有提供配置开关，如果需要完全禁用 Redis，需要：
1. 实现一个不依赖 Redis 的 `RedisInterface` 适配器
2. 或者修改代码，跳过 Redis 相关操作

## 最佳实践

### 1. 监控 Redis 状态

```php
// 定期检查 Redis 连接
try {
    $redis->ping();
} catch (\Exception $e) {
    Log::error('Redis connection failed', ['error' => $e->getMessage()]);
    // 发送告警
}
```

### 2. 记录降级日志

系统会自动记录降级日志，建议：
- 定期查看降级日志
- 分析降级原因
- 优化 Redis 配置

### 3. 性能优化

在降级场景下，可以：
- 增加数据库连接池
- 优化数据库查询
- 添加数据库索引

### 4. 数据一致性

降级到数据库后：
- 使用数据库事务保证一致性
- 定期检查数据一致性
- 提供数据修复工具

## 常见问题

### Q: Redis 完全不可用时，系统还能正常工作吗？

A: 可以。系统会自动降级到数据库操作，功能不受影响，只是性能会下降。

### Q: 降级后数据一致性如何保证？

A: 使用数据库事务保证一致性。所有关键操作都在事务中执行。

### Q: 如何知道系统是否在降级状态？

A: 查看日志中的警告信息，或者监控 Redis 操作失败率。

### Q: 降级会影响用户体验吗？

A: 不会。降级是透明的，用户无感知。只是系统性能可能下降。

### Q: 如何避免降级？

A: 
1. 确保 Redis 服务稳定运行
2. 监控 Redis 性能指标
3. 及时处理 Redis 告警
4. 优化 Redis 配置

## 总结

抽奖组件的降级策略设计完善，能够确保在 Redis 不可用时系统仍能正常运行。建议：

1. **监控 Redis 状态**：及时发现和处理问题
2. **优化 Redis 配置**：减少降级触发
3. **准备应急预案**：在 Redis 完全不可用时能够快速恢复
4. **定期检查日志**：分析降级原因，持续优化
