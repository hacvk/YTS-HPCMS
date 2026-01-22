<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Services\Log\FileLogDriver;

/**
 * 访客日志服务
 * 支持多驱动架构，高性能写入
 *
 * 安全特性:
 * - 参数化查询防止SQL注入
 * - 数据清理防止注入攻击
 * - 异常隔离: 日志失败不影响主流程
 *
 * 性能特性:
 * - 文件驱动异步写入
 * - 批量处理
 * - 内存控制
 */
class VisitorLogService
{
    /**
     * 日志驱动实例（单例）
     */
    private static ?FileLogDriver $driver = null;

    /**
     * 获取日志驱动实例
     *
     * @return FileLogDriver
     */
    private static function getDriver(): FileLogDriver
    {
        if (self::$driver === null) {
            // 从配置文件读取日志配置
            $logConfig = \App\Core\App::getConfig('visitor.log', [
                'dir' => 'storage/logs/visitor',
                'format' => 'visitor-{date}.log',
            ]);

            $logDir = dirname(__DIR__, 2) . '/' . $logConfig['dir'];
            $logFormat = $logConfig['format'];

            self::$driver = new FileLogDriver($logDir, $logFormat);
        }

        return self::$driver;
    }

    /**
     * 记录访客日志
     *
     * 安全特性:
     * - 数据清理
     * - 参数化查询
     * - 异常隔离
     *
     * @param string $requestId 请求ID
     * @param string $sessionId 会话ID
     * @param float $duration 耗时(ms)
     * @param float $memoryUsed 内存使用(KB)
     * @param int $statusCode 状态码
     * @return bool 是否记录成功
     */
    public static function record(
        string $requestId,
        string $sessionId,
        float $duration,
        float $memoryUsed,
        int $statusCode
    ): bool {
        try {
            // 采集访客数据
            $visitorData = self::collectVisitorData();

            // 构建日志数据
            $logData = [
                'request_id' => $requestId,
                'session_id' => $sessionId,
                'ip' => $visitorData['ip'],
                'user_agent' => $visitorData['user_agent'],
                'url' => $visitorData['url'],
                'referrer' => $visitorData['referrer'],
                'method' => $visitorData['method'],
                'status_code' => $statusCode,
                'duration' => round($duration, 2),
                'memory_used' => round($memoryUsed, 2),
                'device_type' => $visitorData['device_type'],
                'browser' => $visitorData['browser'],
                'os' => $visitorData['os'],
                'is_bot' => $visitorData['is_bot'],
                'timestamp' => time(),
            ];

            // 使用文件驱动记录日志（高性能）
            return self::getDriver()->record($logData);
        } catch (\Exception $e) {
            error_log('[VisitorLogService] 日志记录失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 采集访客数据
     *
     * 安全特性:
     * - IP头部白名单控制
     * - 数据清理
     * - 长度限制
     *
     * @return array
     */
    private static function collectVisitorData(): array
    {
        $userAgent = self::getUserAgent();

        return [
            'ip' => self::getClientIp(),
            'user_agent' => $userAgent,
            'url' => self::getUrl(),
            'referrer' => self::getReferrer(),
            'method' => self::getMethod(),
            'device_type' => self::detectDeviceType($userAgent),
            'browser' => self::detectBrowser($userAgent),
            'os' => self::detectOS($userAgent),
            'is_bot' => self::isBot($userAgent),
        ];
    }

    /**
     * 获取客户端IP地址
     *
     * 安全特性:
     * - 可信代理白名单
     * - 防止IP伪造
     * - 验证IP格式
     *
     * @return string
     */
    private static function getClientIp(): string
    {
        // 检查是否启用可信代理模式
        $enableTrustedProxy = \App\Core\App::getConfig('visitor.enable_trusted_proxy', false);
        $trustedProxies = \App\Core\App::getConfig('visitor.trusted_proxies', []);

        $ip = '';

        if ($enableTrustedProxy) {
            // 检查当前IP是否在白名单中
            $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
            if (in_array($remoteAddr, $trustedProxies, true)) {
                // 信任的代理，可以使用X-Forwarded-For
                if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                    // X-Forwarded-For 可能包含多个IP，取第一个
                    $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                    $ip = trim($ips[0]);
                } elseif (isset($_SERVER['HTTP_CLIENT_IP']) && !empty($_SERVER['HTTP_CLIENT_IP'])) {
                    $ip = $_SERVER['HTTP_CLIENT_IP'];
                } elseif (isset($_SERVER['HTTP_X_REAL_IP']) && !empty($_SERVER['HTTP_X_REAL_IP'])) {
                    $ip = $_SERVER['HTTP_X_REAL_IP'];
                }
            }
        }

        // 如果没有从头部获取到IP，使用REMOTE_ADDR
        if (empty($ip)) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }

        // 验证IP格式
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $ip;
        }

        // 如果验证失败，返回REMOTE_ADDR
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
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
    private static function sanitizeString(string $str, int $maxLength = 500): string
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
     * 获取User-Agent
     *
     * 安全特性:
     * - 清理控制字符
     * - 限制长度
     *
     * @return string
     */
    private static function getUserAgent(): string
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return self::sanitizeString($userAgent, 500);
    }

