<?php
/**
 * 路由配置文件
 * 支持分文件配置
 *
 * 格式: 'HTTP_METHOD /path' => ['Controller', 'action']
 * 支持动态参数: {id}, {slug}, {page} 等
 */

// 合并前台路由
$frontRoutes = array_merge(
    require __DIR__ . '/routes/front/home.php',
    require __DIR__ . '/routes/front/search.php',
    require __DIR__ . '/routes/front/article.php',
    require __DIR__ . '/routes/front/category.php',
    require __DIR__ . '/routes/front/seo.php'
);

// 合并后台路由
$adminRoutes = array_merge(
    require __DIR__ . '/routes/admin/auth.php',
    require __DIR__ . '/routes/admin/system.php',
    require __DIR__ . '/routes/admin/content.php',
    require __DIR__ . '/routes/admin/visitor.php',
    require __DIR__ . '/routes/admin/setting.php'
);

// 合并所有路由
return array_merge($frontRoutes, $adminRoutes);