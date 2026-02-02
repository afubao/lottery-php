<?php

use Phinx\Db\Adapter\AdapterInterface;
use think\migration\Migrator;
use think\migration\db\Column;

class CreateLotteryDrawsTable extends Migrator
{
    /**
     * Change Method.
     */
    public function change()
    {
        $table = $this->table('lottery_draws', [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => '抽奖记录表',
            'id' => true,
        ]);
        $table->addColumn(
            (new Column)
                ->setName('draws_id')
                ->setType(AdapterInterface::PHINX_TYPE_STRING)
                ->setOptions([
                    'length' => 48,  // 支持编码后的ID（通常8-16位）或随机字符串（32-48位）
                ])
                ->setNull(true)  // 允许为空，创建记录时先设为空，获取自增ID后再编码更新
                ->setComment('抽奖id（编码后的ID或随机字符串，用于用户展示和验证）')
        );
        $table->addColumn(
            (new Column)
                ->setName('openid')
                ->setType(AdapterInterface::PHINX_TYPE_STRING)
                ->setOptions([
                    'length' => 32,
                ])
                ->setNull(false)
                ->setComment('openid')
        );
        $table->addColumn(
            (new Column)
                ->setName('prize_id')
                ->setType(AdapterInterface::PHINX_TYPE_INTEGER)
                ->setNull(false)
                ->setComment('奖品id')
        );
        $table->addColumn(
            (new Column)
                ->setName('type')
                ->setType(AdapterInterface::PHINX_TYPE_TINY_INTEGER)
                ->setNull(false)
                ->setComment('奖品类型1-虚拟奖品，2-实物奖品')
        );
        $table->addColumn(
            (new Column)
                ->setName('ip')
                ->setType(AdapterInterface::PHINX_TYPE_STRING)
                ->setOptions([
                    'length' => 20,
                ])
                ->setNull(false)
                ->setComment('ip')
        );
        $table->addColumn(
            (new Column)
                ->setName('rule_id')
                ->setType(AdapterInterface::PHINX_TYPE_INTEGER)
                ->setDefault(0)
                ->setNull(false)
                ->setComment('规则id')
        );
        $table->addIndex(['openid']);
        $table->addIndex(['draws_id']);
        // 添加时间字段
        $table->addTimestamps();
        $table->create();
    }
}
