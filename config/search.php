<?php
declare(strict_types=1);

/**
 * 搜索模块配置
 */
return [
    'search' => [
        // 缓存配置
        'cache' => [
            'enabled' => true,           // 是否启用缓存
            'ttl' => 300,                // 缓存TTL（秒）
            'suggestion_ttl' => 300,     // 搜索建议缓存TTL（秒）
        ],

        // 限流配置
        'rate_limit' => [
            'enabled' => true,           // 是否启用限流
            'max_requests' => 60,        // 默认每分钟最大请求数
            'time_window' => 60,         // 时间窗口（秒）
            'block_duration' => 300,     // 拦截时长（秒）
        ],

        // 日志配置
        'log' => [
            'enabled' => true,           // 是否启用日志
            'retention_days' => 30,      // 日志保留天数
        ],

        // 搜索配置
        'search' => [
            'max_results' => 100,        // 最大结果数
            'suggestion_limit' => 5,     // 搜索建议数量
            'cache_results' => true,     // 是否缓存搜索结果
            'cache_suggestions' => true, // 是否缓存搜索建议
        ],

        // 黑名单配置
        'blacklist' => [
            'keywords' => [
                'admin',
                'password',
                'login',
                'config',
                'database',
            ],
            'ips' => [
                // '10.0.0.0/8', // 示例：内网IP段
            ],
        ],

        // 性能配置
        'performance' => [
            'query_timeout' => 5,        // 查询超时时间（秒）
            'max_concurrent' => 10,      // 最大并发数
            'memory_limit' => '128M',    // 内存限制
        ],

        // 安全配置
        'security' => [
            'sanitize_keywords' => true, // 是否清理关键词
            'max_keyword_length' => 100, // 最大关键词长度
            'block_special_chars' => true, // 是否拦截特殊字符
        ],
    ],
];
