<?php
declare(strict_types=1);

namespace App\Services\Log;

/**
 * 文件日志驱动
 * 异步写入文件，避免阻塞主流程
 */
class FileLogDriver implements LogDriverInterface
{
    /**
     * 日志文件目录
     */
    private readonly string $logDir;

    /**
     * 日志文件名格式（按日期分割）
     */
    private readonly string $logFileFormat;
    
    /**
     * 日志文件最大大小（100MB）
     */
    private const MAX_FILE_SIZE = 100 * 1024 * 1024;
    
    /**
     * 日志保留天数
     */
    private const RETENTION_DAYS = 30;

    /**
     * 构造函�?
     *
     * @param string $logDir 日志目录
     * @param string $logFileFormat 日志文件名格式（默认：app-{date}.log�?
     */
    public function __construct(
        string $logDir = '',
        string $logFileFormat = 'app-{date}.log'
    ) {
        $this->logDir = $logDir ?: dirname(__DIR__, 2) . '/storage/logs';
        $this->logFileFormat = $logFileFormat;

        // 确保日志目录存在
        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0755, true);
        }
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
            $logFile = $this->getLogFile();
            $logLine = $this->formatLogLine($logData);
            
            // 异步写入：使�?file_put_contents �?FILE_APPEND | LOCK_EX 标志
            // 在高并发下，LOCK_EX 确保文件写入的原子�?
            $result = file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX) !== false;
            
            // 检查是否需要轮转日志
            $this->rotateLogs();
            
            return $result;
        } catch (\Exception $e) {
            // 日志记录失败不应影响主流�?
            error_log('[FileLogDriver] 日志记录失败: ' . $e->getMessage());
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
            $logFile = $this->getLogFile();
            $logLines = [];
            
            foreach ($logDataList as $logData) {
                $logLines[] = $this->formatLogLine($logData);
            }
            
            $logContent = implode('', $logLines);
            
            // 批量写入，减少文件I/O
            $result = file_put_contents($logFile, $logContent, FILE_APPEND | LOCK_EX) !== false 
                ? count($logDataList) 
                : 0;
            
            // 检查是否需要轮转日志
            $this->rotateLogs();
            
            return $result;
        } catch (\Exception $e) {
            error_log('[FileLogDriver] 批量日志记录失败: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * 获取日志文件路径
     *
     * @return string
     */
    private function getLogFile(): string
    {
        $filename = str_replace('{date}', date('Y-m-d'), $this->logFileFormat);
        return $this->logDir . '/' . $filename;
    }

    /**
     * 格式化日志行
     *
     * @param array<string, mixed> $logData 日志数据
     * @return string
     */
    private function formatLogLine(array $logData): string
    {
        // 访客日志格式：时间戳 级别 消息 JSON数据
        // 示例：[2026-01-22 10:30:45] INFO: Visitor log {"request_id":"req_123","session_id":"sess_456",...}

        $timestamp = date('Y-m-d H:i:s', $logData['timestamp'] ?? time());
        $level = 'INFO';
        $message = 'Visitor log';

        // 构建访客日志数据
        $context = [
            'request_id' => $logData['request_id'] ?? '',
            'session_id' => $logData['session_id'] ?? '',
            'ip' => $logData['ip'] ?? '',
            'user_agent' => $logData['user_agent'] ?? '',
            'url' => $logData['url'] ?? '',
            'referrer' => $logData['referrer'] ?? '',
            'method' => $logData['method'] ?? 'GET',
            'status_code' => $logData['status_code'] ?? 200,
            'duration' => $logData['duration'] ?? 0,
            'memory_used' => $logData['memory_used'] ?? 0,
            'device_type' => $logData['device_type'] ?? 'unknown',
            'browser' => $logData['browser'] ?? 'unknown',
            'os' => $logData['os'] ?? 'unknown',
            'is_bot' => $logData['is_bot'] ?? 0,
            'timestamp' => $logData['timestamp'] ?? time(),
        ];

        $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return sprintf(
            "[%s] %s: %s %s\n",
            $timestamp,
            $level,
            $message,
            $contextJson
        );
    }
    
    /**
     * 执行日志轮转
     *
     * @return void
     */
    private function rotateLogs(): void
    {
        // 检查当前日志文件大小
        $currentLog = $this->getLogFile();
        if (file_exists($currentLog) && filesize($currentLog) > self::MAX_FILE_SIZE) {
            // 按大小轮转，添加后缀
            $suffix = 1;
            $baseFilename = pathinfo($currentLog, PATHINFO_FILENAME);
            $extension = pathinfo($currentLog, PATHINFO_EXTENSION);
            $dir = pathinfo($currentLog, PATHINFO_DIRNAME);
            
            while (file_exists($dir . '/' . $baseFilename . '_' . $suffix . '.' . $extension)) {
                $suffix++;
            }
            
            rename($currentLog, $dir . '/' . $baseFilename . '_' . $suffix . '.' . $extension);
        }
        
        // 清理过期日志文件
        $this->cleanExpiredLogs();
    }
    
    /**
     * 清理过期日志文件
     *
     * @return void
     */
    private function cleanExpiredLogs(): void
    {
        // 获取所有日志文件
        $logFiles = glob($this->logDir . '/app_*.log');
        if (empty($logFiles)) {
            return;
        }
        
        // 计算过期时间
        $expireTime = time() - self::RETENTION_DAYS * 24 * 3600;
        
        foreach ($logFiles as $logFile) {
            if (filemtime($logFile) < $expireTime) {
                @unlink($logFile);
            }
        }
    }
}
