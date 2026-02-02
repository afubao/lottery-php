<?php
declare(strict_types=1);

namespace Tests\Unit\Common;

use PHPUnit\Framework\TestCase;
use Leo\Lottery\Common\Helper;

class HelperTest extends TestCase
{
    public function testGetYmdDate(): void
    {
        $date = Helper::getYmdDate();
        $this->assertIsString($date);
        $this->assertEquals(6, strlen($date));
        $this->assertMatchesRegularExpression('/^\d{6}$/', $date);
    }

    public function testFormatYmdDate(): void
    {
        $timestamp = 1738425600; // 2025-02-02 00:00:00
        $date = Helper::formatYmdDate($timestamp);
        $this->assertEquals('250202', $date);
    }

    public function testFormatYmdDateWithNull(): void
    {
        $date = Helper::formatYmdDate(null);
        $this->assertIsString($date);
        $this->assertEquals(6, strlen($date));
    }

    public function testValidateOpenid(): void
    {
        // 有效 openid
        $this->assertTrue(Helper::validateOpenid('valid_openid_123'));
        $this->assertTrue(Helper::validateOpenid('abc123'));
        $this->assertTrue(Helper::validateOpenid('a'));
        $this->assertTrue(Helper::validateOpenid(str_repeat('a', 32)));

        // 无效 openid
        $this->assertFalse(Helper::validateOpenid(''));
        $this->assertFalse(Helper::validateOpenid(str_repeat('a', 33))); // 超过32字符
        $this->assertFalse(Helper::validateOpenid('invalid openid')); // 包含空格
        $this->assertFalse(Helper::validateOpenid('invalid@openid')); // 包含特殊字符
    }

    public function testValidateIp(): void
    {
        // 有效 IP
        $this->assertTrue(Helper::validateIp('192.168.1.1'));
        $this->assertTrue(Helper::validateIp('127.0.0.1'));
        $this->assertTrue(Helper::validateIp('::1')); // IPv6
        $this->assertTrue(Helper::validateIp('2001:0db8:85a3:0000:0000:8a2e:0370:7334')); // IPv6

        // 无效 IP
        $this->assertFalse(Helper::validateIp(''));
        $this->assertFalse(Helper::validateIp('invalid_ip'));
        $this->assertFalse(Helper::validateIp('999.999.999.999'));
    }

    public function testIsPhysicalPrize(): void
    {
        $this->assertTrue(Helper::isPhysicalPrize(1));
        $this->assertTrue(Helper::isPhysicalPrize(99));
        $this->assertFalse(Helper::isPhysicalPrize(100));
        $this->assertFalse(Helper::isPhysicalPrize(4));
    }

    public function testIsVirtualPrize(): void
    {
        $this->assertTrue(Helper::isVirtualPrize(100));
        $this->assertTrue(Helper::isVirtualPrize(4));
        $this->assertFalse(Helper::isVirtualPrize(1));
        $this->assertFalse(Helper::isVirtualPrize(99));
    }

    public function testIsThanksPrize(): void
    {
        $this->assertTrue(Helper::isThanksPrize(4));
        $this->assertFalse(Helper::isThanksPrize(1));
        $this->assertFalse(Helper::isThanksPrize(100));
    }

    public function testIsCoupon(): void
    {
        $this->assertTrue(Helper::isCoupon(100));
        $this->assertFalse(Helper::isCoupon(101));
        $this->assertFalse(Helper::isCoupon(4));
    }

    public function testGetPrizeTypeName(): void
    {
        $this->assertEquals('谢谢参与', Helper::getPrizeTypeName(4));
        $this->assertEquals('优惠券', Helper::getPrizeTypeName(100));
        $this->assertEquals('积分', Helper::getPrizeTypeName(101));
        $this->assertIsString(Helper::getPrizeTypeName(999)); // 未知类型
    }

    public function testValidateRule(): void
    {
        // 有效规则
        $validRule = [
            'prize_id' => 1,
            'weight' => 10,
            'total_num' => 100,
            'remaining_num' => 50,
        ];
        $result = Helper::validateRule($validRule);
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);

        // 无效规则 - 缺少字段
        $invalidRule = [
            'prize_id' => 1,
        ];
        $result = Helper::validateRule($invalidRule);
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);

        // 无效规则 - 类型错误
        $invalidRule2 = [
            'prize_id' => 'not_int',
            'weight' => -1,
            'total_num' => 100,
            'remaining_num' => 150, // 大于 total_num
        ];
        $result = Helper::validateRule($invalidRule2);
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    public function testValidatePrize(): void
    {
        // 有效奖品
        $validPrize = [
            'id' => 1,
            'name' => '测试奖品',
            'type' => 100,
        ];
        $result = Helper::validatePrize($validPrize);
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);

        // 无效奖品 - 缺少字段
        $invalidPrize = [
            'id' => 1,
        ];
        $result = Helper::validatePrize($invalidPrize);
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);

        // 无效奖品 - 类型错误
        $invalidPrize2 = [
            'id' => 'not_int',
            'name' => '',
            'type' => 300, // 超出范围
        ];
        $result = Helper::validatePrize($invalidPrize2);
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }
}
