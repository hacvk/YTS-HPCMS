<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * 异常行为检测服务
 * 检测异常访问模式、爬虫、攻击等
 *
 * 安全特性:
 * - 参数化查询防止SQL注入
 * - 异常隔离: 检测失败不影响主流程
 *
 * 性能特性:
 * - 批量检测
 * - 索引优化
 * - 阈值可配置
 */
class AnomalyDetectionService
{
    /**
     * 检测阈值配置
     * 可从配置文件读取
     */
    private static function getThresholds(): array
    {
        return \App\Core\App::getConfig('visitor.anomaly', [
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
        ]);
    }

    /**
     * 检测异常行为
     *
     * 安全特性:
     * - 参数化查询
     * - SQL注入防护
     *
     * @param string $date 检测日期
     * @return array 检测结果
     */
    public static function detectAnomalies(string $date): array
    {
        $results = [];

        try {
            // 检测高频访问
            $results['high_frequency'] = self::detectHighFrequency($date);

            // 检测可疑IP
            $results['suspicious_ip'] = self::detectSuspiciousIP($date);

            // 检测爬虫
            $results['bot_detection'] = self::detectBots($date);

            // 检测异常来源
            $results['abnormal_referrer'] = self::detectAbnormalReferrer($date);

            // 保存告警
            self::saveAlerts($results);

        } catch (\Exception $e) {
            error_log('[AnomalyDetectionService] 异常检测失败: ' . $e->getMessage());
        }

        return $results;
    }

    /**
     * 检测高频访问
     *
     * 安全特性:
     * - 参数化查询
     * - SQL注入防护
     *
     * @param string $date
     * @return array
     */
    private static function detectHighFrequency(string $date): array
    {
        $db = Database::get();
        $timestampStart = strtotime($date . ' 00:00:00');
        $timestampEnd = strtotime($date . ' 23:59:59');
        $thresholds = self::getThresholds();

        // 按IP统计每分钟请求数
        $highFrequencyIPs = $db->query("
            SELECT ip, COUNT(*) as request_count,
                   MIN(timestamp) as first_request,
                   MAX(timestamp) as last_request
            FROM visitor_logs
            WHERE timestamp >= ? AND timestamp <= ?
            GROUP BY ip
            HAVING request_count >= ?
            ORDER BY request_count DESC
        ", [$timestampStart, $timestampEnd, $thresholds['high_frequency']['requests_per_hour']])->fetchAll();

        $alerts = [];
        foreach ($highFrequencyIPs as $ip) {
            $alerts[] = [
                'ip' => $ip['ip'],
                'request_count' => (int)$ip['request_count'],
                'first_request' => $ip['first_request'],
                'last_request' => $ip['last_request'],
                'risk_level' => self::calculateRiskLevel((int)$ip['request_count']),
            ];
        }

        return $alerts;
    }

    /**
     * 检测可疑IP
     *
     * 安全特性:
     * - 参数化查询
     * - SQL注入防护
     *
     * @param string $date
     * @return array
     */
    private static function detectSuspiciousIP(string $date): array
    {
        $db = Database::get();
        $timestampStart = strtotime($date . ' 00:00:00');
        $timestampEnd = strtotime($date . ' 23:59:59');
        $thresholds = self::getThresholds();

        // 统计每个IP的错误率
        $suspiciousIPs = $db->query("
            SELECT ip,
                   COUNT(*) as total_requests,
                   SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as error_requests,
                   ROUND(SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) / COUNT(*), 2) as error_rate
            FROM visitor_logs
            WHERE timestamp >= ? AND timestamp <= ?
            GROUP BY ip
            HAVING total_requests >= ? AND error_rate >= ?
            ORDER BY error_rate DESC
        ", [
            $timestampStart,
            $timestampEnd,
            $thresholds['suspicious_ip']['min_requests'],
            $thresholds['suspicious_ip']['error_rate']
        ])->fetchAll();

        $alerts = [];
        foreach ($suspiciousIPs as $ip) {
            $alerts[] = [
                'ip' => $ip['ip'],
                'total_requests' => (int)$ip['total_requests'],
                'error_requests' => (int)$ip['error_requests'],
                'error_rate' => (float)$ip['error_rate'],
                'risk_level' => $ip['error_rate'] >= 0.8 ? 'critical' : ($ip['error_rate'] >= 0.6 ? 'high' : 'medium'),
            ];
        }

