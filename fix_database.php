<?php
/**
 * 数据库修复脚本
 * 用于添加缺失的字段
 */

// 定义应用根目录
define('APP_ROOT', __DIR__);

require_once APP_ROOT . '/app/Core/Autoloader.php';

$autoloader = new App\Core\Autoloader();
$autoloader->register();

$config = require APP_ROOT . '/config.php';

try {
    $db = new \Medoo\Medoo([
        'database_type' => 'mysql',
        'database_name' => $config['db']['database'],
        'server' => $config['db']['host'],
        'username' => $config['db']['username'],
        'password' => $config['db']['password'],
        'charset' => 'utf8mb4',
    ]);

    echo "开始修复数据库...\n\n";

    // 1. 检查 sys_admin 表是否存在 role_id 字段
    echo "1. 检查 sys_admin 表的 role_id 字段...\n";
    $columns = $db->query("SHOW COLUMNS FROM sys_admin LIKE 'role_id'")->fetchAll();
    if (empty($columns)) {
        echo "   - 添加 role_id 字段...\n";
        $db->query("ALTER TABLE `sys_admin` ADD COLUMN `role_id` INT(11) NOT NULL DEFAULT 1 COMMENT '角色ID' AFTER `status`");
        echo "   ✓ role_id 字段添加成功\n";
    } else {
        echo "   ✓ role_id 字段已存在\n";
    }

    // 2. 检查 sys_admin 表是否存在 last_login_ip 字段
    echo "2. 检查 sys_admin 表的 last_login_ip 字段...\n";
    $columns = $db->query("SHOW COLUMNS FROM sys_admin LIKE 'last_login_ip'")->fetchAll();
    if (empty($columns)) {
        echo "   - 添加 last_login_ip 字段...\n";
        $db->query("ALTER TABLE `sys_admin` ADD COLUMN `last_login_ip` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '最后登录IP' AFTER `last_login_time`");
        echo "   ✓ last_login_ip 字段添加成功\n";
    } else {
        echo "   ✓ last_login_ip 字段已存在\n";
    }

    // 3. 检查 sys_log 表是否存在 admin_name 字段
    echo "3. 检查 sys_log 表的 admin_name 字段...\n";
    $columns = $db->query("SHOW COLUMNS FROM sys_log LIKE 'admin_name'")->fetchAll();
    if (empty($columns)) {
        echo "   - 添加 admin_name 字段...\n";
        $db->query("ALTER TABLE `sys_log` ADD COLUMN `admin_name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '管理员姓名' AFTER `admin_id`");
        echo "   ✓ admin_name 字段添加成功\n";
    } else {
        echo "   ✓ admin_name 字段已存在\n";
    }

    // 4. 更新现有管理员的 role_id
    echo "4. 更新现有管理员的 role_id...\n";
    $db->query("UPDATE `sys_admin` SET `role_id` = 1 WHERE `role_id` = 0 OR `role_id` IS NULL");
    echo "   ✓ role_id 更新完成\n";

    // 5. 创建 sys_role 表（如果不存在）
    echo "5. 检查 sys_role 表...\n";
    $tables = $db->query("SHOW TABLES LIKE 'sys_role'")->fetchAll();
    if (empty($tables)) {
        echo "   - 创建 sys_role 表...\n";
        $db->query("
            CREATE TABLE `sys_role` (
                `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT '角色ID',
                `name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '角色名称',
                `description` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '角色描述',
                `permissions` TEXT COMMENT '权限列表（JSON格式）',
                `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '状态：1-正常，0-禁用',
                `create_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
                `update_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='角色表'
        ");
        echo "   ✓ sys_role 表创建成功\n";
        
        // 插入默认角色
        echo "   - 插入默认角色...\n";
        $db->insert('sys_role', [
            'id' => 1,
            'name' => '超级管理员',
            'description' => '拥有所有权限',
            'status' => 1
        ]);
        $db->insert('sys_role', [
            'id' => 2,
            'name' => '普通管理员',
            'description' => '普通管理员权限',
            'status' => 1
        ]);
        echo "   ✓ 默认角色插入成功\n";
    } else {
        echo "   ✓ sys_role 表已存在\n";
    }

    echo "\n✓ 数据库修复完成！\n";
    echo "\n请清除浏览器缓存和 Cookie 后重新登录。\n";

} catch (\Exception $e) {
    echo "\n✗ 数据库修复失败：\n";
    echo "错误信息： " . $e->getMessage() . "\n";
    echo "文件： " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}
