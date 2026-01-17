<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\Content;
use App\Service\DoctorService;
use App\Service\SiteService;
use Medoo\Medoo;

/**
 * 栏目控制器
 * 处理栏目列表页相关逻辑
 */
class CategoryController extends BaseController
{
    /**
     * 构造函数（使用 PHP 8.0 构造函数属性提升）
     *
     * @param Content $contentModel 内容模型实例
     * @param DoctorService $doctorService 专家服务实例
     * @param SiteService $siteService 站点服务实例
     * @param Medoo $db 数据库实例
     */
    public function __construct(
        private readonly Content $contentModel,
        private readonly DoctorService $doctorService,
        private readonly SiteService $siteService,
        private readonly Medoo $db
    ) {
        parent::__construct();
    }

    /**
     * 栏目列表页
     * 对应原 HomeController::category() 方法
     *
     * @return void
     */
    public function index(): void
    {
        $slug = $this->get('slug', '');

        if (empty($slug)) {
            $this->redirect('/');
            return;
        }

        try {
            // 获取栏目信息
            $category = $this->db->get('sys_category', '*', [
                'slug' => $slug,
                'is_hidden' => 0
            ]);

            if (!$category || !is_array($category)) {
                $this->redirect('/');
                return;
            }

            $categoryId = (int)($category['id'] ?? 0);
            if ($categoryId <= 0) {
                $this->redirect('/');
                return;
            }

            // 获取栏目下的文章列表
            $page = (int)$this->get('page', 1);
            if ($page < 1) {
                $page = 1;
            }
            $limit = 10; // 每页显示10篇文章

            $articles = $this->contentModel->getList($page, $limit, ['cid' => $categoryId]);

            // 判定模板：如果是 doctorlist.latte，调用 DoctorService 处理数据
            $listTemplate = $this->resolveListTemplate($category);
            $filterData = [];

            $tplBase = basename($listTemplate);
            if ($tplBase === 'doctorlist.latte' || $tplBase === 'doctorlist') {
                // 处理专家列表数据
                $articles = $this->doctorService->processDoctorList($articles);

                // 获取筛选数据（科室和职称）
                $filterData = $this->doctorService->getFilterData($categoryId);
            }

            // 计算分页信息
            $total = $articles['total'] ?? 0;
            $totalPages = (int)max(1, ceil($total / $limit));

            // 构建分页HTML
            $pagination = $this->generatePagination($page, $totalPages, '/c/' . $slug);

            // 使用 SiteService 构建导航数据
            $navItems = $this->siteService->buildNav();

            // 使用 SiteService 构建当前栏目的子栏目数据
            $channelItems = $this->siteService->buildChannelItems($categoryId);

            // 渲染模板
            $this->render($listTemplate, [
                'title' => (string)($category['name'] ?? ''),
                'category' => $category,
                'articles' => $articles,
                'pagination' => $pagination,
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'navItems' => $navItems,
                'channelItems' => $channelItems,
                'filterData' => $filterData,
            ]);
        } catch (\Throwable $e) {
            error_log('栏目列表页加载失败: ' . $e->getMessage());
            error_log('堆栈跟踪: ' . $e->getTraceAsString());
            $this->redirect('/');
        }
    }

    /**
     * 解析栏目列表模板路径
     *
     * @param array<string, mixed> $category 栏目数据
     * @return string 模板路径
     */
    private function resolveListTemplate(array $category): string
    {
        $listTemplate = 'front/list';

        try {
            $tpl = trim((string)($category['template'] ?? ''));
            if ($tpl === '') {
                return $listTemplate;
            }

            // 确保模板文件扩展名
            if (!str_ends_with($tpl, '.latte')) {
                $tpl .= '.latte';
            }

            // 构建完整路径
            $candidate = 'front/' . ltrim($tpl, '/\\');
            $candidatePath = dirname(__DIR__, 1) . '/View/' . $candidate;

            // 检查文件是否存在
            if (is_file($candidatePath)) {
                $listTemplate = $candidate;
            }
        } catch (\Throwable $e) {
            error_log('解析列表模板失败: ' . $e->getMessage());
            // 忽略错误，使用默认模板
        }

        return $listTemplate;
    }

    /**
     * 生成分页HTML
     *
     * @param int $currentPage 当前页码
     * @param int $totalPages 总页数
     * @param string $baseUrl 基础URL
     * @return string 分页HTML
     */
    private function generatePagination(int $currentPage, int $totalPages, string $baseUrl): string
    {
        if ($totalPages <= 1) {
            return '';
        }

        $pagination = '<div class="pagination">';

        // 上一页
        if ($currentPage > 1) {
            $prevPage = $currentPage - 1;
            $pagination .= '<a href="' . htmlspecialchars($baseUrl . '/page/' . $prevPage, ENT_QUOTES, 'UTF-8') . '" class="prev">上一页</a>';
        }

        // 页码链接
        $startPage = max(1, $currentPage - 4);
        $endPage = min($totalPages, $currentPage + 4);

        if ($startPage > 1) {
            $pagination .= '<a href="' . htmlspecialchars($baseUrl . '/page/1', ENT_QUOTES, 'UTF-8') . '">1</a>';
            if ($startPage > 2) {
                $pagination .= '<span class="ellipsis">...</span>';
            }
        }

        for ($i = $startPage; $i <= $endPage; $i++) {
            if ($i === $currentPage) {
                $pagination .= '<span class="current">' . $i . '</span>';
            } else {
                $pagination .= '<a href="' . htmlspecialchars($baseUrl . '/page/' . $i, ENT_QUOTES, 'UTF-8') . '">' . $i . '</a>';
            }
        }

        if ($endPage < $totalPages) {
            if ($endPage < $totalPages - 1) {
                $pagination .= '<span class="ellipsis">...</span>';
            }
            $pagination .= '<a href="' . htmlspecialchars($baseUrl . '/page/' . $totalPages, ENT_QUOTES, 'UTF-8') . '">' . $totalPages . '</a>';
        }

        // 下一页
        if ($currentPage < $totalPages) {
            $nextPage = $currentPage + 1;
            $pagination .= '<a href="' . htmlspecialchars($baseUrl . '/page/' . $nextPage, ENT_QUOTES, 'UTF-8') . '" class="next">下一页</a>';
        }

        $pagination .= '</div>';

        return $pagination;
    }
}
