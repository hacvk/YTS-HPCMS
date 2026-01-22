<?php
define('APP_ROOT', __DIR__);

require_once __DIR__ . '/app/Core/Autoloader.php';
$autoloader = new \App\Core\Autoloader();
$autoloader->register();

$config = require __DIR__ . '/config.php';
$databaseConfig = $config['db'] ?? [];

try {
    // 创建数据库连接
    $dsn = 'mysql:host=' . ($databaseConfig['host'] ?? 'localhost') . ';dbname=' . ($databaseConfig['database'] ?? 'yycms') . ';charset=utf8mb4';
    $pdo = new PDO($dsn, $databaseConfig['username'] ?? 'root', $databaseConfig['password'] ?? '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "数据库连接成功\n";

    // 检查搜索相关的表
    $searchTables = [
        'search_logs',
        'search_cache',
        'search_blocks',
        'search_keyword_blacklist',
        'search_ip_blacklist',
        'search_rate_limits'
    ];

    echo "检查搜索相关表是否存在：\n";
    foreach ($searchTables as $table) {
        $result = $pdo->prepare("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = ? AND table_name = ?");
        $result->execute([$databaseConfig['database'], $table]);
        $row = $result->fetch();

        if ($row['count'] > 0) {
            echo "✓ $table 表存在\n";
        } else {
            echo "✗ $table 表不存在\n";
        }
    }

} catch (Exception $e) {
    echo "错误：" . $e->getMessage() . "\n";
}