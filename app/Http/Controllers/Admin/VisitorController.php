<?php
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\AdminBaseController;
use App\Core\App;
use App\Http\ResponseContext;
use App\Http\ResponseHaltException;
use App\Services\VisitorLogService;
use App\Services\VisitorStatsService;
use App\Services\AnomalyDetectionService;
use App\Services\CacheService;
use Symfony\Component\HttpFoundation\Response;

/**
 * 访客日志控制器
 */
class VisitorController extends AdminBaseController
{
    /**
     * 访客日志列表页
     *
     * @return void
     */
    public function logs(): void
    {
        try {
            $page = (int)($this->get('page', 1));
            $limit = (int)($this->get('limit', 20));
            $ip = $this->get('ip', '');
            $url = $this->get('url', '');
            $statusCode = (int)($this->get('status_code', 0));
            $startDate = $this->get('start_date', '');
            $endDate = $this->get('end_date', '');
            $isBot = (int)($this->get('is_bot', -1));

            // 构建查询条件
            $conditions = [];
            if (!empty($ip)) {
                $conditions['ip[~]'] = $ip;
            }
            if (!empty($url)) {
                $conditions['url[~]'] = $url;
            }
            if ($statusCode > 0) {
                $conditions['status_code'] = $statusCode;
            }
            if ($isBot >= 0) {
                $conditions['is_bot'] = $isBot;
            }
            if (!empty($startDate)) {
                $conditions['timestamp[>=]'] = strtotime($startDate . ' 00:00:00');
            }
            if (!empty($endDate)) {
                $conditions['timestamp[<=]'] = strtotime($endDate . ' 23:59:59');
            }

            // 获取访客日志列表
            $offset = ($page - 1) * $limit;
            $fileLogs = VisitorLogService::getFileLogs($conditions, $limit, $offset, $startDate, $endDate);
            $fileTotal = VisitorLogService::getFileLogsCount($conditions, $startDate, $endDate);

            if ($fileTotal > 0) {
                $logs = $fileLogs;
                $total = $fileTotal;
            } else {
                $logs = VisitorLogService::getLogs($conditions, $limit, $offset);
                $total = VisitorLogService::getCount($conditions);
            }

            $this->render('admin/visitor/logs', [
                'title' => '访客日志',
                'logs' => $logs,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'ip' => $ip,
                'url' => $url,
                'status_code' => $statusCode,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'is_bot' => $isBot,
            ]);
        } catch (\Exception $e) {
            error_log('获取访客日志列表失败: ' . $e->getMessage());
            $this->error('加载页面失败: ' . $e->getMessage());
        }
    }

