<?php
// 定义必要的常量
define('APP_ROOT', __DIR__);

// 检查数据库表是否存在的脚本
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
    
    // 检查 performance_metrics 表
    $stmt = $pdo->query("SHOW TABLES LIKE 'performance_metrics'");
    $result = $stmt->fetchAll();
    
    if (empty($result)) {
        echo "❌ performance_metrics 表不存在\n";
    } else {
        echo "✅ performance_metrics 表存在\n";
        
        // 检查表结构
        $columnsStmt = $pdo->query("DESCRIBE performance_metrics");
        $columns = $columnsStmt->fetchAll();
        echo "   表结构:\n";
        foreach ($columns as $col) {
            echo "   - {$col['Field']} ({$col['Type']}, {$col['Null']}, {$col['Key']})\n";
        }
    }
    
    // 检查 slow_queries 表
    $stmt = $pdo->query("SHOW TABLES LIKE 'slow_queries'");
    $result = $stmt->fetchAll();
    
    if (empty($result)) {
        echo "❌ slow_queries 表不存在\n";
    } else {
        echo "✅ slow_queries 表存在\n";
        
        // 检查表结构
        $columnsStmt = $pdo->query("DESCRIBE slow_queries");
        $columns = $columnsStmt->fetchAll();
        echo "   表结构:\n";
        foreach ($columns as $col) {
            echo "   - {$col['Field']} ({$col['Type']}, {$col['Null']}, {$col['Key']})\n";
        }
    }
    
    // 检查 error_logs 表
    $stmt = $pdo->query("SHOW TABLES LIKE 'error_logs'");
    $result = $stmt->fetchAll();
    
    if (empty($result)) {
        echo "❌ error_logs 表不存在\n";
    } else {
        echo "✅ error_logs 表存在\n";
        
        // 检查表结构
        $columnsStmt = $pdo->query("DESCRIBE error_logs");
        $columns = $columnsStmt->fetchAll();
        echo "   表结构:\n";
        foreach ($columns as $col) {
            echo "   - {$col['Field']} ({$col['Type']}, {$col['Null']}, {$col['Key']})\n";
        }
    }
    
    echo "\n如果表存在但仍有错误，可能是缓存或连接问题。\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "请确保数据库服务正在运行并且可以从当前机器连接到 " . $dbConfig['host'] . "\n";
}