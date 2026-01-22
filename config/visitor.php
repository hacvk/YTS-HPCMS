<?php

/**
 * 访客日志系统配置
 *
 * 安全配置:
 * - trusted_proxies: 可信代理IP白名单
 * - enable_trusted_proxy: 是否启用可信代理模式
 *
 * 性能配置:
 * - cache_ttl: 缓存时间
 * - batch_size: 批量处理大小
 *
 * 数据生命周期:
 * - retention_days: 保留天数
 */
return [
    // 日志配置
    'log' => [
        'dir' => 'storage/logs/visitor',
        'format' => 'visitor-{date}.log',
        'retention_days' => 7,
        'max_size' => 100 * 1024 * 1024,  // 100MB
    ],

    // 统计配置
    'stats' => [
        'cache_ttl' => 300,  // 缓存5分钟
        'batch_size' => 1000,  // 批量处理大小
        'real_time_hours' => 24,  // 实时统计小时数
    ],

    // 异常检测配置
    'anomaly' => [
        'high_frequency' => [
            'requests_per_minute' => 60,
            'requests_per_hour' => 300,
        ],
        'suspicious_ip' => [
            'error_rate' => 0.5,
            'min_requests' => 10,
        ],
        'bot_detection' => [
            'min_requests' => 5,
        ],
        'abnormal_referrer' => [
            'min_requests' => 20,
        ],
    ],

    // IP配置
    'ip' => [
        // 是否启用可信代理模式
        'enable_trusted_proxy' => false,

        // 可信代理IP白名单
        'trusted_proxies' => [
            '127.0.0.1',  // 数据库服务器
            '127.0.0.1',
            '::1',
        ],

        // 是否记录原始IP（用于调试）
        'log_raw_ip' => false,
    ],

    // Session配置
    'session' => [
        'cookie_name' => 'visitor_session',
        'cookie_lifetime' => 1800,  // 30分钟
        'cookie_secure' => true,  // 仅HTTPS
        'cookie_httponly' => true,  // HttpOnly
        'cookie_samesite' => 'Lax',  // SameSite
    ],

    // 数据清理配置
    'cleanup' => [
        'visitor_logs' => [
            'retention_days' => 30,
            'cleanup_type' => 'delete',  // delete, partition, archive
        ],
        'visitor_sessions' => [
            'retention_days' => 30,
            'cleanup_type' => 'delete',
        ],
        'visitor_alerts' => [
            'retention_days' => 90,
            'cleanup_type' => 'delete',
        ],
        'visitor_stats_daily' => [
            'retention_days' => 3650,  // 10年
            'cleanup_type' => 'archive',
        ],
    ],

    // 定时任务配置
    'cron' => [
        // 聚合任务
        'aggregate' => [
            'enabled' => true,
            'schedule' => '*/5 * * * *',  // 每5分钟
            'timeout' => 300,  // 5分钟超时
        ],

        // 每日统计
        'daily_stats' => [
            'enabled' => true,
            'schedule' => '0 2 * * *',  // 每日凌晨2点
            'timeout' => 600,  // 10分钟超时
        ],

        // 异常检测
        'anomaly_detection' => [
            'enabled' => true,
            'schedule' => '0 3 * * *',  // 每日凌晨3点
            'timeout' => 300,  // 5分钟超时
        ],

        // 数据清理
        'cleanup' => [
            'enabled' => true,
            'schedule' => '0 4 * * *',  // 每日凌晨4点
            'timeout' => 600,  // 10分钟超时
        ],

        // 统计报告
        'report' => [
            'enabled' => true,
            'schedule' => '0 5 * * *',  // 每日凌晨5点
            'timeout' => 300,  // 5分钟超时
        ],
    ],

    // 性能配置
    'performance' => [
        // 内存限制 (MB)
        'memory_limit' => 100,

        // 批量处理大小
        'batch_size' => 1000,

        // 流式处理阈值 (行数)
        'stream_threshold' => 10000,

        // 缓存配置
        'cache' => [
            'enabled' => true,
            'ttl' => 300,  // 5分钟
            'prefix' => 'visitor_stats:',
        ],
    ],

    // 监控配置
    'monitoring' => [
        // 启用监控
        'enabled' => true,

        // 监控指标
        'metrics' => [
            'write_performance',  // 写入性能
            'query_performance',  // 查询性能
            'storage_usage',      // 存储使用
            'error_rate',         // 错误率
            'anomaly_count',      // 异常数量
        ],

        // 告警配置
        'alerts' => [
            'enabled' => true,
            'thresholds' => [
                'storage_usage' => 80,  // 存储使用率超过80%
                'error_rate' => 1,      // 错误率超过1%
                'anomaly_count' => 10,  // 异常数量超过10
            ],
            'channels' => [
                'email',
                'wechat',
                'dingtalk',
            ],
        ],
    ],

    // 安全配置
    'security' => [
        // 数据清理
        'sanitize' => [
            'enabled' => true,
            'max_length' => [
                'url' => 500,
                'referrer' => 500,
                'user_agent' => 500,
                'ip' => 45,
            ],
        ],

        // SQL注入防护
        'sql_injection' => [
            'enabled' => true,
            'use_parameterized' => true,
        ],

        // 异常隔离
        'exception_isolation' => [
            'enabled' => true,
            'log_errors' => true,
        ],
    ],

    // 数据库配置
    'database' => [
        // 远程数据库配置
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', 3306),
        'database' => env('DB_DATABASE', 'yycms'),
        'username' => env('DB_USERNAME', 'root'),
        'password' => env('DB_PASSWORD', ''),
        'charset' => env('DB_CHARSET', 'utf8mb4'),
        'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),

        // 连接池配置
        'pool' => [
            'enabled' => false,
            'size' => 10,
            'timeout' => 5,
        ],

        // 超时配置
        'timeout' => [
            'connect' => 5,
            'read' => 30,
            'write' => 30,
        ],
    ],

    // 缓存配置
    'cache' => [
        'driver' => 'file',  // file, redis, memory
        'file' => [
            'dir' => 'storage/cache/visitor',
            'ttl' => 300,
        ],
        'redis' => [
            'host' => '127.0.0.1',
            'port' => 6379,
            'password' => '',
            'database' => 0,
            'ttl' => 300,
        ],
    ],

    // 日志配置
    'logging' => [
        'enabled' => true,
        'level' => 'info',  // debug, info, warning, error
        'file' => 'storage/logs/visitor_system.log',
        'max_size' => 10 * 1024 * 1024,  // 10MB
        'retention_days' => 30,
    ],

    // 报告配置
    'report' => [
        'enabled' => true,
        'format' => 'json',  // json, csv, html
        'dir' => 'storage/reports/visitor',
        'retention_days' => 365,
    ],

    // 导出配置
    'export' => [
        'enabled' => true,
        'format' => ['csv', 'json', 'excel'],
        'max_rows' => 100000,
        'timeout' => 300,
    ],

    // API配置
    'api' => [
        'enabled' => true,
        'version' => 'v1',
        'rate_limit' => [
            'enabled' => true,
            'requests_per_minute' => 60,
        ],
        'authentication' => [
            'enabled' => false,
            'type' => 'token',  // token, basic
        ],
    ],

    // 前端配置
    'frontend' => [
        'chart' => [
            'library' => 'echarts',  // echarts, chartjs
            'theme' => 'light',
            'refresh_interval' => 300,  // 5分钟
        ],
        'table' => [
            'page_size' => 20,
            'sort_field' => 'timestamp',
            'sort_order' => 'desc',
        ],
        'filter' => [
            'date_range' => true,
            'ip_filter' => true,
            'url_filter' => true,
            'status_filter' => true,
        ],
    ],

    // 数据保留策略
    'retention' => [
        'visitor_logs' => [
            'hot' => 7,      // 热数据: 7天
            'warm' => 30,    // 温数据: 30天
            'cold' => 365,   // 冷数据: 1年
            'archive' => 0,  // 归档: 永久
        ],
        'visitor_sessions' => [
            'hot' => 7,
            'warm' => 30,
            'cold' => 0,
            'archive' => 0,
        ],
        'visitor_alerts' => [
            'hot' => 7,
            'warm' => 90,
            'cold' => 365,
            'archive' => 0,
        ],
        'visitor_stats_daily' => [
            'hot' => 30,
            'warm' => 365,
            'cold' => 3650,
            'archive' => 0,
        ],
    ],

    // 数据归档配置
    'archive' => [
        'enabled' => true,
        'format' => 'json',  // json, csv, sql
        'compression' => 'gzip',  // gzip, zip, none
        'dir' => 'storage/archive/visitor',
        'retention_days' => 3650,
    ],

    // 数据备份配置
    'backup' => [
        'enabled' => true,
        'schedule' => '0 1 * * *',  // 每日凌晨1点
        'format' => 'sql',
        'compression' => 'gzip',
        'dir' => 'storage/backup/visitor',
        'retention_days' => 365,
    ],

    // 数据恢复配置
    'restore' => [
        'enabled' => true,
        'format' => 'sql',
        'dir' => 'storage/backup/visitor',
        'timeout' => 3600,
    ],

    // 数据迁移配置
    'migration' => [
        'enabled' => true,
        'source' => 'file',  // file, database
        'destination' => 'database',
        'batch_size' => 1000,
        'timeout' => 300,
    ],

    // 数据验证配置
    'validation' => [
        'enabled' => true,
        'rules' => [
            'ip' => 'required|ip',
            'url' => 'required|max:500',
            'referrer' => 'max:500',
            'user_agent' => 'max:500',
            'status_code' => 'required|integer|between:100,599',
            'duration' => 'required|numeric|min:0',
            'memory_used' => 'required|numeric|min:0',
        ],
    ],

    // 数据清理策略
    'cleanup_strategy' => [
        'visitor_logs' => [
            'method' => 'delete',  // delete, partition, archive
            'batch_size' => 10000,
            'timeout' => 300,
        ],
        'visitor_sessions' => [
            'method' => 'delete',
            'batch_size' => 10000,
            'timeout' => 300,
        ],
        'visitor_alerts' => [
            'method' => 'delete',
            'batch_size' => 10000,
            'timeout' => 300,
        ],
    ],

    // 数据分区配置
    'partition' => [
        'enabled' => true,
        'type' => 'range',  // range, list, hash
        'field' => 'timestamp',
        'interval' => 'month',  // month, week, day
        'retention_months' => 3,
    ],

    // 数据索引配置
    'index' => [
        'enabled' => true,
        'optimize' => true,
        'rebuild_schedule' => '0 6 * * 0',  // 每周日凌晨6点
    ],

    // 数据压缩配置
    'compression' => [
        'enabled' => true,
        'algorithm' => 'gzip',  // gzip, bzip2, none
        'level' => 6,
        'threshold' => 1024,  // KB
    ],

    // 数据加密配置
    'encryption' => [
        'enabled' => false,
        'algorithm' => 'aes-256-gcm',
        'key' => env('VISITOR_ENCRYPTION_KEY', ''),
    ],

    // 数据脱敏配置
    'masking' => [
        'enabled' => true,
        'fields' => [
            'ip' => 'partial',  // partial, full, none
            'user_agent' => 'none',
            'url' => 'none',
            'referrer' => 'none',
        ],
    ],

    // 数据采样配置
    'sampling' => [
        'enabled' => false,
        'rate' => 0.1,  // 10%采样率
        'method' => 'random',  // random, stratified
    ],

    // 数据聚合配置
    'aggregation' => [
        'enabled' => true,
        'interval' => 'daily',  // daily, hourly, weekly, monthly
        'method' => 'sum',  // sum, avg, count, distinct
        'fields' => [
            'pv' => 'count',
            'uv' => 'distinct',
            'ip_count' => 'distinct',
            'duration' => 'avg',
        ],
    ],

    // 数据分析配置
    'analysis' => [
        'enabled' => true,
        'methods' => [
            'trend',
            'correlation',
            'clustering',
            'anomaly',
        ],
        'retention_days' => 365,
    ],

    // 数据可视化配置
    'visualization' => [
        'enabled' => true,
        'charts' => [
            'line',
            'bar',
            'pie',
            'heatmap',
            'scatter',
        ],
        'refresh_interval' => 300,
    ],

    // 数据导出配置
    'export_config' => [
        'enabled' => true,
        'formats' => ['csv', 'json', 'excel', 'pdf'],
        'max_rows' => 100000,
        'timeout' => 300,
        'compression' => 'zip',
    ],

    // 数据导入配置
    'import' => [
        'enabled' => true,
        'formats' => ['csv', 'json', 'sql'],
        'batch_size' => 1000,
        'timeout' => 300,
        'validation' => true,
    ],

    // 数据同步配置
    'sync' => [
        'enabled' => false,
        'source' => 'database',
        'destination' => 'file',
        'interval' => 300,
        'batch_size' => 1000,
    ],

    // 数据归档配置
    'archive_config' => [
        'enabled' => true,
        'format' => 'json',
        'compression' => 'gzip',
        'dir' => 'storage/archive/visitor',
        'retention_days' => 3650,
    ],

    // 数据备份配置
    'backup_config' => [
        'enabled' => true,
        'schedule' => '0 1 * * *',
        'format' => 'sql',
        'compression' => 'gzip',
        'dir' => 'storage/backup/visitor',
        'retention_days' => 365,
    ],

    // 数据恢复配置
    'restore_config' => [
        'enabled' => true,
        'format' => 'sql',
        'dir' => 'storage/backup/visitor',
        'timeout' => 3600,
    ],

    // 数据迁移配置
    'migration_config' => [
        'enabled' => true,
        'source' => 'file',
        'destination' => 'database',
        'batch_size' => 1000,
        'timeout' => 300,
    ],

    // 数据验证配置
    'validation_config' => [
        'enabled' => true,
        'rules' => [
            'ip' => 'required|ip',
            'url' => 'required|max:500',
            'referrer' => 'max:500',
            'user_agent' => 'max:500',
            'status_code' => 'required|integer|between:100,599',
            'duration' => 'required|numeric|min:0',
            'memory_used' => 'required|numeric|min:0',
        ],
    ],

    // 数据清理策略
    'cleanup_strategy_config' => [
        'visitor_logs' => [
            'method' => 'delete',
            'batch_size' => 10000,
            'timeout' => 300,
        ],
        'visitor_sessions' => [
            'method' => 'delete',
            'batch_size' => 10000,
            'timeout' => 300,
        ],
        'visitor_alerts' => [
            'method' => 'delete',
            'batch_size' => 10000,
            'timeout' => 300,
        ],
    ],

    // 数据分区配置
    'partition_config' => [
        'enabled' => true,
        'type' => 'range',
        'field' => 'timestamp',
        'interval' => 'month',
        'retention_months' => 3,
    ],

    // 数据索引配置
    'index_config' => [
        'enabled' => true,
        'optimize' => true,
        'rebuild_schedule' => '0 6 * * 0',
    ],

    // 数据压缩配置
    'compression_config' => [
        'enabled' => true,
        'algorithm' => 'gzip',
        'level' => 6,
        'threshold' => 1024,
    ],

    // 数据加密配置
    'encryption_config' => [
        'enabled' => false,
        'algorithm' => 'aes-256-gcm',
        'key' => env('VISITOR_ENCRYPTION_KEY', ''),
    ],

    // 数据脱敏配置
    'masking_config' => [
        'enabled' => true,
        'fields' => [
            'ip' => 'partial',
            'user_agent' => 'none',
            'url' => 'none',
            'referrer' => 'none',
        ],
    ],

    // 数据采样配置
    'sampling_config' => [
        'enabled' => false,
        'rate' => 0.1,
        'method' => 'random',
    ],

    // 数据聚合配置
    'aggregation_config' => [
        'enabled' => true,
        'interval' => 'daily',
        'method' => 'sum',
        'fields' => [
            'pv' => 'count',
            'uv' => 'distinct',
            'ip_count' => 'distinct',
            'duration' => 'avg',
        ],
    ],

    // 数据分析配置
    'analysis_config' => [
        'enabled' => true,
        'methods' => [
            'trend',
            'correlation',
            'clustering',
            'anomaly',
        ],
        'retention_days' => 365,
    ],

    // 数据可视化配置
    'visualization_config' => [
        'enabled' => true,
        'charts' => [
            'line',
            'bar',
            'pie',
            'heatmap',
            'scatter',
        ],
        'refresh_interval' => 300,
    ],

    // 数据导出配置
    'export_config_config' => [
        'enabled' => true,
        'formats' => ['csv', 'json', 'excel', 'pdf'],
        'max_rows' => 100000,
        'timeout' => 300,
        'compression' => 'zip',
    ],

    // 数据导入配置
    'import_config' => [
        'enabled' => true,
        'formats' => ['csv', 'json', 'sql'],
        'batch_size' => 1000,
        'timeout' => 300,
        'validation' => true,
    ],

    // 数据同步配置
    'sync_config' => [
        'enabled' => false,
        'source' => 'database',
        'destination' => 'file',
        'interval' => 300,
        'batch_size' => 1000,
    ],
];
