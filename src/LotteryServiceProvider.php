<?php
declare(strict_types=1);

namespace Leo\Lottery;

use Leo\Lottery\Service\LotteryService;
use Leo\Lottery\Service\CacheService;
use Leo\Lottery\Manager\LockManager;
use Leo\Lottery\Manager\StockManager;
use Leo\Lottery\Manager\CacheManager;
use Leo\Lottery\Manager\StatisticsManager;
use Leo\Lottery\Selector\WeightedPrizeSelector;
use Leo\Lottery\Strategy\PeakHoursStrategy;
use Leo\Lottery\Provider\FallbackPrizeProvider;
use Leo\Lottery\Builder\DrawResultBuilder;
use Leo\Lottery\Security\AntiCheatManager;
use Leo\Lottery\Contracts\LockManagerInterface;
use Leo\Lottery\Contracts\PrizeSelectorInterface;
use Leo\Lottery\Contracts\StockManagerInterface;
use Leo\Lottery\Contracts\DistributionStrategyInterface;
use Leo\Lottery\Contracts\CacheManagerInterface;
use Leo\Lottery\Contracts\FallbackPrizeProviderInterface;
use Leo\Lottery\Contracts\RedisInterface;
use Leo\Lottery\Contracts\CacheInterface;
use Leo\Lottery\Common\Constants;
use Leo\Lottery\Adapters\ThinkRedisAdapter;
use Leo\Lottery\Adapters\ThinkCacheAdapter;
use think\Service;

/**
 * 抽奖服务提供者
 */
