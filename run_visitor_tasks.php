<?php
declare(strict_types=1);

/**
 * 访客日志系统任务执行脚本
 *
 * 用法：
 * php run_visitor_tasks.php aggregate --date=2026-01-22
 * php run_visitor_tasks.php daily-stats --date=2026-01-22
 * php run_visitor_tasks.php detect-anomalies --date=2026-01-22
 * php run_visitor_tasks.php cleanup
 * php run_visitor_tasks.php verify --quick
 */

// 引入自动加载器
require_once __DIR__ . '/app/Core/Autoloader.php';

// 注册自动加载器
$autoloader = new App\Core\Autoloader();
$autoloader->register();

// 引入配置文件
$config = require __DIR__ . '/config.php';

// 初始化应用
$app = new App\Core\App($config);

// 解析命令行参数
$command = $argv[1] ?? 'help';
$options = [];

for ($i = 2; $i < count($argv); $i++) {
    if (str_starts_with($argv[$i], '--')) {
        $parts = explode('=', substr($argv[$i], 2), 2);
        $key = $parts[0];
        $value = $parts[1] ?? true;
        $options[$key] = $value;
    }
}

// 执行命令
switch ($command) {
    case 'aggregate':
        aggregateLogs($options);
        break;
    case 'daily-stats':
        calculateDailyStats($options);
        break;
    case 'detect-anomalies':
        detectAnomalies($options);
        break;
    case 'cleanup':
        cleanupData($options);
        break;
    case 'verify':
        verifySystem($options);
        break;
    case 'help':
    default:
        showHelp();
        break;
}

/**
 * 聚合日志到数据库
 */
function aggregateLogs(array $options): void
{
    $date = $options['date'] ?? date('Y-m-d', strtotime('-1 day'));
    $force = isset($options['force']);

    echo "开始聚合访客日志，日期: {$date}\n";

    // 验证日期格式
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        echo "错误: 无效的日期格式: {$date}\n";
        exit(1);
    }

    try {
        // 1. 读取日志文件
        $logFile = getLogFile($date);
        if (!file_exists($logFile)) {
            echo "警告: 日志文件不存在: {$logFile}\n";
            exit(0);
        }

        // 2. 检查是否已聚合（除非强制）
        if (!$force) {
            $db = App\Core\Database::get();
            $existing = $db->get('visitor_stats_daily', '*', ['date' => $date]);
            if ($existing) {
                echo "该日期的统计已存在，跳过聚合\n";
                exit(0);
            }
        }

        // 3. 解析日志并写入数据库
        $count = processLogFile($logFile);
        echo "处理 {$count} 条日志记录\n";

        // 4. 更新每日统计
        $result = App\Services\VisitorStatsService::updateDailyStats($date);
        if ($result) {
            echo "每日统计更新成功\n";
        } else {
            echo "错误: 每日统计更新失败\n";
            exit(1);
        }

        // 5. 检测异常行为
        $anomalies = App\Services\AnomalyDetectionService::detectAnomalies($date);
        $anomalyCount = 0;
        foreach ($anomalies as $type => $items) {
            $anomalyCount += count($items);
        }
        echo "检测到 {$anomalyCount} 个异常\n";

        // 6. 清理过期数据
        cleanupOldData();

        echo "聚合完成\n";

    } catch (Exception $e) {
        echo "错误: 聚合失败: " . $e->getMessage() . "\n";
        error_log("[VisitorAggregate] 聚合失败: " . $e->getMessage());
        exit(1);
    }
}

/**
 * 计算每日统计
 */
function calculateDailyStats(array $options): void
{
    $date = $options['date'] ?? date('Y-m-d', strtotime('-1 day'));

    echo "开始计算每日统计，日期: {$date}\n";

    // 验证日期格式
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        echo "错误: 无效的日期格式: {$date}\n";
        exit(1);
    }

    try {
        // 更新每日统计
        $result = App\Services\VisitorStatsService::updateDailyStats($date);
        if ($result) {
            echo "每日统计更新成功\n";
        } else {
            echo "错误: 每日统计更新失败\n";
            exit(1);
        }

    } catch (Exception $e) {
        echo "错误: 统计计算失败: " . $e->getMessage() . "\n";
        error_log("[VisitorDailyStats] 统计计算失败: " . $e->getMessage());
        exit(1);
    }
}

