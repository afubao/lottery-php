<?php
declare(strict_types=1);

namespace Leo\Lottery\Common;

/**
 * Draw ID 编码器
 * 
 * 功能：将数据库的自增ID编码为随机字符串，用户看到的是随机ID，数据库存储的是自增ID
 * 
 * 优势：
 * 1. 数据库性能最优：使用自增ID作为主键，插入性能最好
 * 2. 安全性高：用户看到的ID是随机的，无法推断真实ID
 * 3. 可逆：可以通过编码后的ID解码得到真实ID，用于查询
 * 
 * 算法：使用 Feistel 网络 + Base62 编码
 */
class DrawIdEncoder
{
    /**
     * 编码字符集（Base62：0-9, a-z, A-Z）
     */
    private const CHARSET = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    
    /**
     * 密钥（用于Feistel网络，建议在配置中设置）
     */
    private int $key;
    
    /**
     * 最小长度（编码后的字符串最小长度）
     */
    private int $minLength;
    
    public function __construct(?int $key = null, int $minLength = 8)
    {
        // 默认密钥：使用配置或固定值（生产环境建议从配置读取）
        $this->key = $key ?? 0x12345678;
        $this->minLength = $minLength;
    }
    
    /**
     * 编码ID（将数字ID编码为随机字符串）
     * 
     * @param int $id 数据库自增ID
     * @return string 编码后的随机字符串
     */
    public function encode(int $id): string
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('ID must be greater than 0');
        }
        
        // 使用 Feistel 网络混淆ID
        $obfuscated = $this->feistelEncode($id);
        
        // 转换为 Base62 编码
        $encoded = $this->base62Encode($obfuscated);
        
        // 如果长度不足，前面补随机字符
        if (strlen($encoded) < $this->minLength) {
            $padding = $this->generatePadding($this->minLength - strlen($encoded), $id);
            $encoded = $padding . $encoded;
        }
        
        return $encoded;
    }
    
    /**
     * 解码ID（将编码后的字符串解码为数字ID）
     * 
     * @param string $encoded 编码后的字符串
     * @return int|null 解码后的ID，如果解码失败返回null
     */
    public function decode(string $encoded): ?int
    {
        if (empty($encoded)) {
            return null;
        }
        
        try {
            // 移除填充（如果长度超过最小长度）
            if (strlen($encoded) > $this->minLength) {
                // 尝试找到实际的编码部分（从后往前）
                $actualLength = $this->minLength;
                $encoded = substr($encoded, -$actualLength);
            }
            
            // Base62 解码
            $obfuscated = $this->base62Decode($encoded);
            
            if ($obfuscated === null) {
                return null;
            }
            
            // Feistel 网络解码
            $id = $this->feistelDecode($obfuscated);
            
            // 验证ID是否有效
            if ($id <= 0) {
                return null;
            }
            
            return $id;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Feistel 网络编码（可逆混淆）
     * 
     * @param int $value 原始值
     * @return int 混淆后的值
     */
    private function feistelEncode(int $value): int
    {
        // 将32位整数分为左右两部分（各16位）
        $left = ($value >> 16) & 0xFFFF;
        $right = $value & 0xFFFF;
        
        // Feistel 网络：进行多轮混淆
        for ($i = 0; $i < 3; $i++) {
            $temp = $right;
            $right = $left ^ $this->feistelFunction($right, $this->key);
            $left = $temp;
        }
        
        // 交换左右两部分
        $temp = $left;
        $left = $right;
        $right = $temp;
        
        // 合并左右两部分
        return ($left << 16) | $right;
    }
    
    /**
     * Feistel 网络解码（逆操作）
     * 
     * @param int $value 混淆后的值
     * @return int 原始值
     */
    private function feistelDecode(int $value): int
    {
        // 将32位整数分为左右两部分（各16位）
        $left = ($value >> 16) & 0xFFFF;
        $right = $value & 0xFFFF;
        
        // 交换左右两部分（编码时最后交换了）
        $temp = $left;
        $left = $right;
        $right = $temp;
        
        // Feistel 网络：进行多轮反混淆（逆序）
        for ($i = 0; $i < 3; $i++) {
            $temp = $left;
            $left = $right ^ $this->feistelFunction($left, $this->key);
            $right = $temp;
        }
        
        // 合并左右两部分
        return ($left << 16) | $right;
    }
    
    /**
     * Feistel 网络的轮函数
     * 
     * @param int $value 输入值
     * @param int $key 密钥
     * @return int 输出值
     */
    private function feistelFunction(int $value, int $key): int
    {
        // 简单的轮函数：使用XOR和位移
        return (($value ^ $key) << 1) | (($value ^ $key) >> 15);
    }
    
    /**
     * Base62 编码（将数字转换为Base62字符串）
     * 
     * @param int $number 数字
     * @return string Base62字符串
     */
    private function base62Encode(int $number): string
    {
        if ($number === 0) {
            return '0';
        }
        
        $result = '';
        $base = strlen(self::CHARSET);
        
        while ($number > 0) {
            $result = self::CHARSET[$number % $base] . $result;
            $number = intval($number / $base);
        }
        
        return $result;
    }
    
    /**
     * Base62 解码（将Base62字符串转换为数字）
     * 
     * @param string $encoded Base62字符串
     * @return int|null 数字，如果解码失败返回null
     */
    private function base62Decode(string $encoded): ?int
    {
        $number = 0;
        $base = strlen(self::CHARSET);
        $length = strlen($encoded);
        
        for ($i = 0; $i < $length; $i++) {
            $char = $encoded[$i];
            $pos = strpos(self::CHARSET, $char);
            
            if ($pos === false) {
                return null; // 无效字符
            }
            
            $number = $number * $base + $pos;
        }
        
        return $number;
    }
    
    /**
     * 生成填充字符（用于使编码后的字符串达到最小长度）
     * 
     * @param int $length 需要填充的长度
     * @param int $seed 种子（使用ID作为种子，确保相同ID的填充相同）
     * @return string 填充字符串
     */
    private function generatePadding(int $length, int $seed): string
    {
        $padding = '';
        $charsetLength = strlen(self::CHARSET);
        
        // 使用种子生成伪随机填充
        mt_srand($seed);
        for ($i = 0; $i < $length; $i++) {
            $padding .= self::CHARSET[mt_rand(0, $charsetLength - 1)];
        }
        mt_srand(); // 重置随机数种子
        
        return $padding;
    }
}