class LotteryServiceProvider extends Service
{
    /**
     * 注册服务
     */
    public function register(): void
    {
        // 验证配置
        $config = $this->app->config->get('lottery', []);
        $this->validateConfig($config);
        
        // 获取配置
        $prefixKey = $config['prefix_key'] ?? Constants::REDIS_PREFIX_KEY;
        // 向后兼容：优先使用 fallback_prizes，如果不存在则使用 virtual_prizes
        $fallbackPrizes = $config['fallback_prizes'] ?? $config['virtual_prizes'] ?? [];
        $isTest = $config['is_test'] ?? false;
        $hotHours = $config['hot_hours'] ?? [];
        $recordThanksPrize = $config['record_thanks_prize'] ?? true; // 默认 true，保持向后兼容
        $enableStatistics = $config['enable_thanks_statistics'] ?? true; // 默认 true，启用统计
        
        // 防作弊配置
        $antiCheatConfig = $config['anti_cheat'] ?? [];
        $antiCheatEnabled = $antiCheatConfig['enabled'] ?? false;
        $antiCheatSecretKey = $antiCheatConfig['secret_key'] ?? null;
        $antiCheatNonceTtl = $antiCheatConfig['nonce_ttl'] ?? 300;
        
        // 向后兼容警告：如果使用了旧的 virtual_prizes 配置，记录警告
        if (isset($config['virtual_prizes']) && !isset($config['fallback_prizes'])) {
            // 可以通过日志记录，但不影响功能
        }
        
        // 注册默认适配器（如果用户没有手动绑定）
        $this->registerDefaultAdapters($config);
        
        // 注册 LockManager
        $this->app->bind(LockManagerInterface::class, function () use ($prefixKey) {
            $redis = $this->app->make(RedisInterface::class);
            return new LockManager($redis, $prefixKey);
        });
        
        // 注册 PrizeSelector
        $this->app->bind(PrizeSelectorInterface::class, WeightedPrizeSelector::class);
        
        // 注册 StockManager
        $this->app->bind(StockManagerInterface::class, function () use ($prefixKey) {
            $redis = $this->app->make(RedisInterface::class);
            $cache = $this->app->make(CacheInterface::class);
            return new StockManager($redis, $cache, $prefixKey);
        });
        
        // 注册 DistributionStrategy
        $this->app->bind(DistributionStrategyInterface::class, function () use ($hotHours, $prefixKey) {
            $cache = $this->app->make(CacheInterface::class);
            return new PeakHoursStrategy($cache, $hotHours, 1.0, 0.2, $prefixKey);
        });
        
        // 注册 CacheManager
        $this->app->bind(CacheManagerInterface::class, function () use ($prefixKey) {
            $redis = $this->app->make(RedisInterface::class);
            $cache = $this->app->make(CacheInterface::class);
            $lockManager = $this->app->make(LockManagerInterface::class);
            return new CacheManager($redis, $cache, $lockManager, $prefixKey);
        });
        
        // 注册 FallbackPrizeProvider
        $this->app->bind(FallbackPrizeProviderInterface::class, function () use ($fallbackPrizes, $prefixKey) {
            $cache = $this->app->make(CacheInterface::class);
            return new FallbackPrizeProvider($cache, $fallbackPrizes, $prefixKey);
        });
        
        // 注册 AntiCheatManager（可选）
        $antiCheatManager = null;
        if ($antiCheatEnabled) {
            try {
                $redis = $this->app->make(RedisInterface::class);
                $cache = $this->app->make(CacheInterface::class);
                $antiCheatManager = new AntiCheatManager(
                    $redis,
                    $cache,
                    $prefixKey,
                    $antiCheatSecretKey,
                    $antiCheatNonceTtl
                );
            } catch (\Exception $e) {
                // 如果 Redis 不可用，防作弊功能自动禁用
                \think\facade\Log::warning('[Lottery] AntiCheatManager disabled due to Redis unavailable');
            }
        }

        // 注册 DrawResultBuilder
        $this->app->bind(DrawResultBuilder::class, function () use ($prefixKey, $recordThanksPrize, $enableStatistics, $antiCheatManager) {
            $cache = $this->app->make(CacheInterface::class);
            // 尝试获取 Redis 实例（如果可用）
            try {
                $redis = $this->app->make(RedisInterface::class);
            } catch (\Exception $e) {
                // Redis 不可用，传递 null，统计功能自动禁用
                $redis = null;
            }
            return new DrawResultBuilder($cache, $prefixKey, $recordThanksPrize, $redis, $enableStatistics, $antiCheatManager);
        });
        
        // 注册日志服务
        $this->app->bind(\Leo\Lottery\Service\LoggerService::class, function () use ($config) {
            $logConfig = $config['logging'] ?? [];
            return new \Leo\Lottery\Service\LoggerService(
                $logConfig['enabled'] ?? true,
                $logConfig['log_performance'] ?? true,
                $logConfig['log_audit'] ?? true,
                $logConfig['performance_threshold'] ?? 100,
                $logConfig['log_level'] ?? 'info'
            );
        });

        // 注册抽奖服务到容器
        $this->app->bind(LotteryService::class, function () use ($isTest, $prefixKey, $antiCheatManager) {
            $lockManager = $this->app->make(LockManagerInterface::class);
            $prizeSelector = $this->app->make(PrizeSelectorInterface::class);
            $stockManager = $this->app->make(StockManagerInterface::class);
            $distributionStrategy = $this->app->make(DistributionStrategyInterface::class);
            $cacheManager = $this->app->make(CacheManagerInterface::class);
            $fallbackPrizeProvider = $this->app->make(FallbackPrizeProviderInterface::class);
            $resultBuilder = $this->app->make(DrawResultBuilder::class);
            $logger = $this->app->make(\Leo\Lottery\Service\LoggerService::class);
            
            return new LotteryService(
                $lockManager,
                $prizeSelector,
                $stockManager,
                $distributionStrategy,
                $cacheManager,
                $fallbackPrizeProvider,
                $resultBuilder,
                $isTest,
                $prefixKey,
                $antiCheatManager,
                $logger
            );
        });

        // 注册缓存管理服务（保持向后兼容）
        $this->app->bind(CacheService::class, function () use ($prefixKey) {
            $redis = $this->app->make(RedisInterface::class);
            $cache = $this->app->make(CacheInterface::class);
            
            return new CacheService($cache, $redis, $prefixKey);
        });

        // 注册统计管理器
        $this->app->bind(StatisticsManager::class, function () use ($prefixKey) {
            try {
                $redis = $this->app->make(RedisInterface::class);
                return new StatisticsManager($redis, $prefixKey);
            } catch (\Exception $e) {
                // Redis 不可用时，返回 null 或抛出异常，由使用者处理
                throw new \RuntimeException('StatisticsManager requires RedisInterface', 0, $e);
            }
        });

        // 注册验证服务
        $this->app->bind(\Leo\Lottery\Service\VerificationService::class, function () use ($antiCheatManager) {
            return new \Leo\Lottery\Service\VerificationService($antiCheatManager);
        });

        // 注册健康检查服务
        $this->app->bind(\Leo\Lottery\Service\HealthCheckService::class, function () {
            // 获取 Redis 和 Cache 实例（可能为 null）
            $redis = null;
            try {
                $redis = $this->app->make(RedisInterface::class);
            } catch (\Exception $e) {
                // Redis 可能未配置
            }

            $cache = $this->app->make(CacheInterface::class);
            return new \Leo\Lottery\Service\HealthCheckService($redis, $cache);
        });
    }

