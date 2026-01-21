<?php
namespace App\Http\Middleware;

use Closure;
use App\Core\App;
use App\Core\Database;
use App\Services\LogService;

class PerformanceMiddleware
{
    public function handle(Closure $next)
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        $requestId = uniqid('req_');
        $_SERVER['REQUEST_ID'] = $requestId;

        $response = $next();

        $endTime = microtime(true);
        $endMemory = memory_get_usage();
        $duration = ($endTime - $startTime) * 1000;
        $memoryUsed = ($endMemory - $startMemory) / 1024;

        $this->logPerformanceMetrics($requestId, $_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/', http_response_code() ?: 200, $duration, $memoryUsed, $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown');

        return $response;
    }

    private function logPerformanceMetrics($requestId, $method, $uri, $statusCode, $duration, $memoryUsed, $ip, $userAgent)
    {
        try {
            $db = Database::get();
            $this->ensurePerformanceMetricsTableExists($db);
            
            // 额外检查表是否真的存在，以防ensurePerformanceMetricsTableExists()方法未能成功创建表
            $tableExists = $db->query('SELECT 1 FROM information_schema.tables WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "performance_metrics" LIMIT 1')->fetchAll();
            
            if (!empty($tableExists)) {
                $adminId = $_SESSION['admin_id'] ?? null;
                $db->insert('performance_metrics', [
                    'request_id' => $requestId,
                    'method' => $method,
                    'uri' => $uri,
                    'status_code' => $statusCode,
                    'duration' => round($duration, 2),
                    'memory_used' => round($memoryUsed, 2),
                    'ip' => $ip,
                    'user_agent' => $userAgent,
                    'admin_id' => $adminId,
                    'timestamp' => time()
                ]);

                if ($duration > 100) {
                    $this->logSlowQuery($requestId, $duration);
                }
            }
        } catch (\Exception $e) {
            // 获取管理员ID用于日志记录，并确保类型安全
            $sessionAdminId = $_SESSION['admin_id'] ?? null;
            $adminId = is_int($sessionAdminId) ? $sessionAdminId : (is_numeric($sessionAdminId) ? (int)$sessionAdminId : null);
            $username = $_SESSION['admin_username'] ?? 'unknown';
            
            LogService::error($e, 'performance_metrics_failed', $adminId, $username);
        }
    }

    private function ensurePerformanceMetricsTableExists($db)
    {
        try {
            $tableExists = $db->query('SELECT 1 FROM information_schema.tables WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "performance_metrics" LIMIT 1')->fetchAll();
            if (empty($tableExists)) {
                // 使用更标准的表创建语句
                $createQuery = "CREATE TABLE IF NOT EXISTS `performance_metrics` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `request_id` varchar(50) NOT NULL,
                    `method` varchar(10) NOT NULL,
                    `uri` varchar(255) NOT NULL,
                    `status_code` int(3) NOT NULL,
                    `duration` decimal(10,2) NOT NULL,
                    `memory_used` decimal(10,2) NOT NULL,
                    `ip` varchar(45) NOT NULL,
                    `user_agent` varchar(255) DEFAULT NULL,
                    `admin_id` int(11) DEFAULT NULL,
                    `timestamp` int(10) NOT NULL,
                    PRIMARY KEY (`id`),
                    INDEX `idx_timestamp` (`timestamp`),
                    INDEX `idx_status_code` (`status_code`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
                $db->query($createQuery);
            }
        } catch (\Exception $e) {
            // 获取管理员ID用于日志记录，并确保类型安全
            $sessionAdminId = $_SESSION['admin_id'] ?? null;
            $adminId = is_int($sessionAdminId) ? $sessionAdminId : (is_numeric($sessionAdminId) ? (int)$sessionAdminId : null);
            $username = $_SESSION['admin_username'] ?? 'unknown';
            
            LogService::error($e, 'table_creation_failed', $adminId, $username);
        }
    }

    private function logSlowQuery($requestId, $duration)
    {
        // SHOW PROFILES may not be available in newer MySQL versions
        // Just log the slow request info
        try {
            $db = Database::get();
            
            // 检查 slow_queries 表是否存在
            $tableExists = $db->query('SELECT 1 FROM information_schema.tables WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "slow_queries" LIMIT 1')->fetchAll();
            
            // 如果 slow_queries 表不存在，先创建它
            if (empty($tableExists)) {
                $createQuery = "CREATE TABLE IF NOT EXISTS `slow_queries` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `request_id` varchar(50) DEFAULT NULL,
                    `sql` text NOT NULL,
                    `params` text DEFAULT NULL,
                    `duration` decimal(10,2) NOT NULL,
                    `timestamp` int(10) NOT NULL,
                    PRIMARY KEY (`id`),
                    INDEX `idx_timestamp` (`timestamp`),
                    INDEX `idx_duration` (`duration`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
                $db->query($createQuery);
            }
            
            if (!empty($tableExists)) {
                $db->insert('slow_queries', [
                    'request_id' => $requestId,
                    'sql' => $_SERVER['REQUEST_URI'] ?? 'unknown',
                    'duration' => $duration,
                    'timestamp' => time()
                ]);
            }
        } catch (\Exception $e) {
            // 获取管理员ID用于日志记录，并确保类型安全
            $sessionAdminId = $_SESSION['admin_id'] ?? null;
            $adminId = is_int($sessionAdminId) ? $sessionAdminId : (is_numeric($sessionAdminId) ? (int)$sessionAdminId : null);
            $username = $_SESSION['admin_username'] ?? 'unknown';
            
            LogService::error($e, 'slow_query_log_failed', $adminId, $username);
        }
    }
}