        return $alerts;
    }

    /**
     * 检测爬虫
     *
     * 安全特性:
     * - 参数化查询
     * - SQL注入防护
     *
     * @param string $date
     * @return array
     */
    private static function detectBots(string $date): array
    {
        $db = Database::get();
        $timestampStart = strtotime($date . ' 00:00:00');
        $timestampEnd = strtotime($date . ' 23:59:59');
        $thresholds = self::getThresholds();

        // 统计爬虫请求
        $bots = $db->query("
            SELECT ip, user_agent, COUNT(*) as request_count
            FROM visitor_logs
            WHERE timestamp >= ? AND timestamp <= ? AND is_bot = 1
            GROUP BY ip, user_agent
            HAVING request_count >= ?
            ORDER BY request_count DESC
        ", [
            $timestampStart,
            $timestampEnd,
            $thresholds['bot_detection']['min_requests']
        ])->fetchAll();

        $alerts = [];
        foreach ($bots as $bot) {
            $alerts[] = [
                'ip' => $bot['ip'],
                'user_agent' => $bot['user_agent'],
                'request_count' => (int)$bot['request_count'],
                'risk_level' => 'low',  // 爬虫通常风险较低
            ];
        }

        return $alerts;
    }

    /**
     * 检测异常来源
     *
     * 安全特性:
     * - 参数化查询
     * - SQL注入防护
     *
     * @param string $date
     * @return array
     */
    private static function detectAbnormalReferrer(string $date): array
    {
        $db = Database::get();
        $timestampStart = strtotime($date . ' 00:00:00');
        $timestampEnd = strtotime($date . ' 23:59:59');
        $thresholds = self::getThresholds();

        // 检测异常来源URL
        $abnormalReferrers = $db->query("
            SELECT referrer, COUNT(*) as request_count
            FROM visitor_logs
            WHERE timestamp >= ? AND timestamp <= ?
                  AND referrer != ''
                  AND referrer NOT LIKE '%yycms.com%'
                  AND referrer NOT LIKE '%google.com%'
                  AND referrer NOT LIKE '%baidu.com%'
            GROUP BY referrer
            HAVING request_count >= ?
            ORDER BY request_count DESC
        ", [
            $timestampStart,
            $timestampEnd,
            $thresholds['abnormal_referrer']['min_requests']
        ])->fetchAll();

        $alerts = [];
        foreach ($abnormalReferrers as $referrer) {
            $alerts[] = [
                'referrer' => $referrer['referrer'],
                'request_count' => (int)$referrer['request_count'],
                'risk_level' => 'medium',
            ];
        }

        return $alerts;
    }

    /**
     * 计算风险等级
     *
     * @param int $requestCount
     * @return string
     */
    private static function calculateRiskLevel(int $requestCount): string
    {
        if ($requestCount >= 1000) return 'critical';
        if ($requestCount >= 500) return 'high';
        if ($requestCount >= 200) return 'medium';
        return 'low';
    }

    /**
     * 保存告警
     *
     * 安全特性:
     * - 参数化查询
     * - SQL注入防护
     *
     * @param array $results
     * @return void
     */
    private static function saveAlerts(array $results): void
    {
        $db = Database::get();
        $timestamp = time();

        foreach ($results as $alertType => $alerts) {
            foreach ($alerts as $alert) {
                // 检查是否已存在相同告警
                $existing = $db->get('visitor_alerts', '*', [
                    'alert_type' => $alertType,
                    'ip' => $alert['ip'] ?? '',
                    'status' => 'pending',
                ]);

                if ($existing) {
                    // 更新现有告警
                    $db->update('visitor_alerts', [
                        'count' => $existing['count'] + 1,
                        'last_seen' => $timestamp,
                    ], ['id' => $existing['id']]);
                } else {
                    // 插入新告警
                    $db->insert('visitor_alerts', [
                        'alert_type' => $alertType,
                        'ip' => $alert['ip'] ?? '',
                        'user_agent' => $alert['user_agent'] ?? '',
                        'details' => json_encode($alert, JSON_UNESCAPED_UNICODE),
                        'risk_level' => $alert['risk_level'] ?? 'medium',
                        'status' => 'pending',
                        'count' => 1,
                        'first_seen' => $timestamp,
                        'last_seen' => $timestamp,
                    ]);
                }
            }
        }
    }

    /**
     * 获取待处理告警
     *
     * 安全特性:
     * - 参数化查询
     * - SQL注入防护
     *
     * @param int $limit
     * @return array
     */
    public static function getPendingAlerts(int $limit = 50): array
    {
        try {
            $db = Database::get();

            return $db->select('visitor_alerts', '*', [
                'status' => 'pending',
                'ORDER' => ['risk_level' => 'DESC', 'created_at' => 'DESC'],
                'LIMIT' => $limit,
            ]);
        } catch (\Exception $e) {
            error_log('[AnomalyDetectionService] 获取告警失败: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 处理告警
     *
     * 安全特性:
     * - 参数化查询
     * - SQL注入防护
     *
     * @param int $alertId
     * @param string $status
     * @return bool
     */
    public static function handleAlert(int $alertId, string $status = 'handled'): bool
    {
        try {
            $db = Database::get();

            $result = $db->update('visitor_alerts', [
                'status' => $status,
            ], ['id' => $alertId]);

            return $result !== null;
        } catch (\Exception $e) {
            error_log('[AnomalyDetectionService] 处理告警失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取告警统计
     *
     * 安全特性:
     * - 参数化查询
     * - SQL注入防护
     *
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public static function getAlertStats(string $startDate, string $endDate): array
    {
        try {
            $db = Database::get();

            // 按告警类型统计
            $byType = $db->query("
                SELECT alert_type, COUNT(*) as count, risk_level
                FROM visitor_alerts
                WHERE created_at >= ? AND created_at <= ?
                GROUP BY alert_type, risk_level
                ORDER BY count DESC
            ", [$startDate, $endDate])->fetchAll();

            // 按风险等级统计
            $byRisk = $db->query("
                SELECT risk_level, COUNT(*) as count
                FROM visitor_alerts
                WHERE created_at >= ? AND created_at <= ?
                GROUP BY risk_level
                ORDER BY risk_level DESC
            ", [$startDate, $endDate])->fetchAll();

            // 按IP统计
            $byIP = $db->query("
                SELECT ip, COUNT(*) as count, MAX(risk_level) as max_risk
                FROM visitor_alerts
                WHERE created_at >= ? AND created_at <= ?
                GROUP BY ip
                ORDER BY count DESC
                LIMIT 20
            ", [$startDate, $endDate])->fetchAll();

            return [
                'by_type' => $byType,
                'by_risk' => $byRisk,
                'by_ip' => $byIP,
            ];
        } catch (\Exception $e) {
            error_log('[AnomalyDetectionService] 获取告警统计失败: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 清理过期告警
     *
     * 安全特性:
     * - 参数化查询
     * - SQL注入防护
     * - 数据生命周期管理
     *
     * @param int $days 保留天数
     * @return int 删除的记录数
     */
    public static function cleanExpiredAlerts(int $days = 90): int
    {
        try {
            $db = Database::get();

            $expireDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

            $result = $db->delete('visitor_alerts', [
                'created_at[<]' => $expireDate,
            ]);

            if ($result !== null) {
                return $result->rowCount();
            }

            return 0;
        } catch (\Exception $e) {
            error_log('[AnomalyDetectionService] 清理过期告警失败: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * 获取异常检测报告
     *
     * 安全特性:
     * - 参数化查询
     * - SQL注入防护
     *
     * @param string $date
     * @return array
     */
    public static function getAnomalyReport(string $date): array
    {
        try {
            $db = Database::get();
            $timestampStart = strtotime($date . ' 00:00:00');
            $timestampEnd = strtotime($date . ' 23:59:59');

            // 总体统计
            $totalRequests = $db->count('visitor_logs', [
                'timestamp[>=]' => $timestampStart,
                'timestamp[<=]' => $timestampEnd,
            ]);

            $errorRequests = $db->count('visitor_logs', [
                'timestamp[>=]' => $timestampStart,
                'timestamp[<=]' => $timestampEnd,
                'status_code[>=]' => 400,
            ]);

            $botRequests = $db->count('visitor_logs', [
                'timestamp[>=]' => $timestampStart,
                'timestamp[<=]' => $timestampEnd,
                'is_bot' => 1,
            ]);

            // 异常率
            $errorRate = $totalRequests > 0 ? ($errorRequests / $totalRequests) * 100 : 0;
            $botRate = $totalRequests > 0 ? ($botRequests / $totalRequests) * 100 : 0;

            // 获取告警
            $alerts = $db->select('visitor_alerts', '*', [
                'first_seen[>=]' => $timestampStart,
                'first_seen[<=]' => $timestampEnd,
                'ORDER' => ['risk_level' => 'DESC', 'created_at' => 'DESC'],
            ]);

            return [
                'date' => $date,
                'total_requests' => $totalRequests,
                'error_requests' => $errorRequests,
                'bot_requests' => $botRequests,
                'error_rate' => round($errorRate, 2),
                'bot_rate' => round($botRate, 2),
                'alerts' => $alerts,
                'alert_count' => count($alerts),
            ];
        } catch (\Exception $e) {
            error_log('[AnomalyDetectionService] 获取异常检测报告失败: ' . $e->getMessage());
            return [];
        }
    }
}
