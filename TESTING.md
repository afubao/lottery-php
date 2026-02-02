# 单元测试说明

## 概述

抽奖组件使用 PHPUnit 进行单元测试，确保代码质量和功能正确性。

## 环境要求

- PHP >= 8.0.0
- PHPUnit >= 11.5
- Composer

## 安装测试依赖

```bash
composer install --dev
```

## 运行测试

### 运行所有测试

```bash
vendor/bin/phpunit
```

### 运行特定测试套件

```bash
# 运行单元测试
vendor/bin/phpunit tests/Unit

# 运行集成测试
vendor/bin/phpunit tests/Integration
```

### 运行特定测试类

```bash
vendor/bin/phpunit tests/Unit/Common/DrawIdEncoderTest.php
```

### 运行特定测试方法

```bash
vendor/bin/phpunit --filter testEncodeDecode tests/Unit/Common/DrawIdEncoderTest.php
```

## 测试覆盖率

### 生成覆盖率报告

```bash
vendor/bin/phpunit --coverage-html coverage/
```

### 查看覆盖率

打开 `coverage/index.html` 查看详细的覆盖率报告。

## 测试目录结构

```
tests/
├── Unit/              # 单元测试
│   ├── Service/      # 服务类测试
│   ├── Manager/      # 管理器测试
│   ├── Selector/     # 选择器测试
│   ├── Strategy/     # 策略测试
│   ├── Common/        # 工具类测试
│   ├── Models/        # 模型测试
│   └── Security/      # 安全类测试
├── Integration/       # 集成测试
├── Mocks/            # Mock 类
└── bootstrap.php     # 测试引导文件
```

## Mock 类

### MockRedis

模拟 Redis 接口，用于测试：

```php
use Tests\Mocks\MockRedis;

$redis = new MockRedis();
$redis->set('key', 'value');
$value = $redis->get('key');
```

### MockCache

模拟 Cache 接口，用于测试：

```php
use Tests\Mocks\MockCache;

$cache = new MockCache();
$cache->set('key', 'value');
$value = $cache->get('key');
```

## 编写测试

### 测试类结构

```php
<?php
declare(strict_types=1);

namespace Tests\Unit\YourNamespace;

use PHPUnit\Framework\TestCase;
use Your\Class\ToTest;

class YourClassTest extends TestCase
{
    private YourClass $instance;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->instance = new YourClass();
    }
    
    public function testSomeMethod(): void
    {
        $result = $this->instance->someMethod();
        $this->assertNotNull($result);
    }
}
```

### 常用断言

```php
// 相等断言
$this->assertEquals($expected, $actual);
$this->assertNotEquals($expected, $actual);

// 类型断言
$this->assertInstanceOf(SomeClass::class, $object);
$this->assertIsString($value);
$this->assertIsInt($value);
$this->assertIsArray($value);

// 布尔断言
$this->assertTrue($condition);
$this->assertFalse($condition);

// null 断言
$this->assertNull($value);
$this->assertNotNull($value);

// 异常断言
$this->expectException(\InvalidArgumentException::class);
$this->expectExceptionMessage('Error message');
```

### 使用 Mock 对象

```php
use PHPUnit\Framework\MockObject\MockObject;

$mock = $this->createMock(SomeInterface::class);
$mock->method('someMethod')
    ->willReturn('expected_value');

$service = new YourService($mock);
```

## 测试用例示例

### DrawIdEncoder 测试

```php
public function testEncodeDecode(): void
{
    $id = 12345;
    $encoded = $this->encoder->encode($id);
    $decoded = $this->encoder->decode($encoded);
    $this->assertEquals($id, $decoded);
}
```

### WeightedPrizeSelector 测试

```php
public function testSelectWithMultipleRules(): void
{
    $rules = [
        ['id' => 1, 'weight' => 10, ...],
        ['id' => 2, 'weight' => 20, ...],
    ];
    
    $selected = $this->selector->select($rules);
    $this->assertInstanceOf(PrizeRule::class, $selected);
    $this->assertContains($selected->id, [1, 2]);
}
```

## 测试最佳实践

1. **测试命名**: 使用描述性的测试方法名，如 `testEncodeDecode`、`testSelectWithEmptyRules`
2. **单一职责**: 每个测试方法只测试一个功能点
3. **独立性**: 测试之间应该相互独立，不依赖执行顺序
4. **Mock 使用**: 使用 Mock 对象隔离外部依赖
5. **边界测试**: 测试边界值和异常情况
6. **覆盖率**: 追求合理的覆盖率，但不盲目追求100%

## 持续集成

### GitHub Actions 示例

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.0'
      - name: Install dependencies
        run: composer install --dev
      - name: Run tests
        run: vendor/bin/phpunit
```

## 常见问题

### Q: 如何测试需要数据库的代码？

A: 使用测试数据库或内存数据库（如 SQLite），在 `setUp` 中初始化数据，在 `tearDown` 中清理。

### Q: 如何测试异步操作？

A: 使用 PHPUnit 的异步测试支持，或使用 Mock 对象模拟异步行为。

### Q: 如何测试私有方法？

A: 通常不需要直接测试私有方法，通过测试公有方法来间接测试。如果必须测试，可以使用反射。

## 测试覆盖率目标

- 核心服务类: > 80%
- 核心算法: > 90%
- 工具类: > 95%
- 模型类: > 70%

## 相关文档

- [PHPUnit 文档](https://phpunit.de/documentation.html)
- [ThinkPHP 测试文档](https://www.kancloud.cn/manual/thinkphp6_0/1037601)
