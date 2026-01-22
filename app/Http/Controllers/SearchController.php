<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SearchService;
use App\Services\SiteService;
use Medoo\Medoo;

/**
 * 搜索控制器
 * 处理内容搜索相关逻辑
 */
class SearchController extends BaseController
{
    /**
     * 构造函数（使用 PHP 8.0 构造函数属性提升）
     *
     * @param SearchService $searchService 搜索服务实例
     * @param SiteService $siteService 站点服务实例
     * @param Medoo $db 数据库实例
     */
    public function __construct(
        private readonly SearchService $searchService,
        private readonly SiteService $siteService,
        private readonly Medoo $db
    ) {
        parent::__construct();
    }

    /**
     * 搜索结果页
     *
     * @return void
     */
    public function index(): void
    {
        // 获取搜索关键词
        $keyword = $this->get('keyword', '');
        $keyword = trim($keyword);

        // 如果没有关键词，显示搜索页面
        if (empty($keyword)) {
            $this->showSearchPage();
            return;
        }

        // 获取页码
        $page = (int)$this->get('page', 1);
        if ($page < 1) {
            $page = 1;
        }

        // 每页显示数量（性能优化：限制结果数量）
        $limit = 10;

        try {
            // 获取客户端IP和User-Agent
            $ip = $this->getClientIp();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

            // 执行搜索
            $result = $this->searchService->search($keyword, $page, $limit, $ip, $userAgent);

            // 检查是否被拦截
            if ($result['blocked'] ?? false) {
                $this->render('front/search', [
                    'title' => '搜索被限制',
                    'keyword' => $keyword,
                    'results' => [],
                    'total' => 0,
                    'page' => 1,
                    'limit' => $limit,
                    'navItems' => $navItems,
                    'appBaseUrl' => $appBaseUrl,
                    'blocked' => true,
                    'blocked_reason' => $result['reason'],
                ]);
                return;
            }

            // 使用 SiteService 构建导航数据
            $navItems = $this->siteService->buildNav();

            // 计算基础URL
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $appBaseUrl = $protocol . '://' . $host;

            // 渲染搜索结果页
            $this->render('front/search', [
                'title' => '搜索结果 - ' . $keyword,
                'keyword' => $keyword,
                'results' => $result['list'] ?? [],
                'total' => $result['total'] ?? 0,
                'page' => $page,
                'limit' => $limit,
                'navItems' => $navItems,
                'appBaseUrl' => $appBaseUrl,
            ]);
        } catch (\Exception $e) {
            error_log('搜索失败: ' . $e->getMessage());
            $this->showSearchPage();
        }
    }

    /**
     * 显示搜索页面（无关键词时）
     *
     * @return void
     */
    private function showSearchPage(): void
    {
        // 使用 SiteService 构建导航数据
        $navItems = $this->siteService->buildNav();

        // 计算基础URL
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $appBaseUrl = $protocol . '://' . $host;

        $this->render('front/search', [
            'title' => '内容搜索',
            'keyword' => '',
            'results' => [],
            'total' => 0,
            'page' => 1,
            'limit' => 10,
            'navItems' => $navItems,
            'appBaseUrl' => $appBaseUrl,
        ]);
    }

    /**
     * 搜索建议（AJAX接口）
     *
     * @return void
     */
    public function suggestions(): void
    {
        $keyword = $this->get('keyword', '');
        $keyword = trim($keyword);

        if (empty($keyword) || mb_strlen($keyword) < 2) {
            echo json_encode([]);
            return;
        }

        try {
            // 获取客户端IP和User-Agent
            $ip = $this->getClientIp();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

            // 获取搜索建议（限制数量，性能优化）
            $suggestions = $this->searchService->getSuggestions($keyword, 5, $ip, $userAgent);

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($suggestions);
        } catch (\Exception $e) {
            error_log('搜索建议失败: ' . $e->getMessage());
            echo json_encode([]);
        }
    }

    /**
     * 获取客户端IP地址
     * 支持代理服务器环境
     *
     * @return string
     */
    private function getClientIp(): string
    {
        // 检查是否有代理服务器
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        // 如果有X-Forwarded-For头，使用它（需要确保代理服务器可信）
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (isset($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        }

        // 验证IP格式
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }

        return '0.0.0.0';
    }
}
