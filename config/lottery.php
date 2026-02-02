<?php

return [
    // Redis 前缀键
    'prefix_key' => 'lottery:',
    
    // 是否测试环境（测试环境跳过奖品发放上限检查）
    'is_test' => false,
    
    // 是否记录"谢谢参与"到数据库
    // true: 记录（默认，保持向后兼容）
    // false: 不记录，只返回结果，避免产生大量无意义记录
    // 注意：配置的兜底奖品（id>0）总是会记录，不受此配置影响
    'record_thanks_prize' => true,
    
    // 是否启用"谢谢参与"统计（当 record_thanks_prize=false 时）
    // true: 使用 Redis 计数器统计（默认）
    // false: 不统计
    // 注意：当 record_thanks_prize=true 时，此配置无效（因为已记录到数据库）
    'enable_thanks_statistics' => true,
    
    // 流量峰值小时（0-23），峰值时段按照总量的100%发放，非峰值时段按照总量的20%发放
    'hot_hours' => [9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21],
    
    // 兜底奖品配置
    // 当没有实物奖品可抽或未中奖时，会从兜底奖品中随机抽取
    // 
    // 重要说明：
    // - 如果不配置或配置为空数组 []，将自动返回空的"谢谢参与"（id=0, name='谢谢参与', type=4, url=''）
    // - 这是默认的兜底行为，确保每次抽奖都有结果
    // - 如果配置了兜底奖品，会从配置中按权重随机选择
    // 
    // 数量无限制，可以通过概率控制发放的数量多少
    // 奖品类型支持 0-255，建议：1-99=实物，100-199=虚拟，200-255=自定义
    // 详见 PrizeType 类和 README.md 中的"奖品类型扩展"章节
    // 
    // 向后兼容：仍支持 virtual_prizes 配置，但建议使用 fallback_prizes
    'fallback_prizes' => [
        [
            'id' => 9,
            'name' => '优惠券', // 奖品名称
            'url' => 'https://www.baidu.com', // 兜底奖品跳转链接
            'weight' => 5, // 权重
            'type' => 100, // 奖品类型（100=优惠券，详见 PrizeType::VIRTUAL_COUPON）
        ],
        [
            'id' => 10,
            'name' => '哈啰组合优惠券包',
            'url' => 'https://www.baidu.com',
            'weight' => 5,
            'type' => 100, // 奖品类型（100=优惠券）
        ],
    ],
    
    // 防作弊配置
    'anti_cheat' => [
        // 是否启用防作弊功能
        'enabled' => false,
        
        // 签名密钥（用于生成和验证抽奖结果签名，防止结果被篡改）
        // 建议使用随机字符串，长度至少32字符
        // 如果为空，则不生成签名
        'secret_key' => '',
        
        // nonce 过期时间（秒），默认5分钟
        'nonce_ttl' => 300,
    ],
    
    // Draw ID 编码配置
    'draw_id_encoder' => [
        // 是否启用ID编码（推荐启用）
        // true: 数据库存储自增ID，用户看到编码后的随机ID（性能最优 + 安全性高）
        // false: 使用随机字符串作为draws_id（向后兼容）
        'enabled' => true,
        
        // 编码密钥（用于Feistel网络，建议使用随机整数）
        // 如果为空，使用默认值（生产环境建议设置）
        'key' => null,
        
        // 编码后的最小长度（默认8位）
        'min_length' => 8,
    ],
    
    // 日志配置
    'logging' => [
        // 是否启用日志
        'enabled' => true,
        
        // 是否记录性能日志
        'log_performance' => true,
        
        // 是否记录审计日志
        'log_audit' => true,
        
        // 性能日志阈值（毫秒），超过此阈值的操作会记录性能日志
        'performance_threshold' => 100,
        
        // 默认日志级别（info、warning、error、debug）
        'log_level' => 'info',
    ],
];
