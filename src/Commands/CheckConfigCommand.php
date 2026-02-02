<?php
declare(strict_types=1);

namespace Leo\Lottery\Commands;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use Leo\Lottery\Service\HealthCheckService;
use Leo\Lottery\Contracts\RedisInterface;
use Leo\Lottery\Contracts\CacheInterface;
use think\facade\App;

/**
 * 检查配置命令
 * 
 * 使用示例：
 * php think lottery:check
 * php think lottery:check --quick
 */
class CheckConfigCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('lottery:check')
            ->setDescription('检查抽奖组件配置和依赖')
            ->addOption('quick', 'q', \think\console\input\Option::VALUE_NONE, '快速检查（只检查关键依赖）');
    }

    protected function execute(Input $input, Output $output): int
    {
        try {
            $quick = $input->getOption('quick');

            // 获取 Redis 和 Cache 实例（可能为 null）
            $redis = null;
            try {
                $redis = App::make(RedisInterface::class);
            } catch (\Exception $e) {
                // Redis 可能未配置
            }

            $cache = App::make(CacheInterface::class);

            /** @var HealthCheckService $healthCheck */
            $healthCheck = new HealthCheckService($redis, $cache);

            if ($quick) {
                $ok = $healthCheck->quickCheck();
                if ($ok) {
                    $output->writeln('<info>快速检查通过</info>');
                    return 0;
                } else {
                    $output->writeln('<error>快速检查失败</error>');
                    return 1;
                }
            } else {
                $result = $healthCheck->check();

                $output->writeln("<info>检查时间: {$result['timestamp']}</info>");
                $output->writeln("<info>总体状态: {$result['status']}</info>");
                $output->writeln('');

                foreach ($result['checks'] as $name => $check) {
                    $status = $check['ok'] ? '<info>✓</info>' : '<error>✗</error>';
                    $output->writeln("{$status} {$name}: {$check['message']}");
                }

                return $result['status'] === 'ok' ? 0 : 1;
            }
        } catch (\Exception $e) {
            $output->writeln('<error>检查失败: ' . $e->getMessage() . '</error>');
            return 1;
        }
    }
}
