<?php
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

    /**
     * 应用配置
     */
    'app' => [
        // 调试模式开关
        'debug' => true,

        // 应用密钥（用于加密、签名等）
        'app_key' => 'yycms_' . bin2hex(random_bytes(32)),
    ],
];

// 合并 app/Config 下的扩展配置（用于后台菜单、模型、系统信息等）
$extraConfigFiles = [
    APP_ROOT . '/app/Config/system.php',
];

foreach ($extraConfigFiles as $file) {
    if (is_file($file)) {
        $extra = require $file;
        if (is_array($extra)) {
            $baseConfig = array_replace_recursive($baseConfig, $extra);
        }
    }
}

return $baseConfig;
