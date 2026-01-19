<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\ContentService;
use App\Services\SiteService;
use App\Services\CategoryService;
use App\Services\DoctorService;

/**
 * 文章控制器
 * 处理文章详情页相关逻辑
 */
class ArticleController extends BaseController
{
    /**
     * 构造函数（使用 PHP 8.0 构造函数属性提升）
     *
     * @param ContentService $contentService 内容服务实例
     * @param Category $categoryModel 栏目模型实例
     * @param SiteService $siteService 站点服务实例
     */
    public function __construct(
        private readonly ContentService $contentService,
        private readonly Category $categoryModel,
        private readonly SiteService $siteService,
        private readonly CategoryService $categoryService,
        private readonly DoctorService $doctorService
    ) {
        parent::__construct();
    }

    /**
     * 文章详情页
     * 对应 HomeController::article() 方法
     *
     * @return void
     */
    public function index(): void
    {
        error_log('ArticleController::index() called');
        $id = (int)$this->get('id', 0);

        // 检查 ID 合法性
        if ($id <= 0) {
            $this->redirect('/');
            return;
        }

        try {
            // 获取文章数据
            $article = $this->contentService->getOne($id);

            // 检查文章是否存在
            if (empty($article)) {
                $this->redirect('/');
                return;
            }

            $categoryId = (int)($article['category_id'] ?? 0);

            // 获取上一篇文章
            $prevArticle = $this->contentService->getPrevArticle($id, $categoryId);

            // 获取下一篇文章
            $nextArticle = $this->contentService->getNextArticle($id, $categoryId);

            // 使用 SiteService 获取导航数据
            $navItems = $this->siteService->buildNav();

            // 构建侧边栏数据
            $sidebarItems = $this->buildSidebarItemsForArticle($categoryId);

            // 读取栏目详情模板（detail_template）并渲染
            $detailTemplate = $this->resolveDetailTemplate($categoryId);

            // 获取栏目信息
            $category = $this->categoryModel->getById($categoryId);
            
            // 如果是医生详情页，需要处理科室ID到科室名称的转换
            $processedArticle = $article;
            if ($category && ($category['type'] ?? '') === 'doctor') {
                // 创建一个模拟的 articles 结构来处理单个文章
                $mockArticles = [
                    'list' => [$article],
                    'total' => 1
                ];
                
                // 使用 DoctorService 处理医生数据
                $processedResult = $this->doctorService->processDoctorList($mockArticles);
                
                // 提取处理后的文章
                $processedList = $processedResult['list'] ?? [];
                if (!empty($processedList)) {
                    $processedArticle = $processedList[0];
                }
            }
            
            // 构建面包屑导航
            $breadcrumbs = $this->buildBreadcrumbs($category);

            $this->render($detailTemplate, [
                'title' => $processedArticle['title'] ?? '',
                'article' => $processedArticle,
                'category' => $category,
                'prevArticle' => $prevArticle,
                'nextArticle' => $nextArticle,
                'sidebarItems' => $sidebarItems,
                'navItems' => $navItems,
                'breadcrumbs' => $breadcrumbs,
            ]);
        } catch (\Throwable $e) {
            error_log('文章详情页加载失败 ' . $e->getMessage());
            error_log('堆栈跟踪: ' . $e->getTraceAsString());
            $this->redirect('/');
        }
    }
    
    /**
     * 构建面包屑导航
     *
     * @param array|null $category 当前栏目
     * @return array
     */
    private function buildBreadcrumbs(?array $category): array
    {
        $breadcrumbs = [];
        
        // 添加首页链接
        $breadcrumbs[] = ['title' => '首页', 'url' => '/'];
        
        if (!$category || !isset($category['id'])) {
            return $breadcrumbs;
        }
        
        // 使用 CategoryService 的 getPath 方法获取栏目路径
        $path = $this->categoryService->getPath((int)$category['id']);
        foreach ($path as $cat) {
            $breadcrumbs[] = [
                'title' => $cat['name'],
                'url' => '/c/' . $cat['slug']
            ];
        }
        
        return $breadcrumbs;
    }

    /**
     * 解析栏目详情模板路径
     *
     * @param int $categoryId 栏目ID
     * @return string 模板路径
     */
    private function resolveDetailTemplate(int $categoryId): string
    {
        $detailTemplate = 'front/article';

        try {
            $category = $this->categoryModel->getById($categoryId);
            if (!$category) {
                return $detailTemplate;
            }

            $tpl = trim((string)($category['detail_template'] ?? ''));
            if ($tpl === '') {
                return $detailTemplate;
            }

            // 确保模板文件扩展名
            if (!str_ends_with($tpl, '.latte')) {
                $tpl .= '.latte';
            }

            // 构建完整路径
            $candidate = 'front/' . ltrim($tpl, '/\\');
            $candidatePath = dirname(__DIR__, 3) . '/views/' . $candidate;
            
            // 添加调试信息
            error_log('Detail template check - candidate: ' . $candidate);
            error_log('Detail template check - candidatePath: ' . $candidatePath);
            error_log('Detail template check - file exists: ' . (is_file($candidatePath) ? 'true' : 'false'));

            // 检查文件是否存在
            if (is_file($candidatePath)) {
                $detailTemplate = $candidate;
            }
        } catch (\Throwable $e) {
            error_log('解析详情模板失败: ' . $e->getMessage());
            // 忽略错误，使用默认模板
        }

        return $detailTemplate;
    }
    
    /**
     * 构建文章页侧边栏菜单项
     *
     * @param int $categoryId 文章所属栏目ID
     * @return array
     */
    private function buildSidebarItemsForArticle(int $categoryId): array
    {
        if ($categoryId <= 0) {
            return [];
        }
        
        try {
            // 获取当前栏目的信息
            $currentCategory = $this->categoryModel->getById($categoryId);
            
            if (!$currentCategory) {
                return [];
            }
            
            // 获取当前栏目的父级ID
            $parentId = (int)($currentCategory['parent_id'] ?? 0);
            
            // 如果当前栏目是顶级栏目（parent_id为0或自身），则显示其子栏目
            if ($parentId <= 0) {
                // 获取当前栏目的子栏目 - 简化查询
                $db = \App\Core\Database::get();
                $subCategories = $db->select('sys_category', [
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
                $db = \App\Core\Database::get();
                $siblingCategories = $db->select('sys_category', [
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
            error_log('构建文章页侧边栏菜单失败: ' . $e->getMessage());
            return [];
        }
    }
}
