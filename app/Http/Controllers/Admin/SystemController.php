<?php
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Database;
use App\Core\App;
use App\Services\LogService;

/**
 * 系统相关页面
 */
class SystemController extends AdminBaseController
{
    /**
     * 关于系统
     *
     * @return void
     */
    public function about(): void
    {
        $runtime = '-';
        if (isset($_SERVER['REQUEST_TIME_FLOAT']) && is_numeric($_SERVER['REQUEST_TIME_FLOAT'])) {
            $seconds = (int) max(0, microtime(true) - (float) $_SERVER['REQUEST_TIME_FLOAT']);
            $days = intdiv($seconds, 86400);
            $hours = intdiv($seconds % 86400, 3600);
            $minutes = intdiv($seconds % 3600, 60);
            $secs = $seconds % 60;
            
            if ($days > 0) {
                $runtime = sprintf('%d天%02d:%02d:%02d', $days, $hours, $minutes, $secs);
            } else {
                $runtime = sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
            }
        }

        $lastRebootAt = '-';
        if (PHP_OS_FAMILY === 'Linux') {
            $uptimeFile = '/proc/uptime';
            if (is_readable($uptimeFile)) {
                $content = @file_get_contents($uptimeFile);
                if ($content !== false) {
                    $parts = preg_split('/\s+/', trim($content));
                    if (isset($parts[0]) && is_numeric($parts[0])) {
                        $bootAtTs = time() - (int) ((float) $parts[0]);
                        $lastRebootAt = date('Y-m-d H:i:s', $bootAtTs);
                    }
                }
            }
        }

        $requiredExtensions = [
            'curl',
            'json',
            'mbstring',
            'pdo',
            'pdo_mysql',
            'openssl',
        ];
        $loadedExtensions = array_map('strtolower', get_loaded_extensions());
        sort($loadedExtensions);

        $requiredExtensionsDisplay = [];
        foreach ($requiredExtensions as $ext) {
            $requiredExtensionsDisplay[] = in_array(strtolower($ext), $loadedExtensions, true)
                ? $ext
                : ($ext . '（缺失）');
        }
        $requiredExtensionsText = implode(', ', $requiredExtensionsDisplay);

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        
        if ($host !== '') {
            $currentUrl = $scheme . '://' . $host . $uri;
        } else {
            $currentUrl = $uri;
        }

        $this->render('admin/system/about', [
            'title' => '关于系统',
            'runtime' => $runtime,
            'last_reboot_at' => $lastRebootAt,
            'required_php_extensions' => $requiredExtensionsText,
            'current_url' => $currentUrl,
        ]);
    }

