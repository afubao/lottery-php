<?php
declare(strict_types=1);

namespace Tests\Unit\Common;

use PHPUnit\Framework\TestCase;
use Leo\Lottery\Common\DrawIdEncoder;

class DrawIdEncoderTest extends TestCase
{
    private DrawIdEncoder $encoder;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->encoder = new DrawIdEncoder(0x12345678, 8);
    }
    
    public function testEncodeDecode(): void
    {
        $id = 12345;
        $encoded = $this->encoder->encode($id);
        
        $this->assertNotEmpty($encoded);
        $this->assertIsString($encoded);
        $this->assertGreaterThanOrEqual(8, strlen($encoded));
        
        $decoded = $this->encoder->decode($encoded);
        $this->assertEquals($id, $decoded);
    }
    
    public function testEncodeUniqueness(): void
    {
        $id = 12345;
        $encoded1 = $this->encoder->encode($id);
        $encoded2 = $this->encoder->encode($id);
        
        // 编码应该是相同的（相同ID应该产生相同编码）
        $this->assertEquals($encoded1, $encoded2);
    }
    
    public function testDecodeInvalidString(): void
    {
        $decoded = $this->encoder->decode('invalid_string');
        $this->assertNull($decoded);
    }
    
    public function testDecodeEmptyString(): void
    {
        $decoded = $this->encoder->decode('');
        $this->assertNull($decoded);
    }
    
    public function testEncodeMinId(): void
    {
        $id = 1;
        $encoded = $this->encoder->encode($id);
        $decoded = $this->encoder->decode($encoded);
        $this->assertEquals($id, $decoded);
    }
    
    public function testEncodeLargeId(): void
    {
        $id = 999999999;
        $encoded = $this->encoder->encode($id);
        $decoded = $this->encoder->decode($encoded);
        $this->assertEquals($id, $decoded);
    }
    
    public function testEncodeThrowsExceptionForZeroId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->encoder->encode(0);
    }
    
    public function testEncodeThrowsExceptionForNegativeId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->encoder->encode(-1);
    }
    
    public function testMinLength(): void
    {
        $encoder = new DrawIdEncoder(0x12345678, 10);
        $id = 12345;
        $encoded = $encoder->encode($id);
        
        $this->assertGreaterThanOrEqual(10, strlen($encoded));
    }
}
