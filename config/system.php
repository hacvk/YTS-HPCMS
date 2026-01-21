<?php
declare(strict_types=1);

/**
 * 后台系统信息配置
 */
return [
    'system' => [
        'name' => 'YTS-HPCMS',
        'version' => '1.0.0',
        'release_date' => '2024-07-30',
        'developer' => '研昌技服',
        'developer_url' => 'https://lingxian.tech',
        'copyright' => '© ' . date('Y') . ' Yanchang Tech-Service. All rights reserved.',
    ],
    'log' => [
        'driver' => 'database',  // 使用数据库驱动存储日志
    ]
];
