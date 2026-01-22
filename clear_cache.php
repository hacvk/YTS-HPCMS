<?php
// 定义必要的常量
if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__);
}

require_once 'config.php';
require_once 'app/Core/App.php';
require_once 'app/Core/View.php';

try {
    // 加载配置
    $config = require 'config.php';
    $app = new App\Core\App($config);
    
    // 创建视图实例并清除缓存
    $view = new App\Core\View();
    $view->clearCache();
    
    echo "模板缓存已清除\n";
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}