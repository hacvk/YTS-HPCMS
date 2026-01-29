<?php
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\AdminBaseController;
use App\Core\Security;
use App\Services\LogService;

/**
 * 日志管理控制器
 */
class LogController extends AdminBaseController
{
    /**
     * 日志列表页
     *
     * @return void
     */
    public function index(): void
    {
        $this->requirePerm('admin:log:view');
        try {
            $page = (int)($this->get('page', 1));
            $limit = (int)($this->get('limit', 20));
            $action = $this->get('action', '');
            $adminId = (int)($this->get('admin_id', 0));
            $startDate = $this->get('start_date', '');
            $endDate = $this->get('end_date', '');
            
            // 构建查询条件
            $conditions = [];
            if (!empty($action)) {
                $conditions['action'] = $action;
            }
            if ($adminId > 0) {
                $conditions['admin_id'] = $adminId;
            }
            if (!empty($startDate)) {
                $conditions['create_time[>=]'] = $startDate . ' 00:00:00';
            }
            if (!empty($endDate)) {
                $conditions['create_time[<=]'] = $endDate . ' 23:59:59';
            }
            
            // 获取日志列表
            $offset = ($page - 1) * $limit;
            $logs = LogService::getLogs($conditions, $limit, $offset);
            $total = LogService::getLogsCount($conditions);
            
            // 获取操作类型列表（用于筛选）
            $actionTypes = [
                'login' => '登录',
                'logout' => '退出',
                'create' => '创建',
                'update' => '更新',
                'delete' => '删除',
                'view' => '查看',
                'upload' => '上传',
                'download' => '下载',
                'error' => '错误',
            ];
            
            $this->render('admin/log/index', [
                'title' => '操作日志',
                'logs' => $logs,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'action' => $action,
                'admin_id' => $adminId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'action_types' => $actionTypes
            ]);
        } catch (\Exception $e) {
            error_log('获取日志列表失败: ' . $e->getMessage());
            $this->error('加载页面失败: ' . $e->getMessage());
        }
    }

    /**
     * 清理过期日志
     *
     * @return void
     */
    public function clean(): void
    {
        $this->requirePerm('admin:log:clear');
        try {
            // 验证POST请求和CSRF
            if (!$this->validatePostAndCsrf()) {
                return;
            }

            $days = (int)$this->post('days', 30);
            
            // 清理过期日志
            $deletedCount = LogService::cleanExpiredLogs($days);
            
            // 记录清理日志
            LogService::record(
                $this->getAdminId(),
                $this->getAdminUsername(),
                'delete',
                "清理了{$deletedCount} 条过期日志",
                "清理 {$days} 天前的日志，共删除{$deletedCount} 条"
            );
            
            $adminPath = $this->getAdminPath();
            $this->successJson("成功清理 {$deletedCount} 条过期日志", null, $adminPath . '/log');
        } catch (\Exception $e) {
            error_log('清理日志失败: ' . $e->getMessage());
            $this->errorJson('清理失败: ' . $e->getMessage());
        }
    }
}
