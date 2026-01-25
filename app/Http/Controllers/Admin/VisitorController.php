<?php
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\AdminBaseController;
use App\Core\App;
use App\Services\VisitorLogService;
use App\Services\VisitorStatsService;
use App\Services\AnomalyDetectionService;

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

            // 获取每日统计
            $dailyStats = VisitorStatsService::getDailyStats($date);

            // 获取时间范围统计
            $rangeStats = VisitorStatsService::getRangeStats($startDate, $endDate);

            // 获取实时统计
            $realTimeStats = VisitorStatsService::getRealTimeStats(24);

            // 获取异常统计
            $anomalyStats = VisitorStatsService::getAnomalyStats($date);

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
            $startDate = $this->get('start_date', '');
            $endDate = $this->get('end_date', '');

            // 构建查询条件
            $conditions = [];
            if (!empty($riskLevel)) {
                $conditions['risk_level'] = $riskLevel;
            }
            if (!empty($alertType)) {
                $conditions['alert_type'] = $alertType;
            }
            if (!empty($startDate)) {
                $conditions['created_at[>=]'] = $startDate . ' 00:00:00';
            }
            if (!empty($endDate)) {
                $conditions['created_at[<=]'] = $endDate . ' 23:59:59';
            }

            // 获取告警列表
            $offset = ($page - 1) * $limit;
            $alerts = AnomalyDetectionService::getPendingAlerts($limit);
            $total = count($alerts);

            // 获取告警统计
            $alertStats = AnomalyDetectionService::getAlertStats(
                date('Y-m-d', strtotime('-7 days')),
                date('Y-m-d')
            );

            $this->render('admin/visitor/alerts', [
                'title' => '异常告警',
                'alerts' => $alerts,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'riskLevel' => $riskLevel,
                'alertType' => $alertType,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'alertStats' => $alertStats,
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

            // 获取异常检测报告
            $report = AnomalyDetectionService::getAnomalyReport($date);

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
}
