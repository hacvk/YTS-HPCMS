<?php
/**
 * 认证路由
 */
return [
    'GET /admin' => ['Admin\\LoginController', 'index'],
    'GET /admin/login/captcha' => ['Admin\\LoginController', 'captcha'],
    'POST /admin/login' => ['Admin\\LoginController', 'login'],
    // 兼容旧路径（可在后续版本移除）
    'POST /admin/login/login' => ['Admin\\LoginController', 'login'],
    'GET /admin/logout' => ['Admin\\LoginController', 'logout'],
    'GET /admin/dashboard' => ['Admin\\DashboardController', 'index'],
    'POST /admin/dashboard/maintenance' => ['Admin\\DashboardController', 'toggleMaintenance'],
];
