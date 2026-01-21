<?php
/**
 * 应用入口文件
 */

// 开启错误显示（调试模式，生产环境请关闭）
ini_set('display_errors', '1');
error_reporting(E_ALL);

// 定义应用根目录
define('APP_ROOT', dirname(__DIR__));

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

// 引入自动加载器
require_once APP_ROOT . '/app/Core/Autoloader.php';

// 注册自动加载器
$autoloader = new App\Core\Autoloader();
$autoloader->register();

// 初始化 Session（使用安全配置）
App\Services\SessionService::init();

// 引入配置文件
$config = require APP_ROOT . '/config.php';

// 实例化应用并运行
$app = new App\Core\App($config);
$app->run();
