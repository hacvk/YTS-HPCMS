<?php
/**
 * 认证路由
 */
return [
    'GET /admin' => ['Admin\\LoginController', 'index'],
    'GET /admin/login/captcha' => ['Admin\\LoginController', 'captcha'],
    'POST /admin/login/login' => ['Admin\\LoginController', 'login'],
    'GET /admin/logout' => ['Admin\\LoginController', 'logout'],
    'GET /admin/dashboard' => ['Admin\\DashboardController', 'index'],
];