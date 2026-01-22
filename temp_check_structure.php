<?php
// 引入Medoo库
require_once 'app/Lib/Medoo.php';

// 定义必要的常量
if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__);
}

require_once 'config.php';
require_once 'app/Core/App.php';
require_once 'app/Core/Container.php';
require_once 'app/Core/Database.php';

try {
    // 加载配置
    $config = require 'config.php';
    $app = new App\Core\App($config);
    
    // 创建容器并注册数据库服务
    $container = new App\Core\Container();
    $container->singleton(Medoo\Medoo::class, App\Core\Database::get());
    
    $db = $container->get(Medoo\Medoo::class);
    $stmt = $db->query('SHOW COLUMNS FROM sys_content');
    $columns = $stmt->fetchAll();
    echo "Table: sys_content\n----------------------------------------\n";
    foreach($columns as $col) {
        printf("%-15s %-20s %-10s %-5s %-5s %s\n", $col['Field'], $col['Type'], $col['Null'], $col['Key'], $col['Default'], $col['Extra']);
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}