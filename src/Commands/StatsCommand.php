<?php
declare(strict_types=1);

namespace Leo\Lottery\Commands;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use Leo\Lottery\Manager\StatisticsManager;
use think\facade\App;

/**
 * 查看抽奖统计命令
 * 
 * 使用示例：
 * php think lottery:stats
 * php think lottery:stats --date=250201
 */
class StatsCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('lottery:stats')
            ->setDescription('查看抽奖统计信息')
            ->addOption('date', 'd', \think\console\input\Option::VALUE_OPTIONAL, '指定日期（ymd格式，如：250201）');
    }

    protected function execute(Input $input, Output $output): int
    {
        try {
            $date = $input->getOption('date');
            if ($date === null) {
                $date = date('ymd');
            }

            /** @var StatisticsManager $statsManager */
            $statsManager = App::make(StatisticsManager::class);

            $stats = $statsManager->getThanksPrizeStats($date);

            $output->writeln("<info>日期: {$date}</info>");
            $output->writeln("<info>谢谢参与数量: {$stats}</info>");

            return 0;
        } catch (\Exception $e) {
            $output->writeln('<error>获取统计信息失败: ' . $e->getMessage() . '</error>');
            return 1;
        }
    }
}
