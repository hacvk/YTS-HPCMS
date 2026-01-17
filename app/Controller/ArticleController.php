<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\Category;
use App\Service\ContentService;
use App\Service\SiteService;

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
        private readonly SiteService $siteService
    ) {
        parent::__construct();
    }

    /**
     * 文章详情页
     * 对应原 HomeController::article() 方法
     *
     * @return void
     */
    public function index(): void
    {
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

            // 使用 SiteService 获取当前文章所属栏目的子栏目数据
            $channelItems = $this->siteService->buildChannelItems($categoryId);

            // 读取栏目详情模板（detail_template）并渲染
            $detailTemplate = $this->resolveDetailTemplate($categoryId);

            // 获取栏目信息
            $category = $this->categoryModel->getById($categoryId);

            $this->render($detailTemplate, [
                'title' => $article['title'] ?? '',
                'article' => $article,
                'category' => $category,
                'prevArticle' => $prevArticle,
                'nextArticle' => $nextArticle,
                'channelItems' => $channelItems,
                'navItems' => $navItems,
            ]);
        } catch (\Throwable $e) {
            error_log('文章详情页加载失败: ' . $e->getMessage());
            error_log('堆栈跟踪: ' . $e->getTraceAsString());
            $this->redirect('/');
        }
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
            $candidatePath = dirname(__DIR__, 1) . '/View/' . $candidate;

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
}
