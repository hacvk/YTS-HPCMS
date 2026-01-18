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
            return file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX) !== false;
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
            return file_put_contents($logFile, $logContent, FILE_APPEND | LOCK_EX) !== false 
                ? count($logDataList) 
                : 0;
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
        $timestamp = $logData['create_time'] ?? date('Y-m-d H:i:s');
        $level = $logData['level'] ?? 'info';
        $message = $logData['message'] ?? '';
        $context = [
            'admin_id' => $logData['admin_id'] ?? 0,
            'admin_name' => $logData['admin_name'] ?? 'system',
            'action' => $logData['action'] ?? '',
            'target' => $logData['target'] ?? null,
            'ip' => $logData['ip'] ?? '',
            'user_agent' => $logData['user_agent'] ?? '',
        ];

        // 如果有异常堆栈，添加到上下文
        if (isset($logData['exception_trace'])) {
            $context['exception_trace'] = $logData['exception_trace'];
        }
        if (isset($logData['file'])) {
            $context['file'] = $logData['file'];
        }
        if (isset($logData['line'])) {
            $context['line'] = $logData['line'];
        }

        $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        return sprintf(
            "[%s] %s: %s %s\n",
            $timestamp,
            strtoupper($level),
            $message,
            $contextJson
        );
    }
}
