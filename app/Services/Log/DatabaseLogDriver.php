<?php
declare(strict_types=1);

namespace App\Services\Log;

use App\Core\Database;
use Medoo\Medoo;

/**
 * 数据库日志驱动（保留原有功能，用于兼容）
 * 注意：在高并发场景下，建议使�?FileLogDriver �?RedisLogDriver
 */
class DatabaseLogDriver implements LogDriverInterface
{
    /**
     * 日志表名
     */
    private const TABLE_NAME = 'sys_log';

    /**
     * 数据库实�?
     */
    private readonly Medoo $db;

    /**
     * 构造函�?
     *
     * @param Medoo|null $db 数据库实例（如果为null，则使用 Database::get()�?
     */
    public function __construct(?Medoo $db = null)
    {
        $this->db = $db ?? Database::get();
    }

    /**
     * 记录日志
     *
     * @param array<string, mixed> $logData 日志数据
     * @return bool 是否记录成功
     */
    public function record(array $logData): bool
    {
        try {
            $result = $this->db->insert(self::TABLE_NAME, $logData);
            return $result !== false;
        } catch (\Exception $e) {
            // 日志记录失败不应影响主流�?
            error_log('[DatabaseLogDriver] 日志记录失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 批量记录日志
     *
     * @param array<int, array<string, mixed>> $logDataList 日志数据列表
     * @return int 成功记录的数�?
     */
    public function recordBatch(array $logDataList): int
    {
        if (empty($logDataList)) {
            return 0;
        }

        try {
            $successCount = 0;
            // 使用事务批量插入，提高性能
            $this->db->action(function ($db) use ($logDataList, &$successCount) {
                foreach ($logDataList as $logData) {
                    $result = $db->insert(self::TABLE_NAME, $logData);
                    if ($result !== false) {
                        $successCount++;
                    }
                }
            });
            
            return $successCount;
        } catch (\Exception $e) {
            error_log('[DatabaseLogDriver] 批量日志记录失败: ' . $e->getMessage());
            return 0;
        }
    }
}
