<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Database;
use App\Services\LogService;

/**
 * 性能监控中间件
 * 记录请求性能指标
 */
class PerformanceMiddleware
{
    /**
     * 处理请求
     *
     * @param callable $next 下一个中间件或请求处理函数
     * @return mixed
     */
    public function handle(callable $next)
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        
        try {
            $response = $next();
            
            // 记录性能指标
            $this->recordPerformanceMetrics([
                'request_id' => $_SERVER['REQUEST_ID'] ?? uniqid('req_'),
                'method' => $_SERVER['REQUEST_METHOD'] ?? '',
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
                'status_code' => http_response_code(),
                'duration' => round((microtime(true) - $startTime) * 1000, 2),
                'memory_used' => round((memory_get_usage() - $startMemory) / 1024, 2),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'admin_id' => $_SESSION['admin_id'] ?? null,
                'timestamp' => time(),
            ]);
            
            return $response;
        } catch (\Throwable $e) {
            // 记录异常性能指标
            $this->recordPerformanceMetrics([
                'request_id' => $_SERVER['REQUEST_ID'] ?? uniqid('req_'),
                'method' => $_SERVER['REQUEST_METHOD'] ?? '',
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
                'status_code' => 500,
                'duration' => round((microtime(true) - $startTime) * 1000, 2),
                'memory_used' => round((memory_get_usage() - $startMemory) / 1024, 2),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'admin_id' => $_SESSION['admin_id'] ?? null,
                'timestamp' => time(),
            ]);
            
            throw $e;
        }
    }
    
    /**
     * 记录性能指标
     *
     * @param array<string, mixed> $data 性能数据
     * @return void
     */
    private function recordPerformanceMetrics(array $data): void
    {
        try {
            $db = Database::get();
            
            // 检查性能指标表是否存在，不存在则创建
            $this->ensurePerformanceMetricsTableExists($db);
            
            $db->insert('performance_metrics', $data);
            
            // 定期清理旧数据（保留30天）
            if (rand(1, 100) == 1) { // 1% 概率执行清理
                $db->delete('performance_metrics', [
                    'timestamp[<]' => time() - 30 * 24 * 3600
                ]);
            }
        } catch (\Exception $e) {
            LogService::error($e, 'performance_metrics_failed');
        }
    }
    
    /**
     * 确保性能指标表存在
     *
     * @param \App\Core\Database $db 数据库实例
     * @return void
     */
    private function ensurePerformanceMetricsTableExists($db): void
    {
        $tableExists = $db->select('information_schema.tables', ['TABLE_NAME'], [
            'TABLE_SCHEMA' => $db->query("SELECT DATABASE() as db")->fetch()['db'],
            'TABLE_NAME' => 'performance_metrics'
        ]);
        
        if (empty($tableExists)) {
            // 创建性能指标表
            $db->query("CREATE TABLE IF NOT EXISTS `performance_metrics` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `request_id` varchar(50) NOT NULL COMMENT '请求ID',
                `method` varchar(10) NOT NULL COMMENT 'HTTP方法',
                `uri` varchar(255) NOT NULL COMMENT '请求URI',
                `status_code` int(3) NOT NULL COMMENT '响应状态码',
                `duration` decimal(10,2) NOT NULL COMMENT '响应时间(毫秒)',
                `memory_used` decimal(10,2) NOT NULL COMMENT '内存使用(KB)',
                `ip` varchar(45) NOT NULL COMMENT '客户端IP',
                `user_agent` varchar(255) DEFAULT NULL COMMENT '浏览器信息',
                `admin_id` int(11) DEFAULT NULL COMMENT '管理员ID',
                `timestamp` int(10) NOT NULL COMMENT '时间戳',
                PRIMARY KEY (`id`),
                KEY `idx_request_id` (`request_id`),
                KEY `idx_timestamp` (`timestamp`),
                KEY `idx_uri` (`uri`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='性能指标表';");
            
            // 创建慢查询表
            $db->query("CREATE TABLE IF NOT EXISTS `slow_queries` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `request_id` varchar(50) NOT NULL COMMENT '请求ID',
                `sql` text NOT NULL COMMENT 'SQL语句',
                `params` text DEFAULT NULL COMMENT '参数',
                `duration` decimal(10,2) NOT NULL COMMENT '执行时间(毫秒)',
                `timestamp` int(10) NOT NULL COMMENT '时间戳',
                PRIMARY KEY (`id`),
                KEY `idx_request_id` (`request_id`),
                KEY `idx_timestamp` (`timestamp`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='慢查询表';");
            
            // 创建错误记录表
            $db->query("CREATE TABLE IF NOT EXISTS `error_logs` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `request_id` varchar(50) NOT NULL COMMENT '请求ID',
                `error_type` varchar(50) NOT NULL COMMENT '错误类型',
                `error_message` text NOT NULL COMMENT '错误信息',
                `error_stack` text DEFAULT NULL COMMENT '错误堆栈',
                `uri` varchar(255) NOT NULL COMMENT '请求URI',
                `ip` varchar(45) NOT NULL COMMENT '客户端IP',
                `admin_id` int(11) DEFAULT NULL COMMENT '管理员ID',
                `timestamp` int(10) NOT NULL COMMENT '时间戳',
                PRIMARY KEY (`id`),
                KEY `idx_request_id` (`request_id`),
                KEY `idx_timestamp` (`timestamp`),
                KEY `idx_error_type` (`error_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='错误记录表';");
        }
    }
}