/**
 * 检测异常行为
 */
function detectAnomalies(array $options): void
{
    $date = $options['date'] ?? date('Y-m-d', strtotime('-1 day'));

    echo "开始检测异常行为，日期: {$date}\n";

    // 验证日期格式
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        echo "错误: 无效的日期格式: {$date}\n";
        exit(1);
    }

    try {
        // 检测异常行为
        $anomalies = App\Services\AnomalyDetectionService::detectAnomalies($date);
        $anomalyCount = 0;
        foreach ($anomalies as $type => $items) {
            $anomalyCount += count($items);
            if (count($items) > 0) {
                echo "检测到 {$type} 异常: " . count($items) . " 个\n";
            }
        }
        echo "总共检测到 {$anomalyCount} 个异常\n";

    } catch (Exception $e) {
        echo "错误: 异常检测失败: " . $e->getMessage() . "\n";
        error_log("[VisitorDetectAnomalies] 异常检测失败: " . $e->getMessage());
        exit(1);
    }
}

/**
 * 清理过期数据
 */
function cleanupData(array $options): void
{
    echo "开始清理过期数据\n";

    try {
        // 清理visitor_logs (30天)
        $deletedLogs = App\Services\VisitorLogService::cleanExpiredLogs(30);
        if ($deletedLogs > 0) {
            echo "清理 {$deletedLogs} 条过期日志\n";
        }

        // 清理visitor_sessions (30天)
        $deletedSessions = App\Services\VisitorLogService::cleanExpiredSessions(30);
        if ($deletedSessions > 0) {
            echo "清理 {$deletedSessions} 条过期会话\n";
        }

        // 清理visitor_alerts (90天)
        $deletedAlerts = App\Services\AnomalyDetectionService::cleanExpiredAlerts(90);
        if ($deletedAlerts > 0) {
            echo "清理 {$deletedAlerts} 条过期告警\n";
        }

        // 清理分区 (3个月前)
        $deletedPartitions = App\Services\VisitorLogService::cleanPartitionedLogs();
        if ($deletedPartitions > 0) {
            echo "清理 {$deletedPartitions} 个旧分区\n";
        }

        echo "清理完成\n";

    } catch (Exception $e) {
        echo "错误: 清理过期数据失败: " . $e->getMessage() . "\n";
        error_log("[VisitorCleanup] 清理过期数据失败: " . $e->getMessage());
        exit(1);
    }
}

/**
 * 验证系统
 */