    /**
     * 访客统计页
     *
     * @return void
     */
    public function stats(): void
    {
        try {
            $date = $this->get('date', date('Y-m-d'));
            $startDate = $this->get('start_date', date('Y-m-d', strtotime('-7 days')));
            $endDate = $this->get('end_date', date('Y-m-d'));
            $export = $this->get('export', '');

            $cache = new CacheService();
            $dailyStats = $cache->remember(
                'visitor:stats:daily:' . $date,
                fn() => VisitorStatsService::getDailyStats($date),
                60
            );

            if (empty($dailyStats)) {
                $summary = VisitorLogService::getFileLogSummary($date, $date);
                if (!empty($summary['total_requests'])) {
                    $dailyStats = [
                        'pv' => (int)$summary['total_requests'],
                        'uv' => (int)$summary['unique_sessions'],
                        'ip_count' => (int)$summary['unique_ips'],
                        'bot_count' => (int)$summary['bot_requests'],
                        'error_count' => (int)$summary['error_requests'],
                        'new_visitor' => 0,
                        'old_visitor' => 0,
                        'bounce_rate' => 0,
                        'avg_duration' => 0,
                        'session_count' => (int)$summary['unique_sessions'],
                    ];
                }
            }

            // 获取时间范围统计
            $rangeStats = $cache->remember(
                'visitor:stats:range:' . $startDate . ':' . $endDate,
                fn() => VisitorStatsService::getRangeStats($startDate, $endDate),
                120
            );

            // 获取实时统计
            $realTimeStats = $cache->remember(
                'visitor:stats:realtime:24',
                fn() => VisitorStatsService::getRealTimeStats(24),
                60
            );

            // 获取异常统计
            $anomalyStats = $cache->remember(
                'visitor:stats:anomaly:' . $date,
                fn() => VisitorStatsService::getAnomalyStats($date),
                120
            );

            if ($export === 'daily') {
                $rows = [
                    ['date', 'pv', 'uv', 'ip_count', 'new_visitor', 'old_visitor', 'bounce_rate', 'avg_duration', 'error_count', 'bot_count', 'session_count'],
                ];
                $rows[] = [
                    $date,
                    $dailyStats['pv'] ?? 0,
                    $dailyStats['uv'] ?? 0,
                    $dailyStats['ip_count'] ?? 0,
                    $dailyStats['new_visitor'] ?? 0,
                    $dailyStats['old_visitor'] ?? 0,
                    $dailyStats['bounce_rate'] ?? 0,
                    $dailyStats['avg_duration'] ?? 0,
                    $dailyStats['error_count'] ?? 0,
                    $dailyStats['bot_count'] ?? 0,
                    $dailyStats['session_count'] ?? 0,
                ];
                $csv = $this->toCsv($rows);
                $response = new Response($csv, 200, [
                    'Content-Type' => 'text/csv; charset=utf-8',
                    'Content-Disposition' => 'attachment; filename="visitor_daily_' . $date . '.csv"',
                ]);
                ResponseContext::set($response);
                throw new ResponseHaltException($response);
            }

            if ($export === 'range') {
                $rows = [
                    ['date', 'pv', 'uv', 'ip_count', 'new_visitor', 'old_visitor', 'bounce_rate', 'avg_duration'],
                ];
                foreach ($rangeStats['daily'] ?? [] as $row) {
                    $rows[] = [
                        $row['date'] ?? '',
                        $row['pv'] ?? 0,
                        $row['uv'] ?? 0,
                        $row['ip_count'] ?? 0,
                        $row['new_visitor'] ?? 0,
                        $row['old_visitor'] ?? 0,
                        $row['bounce_rate'] ?? 0,
                        $row['avg_duration'] ?? 0,
                    ];
                }
                $csv = $this->toCsv($rows);
                $response = new Response($csv, 200, [
                    'Content-Type' => 'text/csv; charset=utf-8',
                    'Content-Disposition' => 'attachment; filename="visitor_range_' . $startDate . '_' . $endDate . '.csv"',
                ]);
                ResponseContext::set($response);
                throw new ResponseHaltException($response);
            }

            $this->render('admin/visitor/stats', [
                'title' => '访客统计',
                'date' => $date,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'dailyStats' => $dailyStats,
                'rangeStats' => $rangeStats,
                'realTimeStats' => $realTimeStats,
                'anomalyStats' => $anomalyStats,
            ]);
        } catch (\Exception $e) {
            error_log('获取访客统计失败: ' . $e->getMessage());
            $this->error('加载页面失败: ' . $e->getMessage());
        }
    }

    /**
     * 告警列表页
     *
     * @return void
     */
    public function alerts(): void
    {
        try {
            $page = (int)($this->get('page', 1));
            $limit = (int)($this->get('limit', 20));
            $riskLevel = $this->get('risk_level', '');
            $alertType = $this->get('alert_type', '');
            $status = $this->get('status', '');
            $startDate = $this->get('start_date', '');
            $endDate = $this->get('end_date', '');
            $export = $this->get('export', '');

            // 构建查询条件
            $conditions = [];
            if (!empty($riskLevel)) {
                $conditions['risk_level'] = $riskLevel;
            }
            if (!empty($alertType)) {
                $conditions['alert_type'] = $alertType;
            }
            if (!empty($status)) {
                $conditions['status'] = $status;
            }
            if (!empty($startDate)) {
                $conditions['created_at[>=]'] = $startDate . ' 00:00:00';
            }
            if (!empty($endDate)) {
                $conditions['created_at[<=]'] = $endDate . ' 23:59:59';
            }

            $cache = new CacheService();
            $offset = ($page - 1) * $limit;
            $fileAlerts = $cache->remember(
                'visitor:alerts:file:' . $startDate . ':' . $endDate . ':' . $riskLevel . ':' . $alertType . ':' . $status . ':' . $page . ':' . $limit,
                fn() => AnomalyDetectionService::getAlertsFromFile(
                    $startDate !== '' ? $startDate : date('Y-m-d', strtotime('-7 days')),
                    $endDate !== '' ? $endDate : date('Y-m-d'),
                    $riskLevel,
                    $alertType,
                    $limit,
                    $offset
                ),
                60
            );

            $fileTotal = $cache->remember(
                'visitor:alerts:file_total:' . $startDate . ':' . $endDate . ':' . $riskLevel . ':' . $alertType,
                fn() => AnomalyDetectionService::getAlertsFromFileCount(
                    $startDate !== '' ? $startDate : date('Y-m-d', strtotime('-7 days')),
                    $endDate !== '' ? $endDate : date('Y-m-d'),
                    $riskLevel,
                    $alertType
                ),
                120
            );

            $fileStats = $cache->remember(
                'visitor:alerts:file_stats:' . $startDate . ':' . $endDate,
                fn() => AnomalyDetectionService::getAlertStatsFromFile(
                    $startDate !== '' ? $startDate : date('Y-m-d', strtotime('-7 days')),
                    $endDate !== '' ? $endDate : date('Y-m-d')
                ),
                120
            );

            $alertsReadonly = false;
            if (!empty($fileAlerts) || $fileTotal > 0) {
                if ($status !== '' && $status !== 'pending') {
                    $alerts = [];
                    $total = 0;
                } else {
                    $alerts = $fileAlerts;
                    $total = $fileTotal;
                }
                $alertStats = $fileStats;
                $alertsReadonly = true;
            } else {
                $alerts = AnomalyDetectionService::getPendingAlerts($limit);
                $total = count($alerts);
                $alertStats = AnomalyDetectionService::getAlertStats(
                    date('Y-m-d', strtotime('-7 days')),
                    date('Y-m-d')
                );
            }

            if ($export === 'csv') {
                $rows = [
                    ['alert_type', 'risk_level', 'ip', 'status', 'count', 'created_at'],
                ];
                foreach ($alerts as $alert) {
                    $rows[] = [
                        $alert['alert_type'] ?? '',
                        $alert['risk_level'] ?? '',
                        $alert['ip'] ?? '',
                        $alert['status'] ?? '',
                        $alert['count'] ?? 1,
                        $alert['created_at'] ?? '',
                    ];
                }
                $csv = $this->toCsv($rows);
                $response = new Response($csv, 200, [
                    'Content-Type' => 'text/csv; charset=utf-8',
                    'Content-Disposition' => 'attachment; filename="visitor_alerts.csv"',
                ]);
                ResponseContext::set($response);
                throw new ResponseHaltException($response);
            }

            $this->render('admin/visitor/alerts', [
                'title' => '异常告警',
                'alerts' => $alerts,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'riskLevel' => $riskLevel,
                'alertType' => $alertType,
                'status' => $status,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'alertStats' => $alertStats,
                'alertsReadonly' => $alertsReadonly,
            ]);
        } catch (\Exception $e) {
            error_log('获取告警列表失败: ' . $e->getMessage());
            $this->error('加载页面失败: ' . $e->getMessage());
        }
    }

