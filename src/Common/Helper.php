<?php
declare(strict_types=1);

namespace Leo\Lottery\Common;

use Leo\Lottery\Common\PrizeType;

/**
 * 抽奖组件工具类
 * 
 * 提供常用的辅助方法
 */
class Helper
{
    /**
     * 获取当前日期的 ymd 格式字符串（如：250201）
     * @return string
     */
    public static function getYmdDate(): string
    {
        return date('ymd');
    }

    /**
     * 将日期转换为 ymd 格式字符串
     * @param int|null $timestamp 时间戳，如果为 null 则使用当前时间
     * @return string
     */
    public static function formatYmdDate(?int $timestamp = null): string
    {
        if ($timestamp === null) {
            $timestamp = time();
        }
        return date('ymd', $timestamp);
    }

    /**
     * 验证 openid 格式
     * @param string $openid
     * @return bool
     */
    public static function validateOpenid(string $openid): bool
    {
        if (empty($openid)) {
            return false;
        }
        
        $length = mb_strlen($openid, 'UTF-8');
        if ($length < 1 || $length > 32) {
            return false;
        }
        
        return (bool) preg_match('/^[a-zA-Z0-9_-]+$/', $openid);
    }

    /**
     * 验证 IP 地址格式
     * @param string $ip
     * @return bool
     */
    public static function validateIp(string $ip): bool
    {
        if (empty($ip)) {
            return false;
        }
        
        $length = strlen($ip);
        if ($length > 45) {
            return false;
        }
        
        return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6);
    }

    /**
     * 判断是否为实物奖品
     * @param int $type 奖品类型
     * @return bool
     */
    public static function isPhysicalPrize(int $type): bool
    {
        return PrizeType::isPhysical($type);
    }

    /**
     * 判断是否为虚拟奖品
     * @param int $type 奖品类型
     * @return bool
     */
    public static function isVirtualPrize(int $type): bool
    {
        return PrizeType::isVirtual($type);
    }

    /**
     * 判断是否为谢谢参与
     * @param int $type 奖品类型
     * @return bool
     */
    public static function isThanksPrize(int $type): bool
    {
        return $type === PrizeType::VIRTUAL_THANKS;
    }

    /**
     * 判断是否为优惠券
     * @param int $type 奖品类型
     * @return bool
     */
    public static function isCoupon(int $type): bool
    {
        return $type === PrizeType::VIRTUAL_COUPON;
    }

    /**
     * 获取奖品类型名称
     * @param int $type 奖品类型
     * @return string
     */
    public static function getPrizeTypeName(int $type): string
    {
        return PrizeType::getName($type);
    }

    /**
     * 验证规则数据格式
     * @param array $rule 规则数据
     * @return array 返回 ['valid' => bool, 'errors' => array]
     */
    public static function validateRule(array $rule): array
    {
        $errors = [];
        
        // 验证必要字段
        $requiredFields = ['prize_id', 'weight', 'total_num', 'remaining_num'];
        foreach ($requiredFields as $field) {
            if (!isset($rule[$field])) {
                $errors[] = "缺少必要字段: {$field}";
            }
        }
        
        // 验证字段类型
        if (isset($rule['prize_id']) && !is_int($rule['prize_id'])) {
            $errors[] = "prize_id 必须是整数";
        }
        
        if (isset($rule['weight']) && (!is_numeric($rule['weight']) || $rule['weight'] < 0)) {
            $errors[] = "weight 必须是非负数";
        }
        
        if (isset($rule['total_num']) && (!is_int($rule['total_num']) || $rule['total_num'] < 0)) {
            $errors[] = "total_num 必须是非负整数";
        }
        
        if (isset($rule['remaining_num']) && (!is_int($rule['remaining_num']) || $rule['remaining_num'] < 0)) {
            $errors[] = "remaining_num 必须是非负整数";
        }
        
        if (isset($rule['total_num']) && isset($rule['remaining_num']) && $rule['remaining_num'] > $rule['total_num']) {
            $errors[] = "remaining_num 不能大于 total_num";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * 验证奖品数据格式
     * @param array $prize 奖品数据
     * @return array 返回 ['valid' => bool, 'errors' => array]
     */
    public static function validatePrize(array $prize): array
    {
        $errors = [];
        
        // 验证必要字段
        $requiredFields = ['id', 'name', 'type'];
        foreach ($requiredFields as $field) {
            if (!isset($prize[$field])) {
                $errors[] = "缺少必要字段: {$field}";
            }
        }
        
        // 验证字段类型
        if (isset($prize['id']) && !is_int($prize['id'])) {
            $errors[] = "id 必须是整数";
        }
        
        if (isset($prize['name']) && (!is_string($prize['name']) || empty(trim($prize['name'])))) {
            $errors[] = "name 必须是非空字符串";
        }
        
        if (isset($prize['type']) && (!is_int($prize['type']) || $prize['type'] < 0 || $prize['type'] > 255)) {
            $errors[] = "type 必须是 0-255 之间的整数";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}
