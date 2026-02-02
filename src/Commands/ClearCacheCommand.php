<?php
declare(strict_types=1);

namespace Leo\Lottery\Commands;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use Leo\Lottery\Service\CacheService;
use think\facade\App;

/**
 * 清除抽奖缓存命令
 * 
 * 使用示例：
 * php think lottery:clear-cache
 * php think lottery:clear-cache --rule=1
 * php think lottery:clear-cache --prize
 * php think lottery:clear-cache --all
 */
class ClearCacheCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('lottery:clear-cache')
            ->setDescription('清除抽奖相关缓存')
            ->addOption('rule', 'r', \think\console\input\Option::VALUE_OPTIONAL, '清除指定规则的缓存（规则ID）')
            ->addOption('prize', 'p', \think\console\input\Option::VALUE_NONE, '清除奖品缓存')
            ->addOption('all', 'a', \think\console\input\Option::VALUE_NONE, '清除所有缓存');
    }

    protected function execute(Input $input, Output $output): int
    {
        try {
            /** @var CacheService $cacheService */
            $cacheService = App::make(CacheService::class);

            $ruleId = $input->getOption('rule');
            $prize = $input->getOption('prize');
            $all = $input->getOption('all');

            if ($all) {
                $cacheService->clearAllCache();
                $output->writeln('<info>已清除所有抽奖缓存</info>');
            } elseif ($prize) {
                $cacheService->clearPrizeCache();
                $output->writeln('<info>已清除奖品缓存</info>');
            } elseif ($ruleId !== null) {
                $ruleId = (int) $ruleId;
                $cacheService->clearRuleCache($ruleId);
                $output->writeln("<info>已清除规则 {$ruleId} 的缓存</info>");
            } else {
                $cacheService->clearRuleCache();
                $output->writeln('<info>已清除规则缓存</info>');
            }

            return 0;
        } catch (\Exception $e) {
            $output->writeln('<error>清除缓存失败: ' . $e->getMessage() . '</error>');
            return 1;
        }
    }
}
