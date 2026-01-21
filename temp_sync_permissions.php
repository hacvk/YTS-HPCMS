<?php
// 临时权限同步脚本
// 用于将新添加的权限同步到数据库

define('APP_ROOT', dirname(__DIR__));

// 引入自动加载器
require_once APP_ROOT . '/app/Core/Autoloader.php';

// 注册自动加载器
$autoloader = new App\Core\Autoloader();
$autoloader->register();

// 引入配置文件
$config = require APP_ROOT . '/config.php';

use App\Services\PermissionService;
use App\Core\Database;

try {
    // 初始化权限服务
    $db = Database::get();
    $permissionService = new PermissionService($db);
    
    // 获取所有权限并同步到数据库
    [$groups, $allPermKeys] = $permissionService->getAllPermissions();
    
    echo "权限同步完成！共同步了 " . count($allPermKeys) . " 个权限。\n";
    
    // 检查我们的权限是否存在
    if (in_array('admin:system:logs:view', $allPermKeys)) {
        echo "权限 'admin:system:logs:view' 已成功添加。\n";
    } else {
        echo "权限 'admin:system:logs:view' 未找到，请检查配置。\n";
    }
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}