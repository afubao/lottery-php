<?php
declare(strict_types=1);

namespace Leo\Lottery\Contracts;

/**
 * Redis Pipeline 接口
 */
interface PipelineInterface
{
    /**
     * 添加命令到管道
     * @param string $method
     * @param array $args
     * @return $this
     */
    public function __call(string $method, array $args);

    /**
     * 执行管道
     * @return array
     */
    public function execute(): array;
}