    /**
     * 注册默认适配器
     * 如果用户没有手动绑定 RedisInterface 和 CacheInterface，则使用 ThinkPHP 默认实现
     * @param array $config
     */
    private function registerDefaultAdapters(array $config): void
    {
        // 检查是否已绑定 RedisInterface
        if (!$this->app->bound(RedisInterface::class)) {
            // 检查配置中是否指定了适配器
            $redisAdapter = $config['adapters']['redis'] ?? null;
            if ($redisAdapter && class_exists($redisAdapter)) {
                $this->app->bind(RedisInterface::class, $redisAdapter);
            } else {
                // 使用默认的 ThinkPHP Redis 适配器
                $this->app->bind(RedisInterface::class, ThinkRedisAdapter::class);
            }
        }

        // 检查是否已绑定 CacheInterface
        if (!$this->app->bound(CacheInterface::class)) {
            // 检查配置中是否指定了适配器
            $cacheAdapter = $config['adapters']['cache'] ?? null;
            if ($cacheAdapter && class_exists($cacheAdapter)) {
                $this->app->bind(CacheInterface::class, $cacheAdapter);
            } else {
                // 使用默认的 ThinkPHP Cache 适配器
                $this->app->bind(CacheInterface::class, ThinkCacheAdapter::class);
            }
        }
    }