    /**
     * 获取URL
     *
     * 安全特性:
     * - 清理控制字符
     * - 限制长度
     *
     * @return string
     */
    private static function getUrl(): string
    {
        $url = $_SERVER['REQUEST_URI'] ?? '/';
        return self::sanitizeString($url, 500);
    }

    /**
     * 获取Referrer
     *
     * 安全特性:
     * - 清理控制字符
     * - 限制长度
     *
     * @return string
     */
    private static function getReferrer(): string
    {
        $referrer = $_SERVER['HTTP_REFERER'] ?? '';
        return self::sanitizeString($referrer, 500);
    }

    /**
     * 获取请求方法
     *
     * @return string
     */
    private static function getMethod(): string
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        return self::sanitizeString($method, 10);
    }

    /**
     * 检测设备类型
     *
     * @param string $userAgent
     * @return string
     */
    private static function detectDeviceType(string $userAgent): string
    {
        $userAgent = strtolower($userAgent);

        if (preg_match('/(mobile|android|iphone|ipad|windows phone)/i', $userAgent)) {
            return 'mobile';
        }

        if (preg_match('/(tablet|ipad)/i', $userAgent)) {
            return 'tablet';
        }

        return 'desktop';
    }

    /**
     * 检测浏览器
     *
     * @param string $userAgent
     * @return string
     */
    private static function detectBrowser(string $userAgent): string
    {
        $userAgent = strtolower($userAgent);

        if (strpos($userAgent, 'chrome') !== false) return 'chrome';
        if (strpos($userAgent, 'firefox') !== false) return 'firefox';
        if (strpos($userAgent, 'safari') !== false) return 'safari';
        if (strpos($userAgent, 'edge') !== false) return 'edge';
        if (strpos($userAgent, 'msie') !== false || strpos($userAgent, 'trident') !== false) return 'ie';

        return 'unknown';
    }

    /**
     * 检测操作系统
     *
     * @param string $userAgent
     * @return string
     */
    private static function detectOS(string $userAgent): string
    {
        $userAgent = strtolower($userAgent);

        if (strpos($userAgent, 'windows') !== false) return 'windows';
        if (strpos($userAgent, 'mac os') !== false) return 'macos';
        if (strpos($userAgent, 'linux') !== false) return 'linux';
        if (strpos($userAgent, 'android') !== false) return 'android';
        if (strpos($userAgent, 'iphone') !== false || strpos($userAgent, 'ipad') !== false) return 'ios';

        return 'unknown';
    }

    /**
     * 检测是否爬虫
     *
     * @param string $userAgent
     * @return bool
     */
    private static function isBot(string $userAgent): bool
    {
        $botPatterns = [
            'bot', 'crawler', 'spider', 'scraper', 'curl', 'wget',
            'python', 'java', 'go-http-client', 'php', 'ruby',
            'baiduspider', 'googlebot', 'bingbot', 'yandexbot',
        ];

        $userAgent = strtolower($userAgent);

        foreach ($botPatterns as $pattern) {
            if (strpos($userAgent, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取访客日志列表
     *
     * 安全特性:
     * - 参数化查询
     * - SQL注入防护
     *
     * @param array $conditions 查询条件
     * @param int $limit 限制数量
     * @param int $offset 偏移量
     * @return array
     */
    public static function getLogs(array $conditions = [], int $limit = 50, int $offset = 0): array
    {
        try {
            $db = Database::get();

            $where = array_merge($conditions, [
                'ORDER' => ['id' => 'DESC'],
                'LIMIT' => [$offset, $limit],
            ]);

            return $db->select('visitor_logs', '*', $where);
        } catch (\Exception $e) {
            error_log('[VisitorLogService] 获取日志失败: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取访客日志总数
     *
     * 安全特性:
     * - 参数化查询
     * - SQL注入防护
     *
     * @param array $conditions 查询条件
     * @return int
     */
    public static function getCount(array $conditions = []): int
    {
        try {
            $db = Database::get();
            return $db->count('visitor_logs', $conditions);
        } catch (\Exception $e) {
            error_log('[VisitorLogService] 获取日志总数失败: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * 清理过期日志
     *
     * 安全特性:
     * - 参数化查询
     * - SQL注入防护
     * - 数据生命周期管理
     *
     * @param int $days 保留天数
     * @return int 删除的记录数
     */
    public static function cleanExpiredLogs(int $days = 30): int
    {
        try {
            $db = Database::get();

            $expireDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

            $result = $db->delete('visitor_logs', [
                'timestamp[<]' => strtotime($expireDate),
            ]);

            if ($result !== null) {
                return $result->rowCount();
            }

            return 0;
        } catch (\Exception $e) {
            error_log('[VisitorLogService] 清理过期日志失败: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * 清理过期会话
     *
     * 安全特性:
     * - 参数化查询
     * - SQL注入防护
     * - 数据生命周期管理
     *
     * @param int $days 保留天数
     * @return int 删除的记录数
     */
    public static function cleanExpiredSessions(int $days = 30): int
    {
        try {
            $db = Database::get();

            $expireTimestamp = strtotime("-{$days} days");

            $result = $db->delete('visitor_sessions', [
                'last_visit[<]' => $expireTimestamp,
            ]);

            if ($result !== null) {
                return $result->rowCount();
            }

            return 0;
        } catch (\Exception $e) {
            error_log('[VisitorLogService] 清理过期会话失败: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * 清理过期告警
     *
     * 安全特性:
     * - 参数化查询
     * - SQL注入防护
     * - 数据生命周期管理
     *
     * @param int $days 保留天数
     * @return int 删除的记录数
     */
    public static function cleanExpiredAlerts(int $days = 90): int
    {
        try {
            $db = Database::get();

            $expireDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

            $result = $db->delete('visitor_alerts', [
                'created_at[<]' => $expireDate,
            ]);

            if ($result !== null) {
                return $result->rowCount();
            }

            return 0;
        } catch (\Exception $e) {
            error_log('[VisitorLogService] 清理过期告警失败: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * 按月分区清理visitor_logs
     *
     * 安全特性:
     * - 参数化查询
     * - SQL注入防护
     * - 数据生命周期管理
     *
     * @return int 删除的分区数
     */
    public static function cleanPartitionedLogs(): int
    {
        try {
            $db = Database::get();

            // 获取当前月份
            $currentMonth = date('Ym');
            $monthsAgo3 = date('Ym', strtotime('-3 months'));

            // 获取所有分区
            $partitions = $db->query("
                SELECT PARTITION_NAME
                FROM information_schema.PARTITIONS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'visitor_logs'
                AND PARTITION_NAME IS NOT NULL
                ORDER BY PARTITION_NAME
            ")->fetchAll();

            $deletedCount = 0;

            foreach ($partitions as $partition) {
                $partitionName = $partition['PARTITION_NAME'];

                // 跳过当前月份和未来月份
                if ($partitionName >= $currentMonth) {
                    continue;
                }

                // 跳过3个月内的分区
                if ($partitionName >= $monthsAgo3) {
                    continue;
                }

                // 删除旧分区
                $db->query("ALTER TABLE visitor_logs DROP PARTITION `{$partitionName}`");
                $deletedCount++;
            }

            return $deletedCount;
        } catch (\Exception $e) {
            error_log('[VisitorLogService] 清理分区失败: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * 获取数据库统计信息
     *
     * @return array
     */
    public static function getDatabaseStats(): array
    {
        try {
            $db = Database::get();

            $stats = [];

            // visitor_logs 统计
            $stats['visitor_logs'] = $db->count('visitor_logs');

            // visitor_stats_daily 统计
            $stats['visitor_stats_daily'] = $db->count('visitor_stats_daily');

            // visitor_sessions 统计
            $stats['visitor_sessions'] = $db->count('visitor_sessions');

            // visitor_alerts 统计
            $stats['visitor_alerts'] = $db->count('visitor_alerts');

            // 获取表大小
            $tableSizes = $db->query("
                SELECT
                    table_name,
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                AND table_name LIKE 'visitor_%'
                ORDER BY size_mb DESC
            ")->fetchAll();

            $stats['table_sizes'] = $tableSizes;

            return $stats;
        } catch (\Exception $e) {
            error_log('[VisitorLogService] 获取统计信息失败: ' . $e->getMessage());
            return [];
        }
    }
}
