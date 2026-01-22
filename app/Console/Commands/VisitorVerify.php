<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Database;
use App\Services\VisitorLogService;
use App\Services\VisitorStatsService;
use App\Services\AnomalyDetectionService;

/**
 * 访客日志系统验证命令
 *
 * 用于验证系统是否正确安装和配置
 */
class VisitorVerify
{
    /**
     * 命令签名
     */
    protected $signature = 'visitor:verify {--quick : 快速验证}';

    /**
     * 命令描述
     */
    protected $description = '验证访客日志系统';

    /**
     * 执行命令
     *
     * @return int 退出码
     */
    public function handle(): int
    {
        $quick = $this->option('quick') ?? false;

        $this->info("开始验证访客日志系统...");
        $this->info("模式: " . ($quick ? '快速验证' : '完整验证'));
        $this->info("");

        $errors = [];
        $warnings = [];
        $success = [];

        // 1. 验证数据库连接
        $this->info("1. 验证数据库连接...");
        if (!$this->verifyDatabaseConnection()) {
            $errors[] = "数据库连接失败";
        } else {
            $success[] = "数据库连接正常";
        }

        // 2. 验证数据库表
        $this->info("2. 验证数据库表...");
        $tableErrors = $this->verifyDatabaseTables();
        if (!empty($tableErrors)) {
            $errors = array_merge($errors, $tableErrors);
        } else {
            $success[] = "所有数据库表存在";
        }

        // 3. 验证日志目录
        $this->info("3. 验证日志目录...");
        if (!$this->verifyLogDirectory()) {
            $warnings[] = "日志目录不存在或不可写";
        } else {
            $success[] = "日志目录正常";
        }

        // 4. 验证配置文件
        $this->info("4. 验证配置文件...");
        $configErrors = $this->verifyConfiguration();
        if (!empty($configErrors)) {
            $errors = array_merge($errors, $configErrors);
        } else {
            $success[] = "配置文件正常";
        }

        // 5. 验证中间件注册
        $this->info("5. 验证中间件注册...");
        if (!$this->verifyMiddlewareRegistration()) {
            $warnings[] = "访客日志中间件未注册";
        } else {
            $success[] = "中间件注册正常";
        }

        // 6. 验证定时任务
        $this->info("6. 验证定时任务...");
        $cronErrors = $this->verifyCronJobs();
        if (!empty($cronErrors)) {
            $warnings = array_merge($warnings, $cronErrors);
        } else {
            $success[] = "定时任务配置正常";
        }

        // 7. 验证数据写入（快速模式跳过）
        if (!$quick) {
            $this->info("7. 验证数据写入...");
            $writeErrors = $this->verifyDataWrite();
            if (!empty($writeErrors)) {
                $errors = array_merge($errors, $writeErrors);
            } else {
                $success[] = "数据写入正常";
            }

            // 8. 验证数据读取
            $this->info("8. 验证数据读取...");
            $readErrors = $this->verifyDataRead();
            if (!empty($readErrors)) {
                $errors = array_merge($errors, $readErrors);
            } else {
                $success[] = "数据读取正常";
            }

            // 9. 验证统计计算
            $this->info("9. 验证统计计算...");
            $statsErrors = $this->verifyStatsCalculation();
            if (!empty($statsErrors)) {
                $errors = array_merge($errors, $statsErrors);
            } else {
                $success[] = "统计计算正常";
            }

            // 10. 验证异常检测
            $this->info("10. 验证异常检测...");
            $anomalyErrors = $this->verifyAnomalyDetection();
            if (!empty($anomalyErrors)) {
                $errors = array_merge($errors, $anomalyErrors);
            } else {
                $success[] = "异常检测正常";
            }
        }

        // 输出结果
        $this->info("");
        $this->info("========================================");
        $this->info("验证结果:");
        $this->info("========================================");

        if (!empty($success)) {
            $this->info("");
            $this->info("✓ 成功 (" . count($success) . "):");
            foreach ($success as $msg) {
                $this->info("  - {$msg}");
            }
        }

        if (!empty($warnings)) {
            $this->info("");
            $this->info("⚠ 警告 (" . count($warnings) . "):");
            foreach ($warnings as $msg) {
                $this->info("  - {$msg}");
            }
        }

        if (!empty($errors)) {
            $this->info("");
            $this->info("✗ 错误 (" . count($errors) . "):");
            foreach ($errors as $msg) {
                $this->error("  - {$msg}");
            }
        }

        $this->info("");
        $this->info("========================================");

        if (empty($errors)) {
            $this->info("✓ 验证通过");
            return 0;
        } else {
            $this->error("✗ 验证失败");
            return 1;
        }
    }

