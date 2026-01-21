<?php
// 添加新权限到数据库的脚本
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

use App\Core\Database;

try {
    // 获取数据库连接
    $db = Database::get();
    
    // 检查权限是否已存在
    $existingPerm = $db->get('sys_permission', ['id'], ['perm_key' => 'admin:system:logs:view']);
    
    if ($existingPerm) {
        echo "权限 'admin:system:logs:view' 已存在于数据库中。\n";
    } else {
        // 插入新权限
        $result = $db->insert('sys_permission', [
            'perm_key' => 'admin:system:logs:view',
            'title' => '系统日志（查看）',
            'group_name' => '系统',
            'status' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        
        if ($result) {
            echo "权限 'admin:system:logs:view' 已成功添加到数据库。\n";
        } else {
            echo "添加权限失败。\n";
        }
    }
    
    // 同时也给超级管理员角色（通常ID为1）添加这个权限
    $superAdminRoleId = 1;
    $permId = $db->get('sys_permission', 'id', ['perm_key' => 'admin:system:logs:view']);
    
    if ($permId) {
        $existingRolePerm = $db->get('sys_role_permission', ['id'], [
            'role_id' => $superAdminRoleId,
            'permission_id' => $permId
        ]);
        
        if (!$existingRolePerm) {
            $result = $db->insert('sys_role_permission', [
                'role_id' => $superAdminRoleId,
                'permission_id' => $permId
            ]);
            
            if ($result) {
                echo "已为超级管理员角色添加系统日志权限。\n";
            } else {
                echo "为超级管理员角色添加权限失败。\n";
            }
        } else {
            echo "超级管理员角色已拥有系统日志权限。\n";
        }
    }
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}