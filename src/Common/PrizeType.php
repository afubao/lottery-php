<?php
declare(strict_types=1);

namespace Leo\Lottery\Common;

/**
 * 奖品类型常量类
 * 
 * 奖品类型是可扩展的，你可以根据需要添加新的类型。
 * 数据库字段 type 是 tinyint 类型，支持 0-255 的值。
 * 
 * 建议的类型定义：
 * - 1: 实物奖品
 * - 2: 虚拟奖品
 * - 3: 谢谢参与（虚拟奖品）
 * - 100-199: 兜底奖品类型（如：100=优惠券，101=积分，102=会员权益等）
 * - 200-255: 自定义类型（根据业务需求定义）
 */
class PrizeType
{
    /**
     * 实物奖品 - 普通实物
     */
    const PHYSICAL_NORMAL = 1;

    /**
     * 虚拟奖品 - 普通虚拟
     */
    const VIRTUAL_NORMAL = 2;

    /**
     * 兜底奖品 - 优惠券
     */
    const VIRTUAL_COUPON = 100;

    /**
     * 兜底奖品 - 积分
     */
    const VIRTUAL_POINTS = 101;

    /**
     * 兜底奖品 - 会员权益
     */
    const VIRTUAL_MEMBERSHIP = 102;

    /**
     * 兜底奖品 - 谢谢参与（可能为空，没有任何内容）
     */
    const VIRTUAL_THANKS = 3;

    /**
     * 检查是否为虚拟奖品类型
     * 
     * @param int $type 奖品类型
     * @return bool
     */
    public static function isVirtual(int $type): bool
    {
        // 虚拟奖品类型范围：2, 3, 100-199, 200-255（根据业务需求调整）
        return $type === self::VIRTUAL_NORMAL
            || $type === self::VIRTUAL_THANKS 
            || ($type >= 100 && $type <= 199)
            || ($type >= 200 && $type <= 255);
    }

    /**
     * 检查是否为实物奖品类型
     * 
     * @param int $type 奖品类型
     * @return bool
     */
    public static function isPhysical(int $type): bool
    {
        return !self::isVirtual($type);
    }

    /**
     * 获取所有虚拟奖品类型
     * 
     * @return array
     */
    public static function getVirtualTypes(): array
    {
        return [
            self::VIRTUAL_NORMAL,
            self::VIRTUAL_THANKS,
            self::VIRTUAL_COUPON,
            self::VIRTUAL_POINTS,
            self::VIRTUAL_MEMBERSHIP,
        ];
    }

    /**
     * 获取所有实物奖品类型
     * 
     * @return array
     */
    public static function getPhysicalTypes(): array
    {
        return [
            self::PHYSICAL_NORMAL,
        ];
    }

    /**
     * 获取类型名称（用于日志和调试）
     * 
     * @param int $type 奖品类型
     * @return string
     */
    public static function getName(int $type): string
    {
        $names = [
            self::PHYSICAL_NORMAL => '实物',
            self::VIRTUAL_NORMAL => '虚拟',
            self::VIRTUAL_THANKS => '谢谢参与',
            self::VIRTUAL_COUPON => '优惠券',
            self::VIRTUAL_POINTS => '积分',
            self::VIRTUAL_MEMBERSHIP => '会员权益',
        ];

        return $names[$type] ?? "未知类型({$type})";
    }
}
