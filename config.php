<?php
// 设置时区为上海
date_default_timezone_set('Asia/Shanghai');

/**
 * 应用配置文件
 * 
 * @return array 配置数组
 */
$baseConfig = [
    /**
     * 数据库配置
     */
    'db' => [
		'host' => '127.0.0.1',
		'database' => 'yycms',
		'username' => 'yycms',
		'password' => 'YOUR_DB_PASSWORD',
		'charset' => 'utf8mb4',
		'collation' => 'utf8mb4_unicode_ci',
			],
	'app' => [
		'debug' => getenv('APP_DEBUG') === 'true',
		'debug' => getenv('APP_DEBUG') === 'true',
		'app_key' => 'yycms_' . bin2hex(random_bytes(32)),
		],
];

// 合并 config 下的扩展配置（用于后台菜单、模型、系统信息等）
$extraConfigFiles = [
    APP_ROOT . '/config/system.php',
];

foreach ($extraConfigFiles as $file) {
    if (is_file($file)) {
        $extra = require $file;
        if (is_array($extra)) {
            $baseConfig = array_replace_recursive($baseConfig, $extra);
        }
    }
}

// 数据库连接测试脚本
if (isset($argv) && in_array('--test-db', $argv)) {
    echo "Testing database connection...\n";
    echo "Host: " . $baseConfig['db']['host'] . "\n";
    echo "Database: " . $baseConfig['db']['database'] . "\n";
    echo "Username: " . $baseConfig['db']['username'] . "\n";
    
    try {
        $pdo = new PDO(
            "mysql:host={$baseConfig['db']['host']};dbname={$baseConfig['db']['database']};charset={$baseConfig['db']['charset']}",
            $baseConfig['db']['username'],
            $baseConfig['db']['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 5,
            ]
        );
        
        echo "Database connection: SUCCESS\n";
        
        // 测试查询
        $stmt = $pdo->query("SELECT 1 as test");
        $result = $stmt->fetch();
        
        if ($result['test'] == 1) {
            echo "Basic query test: SUCCESS\n";
        } else {
            echo "Basic query test: FAILED\n";
        }
        
    } catch (Exception $e) {
        echo "Database connection: FAILED\n";
        echo "Error: " . $e->getMessage() . "\n";
    }
    exit;
}

return $baseConfig;