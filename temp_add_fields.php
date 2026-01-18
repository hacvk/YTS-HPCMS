<?php
// 临时脚本用于添加数据库字段
define('APP_ROOT', __DIR__);
$config = require 'config.php';

try {
    // 获取数据库连接配置
    $dbConfig = $config['db'];
    
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    // 检查字段是否已经存在
    $stmt = $pdo->query("SHOW COLUMNS FROM sys_content LIKE 'is_top'");
    $isTopExists = $stmt->fetch();
    
    $stmt = $pdo->query("SHOW COLUMNS FROM sys_content LIKE 'is_image_news'");
    $isImageNewsExists = $stmt->fetch();
    
    $sql = "";
    if (!$isTopExists && !$isImageNewsExists) {
        // 两个字段都不存在，添加两个字段
        $sql = "ALTER TABLE sys_content ADD COLUMN is_top TINYINT(1) DEFAULT 0 AFTER updated_at, ADD COLUMN is_image_news TINYINT(1) DEFAULT 0 AFTER is_top;";
    } elseif (!$isTopExists) {
        // 只添加 is_top 字段
        $sql = "ALTER TABLE sys_content ADD COLUMN is_top TINYINT(1) DEFAULT 0 AFTER updated_at;";
    } elseif (!$isImageNewsExists) {
        // 只添加 is_image_news 字段
        $sql = "ALTER TABLE sys_content ADD COLUMN is_image_news TINYINT(1) DEFAULT 0 AFTER is_top;";
    }
    
    if (!empty($sql)) {
        $pdo->exec($sql);
        echo "Fields added successfully!\n";
        echo "Added fields to sys_content table.\n";
    } else {
        echo "Both fields already exist.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}