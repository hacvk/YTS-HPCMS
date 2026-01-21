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
        } catch (\Exception $e) {
            LogService::error($e, 'performance_metrics_failed');
        }
    }

    private function ensurePerformanceMetricsTableExists($db)
    {
        try {
            $tableExists = $db->query('SELECT 1 FROM information_schema.tables WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "performance_metrics" LIMIT 1')->fetchAll();
            if (empty($tableExists)) {
                $db->query('CREATE TABLE IF NOT EXISTS performance_metrics (id int(11) NOT NULL AUTO_INCREMENT, request_id varchar(50) NOT NULL, method varchar(10) NOT NULL, uri varchar(255) NOT NULL, status_code int(3) NOT NULL, duration decimal(10,2) NOT NULL, memory_used decimal(10,2) NOT NULL, ip varchar(45) NOT NULL, user_agent varchar(255) DEFAULT NULL, admin_id int(11) DEFAULT NULL, timestamp int(10) NOT NULL, PRIMARY KEY (id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            }
        } catch (\Exception $e) {
            LogService::error($e, 'table_creation_failed');
        }
    }

    private function logSlowQuery($requestId, $duration)
    {
        // SHOW PROFILES may not be available in newer MySQL versions
        // Just log the slow request info
        try {
            $db = Database::get();
            $db->insert('slow_queries', [
                'request_id' => $requestId,
                'sql' => $_SERVER['REQUEST_URI'] ?? 'unknown',
                'duration' => $duration,
                'timestamp' => time()
            ]);
        } catch (\Exception $e) {
            LogService::error($e, 'slow_query_log_failed');
        }
    }
}