    /**
     * 验证数据库连接
     *
     * @return bool
     */
    private function verifyDatabaseConnection(): bool
    {
        try {
            $db = Database::get();
            $result = $db->query("SELECT 1")->fetchAll();
            return !empty($result);
        } catch (\Exception $e) {
            $this->error("  数据库连接失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 验证数据库表
     *
     * @return array
     */
    private function verifyDatabaseTables(): array
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
            $db = Database::get();

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

        } catch (\Exception $e) {
            $errors[] = "验证表失败: " . $e->getMessage();
        }

        return $errors;
    }

    /**
     * 验证日志目录
     *
     * @return bool
     */
    private function verifyLogDirectory(): bool
    {
        $logDir = dirname(__DIR__, 3) . '/storage/logs/visitor';

        if (!is_dir($logDir)) {
            // 尝试创建
            if (!@mkdir($logDir, 0755, true)) {
                $this->error("  无法创建日志目录: {$logDir}");
                return false;
            }
        }

        if (!is_writable($logDir)) {
            $this->error("  日志目录不可写: {$logDir}");
            return false;
        }

        return true;
    }

    /**
     * 验证配置文件
     *
     * @return array
     */
    private function verifyConfiguration(): array
    {
        $errors = [];

        // 验证config/visitor.php
        $configFile = dirname(__DIR__, 2) . '/config/visitor.php';
        if (!file_exists($configFile)) {
            $errors[] = "配置文件不存在: {$configFile}";
        }

        // 验证.env中的数据库配置
        $dbHost = \App\Core\App::getConfig('db.host');
        $dbName = \App\Core\App::getConfig('db.database');
        $dbUser = \App\Core\App::getConfig('db.username');
        $dbPass = \App\Core\App::getConfig('db.password');

        if (empty($dbHost) || empty($dbName) || empty($dbUser)) {
            $errors[] = "数据库配置不完整";
        }

        // 验证可信代理配置
        $enableTrustedProxy = \App\Core\App::getConfig('visitor.ip.enable_trusted_proxy', false);
        if ($enableTrustedProxy) {
            $trustedProxies = \App\Core\App::getConfig('visitor.ip.trusted_proxies', []);
            if (empty($trustedProxies)) {
                $errors[] = "启用可信代理模式但未配置白名单";
            }
        }

        return $errors;
    }

    /**
     * 验证中间件注册
     *
     * @return bool
     */
    private function verifyMiddlewareRegistration(): bool
    {
        $routerFile = dirname(__DIR__, 2) . '/Http/Router.php';

        if (!file_exists($routerFile)) {
            $this->error("  路由文件不存在: {$routerFile}");
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
     *
     * @return array
     */
    private function verifyCronJobs(): array
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
     *
     * @return array
     */
    private function verifyDataWrite(): array
    {
        $errors = [];

        try {
            // 测试写入访客日志
            $result = VisitorLogService::record(
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
            $db = Database::get();
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

        } catch (\Exception $e) {
            $errors[] = "数据写入测试失败: " . $e->getMessage();
        }

        return $errors;
    }

    /**
     * 验证数据读取
     *
     * @return array
     */
    private function verifyDataRead(): array
    {
        $errors = [];

        try {
            // 测试读取访客日志
            $logs = VisitorLogService::getLogs([], 1);
            if (empty($logs)) {
                $errors[] = "访客日志读取失败";
            }

            // 测试读取统计
            $stats = VisitorStatsService::getDailyStats(date('Y-m-d'));
            if (empty($stats)) {
                $errors[] = "统计读取失败";
            }

            // 测试读取告警
            $alerts = AnomalyDetectionService::getPendingAlerts(1);
            if (empty($alerts)) {
                $errors[] = "告警读取失败";
            }

        } catch (\Exception $e) {
            $errors[] = "数据读取测试失败: " . $e->getMessage();
        }

        return $errors;
    }

    /**
     * 验证统计计算
     *
     * @return array
     */
    private function verifyStatsCalculation(): array
    {
        $errors = [];

        try {
            // 测试统计计算
            $date = date('Y-m-d', strtotime('-1 day'));
            $result = VisitorStatsService::updateDailyStats($date);

            if (!$result) {
                $errors[] = "统计计算失败";
            }

            // 测试时间范围统计
            $rangeStats = VisitorStatsService::getRangeStats(
                date('Y-m-d', strtotime('-7 days')),
                date('Y-m-d')
            );

            if (empty($rangeStats['daily'])) {
                $errors[] = "时间范围统计失败";
            }

            // 测试实时统计
            $realTimeStats = VisitorStatsService::getRealTimeStats(24);

            if (empty($realTimeStats)) {
                $errors[] = "实时统计失败";
            }

        } catch (\Exception $e) {
            $errors[] = "统计计算测试失败: " . $e->getMessage();
        }

        return $errors;
    }

    /**
     * 验证异常检测
     *
     * @return array
     */
    private function verifyAnomalyDetection(): array
    {
        $errors = [];

        try {
            // 测试异常检测
            $date = date('Y-m-d', strtotime('-1 day'));
            $anomalies = AnomalyDetectionService::detectAnomalies($date);

            if (empty($anomalies)) {
                $errors[] = "异常检测失败";
            }

            // 测试告警处理
            $pendingAlerts = AnomalyDetectionService::getPendingAlerts(1);
            if (!empty($pendingAlerts)) {
                $alertId = $pendingAlerts[0]['id'];
                $result = AnomalyDetectionService::handleAlert($alertId, 'handled');

                if (!$result) {
                    $errors[] = "告警处理失败";
                }
            }

            // 测试告警统计
            $alertStats = AnomalyDetectionService::getAlertStats(
                date('Y-m-d', strtotime('-7 days')),
                date('Y-m-d')
            );

            if (empty($alertStats)) {
                $errors[] = "告警统计失败";
            }

        } catch (\Exception $e) {
            $errors[] = "异常检测测试失败: " . $e->getMessage();
        }

        return $errors;
    }
}
