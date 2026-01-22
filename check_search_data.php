<?php
// 定义必要的常量
if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__);
}

require_once 'config.php';
require_once 'app/Core/App.php';
require_once 'app/Lib/Medoo.php';
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
    
    // 检查数据库中是否存在标题包含'文章'的内容
    $results = $db->select('sys_content', [
        'id',
        'title',
        'summary',
        'published_at',
        'views',
        'status'
    ], [
        'OR' => [
            'title[~]' => '文章',
            'summary[~]' => '文章',
        ]
    ]);

    echo 'Found ' . count($results) . " results containing '文章':\n";
    foreach($results as $result) {
        echo 'ID: ' . $result['id'] . ', Title: ' . $result['title'] . ', Status: ' . $result['status'] . "\n";
    }
    
    // 检查是否有已发布的文章
    $publishedResults = $db->select('sys_content', [
        'id',
        'title',
        'summary',
        'published_at',
        'views',
        'status'
    ], [
        'status' => 1
    ]);
    
    echo "\nFound " . count($publishedResults) . " published articles:\n";
    foreach($publishedResults as $result) {
        echo 'ID: ' . $result['id'] . ', Title: ' . $result['title'] . ", Summary: " . substr($result['summary'], 0, 50) . "...\n";
    }
    
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}