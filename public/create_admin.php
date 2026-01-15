<?php
// public/create_admin.php

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

require APP_ROOT . '/app/Core/Autoloader.php';
(new \App\Core\Autoloader())->register();

$config = require APP_ROOT . '/config.php';
new \App\Core\App($config);

use App\Core\Database;
use App\Core\Security;

try {
    $username = $argv[1] ?? 'admin';
    $plainPassword = $argv[2] ?? 'Pol1357**';
    $name = $argv[3] ?? $username;
    $email = $argv[4] ?? null;

    echo "正在创建管理员...\n";
    echo "用户名: {$username}\n";

    $passwordHash = Security::passwordHash($plainPassword);

    $db = Database::get();

    if ($db->has('sys_admin', ['username' => $username])) {
        die("❌ 错误：管理员 '{$username}' 已存在！\n");
    }

    $db->insert('sys_admin', [
        'username' => $username,
        'password' => $passwordHash,
        'name' => $name,
        'email' => $email,
        'status' => 1,
        'created_at' => date('Y-m-d H:i:s'),
    ]);

    $adminId = (int)$db->id();

    $db->insert('sys_admin_role', [
        'admin_id' => $adminId,
        'role_id' => 1,
    ]);

    echo "✅ 管理员创建成功！\n";
    echo "--------------------------\n";
    echo "账号: {$username}\n";
    echo "密码: {$plainPassword}\n";
    echo "角色: 1\n";
    echo "--------------------------\n";

} catch (\Throwable $e) {
    echo "❌ 发生错误: " . $e->getMessage() . "\n";
    exit(1);
}