function verifySystem(array $options): void
{
    $quick = isset($options['quick']);

    echo "开始验证访客日志系统...\n";
    echo "模式: " . ($quick ? '快速验证' : '完整验证') . "\n\n";

    $errors = [];
    $warnings = [];
    $success = [];

    // 1. 验证数据库连接
    echo "1. 验证数据库连接...\n";
    if (!verifyDatabaseConnection()) {
        $errors[] = "数据库连接失败";
    } else {
        $success[] = "数据库连接正常";
    }

    // 2. 验证数据库表
    echo "2. 验证数据库表...\n";
    $tableErrors = verifyDatabaseTables();
    if (!empty($tableErrors)) {
        $errors = array_merge($errors, $tableErrors);
    } else {
        $success[] = "所有数据库表存在";
    }

    // 3. 验证日志目录
    echo "3. 验证日志目录...\n";
    if (!verifyLogDirectory()) {
        $warnings[] = "日志目录不存在或不可写";
    } else {
        $success[] = "日志目录正常";
    }

    // 4. 验证配置文件
    echo "4. 验证配置文件...\n";
    $configErrors = verifyConfiguration();
    if (!empty($configErrors)) {
        $errors = array_merge($errors, $configErrors);
    } else {
        $success[] = "配置文件正常";
    }

    // 5. 验证中间件注册
    echo "5. 验证中间件注册...\n";
    if (!verifyMiddlewareRegistration()) {
        $warnings[] = "访客日志中间件未注册";
    } else {
        $success[] = "中间件注册正常";
    }

    // 6. 验证定时任务
    echo "6. 验证定时任务...\n";
    $cronErrors = verifyCronJobs();
    if (!empty($cronErrors)) {
        $warnings = array_merge($warnings, $cronErrors);
    } else {
        $success[] = "定时任务配置正常";
    }

    // 7. 验证数据写入（快速模式跳过）
    if (!$quick) {
        echo "7. 验证数据写入...\n";
        $writeErrors = verifyDataWrite();
        if (!empty($writeErrors)) {
            $errors = array_merge($errors, $writeErrors);
        } else {
            $success[] = "数据写入正常";
        }

        // 8. 验证数据读取
        echo "8. 验证数据读取...\n";
        $readErrors = verifyDataRead();
        if (!empty($readErrors)) {
            $errors = array_merge($errors, $readErrors);
        } else {
            $success[] = "数据读取正常";
        }

        // 9. 验证统计计算
        echo "9. 验证统计计算...\n";
        $statsErrors = verifyStatsCalculation();
        if (!empty($statsErrors)) {
            $errors = array_merge($errors, $statsErrors);
        } else {
            $success[] = "统计计算正常";
        }

        // 10. 验证异常检测
        echo "10. 验证异常检测...\n";
        $anomalyErrors = verifyAnomalyDetection();
        if (!empty($anomalyErrors)) {
            $errors = array_merge($errors, $anomalyErrors);
        } else {
            $success[] = "异常检测正常";
        }
    }

    // 输出结果
    echo "\n";
    echo "========================================\n";
    echo "验证结果:\n";
    echo "========================================\n";

    if (!empty($success)) {
        echo "\n";
        echo "✓ 成功 (" . count($success) . "):\n";
        foreach ($success as $msg) {
            echo "  - {$msg}\n";
        }
    }

    if (!empty($warnings)) {
        echo "\n";
        echo "⚠ 警告 (" . count($warnings) . "):\n";
        foreach ($warnings as $msg) {
            echo "  - {$msg}\n";
        }
    }

    if (!empty($errors)) {
        echo "\n";
        echo "✗ 错误 (" . count($errors) . "):\n";
        foreach ($errors as $msg) {
            echo "  - {$msg}\n";
        }
    }

    echo "\n";
    echo "========================================\n";

    if (empty($errors)) {
        echo "✓ 验证通过\n";
        exit(0);
    } else {
        echo "✗ 验证失败\n";
        exit(1);
    }
}

/**
 * 显示帮助信息
 */
function showHelp(): void
{
    echo "访客日志系统任务执行脚本\n\n";
    echo "用法:\n";
    echo "  php run_visitor_tasks.php aggregate --date=2026-01-22 [--force]\n";
    echo "  php run_visitor_tasks.php daily-stats --date=2026-01-22\n";
    echo "  php run_visitor_tasks.php detect-anomalies --date=2026-01-22\n";
    echo "  php run_visitor_tasks.php cleanup\n";
    echo "  php run_visitor_tasks.php verify [--quick]\n";
    echo "  php run_visitor_tasks.php help\n\n";
    echo "命令说明:\n";
    echo "  aggregate       聚合日志到数据库\n";
    echo "  daily-stats     计算每日统计\n";
    echo "  detect-anomalies 检测异常行为\n";
    echo "  cleanup         清理过期数据\n";
    echo "  verify          验证系统\n";
    echo "  help            显示帮助信息\n\n";
    echo "选项说明:\n";
    echo "  --date=DATE     指定日期 (格式: YYYY-MM-DD)\n";
    echo "  --force         强制重新聚合\n";
    echo "  --quick         快速验证\n";
}

/**
 * 获取日志文件路径
 */
function getLogFile(string $date): string
{
    $logDir = __DIR__ . '/storage/logs/visitor';
    return $logDir . '/visitor-' . $date . '.log';
}

/**
 * 处理日志文件
 */
function processLogFile(string $logFile): int
{
    $db = App\Core\Database::get();
    $count = 0;
    $batch = [];

    $handle = fopen($logFile, 'r');

    try {
        while (($line = fgets($handle)) !== false) {
            $data = parseLogLine($line);

            if ($data) {
                $batch[] = $data;
                $count++;

                // 批量插入
                if (count($batch) >= 1000) {
                    insertBatch($batch);
                    $batch = [];
                }
            }
        }

        // 插入剩余数据
        if (!empty($batch)) {
            insertBatch($batch);
        }
    } finally {
        fclose($handle);
    }

    return $count;
}

