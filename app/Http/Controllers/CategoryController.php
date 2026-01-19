<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Content;
use App\Services\DoctorService;
use App\Services\SiteService;
use App\Services\CategoryService;
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
        private readonly CategoryService $categoryService,
        private readonly Medoo $db
    ) {
        parent::__construct();
    }

    /**
     * 栏目列表页
     * 对应 HomeController::category() 方法
     *
     * @return void
     */
    public function index(): void
    {
        error_log('CategoryController::index() called');
        $slug = $this->get('slug', '');

        if (empty($slug)) {
            $this->redirect('/');
            return;
        }

        try {
            error_log('Looking for slug: ' . $slug);
            // 获取栏目信息
            $category = $this->db->get('sys_category', '*', [
                'slug' => $slug,
                'is_hidden' => 0
            ]);
            
            if ($category) {
                error_log('Category found: ' . print_r($category, true));
            } else {
                error_log('Category not found for slug: ' . $slug);
            }

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
            error_log('CategoryController::index - About to resolve template for category: ' . ($category['name'] ?? 'unknown') . ', template field: ' . ($category['template'] ?? 'not set'));
            $listTemplate = $this->resolveListTemplate($category);
            error_log('CategoryController::index - Resolved template: ' . $listTemplate);
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

            // 构建面包屑导航
            $breadcrumbs = [];
            if (isset($category['id'])) {
                $path = $this->categoryService->getPath((int)$category['id']);
                foreach ($path as $cat) {
                    $breadcrumbs[] = [
                        'title' => $cat['name'],
                        'url' => '/c/' . $cat['slug']
                    ];
                }
            }

            error_log('Rendering template: ' . $listTemplate . ' for category: ' . ($category['name'] ?? 'unknown'));
            
            // 根据模板类型决定是否获取子栏目数据
            $templateData = [
                'title' => (string)($category['name'] ?? ''),
                'category' => $category,
                'articles' => $articles,
                'pagination' => $pagination,
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'navItems' => $navItems,
                'channelItems' => $channelItems,
                'filterData' => $filterData,
                'breadcrumbs' => $breadcrumbs,
            ];
            
            // 如果是 kslist 模板，则获取子栏目和对应的文章数据
            if ($listTemplate === 'front/kslist') {
                // 获取当前栏目的直接子栏目
                $subCategories = $this->db->select('sys_category', [
                    'id',
                    'name',
                    'slug'
                ], [
                    'parent_id' => $categoryId,
                    'is_hidden' => 0,
                    'ORDER' => ['sort' => 'ASC', 'id' => 'ASC']
                ]) ?: [];
                
                $subCategoryArticles = [];
                
                // 为每个子栏目获取文章列表
                foreach ($subCategories as $subCat) {
                    $subArticles = $this->contentModel->getList(1, 12, [
                        'cid' => $subCat['id']
                    ]);
                    $subCategoryArticles[$subCat['id']] = $subArticles['list'] ?? [];
                }
                
                $templateData['subCategories'] = $subCategories;
                $templateData['subCategoryArticles'] = $subCategoryArticles;
            }
            
            // 渲染模板
            $this->render($listTemplate, $templateData);
        } catch (\Throwable $e) {
            error_log('栏目列表页加载失败 ' . $e->getMessage());
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
            error_log('CategoryController::resolveListTemplate - received template: ' . $tpl);
            error_log('CategoryController::resolveListTemplate - category data: ' . print_r($category, true));
            
            if ($tpl === '') {
                error_log('CategoryController::resolveListTemplate - template is empty, using default');
                return $listTemplate;
            }
                
            // 验证允许的模板列表
            $allowedTemplates = [
                'list',
                'list.latte',
                'doctorlist',
                'doctorlist.latte',
                'kslist',
                'kslist.latte'
            ];
            
            error_log('CategoryController::resolveListTemplate - checking if template is allowed: ' . $tpl);
            error_log('CategoryController::resolveListTemplate - allowed templates: ' . print_r($allowedTemplates, true));
            
            if (!in_array($tpl, $allowedTemplates, true)) {
                error_log('CategoryController::resolveListTemplate - 模板无效: ' . $tpl);
                error_log('CategoryController::resolveListTemplate - returning default template: ' . $listTemplate);
                return $listTemplate; // 返回默认模板
            }
            error_log('CategoryController::resolveListTemplate - template is valid: ' . $tpl);
    
            // 确保模板文件扩展名
            error_log('CategoryController::resolveListTemplate - before ends_with check: ' . $tpl);
            if (!str_ends_with($tpl, '.latte')) {
                $tpl .= '.latte';
                error_log('CategoryController::resolveListTemplate - added .latte extension, now: ' . $tpl);
            } else {
                error_log('CategoryController::resolveListTemplate - already has .latte extension');
            }
    
            // 构建完整路径
            error_log('CategoryController::resolveListTemplate - before adding .latte extension: ' . $tpl);
            $candidate = 'front/' . ltrim($tpl, '/\\');
            error_log('CategoryController::resolveListTemplate - candidate template: ' . $candidate);
            $candidatePath = dirname(__DIR__, 3) . '/views/' . $candidate;
            error_log('CategoryController::resolveListTemplate - candidate path: ' . $candidatePath);
                
            // 添加调试信息
            error_log('Template check - candidate: ' . $candidate);
            error_log('Template check - candidatePath: ' . $candidatePath);
            error_log('Template check - file exists: ' . (is_file($candidatePath) ? 'true' : 'false'));
    
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
