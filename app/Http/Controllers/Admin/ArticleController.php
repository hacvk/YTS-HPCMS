<?php
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\AdminBaseController;
use App\Core\App;
use App\Core\Security;
use App\Models\Content;
use App\Models\Category;
use App\Services\ContentService;
use App\Services\CategoryService;
use App\Services\LogService;

/**
 * 文章管理控制器
 */
class ArticleController extends AdminBaseController
{
    public function __construct(
        private readonly ContentService $contentService,
        private readonly CategoryService $categoryService,
        private readonly Content $contentModel,
        private readonly Category $categoryModel
    ) {
        parent::__construct();
    }

    /**
     * 文章列表页
     *
     * @return void
     */
    public function index(): void
    {
        $this->requirePerm('admin:article:view');

        try {
            $page = (int)($this->get('page', 1));
            $limit = (int)($this->get('limit', 10));
            $cid = (int)($this->get('cid', 0));
            $keyword = $this->get('keyword', '');
            
            // 构建查询条件
            $where = [];
            if ($cid > 0) {
                $where['cid'] = $cid;
            }
            if (!empty($keyword)) {
                $where['keyword'] = $keyword;
            }
            
            // 获取文章列表
            $result = $this->contentModel->getList($page, $limit, $where);
            
            // 调试信息（开发环境）
            if (App::isDebug()) {
                error_log('文章列表查询结果: ' . json_encode([
                    'count' => count($result['list'] ?? []),
                    'total' => $result['total'] ?? 0,
                    'page' => $page,
                    'limit' => $limit
                ], JSON_UNESCAPED_UNICODE));
            }
            
            // 获取所有栏目用于筛选
            $categories = $this->categoryModel->getAll();
            
            $this->render('admin/article/index', [
                'title' => '文章管理',
                'articles' => $result['list'] ?? [],
                'total' => $result['total'] ?? 0,
                'page' => $page,
                'limit' => $limit,
                'cid' => $cid,
                'keyword' => $keyword,
                'categories' => $categories ?? []
            ]);
        } catch (\Exception $e) {
            error_log('获取文章列表失败: ' . $e->getMessage());
            error_log('错误堆栈: ' . $e->getTraceAsString());
            $this->render('admin/article/index', [
                'title' => '文章管理',
                'articles' => [],
                'total' => 0,
                'page' => 1,
                'limit' => 10,
                'cid' => 0,
                'keyword' => '',
                'categories' => [],
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * 添加文章页面
     *
     * @return void
     */
    public function add(): void
    {
        $this->requirePerm('admin:article:save');

        try {
            error_log('开始加载添加文章页面..');
            
            // 获取 URL 中的 cid 参数（快捷导航传入的栏目ID）
            $preCid = (int)($this->get('cid', 0));
            
            // 获取栏目树
            error_log('获取栏目树..');
            $categories = $this->categoryService->getTree();
            error_log('栏目树获取成功，共' . count($categories) . ' 个顶级栏目');
            
            // 渲染模板
            error_log('渲染模板 admin/article/edit...');
            $this->render('admin/article/edit', [
                'title' => '添加文章',
                'action' => '/admin/article/save',
                'article' => [],
                'article_json' => 'null',
                'categories' => $categories,
                'pre_cid' => $preCid
            ]);
            error_log('添加文章页面加载成功');
        } catch (\Exception $e) {
            // 记录页面加载失败日志
            $errorMsg = $e->getMessage();
            LogService::record(
                $this->getAdminId(),
                $this->getAdminUsername(),
                'error',
                "添加文章页面加载失败",
                "加载添加文章页面失败: {$errorMsg}"
            );
            error_log('加载添加文章页面失败: ' . $errorMsg);
            error_log('错误文件: ' . $e->getFile());
            error_log('错误行号: ' . $e->getLine());
            error_log('错误堆栈: ' . $e->getTraceAsString());
            $this->error('加载页面失败: ' . $errorMsg);
        }
    }
    
    /**
     * 编辑文章页面
     *
     * @return void
     */
    public function edit(): void
    {
        $this->requirePerm('admin:article:save');

        try {
            // 从URL中获取ID参数
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            preg_match('/\/admin\/article\/edit\/(\d+)/', $uri, $matches);
            $id = $matches[1] ?? 0;
            
            if ((int)$id <= 0) {
                $this->error('文章ID无效');
                return;
            }
            
            // 获取文章数据
            try {
                $article = $this->contentService->getOne((int)$id);
            } catch (\App\Core\Exception\RecordNotFoundException $e) {
                $this->error('文章不存在');
                return;
            }
            
            // 格式化发布时间为datetime-local格式
            if (!empty($article['published_at'])) {
                $article['published_at_formatted'] = date('Y-m-d\TH:i', strtotime($article['published_at']));
            } else {
                $article['published_at_formatted'] = '';
            }
            
            // 确保新字段存在
            $article['is_top'] = $article['is_top'] ?? 0;
            $article['is_image_news'] = $article['is_image_news'] ?? 0;
            
            // 获取栏目树
            $categories = $this->categoryService->getTree();
            
            // 将文章数据编码为 JSON，供前端使用
            $articleJson = json_encode($article, JSON_UNESCAPED_UNICODE);
            
            $this->render('admin/article/edit', [
                'title' => '编辑文章',
                'action' => '/admin/article/save',
                'article' => $article,
                'article_json' => $articleJson,
                'categories' => $categories
            ]);
        } catch (\Exception $e) {
            // 记录页面加载失败日志
            $errorMsg = $e->getMessage();
            LogService::record(
                $this->getAdminId(),
                $this->getAdminUsername(),
                'error',
                "编辑文章页面加载失败: ID {$id}",
                "加载编辑文章页面失败: {$errorMsg}"
            );
            error_log('加载编辑文章页面失败: ' . $errorMsg);
            $this->error('加载页面失败');
        }
    }
    
    /**
     * 保存文章（添加或更新）
     *
     * @return void
     */
    public function save(): void
    {
        $this->requirePerm('admin:article:save');

        // 验证POST请求和CSRF
        if (!$this->validatePostAndCsrf()) {
            return;
        }

        try {
            $data = [
                'id' => (int)$this->post('id', 0),
                'category_id' => (int)$this->post('category_id', 0),
                'title' => $this->post('title', ''),
                'cover_img' => $this->post('cover_img', ''),
                'summary' => $this->post('summary', ''),
                'content' => Security::sanitizeHtml($this->post('content', '')),
                'published_at' => $this->post('published_at', ''),
                'status' => (int)$this->post('status', 1),
                'is_top' => (int)$this->post('is_top', 0),
                'is_image_news' => (int)$this->post('is_image_news', 0),
            ];

            $allPostData = $_POST;
            $excludeFields = [
                'id', 'category_id', 'title', 'cover_img', 'summary',
                'content', 'status', 'published_at', 'is_top', 'is_image_news', 'csrf_token'
            ];

            foreach ($allPostData as $key => $value) {
                if (!in_array($key, $excludeFields, true) && !is_array($value)) {
                    $data[$key] = $value;
                }
            }

            $result = $this->contentService->save(
                $data,
                $this->getAdminId(),
                $this->getAdminUsername()
            );

            // 如果保存的数据中包含 category_id，跳转时带上 cid 参数
            $redirectUrl = '/admin/article';
            if (isset($data['category_id']) && $data['category_id'] > 0) {
                $redirectUrl .= '?cid=' . (int)$data['category_id'];
            }

            $this->successJson('保存成功', null, $redirectUrl);
        } catch (\App\Core\Exception\ValidationException $e) {
            $this->errorJson($e->getMessage());
        } catch (\App\Core\Exception\DatabaseOperationException $e) {
            error_log('保存文章失败: ' . $e->getMessage());
            $this->errorJson('保存失败: ' . $e->getMessage());
        } catch (\Exception $e) {
            error_log('保存文章异常: ' . $e->getMessage());
            $this->errorJson('保存失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 删除文章
     *
     * @return void
     */
    public function delete(): void
    {
        $this->requirePerm('admin:article:delete');

        // 只接受POST请求
        if (!$this->isPost()) {
            $this->errorJson('请求方法错误');
            return;
        }
        
        try {
            // 从URL中获取ID参数
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            preg_match('/\/admin\/article\/delete\/(\d+)/', $uri, $matches);
            $id = $matches[1] ?? 0;
            
            if ((int)$id <= 0) {
                $this->errorJson('文章ID无效');
                return;
            }
            
            // 验证POST请求和CSRF
            if (!$this->validatePostAndCsrf()) {
                return;
            }
            
            // 获取文章信息（用于日志）
            $articleTitle = '未知';
            try {
                $article = $this->contentService->getOne((int)$id);
                $articleTitle = $article['title'] ?? '未知';
            } catch (\App\Core\Exception\RecordNotFoundException $e) {
                // 文章不存在，继续删除操作（幂等）
            }
            
            // 删除文章
            $result = $this->contentModel->delete((int)$id);
            
            if (!$result) {
                // 记录删除失败日志
                LogService::record(
                    $this->getAdminId(),
                    $this->getAdminUsername(),
                    'error',
                    "文章删除失败: {$articleTitle} (ID: {$id})",
                    "删除文章失败，标题 {$articleTitle}"
                );
                $this->errorJson('删除失败');
                return;
            }
            
            // 记录删除成功日志
            LogService::record(
                $this->getAdminId(),
                $this->getAdminUsername(),
                'delete',
                "文章ID: {$id}",
                "删除文章: {$articleTitle} (ID: {$id})"
            );
            
            $this->successJson('删除成功', null, '/admin/article');
        } catch (\Exception $e) {
            // 记录异常日志
            $errorMsg = $e->getMessage();
            LogService::record(
                $this->getAdminId(),
                $this->getAdminUsername(),
                'error',
                "文章删除异常: ID {$id}",
                "删除文章异常: {$errorMsg}"
            );
            error_log('删除文章失败: ' . $errorMsg);
            $this->errorJson('删除失败: ' . $errorMsg);
        }
    }
}