<?php

use Phinx\Db\Adapter\AdapterInterface;
use think\migration\Migrator;
use think\migration\db\Column;

class CreateLotteryPrizesTable extends Migrator
{
    /**
     * Change Method.
     */
    public function change()
    {
        $table = $this->table('lottery_prizes', [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => '奖品表',
            'id' => true,
        ]);
        $table->addColumn(
            (new Column)
                ->setName('type')
                ->setType(AdapterInterface::PHINX_TYPE_TINY_INTEGER)
                ->setNull(false)
                ->setComment('奖品类型1-虚拟奖品，2-实物奖品')
        );
        $table->addColumn(
            (new Column)
                ->setName('name')
                ->setType(AdapterInterface::PHINX_TYPE_STRING)
                ->setOptions([
                    'length' => 100,
                ])
                ->setNull(false)
                ->setComment('奖品名称')
        );
        $table->addColumn(
            (new Column)
                ->setName('image_url')
                ->setType(AdapterInterface::PHINX_TYPE_STRING)
                ->setOptions([
                    'length' => 255,
                ])
                ->setNull(false)
                ->setComment('奖品图片')
        );
        $table->addColumn(
            (new Column)
                ->setName('url')
                ->setType(AdapterInterface::PHINX_TYPE_STRING)
                ->setDefault('')
                ->setOptions([
                    'length' => 255,
                ])
                ->setNull(false)
                ->setComment('虚拟奖品跳转地址')
        );
        $table->addColumn(
            (new Column)
                ->setName('total')
                ->setType(AdapterInterface::PHINX_TYPE_INTEGER)
                ->setUnsigned()
                ->setNull(false)
                ->setComment('总数量')
        );
        $table->addColumn(
            (new Column)
                ->setName('remaining_quantity')
                ->setType(AdapterInterface::PHINX_TYPE_INTEGER)
                ->setUnsigned()
                ->setNull(false)
                ->setComment('剩余数量')
        );
        $table->addColumn(
            (new Column)
                ->setName('weight')
                ->setType(AdapterInterface::PHINX_TYPE_INTEGER)
                ->setUnsigned()
                ->setNull(false)
                ->setComment('中奖概率权重，越大，越容易中奖')
        );
        // 添加时间字段
        $table->addTimestamps();
        $table->create();
    }
}
