<?php
// 检查 slow_queries 表的状态

define('APP_ROOT', __DIR__);

// 加载 .env 文件
if (file_exists(APP_ROOT . '/.env')) {
    $envLines = file(APP_ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envLines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue; // 跳过注释
        }
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (!isset($_SERVER[$key])) {
                $_SERVER[$key] = $value;
            }
            if (!getenv($key)) {
                putenv("$key=$value");
            }
        }
    }
}

require_once APP_ROOT . '/app/Core/Autoloader.php';
$autoloader = new App\Core\Autoloader();
$autoloader->register();

$config = require APP_ROOT . '/config.php';

use App\Core\Database;

$db = Database::get();

try {
    // 检查 slow_queries 表记录数
    $slowQueryCount = $db->count('slow_queries');
    echo 'slow_queries 表记录数: ' . $slowQueryCount . "\n";
    
    // 检查 performance_metrics 表记录数
    $perfMetricCount = $db->count('performance_metrics');
    echo 'performance_metrics 表记录数: ' . $perfMetricCount . "\n";
    
    // 检查 error_logs 表记录数
    $errorLogCount = $db->count('error_logs');
    echo 'error_logs 表记录数: ' . $errorLogCount . "\n";
    
    echo "\n说明：\n";
    echo "- slow_queries 表记录处理时间超过100毫秒的请求\n";
    echo "- performance_metrics 表记录所有请求的性能数据\n";
    echo "- error_logs 表记录系统错误\n";
    echo "\n如果 slow_queries 表为空，这很正常，因为只有非常慢的请求才会被记录。\n";
    echo "当前阈值是100毫秒，大部分Web请求都会快于这个时间。\n";
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}