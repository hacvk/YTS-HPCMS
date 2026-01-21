<?php
// 定义必要的常量
define('APP_ROOT', __DIR__);

// 直接连接数据库并创建缺失的表
$config = require_once __DIR__ . '/config.php';

try {
    $dbConfig = $config['db'];
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    
    echo "数据库连接成功!\n";
    
    // 创建 performance_metrics 表
    $createPerformanceMetricsTable = "
    CREATE TABLE IF NOT EXISTS `performance_metrics` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $pdo->exec($createPerformanceMetricsTable);
    echo "performance_metrics 表创建成功!\n";
    
    // 创建 slow_queries 表
    $createSlowQueriesTable = "
    CREATE TABLE IF NOT EXISTS `slow_queries` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `request_id` varchar(50) DEFAULT NULL,
        `sql` text NOT NULL,
        `params` text DEFAULT NULL,
        `duration` decimal(10,2) NOT NULL,
        `timestamp` int(10) NOT NULL,
        PRIMARY KEY (`id`),
        INDEX `idx_timestamp` (`timestamp`),
        INDEX `idx_duration` (`duration`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $pdo->exec($createSlowQueriesTable);
    echo "slow_queries 表创建成功!\n";
    
    // 创建 error_logs 表（App.php 中也需要这个表）
    $createErrorLogsTable = "
    CREATE TABLE IF NOT EXISTS `error_logs` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `request_id` varchar(50) DEFAULT NULL,
        `error_type` varchar(255) NOT NULL,
        `error_message` text NOT NULL,
        `error_stack` text NOT NULL,
        `uri` varchar(255) DEFAULT NULL,
        `ip` varchar(45) DEFAULT NULL,
        `admin_id` int(11) DEFAULT NULL,
        `timestamp` int(10) NOT NULL,
        PRIMARY KEY (`id`),
        INDEX `idx_timestamp` (`timestamp`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $pdo->exec($createErrorLogsTable);
    echo "error_logs 表创建成功!\n";
    
    echo "\n所有缺失的表都已创建成功！\n";
    echo "现在性能监控和告警系统应该可以正常工作了。\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "请确保数据库服务正在运行并且可以从当前机器连接到 " . $dbConfig['host'] . "\n";
}