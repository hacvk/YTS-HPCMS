<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\VisitorLogService;
use App\Services\VisitorStatsService;
use App\Services\AnomalyDetectionService;

/**
 * 访客日志聚合命令
 *
 * 安全特性:
 * - 参数化查询
 * - SQL注入防护
 * - 异常隔离: 聚合失败不影响主流程
 *
 * 性能特性:
 * - 批量处理
 * - 流式读取
 * - 内存控制
 */
class VisitorAggregate
{
    /**
     * 命令签名
     */
    protected $signature = 'visitor:aggregate {--date= : 统计日期} {--force : 强制重新聚合}';

    /**
     * 命令描述
     */
    protected $description = '聚合访客日志并更新统计';

    /**
     * 批量处理大小
     */
    private const BATCH_SIZE = 1000;

    /**
     * 内存限制 (MB)
     */
    private const MEMORY_LIMIT = 100;

    /**
     * 执行命令
     *
     * 安全特性:
     * - 参数验证
     * - 异常隔离
     *
     * @return int 退出码
     */
    public function handle(): int
    {
        $date = $this->option('date') ?? date('Y-m-d', strtotime('-1 day'));
        $force = $this->option('force') ?? false;

        // 验证日期格式
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->error("无效的日期格式: {$date}");
            return 1;
        }

        $this->info("开始聚合访客日志，日期: {$date}");

