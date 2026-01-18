<?php
declare(strict_types=1);

namespace App\Services\Log;

/**
 * 日志驱动接口
 * 支持多种日志存储方式：文件、Redis队列、Monolog�?
 */
interface LogDriverInterface
{
    /**
     * 记录日志
     *
     * @param array<string, mixed> $logData 日志数据
     * @return bool 是否记录成功
     */
    public function record(array $logData): bool;

    /**
     * 批量记录日志
     *
     * @param array<int, array<string, mixed>> $logDataList 日志数据列表
     * @return int 成功记录的数�?
     */
    public function recordBatch(array $logDataList): int;
}
