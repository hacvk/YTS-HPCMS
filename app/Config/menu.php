<?php
// app/Config/menu.php
return [
    [
        'title' => '快捷导航',
        'icon'  => 'star',
        'children' => [
            ['title' => '单页管理', 'route' => '/admin/page', 'perm' => 'admin:page:view'],
            ['title' => '缓存管理', 'route' => '/admin/cache', 'perm' => 'admin:cache:view'],
        ],
        'is_shortcut' => true, // 标识为快捷导航组
    ],
    [
        'title' => '控制台',
        'icon'  => 'speedometer2',
        'route' => '/admin/dashboard',
        'perm' => 'admin:dashboard:view'
    ],
    [
        'title' => '内容管理',
        'icon'  => 'file-text',
        'children' => [
            ['title' => '栏目管理', 'route' => '/admin/category', 'perm' => 'admin:category:view'],
            ['title' => '文章管理', 'route' => '/admin/article', 'perm' => 'admin:article:view'],
            ['title' => '单页管理', 'route' => '/admin/page', 'perm' => 'admin:page:view'],
            ['title' => '专家管理', 'route' => '/admin/doctor', 'perm' => 'admin:doctor:view'], // 后续开发
        ]
    ],

    [
        'title' => '系统设置',
        'icon'  => 'gear',
        'children' => [
            ['title' => '基本设置', 'route' => '/admin/setting', 'perm' => 'admin:setting:view'],
            ['title' => '角色管理', 'route' => '/admin/role', 'perm' => 'admin:role:view'],
            ['title' => '管理员管理', 'route' => '/admin/admin', 'perm' => 'admin:admin:view'],
            ['title' => '操作日志', 'route' => '/admin/log', 'perm' => 'admin:log:view'],
            ['title' => '缓存管理', 'route' => '/admin/cache', 'perm' => 'admin:cache:view'],
        ]
    ],

    [
        'title' => '资源管理',
        'icon'  => 'folder',
        'children' => [
            ['title' => '附件管理', 'route' => '/admin/attachment', 'perm' => 'admin:attachment:view'],
            ['title' => 'Banner管理', 'route' => '/admin/banner', 'perm' => 'admin:banner:view'],
            ['title' => '友情链接管理', 'route' => '/admin/friendlink', 'perm' => 'admin:friendlink:view'],
        ]
    ],

    [
        'title' => '系统',
        'icon'  => 'info-circle',
        'children' => [
            ['title' => '关于系统', 'route' => '/admin/system/about', 'perm' => 'admin:system:view'],
        ]
    ]
];