<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\Admin\AdminBaseController;
use App\Core\Security;
use App\Model\Content;
use App\Model\Category;
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
            $categories = Category::getAll();
            $pageCategories = [];
            
            foreach ($categories as $category) {
                if (isset($category['type']) && $category['type'] === 'page') {
                    $pageCategories[] = $category;
                }
            }
            
            // 获取每个单页栏目的内容
            $pages = [];
            foreach ($pageCategories as $category) {
                $content = Content::getByCategoryId((int)$category['id']);
                $pages[] = [
                    'category_id' => $category['id'],
                    'category_name' => $category['name'],
                    'category_slug' => $category['slug'] ?? '',
                    'content' => $content,
                    'has_content' => $content !== false
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
            $allCategories = Category::getAll();
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

        try {
            // 从URL中获取栏目ID参数
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            preg_match('/\/admin\/page\/edit\/(\d+)/', $uri, $matches);
            $categoryId = $matches[1] ?? 0;
            
            if ((int)$categoryId <= 0) {
                $this->error('栏目ID无效');
                return;
            }
            
            // 验证栏目类型是否为单页
            $category = Category::getById((int)$categoryId);
            if (!$category) {
                $this->error('栏目不存在');
                return;
            }
            
            if (!isset($category['type']) || $category['type'] !== 'page') {
                $this->error('该栏目不是单页类型');
                return;
            }
            
            // 获取单页内容
            $page = Content::getByCategoryId((int)$categoryId);
            
            if (!$page) {
                // 如果没有内容，跳转到添加页面
                $this->redirect('/admin/page/add?category_id=' . $categoryId);
                return;
            }
            
            // 获取单页类型的栏目列表
            $allCategories = Category::getAll();
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
    
    /**
     * 保存单页（添加或更新）
     *
     * @return void
     */
    public function save(): void
    {
        $this->requirePerm('admin:page:save');

        // 只接受POST请求
        if (!$this->isPost()) {
            $this->errorJson('请求方法错误');
            return;
        }
        
        try {
            // 验证CSRF Token
            $csrfToken = $this->post('csrf_token', '');
            if (!Security::checkCsrfToken($csrfToken)) {
                $this->errorJson('CSRF Token验证失败');
                return;
            }
            
            // 获取提交的数据
            $id = (int)$this->post('id', 0);
            $categoryId = (int)$this->post('category_id', 0);
            $title = $this->post('title', '');
            $coverImg = $this->post('cover_img', '');
            $summary = $this->post('summary', '');
            $content = $this->post('content', ''); // 富文本内容，保留HTML
            
            // 验证必填字段
            if (empty($title)) {
                $this->errorJson('标题不能为空');
                return;
            }
            
            if ($categoryId <= 0) {
                $this->errorJson('请选择栏目');
                return;
            }
            
            // 验证栏目类型
            $category = Category::getById($categoryId);
            if (!$category || !isset($category['type']) || $category['type'] !== 'page') {
                $this->errorJson('请选择单页类型的栏目');
                return;
            }
            
            // 检查该栏目是否已有单页内容（如果是新增）
            if ($id <= 0) {
                $existingPage = Content::getByCategoryId($categoryId);
                if ($existingPage) {
                    $this->errorJson('该栏目已有单页内容，请编辑现有内容');
                    return;
                }
            }
            
            // 准备保存数据
            $data = [
                'category_id' => $categoryId,
                'title' => Security::clean($title),
                'cover_img' => Security::clean($coverImg),
                'summary' => Security::clean($summary),
                'content' => $content, // 富文本内容不进行HTML转义
                'status' => 1, // 单页默认已发布
                'published_at' => date('Y-m-d H:i:s')
            ];
            
            // 如果有ID，则是更新
            if ($id > 0) {
                $data['id'] = $id;
            }
            
            // 获取所有其他字段（扩展字段）
            $allPostData = $_POST;
            $excludeFields = ['id', 'category_id', 'title', 'cover_img', 'summary', 'content', 'status', 'published_at', 'csrf_token'];
            
            foreach ($allPostData as $key => $value) {
                if (!in_array($key, $excludeFields) && !is_array($value)) {
                    $data[$key] = Security::clean($value);
                }
            }
            
            // 保存单页
            $result = Content::save($data);
            
            if ($result === false) {
                LogService::record(
                    $this->getAdminId(),
                    $this->getAdminUsername(),
                    'error',
                    "单页保存失败: {$title}",
                    "保存单页失败，标题: {$title}"
                );
                $this->errorJson('保存失败');
                return;
            }
            
            // 记录成功日志
            $action = $id > 0 ? 'update' : 'create';
            $message = $id > 0 ? "编辑单页: {$title} (ID: {$result})" : "新建单页: {$title} (ID: {$result})";
            LogService::record(
                $this->getAdminId(),
                $this->getAdminUsername(),
                $action,
                "单页ID: {$result}",
                $message
            );
            
            $this->successJson('保存成功', null, '/admin/page');
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            LogService::record(
                $this->getAdminId(),
                $this->getAdminUsername(),
                'error',
                "单页保存异常: {$title}",
                "保存单页异常: {$errorMsg}"
            );
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

        // 只接受POST请求
        if (!$this->isPost()) {
            $this->errorJson('请求方法错误');
            return;
        }
        
        try {
            // 从URL中获取ID参数
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            preg_match('/\/admin\/page\/delete\/(\d+)/', $uri, $matches);
            $id = $matches[1] ?? 0;
            
            if ((int)$id <= 0) {
                $this->errorJson('单页ID无效');
                return;
            }
            
            // 验证CSRF Token
            $csrfToken = $this->post('csrf_token', '');
            if (!Security::checkCsrfToken($csrfToken)) {
                $this->errorJson('CSRF Token验证失败');
                return;
            }
            
            // 获取单页信息（用于日志）
            $page = Content::getOne((int)$id);
            $pageTitle = $page ? ($page['title'] ?? '未知') : '未知';
            
            // 删除单页
            $result = Content::delete((int)$id);
            
            if (!$result) {
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
            
            // 记录删除成功日志
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
