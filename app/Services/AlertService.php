<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Core\Database;

/**
 * 告警服务类
 */
class AlertService
{
    /**
     * 告警阈值配置
     *
     * @var array<string, mixed>
     */
    private static array $thresholds = [
        'error_rate' => 0.05, // 5% 错误率
        'slow_query_count' => 10, // 每分钟10条慢查询
        'response_time' => 1000, // 平均响应时间超过1秒
    ];
    
    /**
     * 检查告警阈值
     *
     * @return void
     */
    public static function checkAlerts(): void
    {
        try {
            $db = Database::get();
            
            // 检查数据库连接是否正常以及所需表是否存在
            $tableExists = $db->query("SELECT 1 FROM information_schema.tables WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'performance_metrics' LIMIT 1")->fetchAll();
            
            if (empty($tableExists)) {
                // 如果表不存在，跳过性能监控检查
                return;
            }
            
            // 检查最近5分钟的错误率
            $now = time();
            $fiveMinutesAgo = $now - 5 * 60;
            
            $totalRequests = $db->count('performance_metrics', [
                'timestamp[>=]' => $fiveMinutesAgo
            ]);
            
            $errorRequests = $db->count('performance_metrics', [
                'timestamp[>=]' => $fiveMinutesAgo,
                'status_code[>=]' => 400
            ]);
            
            if ($totalRequests > 0) {
                $errorRate = $errorRequests / $totalRequests;
                if ($errorRate >= self::$thresholds['error_rate']) {
                    self::sendAlert('High Error Rate', "Error rate is {$errorRate*100}% in the last 5 minutes");
                }
            }
            
            // 检查慢查询数量
            $slowQueryCount = $db->count('slow_queries', [
                'timestamp[>=]' => $fiveMinutesAgo
            ]);
            
            if ($slowQueryCount >= self::$thresholds['slow_query_count']) {
                self::sendAlert('High Slow Query Count', "{$slowQueryCount} slow queries in the last 5 minutes");
            }
            
            // 检查平均响应时间
            $avgResponseTime = $db->avg('performance_metrics', 'duration', [
                'timestamp[>=]' => $fiveMinutesAgo
            ]) ?? 0;
            
            if ($avgResponseTime >= self::$thresholds['response_time']) {
                self::sendAlert('High Average Response Time', "Average response time is {$avgResponseTime}ms in the last 5 minutes");
            }
        } catch (\Exception $e) {
            // 添加更详细的错误日志，帮助排查问题
            // 获取管理员ID用于日志记录，并确保类型安全
            $sessionAdminId = $_SESSION['admin_id'] ?? null;
            $adminId = is_int($sessionAdminId) ? $sessionAdminId : (is_numeric($sessionAdminId) ? (int)$sessionAdminId : null);
            $username = $_SESSION['admin_username'] ?? 'unknown';
            
            LogService::error($e, 'alert_check_failed', $adminId, $username);
        }
    }
    
    /**
     * 发送告警
     *
     * @param string $title 告警标题
     * @param string $message 告警消息
     * @return void
     */
    private static function sendAlert(string $title, string $message): void
    {
        // 1. 记录告警到日志
        LogService::critical('ALERT: ' . $title, ['message' => $message]);
        
        // 2. 发送邮件告警（如果配置了SMTP）
        if (App::getConfig('smtp.enabled')) {
            self::sendEmailAlert($title, $message);
        }
        
        // 3. 可以扩展其他告警方式，如短信、Webhook等
    }
    
    /**
     * 发送邮件告警
     *
     * @param string $title 告警标题
     * @param string $message 告警消息
     * @return void
     */
    private static function sendEmailAlert(string $title, string $message): void
    {
        try {
            // 使用 PHP 内置 mail() 函数或简单的 SMTP 实现
            $to = App::getConfig('smtp.admin_email');
            $subject = "[ALERT] {$title}";
            $headers = "From: " . App::getConfig('smtp.from_email') . "\r\n" .
                      "Content-Type: text/plain; charset=utf-8\r\n";
            
            mail($to, $subject, $message, $headers);
        } catch (\Exception $e) {
            LogService::error($e, 'email_alert_failed');
        }
    }
}