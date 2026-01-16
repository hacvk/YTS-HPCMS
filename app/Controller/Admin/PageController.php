<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\Admin\AdminBaseController;
use App\Core\Database;
use App\Core\Security;
use App\Model\Content;
use App\Model\Category;
use App\Service\CategoryService;
use App\Service\ContentService;
use App\Service\LogService;

/**
 * 单页管理控制器
 */
class PageController extends AdminBaseController
{
    /**
     * 单页列表页
     *
     * @return void
     */
    public function index(): void
    {
        $this->requirePerm('admin:page:view');

        try {
            // 获取所有单页类型的栏目
            $db = Database::get();
            $categoryModel = new Category($db);
            $categories = $categoryModel->getAll();
            $pageCategories = [];
            
            
            foreach ($categories as $category) {
                if (isset($category['type']) && $category['type'] === 'page') {
                    $pageCategories[] = $category;
                }
            }
            
            // 获取每个单页栏目的内容
            $contentModel = new Content($db);
            $contentService = new ContentService($contentModel, $db);

            $pages = [];
            foreach ($pageCategories as $category) {
                $content = null;
                try {
                    $content = $contentService->getByCategoryId((int)$category['id']);
                } catch (\App\Core\Exception\RecordNotFoundException $e) {
                    $content = null;
                }

                $pages[] = [
                    'category_id' => $category['id'],
                    'category_name' => $category['name'],
                    'category_slug' => $category['slug'] ?? '',
                    'content' => $content,
                    'has_content' => $content !== null
                ];
            }
            
            $this->render('admin/page/index', [
                'title' => '单页管理',
                'pages' => $pages,
            ]);
        } catch (\Exception $e) {
            error_log('获取单页列表失败: ' . $e->getMessage());
            $this->render('admin/page/index', [
                'title' => '单页管理',
                'pages' => [],
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * 添加单页页面
     *
     * @return void
     */
    public function add(): void
    {
        $this->requirePerm('admin:page:save');

        try {
            // 获取单页类型的栏目树
            $db = Database::get();
            $categoryModel = new Category($db);
            $allCategories = $categoryModel->getAll();
            $pageCategories = [];
            
            foreach ($allCategories as $category) {
                if (isset($category['type']) && $category['type'] === 'page') {
                    $pageCategories[] = $category;
                }
            }
            
            // 获取栏目ID参数
            $categoryId = (int)$this->get('category_id', 0);
            
            $this->render('admin/page/edit', [
                'title' => '添加单页',
                'action' => '/admin/page/save',
                'page' => [],
                'page_json' => 'null',
                'categories' => $pageCategories,
                'category_id' => $categoryId
            ]);
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            LogService::record(
                $this->getAdminId(),
                $this->getAdminUsername(),
                'error',
                "添加单页页面加载失败",
                "加载添加单页页面失败: {$errorMsg}"
            );
            error_log('加载添加单页页面失败: ' . $errorMsg);
            $this->error('加载页面失败: ' . $errorMsg);
        }
    }
    
    /**
     * 编辑单页页面
     *
     * @return void
     */
    public function edit(): void
    {
        $this->requirePerm('admin:page:save');

        $categoryId = 0;

        try {
            // 优先从参数读取（Router 动态路由会注入 $_GET['category_id'] 或 $_GET['id']）
            $categoryId = (int)$this->get('category_id', 0);
            if ($categoryId <= 0) {
                $categoryId = (int)$this->get('id', 0);
            }

            if ($categoryId <= 0) {
                $this->error('栏目ID无效');
                return;
            }

            $db = Database::get();
            $categoryModel = new Category($db);
            $contentModel = new Content($db);
            $contentService = new ContentService($contentModel, $db);

            // 验证栏目类型是否为单页
            $category = $categoryModel->getById($categoryId);
            if (!$category) {
                $this->error('栏目不存在');
                return;
            }

            if (!isset($category['type']) || $category['type'] !== 'page') {
                $this->error('该栏目不是单页类型');
                return;
            }

            // 获取单页内容
            try {
                $page = $contentService->getByCategoryId($categoryId);
            } catch (\App\Core\Exception\RecordNotFoundException $e) {
                // 如果没有内容，跳转到添加页面
                $this->redirect('/admin/page/add?category_id=' . $categoryId);
                return;
            }

            // 获取单页类型的栏目列表
            $allCategories = $categoryModel->getAll();
            $pageCategories = [];

            foreach ($allCategories as $cat) {
                if (isset($cat['type']) && $cat['type'] === 'page') {
                    $pageCategories[] = $cat;
                }
            }

            // 将单页数据编码为 JSON，供前端使用
            $pageJson = json_encode($page, JSON_UNESCAPED_UNICODE);

            $this->render('admin/page/edit', [
                'title' => '编辑单页',
                'action' => '/admin/page/save',
                'page' => $page,
                'page_json' => $pageJson,
                'categories' => $pageCategories,
                'category_id' => $categoryId
            ]);
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            LogService::record(
                $this->getAdminId(),
                $this->getAdminUsername(),
                'error',
                "编辑单页页面加载失败: Category ID {$categoryId}",
                "加载编辑单页页面失败: {$errorMsg}"
            );
            error_log('加载编辑单页页面失败: ' . $errorMsg);
            $this->error('加载页面失败');
        }
    }
    
    public function save(): void
    {
        $this->requirePerm('admin:page:save');

        if (!$this->isPost()) {
            $this->errorJson('请求方法错误');
            return;
        }

        $title = '';

        try {
            $csrfToken = $this->post('csrf_token', '');
            if (!Security::checkCsrfToken($csrfToken)) {
                $this->errorJson('CSRF Token验证失败');
                return;
            }

            $id = (int)$this->post('id', 0);
            $categoryId = (int)$this->post('category_id', 0);
            $title = $this->post('title', '');
            $coverImg = $this->post('cover_img', '');
            $summary = $this->post('summary', '');
            $content = Security::sanitizeHtml($this->post('content', ''));

            if (empty($title)) {
                $this->errorJson('标题不能为空');
                return;
            }
            if ($categoryId <= 0) {
                $this->errorJson('请选择栏目');
                return;
            }

            $db = Database::get();
            $categoryModel = new Category($db);
            $contentModel = new Content($db);
            $contentService = new ContentService($contentModel, $db);

            $category = $categoryModel->getById($categoryId);
            if (!$category || ($category['type'] ?? '') !== 'page') {
                $this->errorJson('请选择单页类型的栏目');
                return;
            }

            if ($id <= 0) {
                try {
                    $contentService->getByCategoryId($categoryId);
                    $this->errorJson('该栏目已有单页内容，请编辑现有内容');
                    return;
                } catch (\App\Core\Exception\RecordNotFoundException $e) {
                    // 正常，可以创建
                }
            }

            $data = [
                'category_id' => $categoryId,
                'title' => Security::clean($title),
                'cover_img' => Security::clean($coverImg),
                'summary' => Security::clean($summary),
                'content' => $content,
                'status' => 1,
                'published_at' => date('Y-m-d H:i:s')
            ];

            if ($id > 0) {
                $data['id'] = $id;
            }

            $allPostData = $_POST;
            $excludeFields = ['id', 'category_id', 'title', 'cover_img', 'summary', 'content', 'status', 'published_at', 'csrf_token'];
            foreach ($allPostData as $key => $value) {
                if (!in_array($key, $excludeFields) && !is_array($value)) {
                    $data[$key] = Security::clean($value);
                }
            }

            $resultId = $contentService->save($data);

            $action = $id > 0 ? 'update' : 'create';
            $message = $id > 0 ? "编辑单页: {$title} (ID: {$resultId})" : "新建单页: {$title} (ID: {$resultId})";
            LogService::record($this->getAdminId(), $this->getAdminUsername(), $action, "单页ID: {$resultId}", $message);

            $this->successJson('保存成功', null, '/admin/page');
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            LogService::record($this->getAdminId(), $this->getAdminUsername(), 'error', "单页保存异常: {$title}", "保存单页异常: {$errorMsg}");
            error_log('保存单页失败: ' . $errorMsg);
            $this->errorJson('保存失败: ' . $errorMsg);
        }
    }
    
    /**
     * 删除单页
     *
     * @return void
     */
    public function delete(): void
    {
        $this->requirePerm('admin:page:delete');

        if (!$this->isPost()) {
            $this->errorJson('请求方法错误');
            return;
        }

        $id = 0;
        $pageTitle = '未知';

        try {
            // id 优先来自 POST（Router 动态路由会注入 $_POST['id']）
            $id = (int)$this->post('id', 0);
            if ($id <= 0) {
                $this->errorJson('单页ID无效');
                return;
            }

            $csrfToken = $this->post('csrf_token', '');
            if (!Security::checkCsrfToken($csrfToken)) {
                $this->errorJson('CSRF Token验证失败');
                return;
            }

            $db = Database::get();
            $contentModel = new Content($db);

            // 获取单页信息（用于日志；不存在也允许继续删除，保证幂等）
            try {
                $page = $contentModel->getById($id);
                if ($page) {
                    $pageTitle = $page['title'] ?? '未知';
                }
            } catch (\Throwable $e) {
                // ignore
            }

            // 执行删除
            $deleted = $contentModel->delete($id);
            if (!$deleted) {
                LogService::record(
                    $this->getAdminId(),
                    $this->getAdminUsername(),
                    'error',
                    "单页删除失败: {$pageTitle} (ID: {$id})",
                    "删除单页失败，标题: {$pageTitle}"
                );
                $this->errorJson('删除失败');
                return;
            }

            LogService::record(
                $this->getAdminId(),
                $this->getAdminUsername(),
                'delete',
                "单页ID: {$id}",
                "删除单页: {$pageTitle} (ID: {$id})"
            );

            $this->successJson('删除成功', null, '/admin/page');
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            LogService::record(
                $this->getAdminId(),
                $this->getAdminUsername(),
                'error',
                "单页删除异常: ID {$id}",
                "删除单页异常: {$errorMsg}"
            );
            error_log('删除单页失败: ' . $errorMsg);
            $this->errorJson('删除失败: ' . $errorMsg);
        }
    }
}
