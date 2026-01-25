<?php
declare(strict_types=1);

namespace App\Services\Log;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

/**
 * Monolog 日志驱动
 */
class MonologDriver implements LogDriverInterface
{
    private Logger $logger;

    public function __construct(string $logDir = '', string $logFileFormat = 'app-{date}.log')
    {
        $logDir = $logDir ?: dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $filename = str_replace('{date}', date('Y-m-d'), $logFileFormat);
        $logFile = $logDir . '/' . $filename;

        $this->logger = new Logger('yycms');
        $this->logger->pushHandler(new StreamHandler($logFile, Logger::DEBUG));
    }

    /**
     * @param array<string, mixed> $logData
     */
    public function record(array $logData): bool
    {
        try {
            $level = $this->mapLevel((string)($logData['level'] ?? 'info'));
            $message = (string)($logData['message'] ?? 'log');
            $context = $logData;
            $this->logger->log($level, $message, $context);
            return true;
        } catch (\Throwable $e) {
            error_log('[MonologDriver] 日志记录失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $logDataList
     */
    public function recordBatch(array $logDataList): int
    {
        $count = 0;
        foreach ($logDataList as $logData) {
            if ($this->record($logData)) {
                $count++;
            }
        }
        return $count;
    }

    private function mapLevel(string $level): int
    {
        return match (strtolower($level)) {
            'debug' => Logger::DEBUG,
            'info', 'success' => Logger::INFO,
            'warning' => Logger::WARNING,
            'error' => Logger::ERROR,
            'critical' => Logger::CRITICAL,
            default => Logger::INFO,
        };
    }
}