    /**
     * 处理告警
     *
     * @return void
     */
    public function handleAlert(): void
    {
        try {
            // 验证POST请求和CSRF
            if (!$this->validatePostAndCsrf()) {
                return;
            }

            $alertId = (int)$this->post('alert_id', 0);
            $status = $this->post('status', 'handled');

            if ($alertId <= 0) {
                $this->errorJson('告警ID不能为空');
                return;
            }

            // 处理告警
            $result = AnomalyDetectionService::handleAlert($alertId, $status);

            if ($result) {
                $this->successJson('告警处理成功', null, '/admin/visitor/alerts');
            } else {
                $this->errorJson('告警处理失败');
            }
        } catch (\Exception $e) {
            error_log('处理告警失败: ' . $e->getMessage());
            $this->errorJson('处理失败: ' . $e->getMessage());
        }
    }

    /**
     * 异常检测报告页
     *
     * @return void
     */
    public function anomalyReport(): void
    {
        try {
            $date = $this->get('date', date('Y-m-d', strtotime('-1 day')));
            $export = $this->get('export', '');

            // 获取异常检测报告
            $report = AnomalyDetectionService::getAnomalyReport($date);

            if ($export === 'csv' && !empty($report)) {
                $rows = [
                    ['date', 'total_requests', 'error_requests', 'bot_requests', 'error_rate', 'bot_rate', 'alert_count'],
                    [
                        $report['date'] ?? $date,
                        $report['total_requests'] ?? 0,
                        $report['error_requests'] ?? 0,
                        $report['bot_requests'] ?? 0,
                        $report['error_rate'] ?? 0,
                        $report['bot_rate'] ?? 0,
                        $report['alert_count'] ?? 0,
                    ],
                ];
                $csv = $this->toCsv($rows);
                $response = new Response($csv, 200, [
                    'Content-Type' => 'text/csv; charset=utf-8',
                    'Content-Disposition' => 'attachment; filename="visitor_anomaly_' . $date . '.csv"',
                ]);
                ResponseContext::set($response);
                throw new ResponseHaltException($response);
            }

            $this->render('admin/visitor/anomaly-report', [
                'title' => '异常检测报告',
                'date' => $date,
                'report' => $report,
            ]);
        } catch (\Exception $e) {
            error_log('获取异常检测报告失败: ' . $e->getMessage());
            $this->error('加载页面失败: ' . $e->getMessage());
        }
    }

    /**
     * 输出 CSV
     *
     * @param array<int, array<int, mixed>> $rows
     * @return string
     */
    private function toCsv(array $rows): string
    {
        $buffer = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($buffer, $row);
        }
        rewind($buffer);
        $csv = stream_get_contents($buffer);
        fclose($buffer);
        return $csv === false ? '' : "\xEF\xBB\xBF" . $csv;
    }
}
