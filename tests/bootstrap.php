<?php
declare(strict_types=1);

/**
 * PHPUnit 测试引导文件
 */

// 定义测试环境
define('APP_PATH', __DIR__ . '/../');
define('RUNTIME_PATH', __DIR__ . '/../runtime/');

// 加载 Composer 自动加载
require_once __DIR__ . '/../vendor/autoload.php';

// 初始化 ThinkPHP（如果需要）
// 注意：这里假设 ThinkPHP 已经通过 Composer 安装
// 如果测试不需要完整的 ThinkPHP 环境，可以注释掉以下代码

// 设置测试环境配置
if (file_exists(__DIR__ . '/config.php')) {
    $testConfig = require __DIR__ . '/config.php';
    // 可以在这里设置测试专用的配置
}
