<?php

use Phinx\Db\Adapter\AdapterInterface;
use think\migration\Migrator;
use think\migration\db\Column;

class CreatePrizeRuleTable extends Migrator
{
    /**
     * Change Method.
     */
    public function change()
    {
        $table = $this->table('prize_rule', [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => '奖品规则表',
            'id' => true,
        ]);
        $table->addColumn(
            (new Column)
                ->setName('prize_id')
                ->setType(AdapterInterface::PHINX_TYPE_INTEGER)
                ->setDefault(0)
                ->setNull(false)
                ->setComment('奖品id')
        );
        $table->addColumn(
            (new Column)
                ->setName('total_num')
                ->setType(AdapterInterface::PHINX_TYPE_INTEGER)
                ->setDefault(0)
                ->setUnsigned()
                ->setNull(false)
                ->setComment('总发放数量')
        );
        $table->addColumn(
            (new Column)
                ->setName('surplus_num')
                ->setType(AdapterInterface::PHINX_TYPE_INTEGER)
                ->setDefault(0)
                ->setUnsigned()
                ->setNull(false)
                ->setComment('剩余数量')
        );
        $table->addColumn(
            (new Column)
                ->setName('weight')
                ->setType(AdapterInterface::PHINX_TYPE_INTEGER)
                ->setDefault(0)
                ->setUnsigned()
                ->setNull(false)
                ->setComment('权重')
        );
        $table->addColumn(
            (new Column)
                ->setName('start_time')
                ->setType(AdapterInterface::PHINX_TYPE_DATETIME)
                ->setNull(false)
                ->setComment('开始时间')
        );
        $table->addColumn(
            (new Column)
                ->setName('end_time')
                ->setType(AdapterInterface::PHINX_TYPE_DATETIME)
                ->setNull(false)
                ->setComment('截止时间')
        );
        // 添加时间字段
        $table->addTimestamps();
        $table->create();
    }
}
