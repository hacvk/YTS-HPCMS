<?php
/**
 * 文章管理模块测试脚本
 */

// 定义应用根目录
define('APP_ROOT', __DIR__);

// 引入自动加载器
require_once APP_ROOT . '/app/Core/Autoloader.php';

// 注册自动加载器
$autoloader = new App\Core\Autoloader();
$autoloader->register();

// 开启 Session
session_start();

// 引入配置文件
$config = require APP_ROOT . '/config.php';

// 实例化应用
$app = new App\Core\App($config);

echo "=== 文章管理模块测试 ===\n\n";

try {
    // 测试1：内容模型初始化
    echo "1. 测试内容模型初始化...";
    $db = App\Core\Database::get();
    echo "OK\n";
    
    // 测试2：获取文章列表
    echo "2. 测试获取文章列表...";
    $result = App\Model\Content::getList(1, 10);
    echo "OK (总共有 {$result['total']} 篇文章)\n";
    
    // 测试3：获取栏目树
    echo "3. 测试获取栏目树...";
    $categories = App\Model\Category::getTree();
    echo "OK (总共有 " . count($categories) . " 个顶级栏目)\n";
    
    echo "\n=== 测试完成 ===\n";
    echo "所有基础功能测试通过！\n";
    
} catch (Exception $e) {
    echo "\n\n=== 测试失败 ===\n";
    echo "错误信息: " . $e->getMessage() . "\n";
    echo "错误文件: " . $e->getFile() . "\n";
    echo "错误行号: " . $e->getLine() . "\n";
    exit(1);
}