/**
 * 解析日志行
 */
function parseLogLine(string $line): ?array
{
    // 查找JSON数据的起始位置（最后一个{）
    $jsonStart = strrpos($line, '{');
    if ($jsonStart === false) {
        return null;
    }

    // 提取JSON部分
    $jsonPart = substr($line, $jsonStart);
    $data = json_decode(trim($jsonPart), true);

    if (!$data) {
        return null;
    }

    // 数据清理
    return [
        'request_id' => sanitizeString($data['request_id'] ?? '', 50),
        'session_id' => sanitizeString($data['session_id'] ?? '', 50),
        'ip' => sanitizeString($data['ip'] ?? '', 45),
        'user_agent' => sanitizeString($data['user_agent'] ?? '', 500),
        'url' => sanitizeString($data['url'] ?? '', 500),
        'referrer' => sanitizeString($data['referrer'] ?? '', 500),
        'method' => sanitizeString($data['method'] ?? 'GET', 10),
        'status_code' => (int)($data['status_code'] ?? 200),
        'duration' => (float)($data['duration'] ?? 0),
        'memory_used' => (float)($data['memory_used'] ?? 0),
        'device_type' => sanitizeString($data['device_type'] ?? 'unknown', 20),
        'browser' => sanitizeString($data['browser'] ?? 'unknown', 50),
        'os' => sanitizeString($data['os'] ?? 'unknown', 50),
        'is_bot' => (int)($data['is_bot'] ?? 0),
        'timestamp' => (int)($data['timestamp'] ?? time()),
    ];
}

/**
 * 清理字符串中的控制字符
 */
function sanitizeString(string $str, int $maxLength = 500): string
{
    // 移除控制字符 (ASCII 0-31, 127)
    $str = preg_replace('/[\x00-\x1F\x7F]/', '', $str);

    // 移除Unicode控制字符
    $str = preg_replace('/\p{C}/u', '', $str);

    // 限制长度
    if (mb_strlen($str) > $maxLength) {
        $str = mb_substr($str, 0, $maxLength);
    }

    return $str;
}

/**
 * 批量插入
 */
function insertBatch(array $batch): void
{
    $db = App\Core\Database::get();

    try {
        $db->action(function ($db) use ($batch) {
            foreach ($batch as $data) {
                $db->insert('visitor_logs', $data);
            }
        });
    } catch (Exception $e) {
        error_log("[VisitorAggregate] 批量插入失败: " . $e->getMessage());
    }
}

/**
 * 清理过期数据
 */
function cleanupOldData(): void
{
    $db = App\Core\Database::get();

    try {
        // 清理visitor_logs (30天)
        $deletedLogs = App\Services\VisitorLogService::cleanExpiredLogs(30);
        if ($deletedLogs > 0) {
            echo "清理 {$deletedLogs} 条过期日志\n";
        }

        // 清理visitor_sessions (30天)
        $deletedSessions = App\Services\VisitorLogService::cleanExpiredSessions(30);
        if ($deletedSessions > 0) {
            echo "清理 {$deletedSessions} 条过期会话\n";
        }

        // 清理visitor_alerts (90天)
        $deletedAlerts = App\Services\AnomalyDetectionService::cleanExpiredAlerts(90);
        if ($deletedAlerts > 0) {
            echo "清理 {$deletedAlerts} 条过期告警\n";
        }

        // 清理分区 (3个月前)
        $deletedPartitions = App\Services\VisitorLogService::cleanPartitionedLogs();
        if ($deletedPartitions > 0) {
            echo "清理 {$deletedPartitions} 个旧分区\n";
        }

    } catch (Exception $e) {
        echo "警告: 清理过期数据失败: " . $e->getMessage() . "\n";
        error_log("[VisitorAggregate] 清理过期数据失败: " . $e->getMessage());
    }
}

/**
 * 验证数据库连接
 */
function verifyDatabaseConnection(): bool
{
    try {
        $db = App\Core\Database::get();
        $result = $db->query("SELECT 1")->fetchAll();
        return !empty($result);
    } catch (Exception $e) {
        echo "  数据库连接失败: " . $e->getMessage() . "\n";
        return false;
    }
}

/**
 * 验证数据库表
 */