    /**
     * 统一日志页面
     *
     * @return void
     */
    public function logs(): void
    {
        try {
            $type = $this->get('type', 'operation');
            // 验证 type 参数，防止非法值
            $validTypes = ['operation', 'performance', 'error', 'slow_query'];
            if (!in_array($type, $validTypes, true)) {
                $type = 'operation'; // 默认值
            }
            $page = (int)($this->get('page', 1));
            $limit = (int)($this->get('limit', 20));
            
            $offset = ($page - 1) * $limit;
            $logs = [];
            $total = 0;
            $title = '系统日志';
            
            $db = Database::get();
            
            // 为所有类型都获取action参数，以供视图使用
            $action = $this->get('action', '');
            
            switch ($type) {
                case 'operation':
                    // 操作日志 - 从LogService 获取
                    $conditions = [];
                    $adminId = (int)($this->get('admin_id', 0));
                    $startDate = $this->get('start_date', '');
                    $endDate = $this->get('end_date', '');
                    
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

                    $driverType = App::getConfig('log.driver', 'file');
                    if ($driverType === 'monolog') {
                        $logs = LogService::getFileLogs($conditions, $limit, $offset, $startDate, $endDate);
                        $total = LogService::getFileLogsCount($conditions, $startDate, $endDate);
                    } else {
                        $logs = LogService::getLogs($conditions, $limit, $offset);
                        $total = LogService::getLogsCount($conditions);
                    }
                    $title = '操作日志';
                    break;
                
                case 'performance':
                    // 性能日志
                    $where = ['ORDER' => ['id' => 'DESC'], 'LIMIT' => [$offset, $limit]];
                    $startDate = $this->get('start_date', '');
                    $endDate = $this->get('end_date', '');
                    
                    if (!empty($startDate)) {
                        $where['timestamp[>=]'] = strtotime($startDate . ' 00:00:00');
                    }
                    if (!empty($endDate)) {
                        $where['timestamp[<=]'] = strtotime($endDate . ' 23:59:59');
                    }
                    
                    $logs = $db->select('performance_metrics', '*', $where);
                    
                    // 为计数查询构建单独的条件（排除 ORDER 和 LIMIT）
                    $countWhere = [];
                    if (isset($where['timestamp[>=]'])) {
                        $countWhere['timestamp[>=]'] = $where['timestamp[>=]'];
                    }
                    if (isset($where['timestamp[<=]'])) {
                        $countWhere['timestamp[<=]'] = $where['timestamp[<=]'];
                    }
                    $total = $db->count('performance_metrics', $countWhere);
                    $title = '性能日志';
                    break;
                
                case 'error':
                    // 错误日志
                    $where = ['ORDER' => ['id' => 'DESC'], 'LIMIT' => [$offset, $limit]];
                    $startDate = $this->get('start_date', '');
                    $endDate = $this->get('end_date', '');
                    
                    if (!empty($startDate)) {
                        $where['timestamp[>=]'] = strtotime($startDate . ' 00:00:00');
                    }
                    if (!empty($endDate)) {
                        $where['timestamp[<=]'] = strtotime($endDate . ' 23:59:59');
                    }
                    
                    $logs = $db->select('error_logs', '*', $where);
                    
                    // 为计数查询构建单独的条件（排除 ORDER 和 LIMIT）
                    $countWhere = [];
                    if (isset($where['timestamp[>=]'])) {
                        $countWhere['timestamp[>=]'] = $where['timestamp[>=]'];
                    }
                    if (isset($where['timestamp[<=]'])) {
                        $countWhere['timestamp[<=]'] = $where['timestamp[<=]'];
                    }
                    $total = $db->count('error_logs', $countWhere);
                    $title = '错误日志';
                    break;
                
                case 'slow_query':
                    // 慢查询日志
                    $where = ['ORDER' => ['id' => 'DESC'], 'LIMIT' => [$offset, $limit]];
                    $startDate = $this->get('start_date', '');
                    $endDate = $this->get('end_date', '');
                    
                    if (!empty($startDate)) {
                        $where['timestamp[>=]'] = strtotime($startDate . ' 00:00:00');
                    }
                    if (!empty($endDate)) {
                        $where['timestamp[<=]'] = strtotime($endDate . ' 23:59:59');
                    }
                    
                    $logs = $db->select('slow_queries', '*', $where);
                    
                    // 为计数查询构建单独的条件（排除 ORDER 和 LIMIT）
                    $countWhere = [];
                    if (isset($where['timestamp[>=]'])) {
                        $countWhere['timestamp[>=]'] = $where['timestamp[>=]'];
                    }
                    if (isset($where['timestamp[<=]'])) {
                        $countWhere['timestamp[<=]'] = $where['timestamp[<=]'];
                    }
                    $total = $db->count('slow_queries', $countWhere);
                    $title = '慢查询日志';
                    break;
                
                default:
                    // 默认显示操作日志
                    $conditions = [];
                    $adminId = (int)($this->get('admin_id', 0));
                    $startDate = $this->get('start_date', '');
                    $endDate = $this->get('end_date', '');
                    
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
                    
                    $logs = LogService::getLogs($conditions, $limit, $offset);
                    $total = LogService::getLogsCount($conditions);
                    $title = '操作日志';
                    break;
            }
            
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
                'slow_query' => '慢查询',
            ];
            
            $this->render('admin/system/logs', [
                'title' => $title,
                'logs' => $logs,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'type' => $type,
                'start_date' => $startDate ?? '',
                'end_date' => $endDate ?? '',
                'action' => $action ?? '',
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
    public function cleanLogs(): void
    {
        try {
            // 验证POST请求和CSRF
            if (!$this->validatePostAndCsrf()) {
                return;
            }

            $type = $this->post('type', 'operation');
            // 验证 type 参数，防止非法值
            $validTypes = ['operation', 'performance', 'error', 'slow_query'];
            if (!in_array($type, $validTypes, true)) {
                $type = 'operation'; // 默认值
            }
            $days = (int)$this->post('days', 30);
            
            $db = Database::get();
            $deletedCount = 0;
            
            switch ($type) {
                case 'operation':
                    // 清理操作日志
                    $deletedCount = LogService::cleanExpiredLogs($days);
                    break;
                
                case 'performance':
                    // 清理性能日志
                    $expireDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));
                    $result = $db->delete('performance_metrics', [
                        'timestamp[<]' => strtotime($expireDate),
                    ]);
                    $deletedCount = $result->rowCount();
                    break;
                
                case 'error':
                    // 清理错误日志
                    $expireDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));
                    $result = $db->delete('error_logs', [
                        'timestamp[<]' => strtotime($expireDate),
                    ]);
                    $deletedCount = $result->rowCount();
                    break;
                
                case 'slow_query':
                    // 清理慢查询日志
                    $expireDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));
                    $result = $db->delete('slow_queries', [
                        'timestamp[<]' => strtotime($expireDate),
                    ]);
                    $deletedCount = $result->rowCount();
                    break;
                
                default:
                    // 默认清理操作日志
                    $deletedCount = LogService::cleanExpiredLogs($days);
                    break;
            }
            
            // 记录清理操作日志
            $logTypeLabels = [
                'operation' => '操作',
                'performance' => '性能',
                'error' => '错误',
                'slow_query' => '慢查询',
            ];
            $typeLabel = $logTypeLabels[$type] ?? '未知';
            
            LogService::record(
                $this->getAdminId(),
                $this->getAdminUsername(),
                'delete',
                "清理了{$deletedCount} 条{$typeLabel}日志",
                "清理 {$days} 天前的{$typeLabel}日志，共删除{$deletedCount} 条"
            );
            
            $this->successJson("成功清理 {$deletedCount} 条过期{$typeLabel}日志", null, "/admin/system/logs?type={$type}");
        } catch (\Exception $e) {
            error_log('清理日志失败: ' . $e->getMessage());
            $this->errorJson('清理失败: ' . $e->getMessage());
        }
    }
}


