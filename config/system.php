<?php
declare(strict_types=1);

/**
 * 后台系统信息配置
 */
return [
    'system' => [
        'name' => 'YTS-HPCMS',
        'version' => '1.9.1',
        'release_date' => '2026-01-23',
        'developer' => '研昌技服',
        'developer_url' => 'https://lingxian.tech',
        'copyright' => '© 2015-2026 Yanchang Tech-Service. All rights reserved.',
    ],
    'log' => [
        'driver' => 'monolog',  // 使用 Monolog 存储日志
        'file' => [
            'dir' => APP_ROOT . '/storage/logs',
            'format' => 'app-{date}.log',
        ],
    ]
];
