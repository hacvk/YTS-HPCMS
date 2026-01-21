<?php
/**
 * 清除权限缓存脚本
 * 当添加新权限后，运行此脚本以清除管理员的权限缓存，使其重新加载权限
 */

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

// 引入配置文件
$config = require APP_ROOT . '/config.php';

use App\Services\SessionService;

// 开始会话以清除权限缓存
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 清除当前会话中的权限缓存
if (isset($_SESSION['admin_permissions'])) {
    unset($_SESSION['admin_permissions']);
    echo "已清除当前管理员的权限缓存。\n";
} else {
    echo "当前没有权限缓存需要清除。\n";
}

// 如果你想清除所有用户的权限缓存，可以在这里添加代码
echo "请重新登录系统，权限系统将会重新加载权限。\n";

// 如果要清除所有管理员的权限缓存，可以考虑以下方法：
// 1. 退出登录所有用户
// 2. 或者在数据库层面做一些操作

echo "提示：要使新权限生效，请执行以下操作之一：\n";
echo "1. 退出当前管理员登录并重新登录\n";
echo "2. 或者在角色管理中重新编辑并保存当前角色\n";