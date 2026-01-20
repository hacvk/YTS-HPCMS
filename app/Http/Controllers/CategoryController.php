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
            $limit = 6; // 每页显示6篇文章

            // 检查当前栏目是否为频道
            $currentCategory = $this->db->get('sys_category', ['id', 'parent_id', 'name', 'slug', 'type', 'template', 'detail_template', 'sort', 'is_hidden', 'is_channel', 'banner', 'create_time', 'update_time'], ['id' => $categoryId]);
            $isChannel = !empty($currentCategory) && $currentCategory['is_channel'] == 1;

            // 如果当前栏目是频道，则获取所有子分类的文章
            if ($isChannel) {
                // 获取当前栏目的所有子分类ID
                $subCategoryIds = $this->categoryService->getAllChildrenIds($categoryId);
                
                if (!empty($subCategoryIds)) {
                    // 将当前栏目ID也加入到查询条件中（以防频道栏目本身也有文章）
                    $allCategoryIds = array_merge([$categoryId], $subCategoryIds);
                    
                    // 获取所有子分类的文章列表
                    $articles = $this->contentModel->getList($page, $limit, ['cids' => $allCategoryIds]);
                } else {
                    // 如果频道栏目没有子分类，返回空列表
                    $articles = ['list' => [], 'total' => 0];
                }
            } else {
                // 普通栏目：只获取当前栏目的文章
                $articles = $this->contentModel->getList($page, $limit, ['cid' => $categoryId]);
            }

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
            
            // 构建侧边栏数据
            $sidebarItems = $this->buildSidebarItems($category);

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
                'sidebarItems' => $sidebarItems,
                'filterData' => $filterData,
                'breadcrumbs' => $breadcrumbs,
            ];
            
            // 如果是 kslist 模板，则获取子栏目和对应的文章数据
            // 检查模板路径是否包含 kslist（支持 'front/kslist' 和 'front/kslist.latte' 两种格式）
            $templateBaseName = basename($listTemplate, '.latte');
            if ($templateBaseName === 'kslist') {
                // 获取当前栏目的直接子栏目
                $allSubCategories = $this->db->select('sys_category', [
                    'id',
                    'name',
                    'slug'
                ], [
                    'parent_id' => $categoryId,
                    'is_hidden' => 0,
                    'ORDER' => ['sort' => 'ASC', 'id' => 'ASC']
                ]) ?: [];
                
                $subCategories = [];
                $subCategoryArticles = [];
                
                // 为每个子栏目获取文章列表（每个子栏目最多显示12篇文章）
                // 只包含有文章的子栏目
                foreach ($allSubCategories as $subCat) {
                    $subCatId = (int)$subCat['id'];
                    $subArticles = $this->contentModel->getList(1, 12, [
                        'cid' => $subCatId
                    ]);
                    $articleList = $subArticles['list'] ?? [];
                    
                    // 只添加有文章的子栏目
                    if (!empty($articleList)) {
                        $subCategories[] = $subCat;
                        // 使用字符串键确保与模板中的 $subCategory['id'] 匹配（Medoo 返回的 id 可能是字符串）
                        $subCategoryArticles[(string)$subCatId] = $articleList;
                    }
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
     * 构建侧边栏菜单项
     *
     * @param array $currentCategory 当前栏目
     * @return array
     */
    private function buildSidebarItems(array $currentCategory): array
    {
        $categoryId = (int)($currentCategory['id'] ?? 0);
        
        if ($categoryId <= 0) {
            return [];
        }
        
        try {
            // 获取当前栏目的父级ID
            $parentId = (int)($currentCategory['parent_id'] ?? 0);
            
            // 如果当前栏目是顶级栏目（parent_id为0或自身），则显示其子栏目
            if ($parentId <= 0) {
                // 获取当前栏目的子栏目 - 简化查询，移除 COUNT
                $subCategories = $this->db->select('sys_category', [
                    'id',
                    'name',
                    'slug',
                    'parent_id',
                    'type'
                ], [
                    'parent_id' => $categoryId,
                    'is_hidden' => 0,
                    'ORDER' => ['sort' => 'ASC', 'id' => 'ASC']
                ]);
                
                // 构建子栏目菜单项
                $items = [];
                foreach ($subCategories as $subCategory) {
                    $items[] = [
                        'id' => $subCategory['id'],
                        'name' => $subCategory['name'],
                        'url' => '/c/' . $subCategory['slug'],
                        'type' => $subCategory['type']
                    ];
                }
                
                return $items;
            } else {
                // 如果当前栏目是子栏目，则显示同父级的其他栏目 - 简化查询
                $siblingCategories = $this->db->select('sys_category', [
                    'id',
                    'name',
                    'slug',
                    'parent_id',
                    'type'
                ], [
                    'parent_id' => $parentId,
                    'is_hidden' => 0,
                    'ORDER' => ['sort' => 'ASC', 'id' => 'ASC']
                ]);
                
                // 构建同级栏目菜单项
                $items = [];
                foreach ($siblingCategories as $siblingCategory) {
                    $items[] = [
                        'id' => $siblingCategory['id'],
                        'name' => $siblingCategory['name'],
                        'url' => '/c/' . $siblingCategory['slug'],
                        'type' => $siblingCategory['type']
                    ];
                }
                
                return $items;
            }
        } catch (\Throwable $e) {
            error_log('构建侧边栏菜单失败: ' . $e->getMessage());
            return [];
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
                
            // 防止路径遍历攻击：确保文件名不包含 ../ 等危险字符
            if (str_contains($tpl, '..') || str_contains($tpl, '/') || str_contains($tpl, '\\')) {
                error_log('CategoryController::resolveListTemplate - 模板文件名包含非法字符: ' . $tpl);
                return $listTemplate;
            }
            
            // 验证文件名格式：只允许字母、数字、下划线、横杠和点号，且必须以.latte结尾或可以添加.latte
            $tplBase = str_replace('.latte', '', $tpl);
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $tplBase)) {
                error_log('CategoryController::resolveListTemplate - 模板文件名格式无效: ' . $tpl);
                return $listTemplate;
            }
    
            // 确保模板文件扩展名
            if (!str_ends_with($tpl, '.latte')) {
                $tpl .= '.latte';
            }
    
            // 构建完整路径并检查文件是否存在（动态验证，允许自定义模板）
            $candidate = 'front/' . $tpl;
            $candidatePath = dirname(__DIR__, 3) . '/views/' . $candidate;
            
            error_log('CategoryController::resolveListTemplate - 检查模板文件: ' . $candidatePath);
    
            // 检查文件是否存在
            if (is_file($candidatePath)) {
                $listTemplate = $candidate;
                error_log('CategoryController::resolveListTemplate - 模板文件存在，使用: ' . $listTemplate);
            } else {
                error_log('CategoryController::resolveListTemplate - 模板文件不存在: ' . $candidatePath . '，使用默认模板');
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
