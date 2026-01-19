<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Content;
use App\Models\Category;
use App\Services\ContentService;
use App\Services\SiteService;
use App\Services\CategoryService;
use Medoo\Medoo;

/**
 * 单页控制器
 * 处理单页类型栏目的显示逻辑
 */
class PageController extends BaseController
{
    /**
     * 构造函数（使用 PHP 8.0 构造函数属性提升）
     *
     * @param Content $contentModel 内容模型实例
     * @param Category $categoryModel 栏目模型实例
     * @param ContentService $contentService 内容服务实例
     * @param SiteService $siteService 站点服务实例
     * @param CategoryService $categoryService 栏目服务实例
     * @param Medoo $db 数据库实例
     */
    public function __construct(
        private readonly Content $contentModel,
        private readonly Category $categoryModel,
        private readonly ContentService $contentService,
        private readonly SiteService $siteService,
        private readonly CategoryService $categoryService,
        private readonly Medoo $db
    ) {
        parent::__construct();
    }

    /**
     * 单页显示
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

            // 验证栏目类型是否为单页
            if (($category['type'] ?? '') !== 'page') {
                // 如果不是单页类型，重定向到栏目列表
                $this->redirect('/c/' . $slug);
                return;
            }

            $categoryId = (int)($category['id'] ?? 0);
            if ($categoryId <= 0) {
                $this->redirect('/');
                return;
            }

            // 获取单页内容
            try {
                $pageContent = $this->contentService->getByCategoryId($categoryId);
            } catch (\App\Core\Exception\RecordNotFoundException $e) {
                // 如果没有找到单页内容，可以显示空白页面或错误信息
                $pageContent = [
                    'id' => 0,
                    'category_id' => $categoryId,
                    'title' => $category['name'] ?? '',
                    'content' => '<p>暂无内容</p>',
                    'summary' => '',
                    'cover_img' => '',
                    'views' => 0,
                    'status' => 1,
                    'published_at' => date('Y-m-d H:i:s'),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                    'is_top' => 0,
                    'is_image_news' => 0
                ];
            }

            // 使用栏目配置的详情模板，验证模板文件是否存在
            $configuredTemplate = $category['detail_template'] ?? 'front/page';
            
            // 确保模板名称不包含 .latte 扩展名，因为框架会自动添加
            if (str_ends_with($configuredTemplate, '.latte')) {
                $configuredTemplate = substr($configuredTemplate, 0, -6); // 移除最后6个字符 '.latte'
            }
            
            // 验证模板文件是否存在，如果不存在则使用默认模板
            $templatePath = dirname(__DIR__, 3) . '/views/' . $configuredTemplate . '.latte';
            if (file_exists($templatePath)) {
                $template = $configuredTemplate;
            } else {
                // 如果配置的模板不存在，使用默认的单页模板
                $template = 'front/page';
                
                // 记录模板缺失的警告
                error_log("警告：配置的模板文件不存在: {$templatePath}，使用默认模板: front/page");
            }

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

            // 渲染模板
            $this->render($template, [
                'title' => (string)($category['name'] ?? ''),
                'category' => $category,
                'page' => $pageContent,
                'navItems' => $navItems,
                'breadcrumbs' => $breadcrumbs,
            ]);
        } catch (\Throwable $e) {
            error_log('单页显示失败 ' . $e->getMessage());
            error_log('堆栈跟踪: ' . $e->getTraceAsString());
            $this->redirect('/');
        }
    }
}