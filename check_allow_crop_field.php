<?php
// 定义应用根目录
define('APP_ROOT', __DIR__);

// 引入自动加载器
require_once APP_ROOT . '/app/Core/Autoloader.php';
$autoloader = new App\Core\Autoloader();
$autoloader->register();

// 引入项目配置
$config = require __DIR__ . '/config.php';

// 创建应用实例以初始化配置
$app = new App\Core\App($config);

use App\Core\Database;

try {
    $db = Database::get();
    
    // 检查sys_category表结构
    $result = $db->query("DESCRIBE sys_category")->fetchAll();
    
    echo "sys_category表字段列表：\n";
    foreach ($result as $row) {
        echo "- {$row['Field']} | {$row['Type']} | {$row['Null']} | {$row['Key']} | {$row['Default']} | {$row['Extra']}\n";
    }
    
    // 检查allow_crop字段是否存在
    $hasAllowCrop = false;
    foreach ($result as $row) {
        if ($row['Field'] === 'allow_crop') {
            $hasAllowCrop = true;
            echo "\n找到allow_crop字段：\n";
            echo "  类型: {$row['Type']}\n";
            echo "  是否可空: {$row['Null']}\n";
            echo "  默认值: {$row['Default']}\n";
            echo "  额外属性: {$row['Extra']}\n";
            break;
        }
    }
    
    if (!$hasAllowCrop) {
        echo "\n未找到allow_crop字段！\n";
    } else {
        echo "\nallow_crop字段存在，符合预期。\n";
    }
    
} catch (Exception $e) {
    echo "错误：" . $e->getMessage() . "\n";
}