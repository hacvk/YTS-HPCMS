<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\CacheService;

/**
 * 后台首页控制器
 */
class DashboardController extends AdminBaseController
{
    public function __construct(
        private readonly \Medoo\Medoo $db,
        private readonly CacheService $cacheService
    ) {
        parent::__construct();
    }

    /**
     * 控制台首页
     *
     * @return void
     */
    public function index(): void
    {
        try {
            $db = $this->db;

            // 获取统计数据
            $stats = $this->getStats();

            // 获取最近的操作日志
            $recentLogs = $this->getRecentLogs($db);

            $this->render('admin/dashboard', [
                'title' => '控制台',
                'stats' => $stats,
                'recent_logs' => $recentLogs,
            ]);

        } catch (\Exception $e) {
            // 数据库查询失败，显示错误或使用默认数据
            $this->render('admin/dashboard', [
                'title' => '控制台',
                'stats' => $this->getDefaultStats(),
                'recent_logs' => [],
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 获取统计数据（使用缓存优化性能）
     * 缓存时间：10-60 分钟（根据数据更新频率调整）
     *
     * @return array<string, mixed>
     */
    private function getStats(): array
    {
        $cacheService = $this->cacheService;
        $db = $this->db;
        
        // 使用缓存记住统计数据，避免每次刷新都查询数据库
        // 文章和栏目总数：缓存 30 分钟（更新频率低）
        // 今日登录次数：缓存 10 分钟（需要相对实时）
        // 系统运行天数：缓存 60 分钟（几乎不变）
        // 管理员总数：缓存 30 分钟（更新频率低）
        
        return [
            // 文章总数（缓存 30 分钟）
            'articles' => $cacheService->remember(
                'dashboard:stats:articles',
                fn() => $this->getArticleCount($db),
                1800 // 30 分钟
            ),

            // 栏目总数（缓存 30 分钟）
            'categories' => $cacheService->remember(
                'dashboard:stats:categories',
                fn() => $this->getCategoryCount($db),
                1800 // 30 分钟
            ),

            // 今日登录次数（缓存 10 分钟，需要相对实时）
            'today_logins' => $cacheService->remember(
                'dashboard:stats:today_logins',
                fn() => $this->getTodayLoginCount($db),
                600 // 10 分钟
            ),

            // 系统运行天数（缓存 60 分钟，几乎不变）
            'system_days' => $cacheService->remember(
                'dashboard:stats:system_days',
                fn() => $this->getSystemDays($db),
                3600 // 60 分钟
            ),

            // 以下用于"管理员信息"表格
            'login_count' => $this->admin['login_count'] ?? 0,
            'last_login_time' => $this->admin['last_login_time'] ?? '首次登录',
            
            // 管理员总数（缓存 30 分钟）
            'admin_count' => $cacheService->remember(
                'dashboard:stats:admin_count',
                fn() => (int)$db->count('sys_admin'),
                1800 // 30 分钟
            ),
        ];
    }

    /**
     * 获取文章总数（伪数据）
     *
     * @param \Medoo\Medoo $db
     * @return int
     */
    private function getArticleCount(\Medoo\Medoo $db): int
    {
        try {
            return (int)$db->count('sys_content');
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * 获取栏目总数（伪数据）
     *
     * @param \Medoo\Medoo $db
     * @return int
     */
    private function getCategoryCount(\Medoo\Medoo $db): int
    {
        try {
            return (int)$db->count('sys_category');
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * 获取今日登录次数
     *
     * @param \Medoo\Medoo $db
     * @return int
     */
    private function getTodayLoginCount(\Medoo\Medoo $db): int
    {
        try {
            $today = date('Y-m-d');

            return (int)$db->count('sys_log', [
                'action' => 'login',
                'create_time[>=]' => $today . ' 00:00:00',
                'create_time[<=]' => $today . ' 23:59:59',
            ]);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * 获取系统运行天数
     * 口径：从数据库中最早的栏目创建时间开始计算（如果没有栏目，则返回 0）
     *
     * @param \Medoo\Medoo $db
     * @return int
     */
    private function getSystemDays(\Medoo\Medoo $db): int
    {
        try {
            $start = $db->get('sys_category', ['create_time'], [
                'ORDER' => ['create_time' => 'ASC']
            ]);

            $startTime = $start['create_time'] ?? null;
            if (empty($startTime)) {
                return 0;
            }

            $startTs = strtotime($startTime);
            if ($startTs === false) {
                return 0;
            }

            $diff = time() - $startTs;
            return (int)max(0, floor($diff / 86400));
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * 获取最近的操作日志
     *
     * @param \Medoo\Medoo $db
     * @return array<int, array<string, mixed>>
     */
    private function getRecentLogs(\Medoo\Medoo $db): array
    {
        return $db->select('sys_log', [
            'id',
            'admin_name',
            'action',
            'message',
            'ip',
            'create_time'
        ], [
            'ORDER' => ['id' => 'DESC'],
            'LIMIT' => 10,
        ]);
    }

    /**
     * 获取默认统计数据
     *
     * @return array<string, mixed>
     */
    private function getDefaultStats(): array
    {
        return [
            'articles' => 0,
            'categories' => 0,
            'login_count' => 0,
            'last_login_time' => '-',
            'admin_count' => 0,
            'today_logins' => 0,
            'system_days' => 0,
        ];
    }
}
