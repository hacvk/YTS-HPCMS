<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * 访客统计服务
 * 负责每日统计、分析计算
 *
 * 安全特性:
 * - 参数化查询防止SQL注入
 * - 异常隔离: 统计失败不影响主流程
 *
 * 性能特性:
 * - 批量计算
 * - 索引优化
 * - 缓存友好
 */
class VisitorStatsService
{
    /**
     * 更新每日统计
     *
     * 安全特性:
     * - 参数化查询
     * - SQL注入防护
     *
     * @param string $date 统计日期(YYYY-MM-DD)
     * @return bool 是否更新成功
     */
    public static function updateDailyStats(string $date): bool
    {
        try {
            $db = Database::get();

            // 计算基础统计
            $stats = self::calculateBasicStats($date);

            // 计算新老访客
            $visitorStats = self::calculateVisitorTypes($date);

            // 计算跳出率和平均停留时长
            $engagementStats = self::calculateEngagement($date);

            // 获取TOP数据
            $topData = self::getTopData($date);

            // 合并数据
            $data = array_merge($stats, $visitorStats, $engagementStats, $topData);

            // 插入或更新统计
            $result = $db->update('visitor_stats_daily', $data, ['date' => $date]);

            if ($result === null) {
                // 如果更新失败（记录不存在），则插入
                $data['date'] = $date;
                $result = $db->insert('visitor_stats_daily', $data);
            }

            return $result !== null;
        } catch (\Exception $e) {
            error_log('[VisitorStatsService] 更新每日统计失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 计算基础统计 (PV/UV/IP)
     *
     * 安全特性:
     * - 参数化查询
     * - SQL注入防护
     *
     * @param string $date
     * @return array
     */
    private static function calculateBasicStats(string $date): array
    {
        $db = Database::get();
        $timestampStart = strtotime($date . ' 00:00:00');
        $timestampEnd = strtotime($date . ' 23:59:59');

        // PV (页面浏览量)
        $pv = $db->count('visitor_logs', [
            'timestamp[>=]' => $timestampStart,
            'timestamp[<=]' => $timestampEnd,
        ]);

        // UV (独立访客数) - 基于session_id
        $uv = $db->count('visitor_sessions', [
            'first_visit[>=]' => $timestampStart,
            'first_visit[<=]' => $timestampEnd,
        ]);

        // IP数
        $ipCountResult = $db->query(
            "SELECT COUNT(DISTINCT ip) as ip_count FROM visitor_logs WHERE timestamp >= :start AND timestamp <= :end",
            [
                ':start' => $timestampStart,
                ':end' => $timestampEnd
            ]
        )->fetch();
        $ipCount = $ipCountResult ? (int)$ipCountResult['ip_count'] : 0;

        // 爬虫数
        $botCount = $db->count('visitor_logs', [
            'timestamp[>=]' => $timestampStart,
            'timestamp[<=]' => $timestampEnd,
            'is_bot' => 1,
        ]);

        // 错误数
        $errorCount = $db->count('visitor_logs', [
            'timestamp[>=]' => $timestampStart,
            'timestamp[<=]' => $timestampEnd,
            'status_code[>=]' => 400,
        ]);

        return [
            'pv' => (int)$pv,
            'uv' => (int)$uv,
            'ip_count' => (int)$ipCount,
            'bot_count' => (int)$botCount,
            'error_count' => (int)$errorCount,
        ];
    }

    /**
     * 计算新老访客
     *
     * 安全特性:
     * - 参数化查询
     * - SQL注入防护
     *
     * @param string $date
     * @return array
     */
    private static function calculateVisitorTypes(string $date): array
    {
        $db = Database::get();
        $timestampStart = strtotime($date . ' 00:00:00');
        $timestampEnd = strtotime($date . ' 23:59:59');

        // 新访客数 (首次访问在当天)
        $newVisitor = $db->count('visitor_sessions', [
            'first_visit[>=]' => $timestampStart,
            'first_visit[<=]' => $timestampEnd,
        ]);

        // 老访客数 (回访)
        $oldVisitor = $db->count('visitor_sessions', [
            'first_visit[<]' => $timestampStart,
            'last_visit[>=]' => $timestampStart,
            'last_visit[<=]' => $timestampEnd,
        ]);

        // 会话数
        $sessionCount = $db->count('visitor_sessions', [
            'first_visit[>=]' => $timestampStart,
            'first_visit[<=]' => $timestampEnd,
        ]);

        return [
            'new_visitor' => (int)$newVisitor,
            'old_visitor' => (int)$oldVisitor,
            'session_count' => (int)$sessionCount,
        ];
    }

    /**
     * 计算跳出率和平均停留时长
     *
     * 安全特性:
     * - 参数化查询
     * - SQL注入防护
     *
     * @param string $date
     * @return array
     */
    private static function calculateEngagement(string $date): array
    {
        $db = Database::get();
        $timestampStart = strtotime($date . ' 00:00:00');
        $timestampEnd = strtotime($date . ' 23:59:59');

        // 获取会话数据
        $sessions = $db->select('visitor_sessions', '*', [
            'first_visit[>=]' => $timestampStart,
            'first_visit[<=]' => $timestampEnd,
        ]);

        if (empty($sessions)) {
            return ['bounce_rate' => 0, 'avg_duration' => 0];
        }

        $bounceCount = 0;
        $totalDuration = 0;

        foreach ($sessions as $session) {
            // 跳出会话: 只访问1个页面
            if ((int)$session['page_count'] === 1) {
                $bounceCount++;
            }

            // 累加停留时长
            $totalDuration += (float)$session['total_duration'];
        }

        $bounceRate = $bounceCount > 0 ? ($bounceCount / count($sessions)) * 100 : 0;
        $avgDuration = count($sessions) > 0 ? $totalDuration / count($sessions) : 0;

        return [
            'bounce_rate' => round($bounceRate, 2),
            'avg_duration' => round($avgDuration, 2),
        ];
    }

    /**
     * 获取TOP数据
     *
     * 安全特性:
     * - 参数化查询
     * - SQL注入防护
     *
     * @param string $date
     * @return array
     */
    private static function getTopData(string $date): array
    {
        $db = Database::get();
        $timestampStart = strtotime($date . ' 00:00:00');
        $timestampEnd = strtotime($date . ' 23:59:59');

        // TOP10页面
        $topPages = $db->query(
            "SELECT url, COUNT(*) as count
            FROM visitor_logs
            WHERE timestamp >= :start AND timestamp <= :end
            GROUP BY url
            ORDER BY count DESC
            LIMIT 10",
            [':start' => $timestampStart, ':end' => $timestampEnd]
        )->fetchAll();

        // TOP10来源
        $topReferrers = $db->query(
            "SELECT referrer, COUNT(*) as count
            FROM visitor_logs
            WHERE timestamp >= :start AND timestamp <= :end AND referrer != ''
            GROUP BY referrer
            ORDER BY count DESC
            LIMIT 10",
            [':start' => $timestampStart, ':end' => $timestampEnd]
        )->fetchAll();

        // TOP10 IP
        $topIps = $db->query(
            "SELECT ip, COUNT(*) as count
            FROM visitor_logs
            WHERE timestamp >= :start AND timestamp <= :end
            GROUP BY ip
            ORDER BY count DESC
            LIMIT 10",
            [':start' => $timestampStart, ':end' => $timestampEnd]
        )->fetchAll();

        return [
            'top_pages' => json_encode($topPages, JSON_UNESCAPED_UNICODE),
            'top_referrers' => json_encode($topReferrers, JSON_UNESCAPED_UNICODE),
            'top_ips' => json_encode($topIps, JSON_UNESCAPED_UNICODE),
        ];
    }

    /**
     * 获取时间范围统计
     *
     * 安全特性:
     * - 参数化查询
     * - SQL注入防护
     *
     * @param string $startDate 开始日期
     * @param string $endDate 结束日期
     * @return array
     */
    public static function getRangeStats(string $startDate, string $endDate): array
    {
        try {
            $db = Database::get();

            $stats = $db->select('visitor_stats_daily', '*', [
                'date[>=]' => $startDate,
                'date[<=]' => $endDate,
                'ORDER' => ['date' => 'ASC'],
            ]);

            // 计算总和
            $total = [
                'pv' => 0,
                'uv' => 0,
                'ip_count' => 0,
                'new_visitor' => 0,
                'old_visitor' => 0,
                'bounce_rate' => 0,
                'avg_duration' => 0,
            ];

            $count = count($stats);
            if ($count > 0) {
                foreach ($stats as $row) {
                    $total['pv'] += (int)$row['pv'];
                    $total['uv'] += (int)$row['uv'];
                    $total['ip_count'] += (int)$row['ip_count'];
                    $total['new_visitor'] += (int)$row['new_visitor'];
                    $total['old_visitor'] += (int)$row['old_visitor'];
                }

                // 平均跳出率和停留时长
                $total['bounce_rate'] = $count > 0 ? array_sum(array_column($stats, 'bounce_rate')) / $count : 0;
                $total['avg_duration'] = $count > 0 ? array_sum(array_column($stats, 'avg_duration')) / $count : 0;
            }

            return [
                'daily' => $stats,
                'total' => $total,
            ];
        } catch (\Exception $e) {
            error_log('[VisitorStatsService] 获取时间范围统计失败: ' . $e->getMessage());
            return ['daily' => [], 'total' => []];
        }
    }

    /**
     * 获取实时统计 (最近N小时)
     *
     * 安全特性:
     * - 参数化查询
     * - SQL注入防护
     *
     * @param int $hours 小时数
     * @return array
     */
    public static function getRealTimeStats(int $hours = 24): array
    {
        try {
            $db = Database::get();
            $timestampStart = time() - ($hours * 3600);

            // 最近N小时PV
            $pv = $db->count('visitor_logs', [
                'timestamp[>=]' => $timestampStart,
            ]);

            // 最近N小时UV
            $uv = $db->count('visitor_sessions', [
                'last_visit[>=]' => $timestampStart,
            ]);

            // 按小时统计
            $hourlyStats = $db->query(
                "SELECT
                    FROM_UNIXTIME(timestamp, '%Y-%m-%d %H:00:00') as hour,
                    COUNT(*) as count
                FROM visitor_logs
                WHERE timestamp >= :start
                GROUP BY hour
                ORDER BY hour ASC
            ", [':start' => $timestampStart])->fetchAll();

            return [
                'pv' => (int)$pv,
                'uv' => (int)$uv,
                'hourly' => $hourlyStats,
            ];
        } catch (\Exception $e) {
            error_log('[VisitorStatsService] 获取实时统计失败: ' . $e->getMessage());
            return ['pv' => 0, 'uv' => 0, 'hourly' => []];
        }
    }

    /**
     * 获取每日统计详情
     *
     * 安全特性:
     * - 参数化查询
     * - SQL注入防护
     *
     * @param string $date
     * @return array
     */
    public static function getDailyStats(string $date): array
    {
        try {
            $db = Database::get();

            $stats = $db->get('visitor_stats_daily', '*', ['date' => $date]);

            if (!$stats) {
                return [];
            }

            // 解析JSON字段
            $stats['top_pages'] = json_decode($stats['top_pages'] ?? '[]', true);
            $stats['top_referrers'] = json_decode($stats['top_referrers'] ?? '[]', true);
            $stats['top_ips'] = json_decode($stats['top_ips'] ?? '[]', true);

            return $stats;
        } catch (\Exception $e) {
            error_log('[VisitorStatsService] 获取每日统计失败: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取新老访客趋势
     *
     * 安全特性:
     * - 参数化查询
     * - SQL注入防护
     *
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public static function getVisitorTrend(string $startDate, string $endDate): array
    {
        try {
            $db = Database::get();

            $stats = $db->select('visitor_stats_daily', '*', [
                'date[>=]' => $startDate,
                'date[<=]' => $endDate,
                'ORDER' => ['date' => 'ASC'],
            ]);

            $trend = [];
            foreach ($stats as $row) {
                $trend[] = [
                    'date' => $row['date'],
                    'new_visitor' => (int)$row['new_visitor'],
                    'old_visitor' => (int)$row['old_visitor'],
                    'total_visitor' => (int)$row['new_visitor'] + (int)$row['old_visitor'],
                ];
            }

            return $trend;
        } catch (\Exception $e) {
            error_log('[VisitorStatsService] 获取访客趋势失败: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取来源分析
     *
     * 安全特性:
     * - 参数化查询
     * - SQL注入防护
     *
     * @param string $date
     * @return array
     */
    public static function getReferrerAnalysis(string $date): array
    {
        try {
            $db = Database::get();
            $timestampStart = strtotime($date . ' 00:00:00');
            $timestampEnd = strtotime($date . ' 23:59:59');

            // 按来源类型统计
            $referrerTypes = $db->query(
                "SELECT
                    CASE
                        WHEN referrer = '' THEN 'direct'
                        WHEN referrer LIKE '%google.com%' THEN 'google'
                        WHEN referrer LIKE '%baidu.com%' THEN 'baidu'
                        WHEN referrer LIKE '%bing.com%' THEN 'bing'
                        WHEN referrer LIKE '%yandex.com%' THEN 'yandex'
                        WHEN referrer LIKE '%yycms.com%' THEN 'internal'
                        ELSE 'external'
                    END as referrer_type,
                    COUNT(*) as count
                FROM visitor_logs
                WHERE timestamp >= :start AND timestamp <= :end
                GROUP BY referrer_type
                ORDER BY count DESC
            ", [':start' => $timestampStart, ':end' => $timestampEnd])->fetchAll();

            // 搜索引擎来源
            $searchEngines = $db->query(
                "SELECT
                    CASE
                        WHEN referrer LIKE '%google.com%' THEN 'google'
                        WHEN referrer LIKE '%baidu.com%' THEN 'baidu'
                        WHEN referrer LIKE '%bing.com%' THEN 'bing'
                        WHEN referrer LIKE '%yandex.com%' THEN 'yandex'
                        ELSE 'other'
                    END as search_engine,
                    COUNT(*) as count
                FROM visitor_logs
                WHERE timestamp >= :start AND timestamp <= :end
                    AND (referrer LIKE '%google.com%'
                        OR referrer LIKE '%baidu.com%'
                        OR referrer LIKE '%bing.com%'
                        OR referrer LIKE '%yandex.com%')
                GROUP BY search_engine
                ORDER BY count DESC
            ", [':start' => $timestampStart, ':end' => $timestampEnd])->fetchAll();

            return [
                'referrer_types' => $referrerTypes,
                'search_engines' => $searchEngines,
            ];
        } catch (\Exception $e) {
            error_log('[VisitorStatsService] 获取来源分析失败: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取设备/浏览器分析
     *
     * 安全特性:
     * - 参数化查询
     * - SQL注入防护
     *
     * @param string $date
     * @return array
     */
    public static function getDeviceAnalysis(string $date): array
    {
        try {
            $db = Database::get();
            $timestampStart = strtotime($date . ' 00:00:00');
            $timestampEnd = strtotime($date . ' 23:59:59');

            // 设备类型统计
            $deviceTypes = $db->query(
                "SELECT device_type, COUNT(*) as count
                FROM visitor_logs
                WHERE timestamp >= :start AND timestamp <= :end
                GROUP BY device_type
                ORDER BY count DESC
            ", [':start' => $timestampStart, ':end' => $timestampEnd])->fetchAll();

            // 浏览器统计
            $browsers = $db->query(
                "SELECT browser, COUNT(*) as count
                FROM visitor_logs
                WHERE timestamp >= :start AND timestamp <= :end
                GROUP BY browser
                ORDER BY count DESC
            ", [':start' => $timestampStart, ':end' => $timestampEnd])->fetchAll();

            // 操作系统统计
            $operatingSystems = $db->query(
                "SELECT os, COUNT(*) as count
                FROM visitor_logs
                WHERE timestamp >= :start AND timestamp <= :end
                GROUP BY os
                ORDER BY count DESC
            ", [':start' => $timestampStart, ':end' => $timestampEnd])->fetchAll();

            return [
                'device_types' => $deviceTypes,
                'browsers' => $browsers,
                'operating_systems' => $operatingSystems,
            ];
        } catch (\Exception $e) {
            error_log('[VisitorStatsService] 获取设备分析失败: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取地理位置分析
     *
     * 安全特性:
     * - 参数化查询
     * - SQL注入防护
     *
     * @param string $date
     * @return array
     */
    public static function getLocationAnalysis(string $date): array
    {
        try {
            $db = Database::get();
            $timestampStart = strtotime($date . ' 00:00:00');
            $timestampEnd = strtotime($date . ' 23:59:59');

            // 国家统计
            $countries = $db->query(
                "SELECT country, COUNT(*) as count
                FROM visitor_logs
                WHERE timestamp >= :start AND timestamp <= :end AND country IS NOT NULL
                GROUP BY country
                ORDER BY count DESC
                LIMIT 20
            ", [':start' => $timestampStart, ':end' => $timestampEnd])->fetchAll();

            // 城市统计
            $cities = $db->query(
                "SELECT city, COUNT(*) as count
                FROM visitor_logs
                WHERE timestamp >= :start AND timestamp <= :end AND city IS NOT NULL
                GROUP BY city
                ORDER BY count DESC
                LIMIT 20
            ", [':start' => $timestampStart, ':end' => $timestampEnd])->fetchAll();

            return [
                'countries' => $countries,
                'cities' => $cities,
            ];
        } catch (\Exception $e) {
            error_log('[VisitorStatsService] 获取地理位置分析失败: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取异常统计
     *
     * 安全特性:
     * - 参数化查询
     * - SQL注入防护
     *
     * @param string $date
     * @return array
     */
    public static function getAnomalyStats(string $date): array
    {
        try {
            $db = Database::get();
            $timestampStart = strtotime($date . ' 00:00:00');
            $timestampEnd = strtotime($date . ' 23:59:59');

            // 错误请求统计
            $errorStats = $db->query(
                "SELECT status_code, COUNT(*) as count
                FROM visitor_logs
                WHERE timestamp >= :start AND timestamp <= :end AND status_code >= 400
                GROUP BY status_code
                ORDER BY count DESC
            ", [':start' => $timestampStart, ':end' => $timestampEnd])->fetchAll();

            // 爬虫统计
            $botStats = $db->query(
                "SELECT user_agent, COUNT(*) as count
                FROM visitor_logs
                WHERE timestamp >= :start AND timestamp <= :end AND is_bot = 1
                GROUP BY user_agent
                ORDER BY count DESC
                LIMIT 20
            ", [':start' => $timestampStart, ':end' => $timestampEnd])->fetchAll();

            // 高频访问IP
            $highFrequencyIPs = $db->query(
                "SELECT ip, COUNT(*) as count
                FROM visitor_logs
                WHERE timestamp >= :start AND timestamp <= :end
                GROUP BY ip
                HAVING count >= 100
                ORDER BY count DESC
                LIMIT 20
            ", [':start' => $timestampStart, ':end' => $timestampEnd])->fetchAll();

            return [
                'error_stats' => $errorStats,
                'bot_stats' => $botStats,
                'high_frequency_ips' => $highFrequencyIPs,
            ];
        } catch (\Exception $e) {
            error_log('[VisitorStatsService] 获取异常统计失败: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取会话分析
     *
     * 安全特性:
     * - 参数化查询
     * - SQL注入防护
     *
     * @param string $date
     * @return array
     */
    public static function getSessionAnalysis(string $date): array
    {
        try {
            $db = Database::get();
            $timestampStart = strtotime($date . ' 00:00:00');
            $timestampEnd = strtotime($date . ' 23:59:59');

            // 会话时长分布
            $durationDistribution = $db->query(
                "SELECT
                    CASE
                        WHEN total_duration < 10 THEN '0-10s'
                        WHEN total_duration < 30 THEN '10-30s'
                        WHEN total_duration < 60 THEN '30-60s'
                        WHEN total_duration < 180 THEN '1-3min'
                        WHEN total_duration < 300 THEN '3-5min'
                        ELSE '5min+'
                    END as duration_range,
                    COUNT(*) as count
                FROM visitor_sessions
                WHERE first_visit >= :start AND first_visit <= :end
                GROUP BY duration_range
                ORDER BY duration_range
            ", [':start' => $timestampStart, ':end' => $timestampEnd])->fetchAll();

            // 页面数分布
            $pageCountDistribution = $db->query(
                "SELECT
                    CASE
                        WHEN page_count = 1 THEN '1页'
                        WHEN page_count = 2 THEN '2页'
                        WHEN page_count = 3 THEN '3页'
                        WHEN page_count <= 5 THEN '4-5页'
                        WHEN page_count <= 10 THEN '6-10页'
                        ELSE '10页+'
                    END as page_range,
                    COUNT(*) as count
                FROM visitor_sessions
                WHERE first_visit >= :start AND first_visit <= :end
                GROUP BY page_range
                ORDER BY page_range
            ", [':start' => $timestampStart, ':end' => $timestampEnd])->fetchAll();

            // 访问频次分布
            $visitCountDistribution = $db->query(
                "SELECT
                    CASE
                        WHEN visit_count = 1 THEN '1次'
                        WHEN visit_count = 2 THEN '2次'
                        WHEN visit_count = 3 THEN '3次'
                        WHEN visit_count <= 5 THEN '4-5次'
                        WHEN visit_count <= 10 THEN '6-10次'
                        ELSE '10次+'
                    END as visit_range,
                    COUNT(*) as count
                FROM visitor_sessions
                WHERE first_visit >= :start AND first_visit <= :end
                GROUP BY visit_range
                ORDER BY visit_range
            ", [':start' => $timestampStart, ':end' => $timestampEnd])->fetchAll();

            return [
                'duration_distribution' => $durationDistribution,
                'page_count_distribution' => $pageCountDistribution,
                'visit_count_distribution' => $visitCountDistribution,
            ];
        } catch (\Exception $e) {
            error_log('[VisitorStatsService] 获取会话分析失败: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取趋势分析
     *
     * 安全特性:
     * - 参数化查询
     * - SQL注入防护
     *
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public static function getTrendAnalysis(string $startDate, string $endDate): array
    {
        try {
            $db = Database::get();

            // 按小时统计
            $hourlyStats = $db->query(
                "SELECT
                    FROM_UNIXTIME(timestamp, '%Y-%m-%d %H:00:00') as hour,
                    COUNT(*) as count
                FROM visitor_logs
                WHERE timestamp >= :start AND timestamp <= :end
                GROUP BY hour
                ORDER BY hour ASC
            ", [':start' => strtotime($startDate . ' 00:00:00'), ':end' => strtotime($endDate . ' 23:59:59')])->fetchAll();

            // 按天统计
            $dailyStats = $db->query(
                "SELECT
                    FROM_UNIXTIME(timestamp, '%Y-%m-%d') as date,
                    COUNT(*) as count
                FROM visitor_logs
                WHERE timestamp >= :start AND timestamp <= :end
                GROUP BY date
                ORDER BY date ASC
            ", [':start' => strtotime($startDate . ' 00:00:00'), ':end' => strtotime($endDate . ' 23:59:59')])->fetchAll();

            return [
                'hourly' => $hourlyStats,
                'daily' => $dailyStats,
            ];
        } catch (\Exception $e) {
            error_log('[VisitorStatsService] 获取趋势分析失败: ' . $e->getMessage());
            return [];
        }
    }
}