    /**
     * 验证配置项的有效性
     * @param array $config
     * @throws \InvalidArgumentException
     */
    private function validateConfig(array $config): void
    {
        $configFile = 'config/lottery.php';
        
        // 验证 prefix_key
        if (isset($config['prefix_key'])) {
            if (!is_string($config['prefix_key']) || empty(trim($config['prefix_key']))) {
                throw new \InvalidArgumentException(
                    "配置文件 {$configFile} 中的配置项 'prefix_key' 必须是非空字符串。\n" .
                    "当前值: " . var_export($config['prefix_key'], true) . "\n" .
                    "示例: 'prefix_key' => 'lottery:'"
                );
            }
        }

        // 验证 hot_hours
        if (isset($config['hot_hours'])) {
            if (!is_array($config['hot_hours'])) {
                throw new \InvalidArgumentException(
                    "配置文件 {$configFile} 中的配置项 'hot_hours' 必须是数组。\n" .
                    "当前类型: " . gettype($config['hot_hours']) . "\n" .
                    "当前值: " . var_export($config['hot_hours'], true) . "\n" .
                    "示例: 'hot_hours' => [9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21]"
                );
            }
            
            // 验证每个小时值在 0-23 范围内
            foreach ($config['hot_hours'] as $index => $hour) {
                if (!is_int($hour) || $hour < 0 || $hour > 23) {
                    throw new \InvalidArgumentException(
                        "配置文件 {$configFile} 中的配置项 'hot_hours[{$index}]' 的值必须在 0-23 之间。\n" .
                        "当前值: " . var_export($hour, true) . " (类型: " . gettype($hour) . ")\n" .
                        "有效范围: 0-23（表示小时）"
                    );
                }
            }
            
            // 去重并排序（验证后处理）
            $config['hot_hours'] = array_values(array_unique($config['hot_hours']));
            sort($config['hot_hours']);
        }

        // 验证 fallback_prizes（向后兼容：也验证 virtual_prizes）
        $prizesConfig = $config['fallback_prizes'] ?? $config['virtual_prizes'] ?? null;
        $prizesConfigKey = isset($config['fallback_prizes']) ? 'fallback_prizes' : 'virtual_prizes';
        
        if ($prizesConfig !== null) {
            if (!is_array($prizesConfig)) {
                throw new \InvalidArgumentException(
                    "配置文件 {$configFile} 中的配置项 '{$prizesConfigKey}' 必须是数组。\n" .
                    "当前类型: " . gettype($prizesConfig) . "\n" .
                    "当前值: " . var_export($prizesConfig, true) . "\n" .
                    "示例: '{$prizesConfigKey}' => [['id' => 9, 'name' => '优惠券', 'type' => 100, 'weight' => 5]]\n" .
                    "或者设置为空数组: '{$prizesConfigKey}' => []"
                );
            }
            
            // 验证每个兜底奖品的格式
            foreach ($prizesConfig as $index => $prize) {
                if (!is_array($prize)) {
                    throw new \InvalidArgumentException(
                        "配置文件 {$configFile} 中的配置项 '{$prizesConfigKey}[{$index}]' 必须是数组。\n" .
                        "当前类型: " . gettype($prize) . "\n" .
                        "当前值: " . var_export($prize, true)
                    );
                }
                
                // 验证必要字段
                $requiredFields = ['id', 'name', 'type'];
                foreach ($requiredFields as $field) {
                    if (!isset($prize[$field])) {
                        throw new \InvalidArgumentException(
                            "配置文件 {$configFile} 中的配置项 '{$prizesConfigKey}[{$index}]' 缺少必要字段 '{$field}'。\n" .
                            "当前配置: " . json_encode($prize, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n" .
                            "必需字段: " . implode(', ', $requiredFields) . "\n" .
                            "示例: ['id' => 9, 'name' => '优惠券', 'type' => 100, 'weight' => 5, 'url' => 'https://...']"
                        );
                    }
                }
                
                // 验证字段类型
                if (!is_int($prize['id'])) {
                    throw new \InvalidArgumentException(
                        "配置文件 {$configFile} 中的配置项 '{$prizesConfigKey}[{$index}].id' 必须是整数。\n" .
                        "当前值: " . var_export($prize['id'], true) . " (类型: " . gettype($prize['id']) . ")"
                    );
                }
                if (!is_string($prize['name']) || empty(trim($prize['name']))) {
                    throw new \InvalidArgumentException(
                        "配置文件 {$configFile} 中的配置项 '{$prizesConfigKey}[{$index}].name' 必须是非空字符串。\n" .
                        "当前值: " . var_export($prize['name'], true) . " (类型: " . gettype($prize['name']) . ")"
                    );
                }
                if (!is_int($prize['type'])) {
                    throw new \InvalidArgumentException(
                        "配置文件 {$configFile} 中的配置项 '{$prizesConfigKey}[{$index}].type' 必须是整数（0-255）。\n" .
                        "当前值: " . var_export($prize['type'], true) . " (类型: " . gettype($prize['type']) . ")\n" .
                        "建议: 1-99=实物奖品, 100-199=虚拟奖品, 200-255=自定义类型"
                    );
                }
            }
        }

        // 验证 is_test
        if (isset($config['is_test']) && !is_bool($config['is_test'])) {
            throw new \InvalidArgumentException(
                "配置文件 {$configFile} 中的配置项 'is_test' 必须是布尔值（true 或 false）。\n" .
                "当前值: " . var_export($config['is_test'], true) . " (类型: " . gettype($config['is_test']) . ")\n" .
                "示例: 'is_test' => false"
            );
        }

        // 验证 record_thanks_prize
        if (isset($config['record_thanks_prize']) && !is_bool($config['record_thanks_prize'])) {
            throw new \InvalidArgumentException(
                "配置文件 {$configFile} 中的配置项 'record_thanks_prize' 必须是布尔值（true 或 false）。\n" .
                "当前值: " . var_export($config['record_thanks_prize'], true) . " (类型: " . gettype($config['record_thanks_prize']) . ")\n" .
                "说明: true=记录'谢谢参与'到数据库, false=不记录（使用 Redis 统计）"
            );
        }

        // 验证 enable_thanks_statistics
        if (isset($config['enable_thanks_statistics']) && !is_bool($config['enable_thanks_statistics'])) {
            throw new \InvalidArgumentException(
                "配置文件 {$configFile} 中的配置项 'enable_thanks_statistics' 必须是布尔值（true 或 false）。\n" .
                "当前值: " . var_export($config['enable_thanks_statistics'], true) . " (类型: " . gettype($config['enable_thanks_statistics']) . ")\n" .
                "说明: true=启用统计（当 record_thanks_prize=false 时）, false=不统计"
            );
        }
    }

    /**
     * 启动服务
     */
    public function boot(): void
    {
        // 发布配置文件
        $this->publishes();
    }

    /**
     * 发布配置文件
     */
    protected function publishes(): void
    {
        $configPath = $this->app->getRootPath() . 'config' . DIRECTORY_SEPARATOR . 'lottery.php';
        $packageConfigPath = __DIR__ . '/../config/lottery.php';
        
        if (!file_exists($configPath) && file_exists($packageConfigPath)) {
            if (!is_dir(dirname($configPath))) {
                mkdir(dirname($configPath), 0755, true);
            }
            copy($packageConfigPath, $configPath);
        }
    }
}