        try {
            // 1. 读取日志文件
            $logFile = $this->getLogFile($date);
            if (!file_exists($logFile)) {
                $this->warning("日志文件不存在: {$logFile}");
                return 0;
            }

            // 2. 检查是否已聚合（除非强制）
            if (!$force) {
                $db = \App\Core\Database::get();
                $existing = $db->get('visitor_stats_daily', '*', ['date' => $date]);
                if ($existing) {
                    $this->info("该日期的统计已存在，跳过聚合");
                    return 0;
                }
            }

            // 3. 解析日志并写入数据库
            $count = $this->processLogFile($logFile);
            $this->info("处理 {$count} 条日志记录");

            // 4. 更新每日统计
            $result = VisitorStatsService::updateDailyStats($date);
            if ($result) {
                $this->info("每日统计更新成功");
            } else {
                $this->error("每日统计更新失败");
                return 1;
            }

            // 5. 检测异常行为
            $anomalies = AnomalyDetectionService::detectAnomalies($date);
            $anomalyCount = 0;
            foreach ($anomalies as $type => $items) {
                $anomalyCount += count($items);
            }
            $this->info("检测到 {$anomalyCount} 个异常");

            // 6. 清理过期数据
            $this->cleanupOldData();

            $this->info("聚合完成");
            return 0;

        } catch (\Exception $e) {
            $this->error("聚合失败: " . $e->getMessage());
            error_log("[VisitorAggregate] 聚合失败: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * 处理日志文件
     *
     * 安全特性:
     * - 流式读取，避免内存溢出
     * - 异常隔离
     *
     * @param string $logFile
     * @return int 处理的记录数
     */
    private function processLogFile(string $logFile): int
    {
        $db = \App\Core\Database::get();
        $count = 0;
        $batch = [];

        $handle = fopen($logFile, 'r');

        try {
            while (($line = fgets($handle)) !== false) {
                // 检查内存使用
                if (memory_get_usage() / 1024 / 1024 > self::MEMORY_LIMIT) {
                    $this->warning("内存使用超过限制，强制垃圾回收");
                    gc_collect_cycles();
                }

                $data = $this->parseLogLine($line);

                if ($data) {
                    $batch[] = $data;
                    $count++;

                    // 批量插入
                    if (count($batch) >= self::BATCH_SIZE) {
                        $this->insertBatch($batch);
                        $batch = [];
                    }
                }
            }

            // 插入剩余数据
            if (!empty($batch)) {
                $this->insertBatch($batch);
            }
        } finally {
            fclose($handle);
        }

        return $count;
    }

    /**
     * 解析日志行
     *
     * 安全特性:
     * - 数据清理
     * - 长度限制
     *
     * @param string $line
     * @return array|null
     */
    private function parseLogLine(string $line): ?array
    {
        // 解析JSON格式的日志
        // 格式：[2026-01-22 10:30:45] INFO: Visitor log {"request_id":"req_123",...}
        // 按空格分割，JSON数据在最后一个部分

        // 查找JSON数据的起始位置（最后一个{）
        $jsonStart = strrpos($line, '{');
        if ($jsonStart === false) {
            return null;
        }

        // 提取JSON部分
        $jsonPart = substr($line, $jsonStart);
        $data = json_decode(trim($jsonPart), true);

        if (!$data) {
            return null;
        }

        // 数据清理
        return [
            'request_id' => $this->sanitizeString($data['request_id'] ?? '', 50),
            'session_id' => $this->sanitizeString($data['session_id'] ?? '', 50),
            'ip' => $this->sanitizeString($data['ip'] ?? '', 45),
            'user_agent' => $this->sanitizeString($data['user_agent'] ?? '', 500),
            'url' => $this->sanitizeString($data['url'] ?? '', 500),
            'referrer' => $this->sanitizeString($data['referrer'] ?? '', 500),
            'method' => $this->sanitizeString($data['method'] ?? 'GET', 10),
            'status_code' => (int)($data['status_code'] ?? 200),
            'duration' => (float)($data['duration'] ?? 0),
            'memory_used' => (float)($data['memory_used'] ?? 0),
            'device_type' => $this->sanitizeString($data['device_type'] ?? 'unknown', 20),
            'browser' => $this->sanitizeString($data['browser'] ?? 'unknown', 50),
            'os' => $this->sanitizeString($data['os'] ?? 'unknown', 50),
            'is_bot' => (int)($data['is_bot'] ?? 0),
            'timestamp' => (int)($data['timestamp'] ?? time()),
        ];
    }

    /**
     * 清理字符串中的控制字符
     *
     * 安全特性:
     * - 移除不可打印字符
     * - 防止注入攻击
     * - 限制长度
     *
     * @param string $str
     * @param int $maxLength 最大长度
     * @return string
     */
    private function sanitizeString(string $str, int $maxLength = 500): string
    {
        // 移除控制字符 (ASCII 0-31, 127)
        $str = preg_replace('/[\x00-\x1F\x7F]/', '', $str);

        // 移除Unicode控制字符
        $str = preg_replace('/\p{C}/u', '', $str);

        // 限制长度
        if (mb_strlen($str) > $maxLength) {
            $str = mb_substr($str, 0, $maxLength);
        }

        return $str;
    }

    /**
     * 批量插入
     *
     * 安全特性:
     * - 参数化查询
     * - SQL注入防护
     * - 事务处理
     *
     * @param array $batch
     * @return void
     */
    private function insertBatch(array $batch): void
    {
        $db = \App\Core\Database::get();

        try {
            $db->action(function ($db) use ($batch) {
                foreach ($batch as $data) {
                    $db->insert('visitor_logs', $data);
                }
            });
        } catch (\Exception $e) {
            error_log("[VisitorAggregate] 批量插入失败: " . $e->getMessage());
        }
    }

    /**
     * 获取日志文件路径
     *
     * @param string $date
     * @return string
     */
    private function getLogFile(string $date): string
    {
        $logDir = dirname(__DIR__, 3) . '/storage/logs/visitor';
        return $logDir . '/visitor-' . $date . '.log';
    }

    /**
     * 清理过期数据
     *
     * 安全特性:
     * - 参数化查询
     * - SQL注入防护
     * - 数据生命周期管理
     *
     * @return void
     */
    private function cleanupOldData(): void
    {
        $db = \App\Core\Database::get();

        try {
            // 清理visitor_logs (30天)
            $deletedLogs = VisitorLogService::cleanExpiredLogs(30);
            if ($deletedLogs > 0) {
                $this->info("清理 {$deletedLogs} 条过期日志");
            }

            // 清理visitor_sessions (30天)
            $deletedSessions = VisitorLogService::cleanExpiredSessions(30);
            if ($deletedSessions > 0) {
                $this->info("清理 {$deletedSessions} 条过期会话");
            }

            // 清理visitor_alerts (90天)
            $deletedAlerts = AnomalyDetectionService::cleanExpiredAlerts(90);
            if ($deletedAlerts > 0) {
                $this->info("清理 {$deletedAlerts} 条过期告警");
            }

            // 清理分区 (3个月前)
            $deletedPartitions = VisitorLogService::cleanPartitionedLogs();
            if ($deletedPartitions > 0) {
                $this->info("清理 {$deletedPartitions} 个旧分区");
            }

        } catch (\Exception $e) {
            $this->warning("清理过期数据失败: " . $e->getMessage());
            error_log("[VisitorAggregate] 清理过期数据失败: " . $e->getMessage());
        }
    }

    /**
     * 获取数据库统计信息
     *
     * @return void
     */
    public function stats(): void
    {
        try {
            $stats = VisitorLogService::getDatabaseStats();

            $this->info("数据库统计信息:");
            $this->info("  visitor_logs: {$stats['visitor_logs']} 条");
            $this->info("  visitor_stats_daily: {$stats['visitor_stats_daily']} 条");
            $this->info("  visitor_sessions: {$stats['visitor_sessions']} 条");
            $this->info("  visitor_alerts: {$stats['visitor_alerts']} 条");

            if (!empty($stats['table_sizes'])) {
                $this->info("  表大小:");
                foreach ($stats['table_sizes'] as $table) {
                    $this->info("    {$table['table_name']}: {$table['size_mb']} MB");
                }
            }

        } catch (\Exception $e) {
            $this->error("获取统计信息失败: " . $e->getMessage());
        }
    }
}