function verifyDatabaseTables(): array
{
    $errors = [];
    $requiredTables = [
        'visitor_logs',
        'visitor_stats_daily',
        'visitor_sessions',
        'visitor_alerts',
        'visitor_cleanup_config',
    ];

    try {
        $db = App\Core\Database::get();

        foreach ($requiredTables as $table) {
            $exists = $db->query("
                SELECT 1 FROM information_schema.tables
                WHERE table_schema = DATABASE()
                AND table_name = ?
            ", [$table])->fetchAll();

            if (empty($exists)) {
                $errors[] = "表 {$table} 不存在";
            }
        }

        // 验证visitor_logs表是否有分区
        $partitions = $db->query("
            SELECT PARTITION_NAME
            FROM information_schema.PARTITIONS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'visitor_logs'
            AND PARTITION_NAME IS NOT NULL
        ")->fetchAll();

        if (empty($partitions)) {
            $errors[] = "visitor_logs表没有分区";
        }

    } catch (Exception $e) {
        $errors[] = "验证表失败: " . $e->getMessage();
    }

    return $errors;
}

/**
 * 验证日志目录
 */
function verifyLogDirectory(): bool
{
    $logDir = __DIR__ . '/storage/logs/visitor';

    if (!is_dir($logDir)) {
        // 尝试创建
        if (!@mkdir($logDir, 0755, true)) {
            echo "  无法创建日志目录: {$logDir}\n";
            return false;
        }
    }

    if (!is_writable($logDir)) {
        echo "  日志目录不可写: {$logDir}\n";
        return false;
    }

    return true;
}

/**
 * 验证配置文件
 */
function verifyConfiguration(): array
{
    $errors = [];

    // 验证config/visitor.php
    $configFile = __DIR__ . '/config/visitor.php';
    if (!file_exists($configFile)) {
        $errors[] = "配置文件不存在: {$configFile}";
    }

    // 验证.env中的数据库配置
    $dbHost = App\Core\App::getConfig('db.host');
    $dbName = App\Core\App::getConfig('db.database');
    $dbUser = App\Core\App::getConfig('db.username');
    $dbPass = App\Core\App::getConfig('db.password');

    if (empty($dbHost) || empty($dbName) || empty($dbUser)) {
        $errors[] = "数据库配置不完整";
    }

    // 验证可信代理配置
    $enableTrustedProxy = App\Core\App::getConfig('visitor.ip.enable_trusted_proxy', false);
    if ($enableTrustedProxy) {
        $trustedProxies = App\Core\App::getConfig('visitor.ip.trusted_proxies', []);
        if (empty($trustedProxies)) {
            $errors[] = "启用可信代理模式但未配置白名单";
        }
    }

    return $errors;
}

/**
 * 验证中间件注册
 */
function verifyMiddlewareRegistration(): bool
{
    $routerFile = __DIR__ . '/app/Http/Router.php';

    if (!file_exists($routerFile)) {
        echo "  路由文件不存在: {$routerFile}\n";
        return false;
    }

    $content = file_get_contents($routerFile);

    // 检查是否注册了VisitorLogMiddleware
    if (strpos($content, 'VisitorLogMiddleware') === false) {
        return false;
    }

    return true;
}

/**
 * 验证定时任务
 */
function verifyCronJobs(): array
{
    $warnings = [];

    // 检查crontab
    $cronFile = '/etc/crontab';
    if (!file_exists($cronFile)) {
        $warnings[] = "crontab文件不存在: {$cronFile}";
        return $warnings;
    }

    $content = file_get_contents($cronFile);

    // 检查是否配置了访客日志相关任务
    $requiredCrons = [
        'visitor:aggregate',
        'visitor:daily-stats',
        'visitor:detect-anomalies',
        'visitor:cleanup',
    ];

    foreach ($requiredCrons as $cron) {
        if (strpos($content, $cron) === false) {
            $warnings[] = "定时任务未配置: {$cron}";
        }
    }

    return $warnings;
}

/**
 * 验证数据写入
 */
function verifyDataWrite(): array
{
    $errors = [];

    try {
        // 测试写入访客日志
        $result = App\Services\VisitorLogService::record(
            'test_req_' . uniqid(),
            'test_sess_' . uniqid(),
            10.5,
            1024,
            200
        );

        if (!$result) {
            $errors[] = "访客日志写入失败";
        }

        // 测试写入会话
        $db = App\Core\Database::get();
        $sessionId = 'test_sess_' . uniqid();
        $result = $db->insert('visitor_sessions', [
            'session_id' => $sessionId,
            'ip' => '127.0.0.1',
            'user_agent_hash' => hash('sha256', 'test'),
            'first_visit' => time(),
            'last_visit' => time(),
            'visit_count' => 1,
            'page_count' => 1,
            'total_duration' => 10.5,
            'is_new_visitor' => 1,
            'status' => 'active',
        ]);

        if (!$result) {
            $errors[] = "会话写入失败";
        }

        // 测试写入告警
        $result = $db->insert('visitor_alerts', [
            'alert_type' => 'test',
            'ip' => '127.0.0.1',
            'user_agent' => 'test',
            'details' => json_encode(['test' => 'data']),
            'risk_level' => 'low',
            'status' => 'pending',
            'count' => 1,
            'first_seen' => time(),
            'last_seen' => time(),
        ]);

        if (!$result) {
            $errors[] = "告警写入失败";
        }

    } catch (Exception $e) {
        $errors[] = "数据写入测试失败: " . $e->getMessage();
    }

    return $errors;
}

/**
 * 验证数据读取
 */
function verifyDataRead(): array
{
    $errors = [];

    try {
        // 测试读取访客日志
        $logs = App\Services\VisitorLogService::getLogs([], 1);
        if (empty($logs)) {
            $errors[] = "访客日志读取失败";
        }

        // 测试读取统计
        $stats = App\Services\VisitorStatsService::getDailyStats(date('Y-m-d'));
        if (empty($stats)) {
            $errors[] = "统计读取失败";
        }

        // 测试读取告警
        $alerts = App\Services\AnomalyDetectionService::getPendingAlerts(1);
        if (empty($alerts)) {
            $errors[] = "告警读取失败";
        }

    } catch (Exception $e) {
        $errors[] = "数据读取测试失败: " . $e->getMessage();
    }

    return $errors;
}

/**
 * 验证统计计算
 */
function verifyStatsCalculation(): array
{
    $errors = [];

    try {
        // 测试统计计算
        $date = date('Y-m-d', strtotime('-1 day'));
        $result = App\Services\VisitorStatsService::updateDailyStats($date);

        if (!$result) {
            $errors[] = "统计计算失败";
        }

        // 测试时间范围统计
        $rangeStats = App\Services\VisitorStatsService::getRangeStats(
            date('Y-m-d', strtotime('-7 days')),
            date('Y-m-d')
        );

        if (empty($rangeStats['daily'])) {
            $errors[] = "时间范围统计失败";
        }

        // 测试实时统计
        $realTimeStats = App\Services\VisitorStatsService::getRealTimeStats(24);

        if (empty($realTimeStats)) {
            $errors[] = "实时统计失败";
        }

    } catch (Exception $e) {
        $errors[] = "统计计算测试失败: " . $e->getMessage();
    }

    return $errors;
}

/**
 * 验证异常检测
 */
function verifyAnomalyDetection(): array
{
    $errors = [];

    try {
        // 测试异常检测
        $date = date('Y-m-d', strtotime('-1 day'));
        $anomalies = App\Services\AnomalyDetectionService::detectAnomalies($date);

        if (empty($anomalies)) {
            $errors[] = "异常检测失败";
        }

        // 测试告警处理
        $pendingAlerts = App\Services\AnomalyDetectionService::getPendingAlerts(1);
        if (!empty($pendingAlerts)) {
            $alertId = $pendingAlerts[0]['id'];
            $result = App\Services\AnomalyDetectionService::handleAlert($alertId, 'handled');

            if (!$result) {
                $errors[] = "告警处理失败";
            }
        }

        // 测试告警统计
        $alertStats = App\Services\AnomalyDetectionService::getAlertStats(
            date('Y-m-d', strtotime('-7 days')),
            date('Y-m-d')
        );

        if (empty($alertStats)) {
            $errors[] = "告警统计失败";
        }

    } catch (Exception $e) {
        $errors[] = "异常检测测试失败: " . $e->getMessage();
    }

    return $errors;
}
