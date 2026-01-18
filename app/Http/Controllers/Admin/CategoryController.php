<?php
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Database;
use App\Core\Security;
use App\Models\Category;
use App\Models\Content;
use App\Services\CategoryService;
use App\Services\LogService;
use App\Enums\CategoryType;

/**
 * 栏目管理控制器
 */
class CategoryController extends AdminBaseController
{
    public function __construct(
        private readonly CategoryService $categoryService,
        private readonly Category $categoryModel,
        private readonly Content $contentModel
    ) {
        parent::__construct();
    }

    /**
     * 栏目列表
     *
     * @return void
     */
    public function index(): void
    {
        try {
            // 获取栏目树
            $categoryService = $this->categoryService;
            $categories = $categoryService->getTree();

            $this->render('admin/category/index', [
                'title' => '栏目管理',
                'categories' => $categories,
            ]);
        } catch (\Exception $e) {
            $this->errorJson('加载栏目列表失败：' . $e->getMessage());
        }
    }

    /**
     * 添加栏目
     *
     * @return void
     */
    public function add(): void
    {
        try {
            // 生成 CSRF Token
            $csrfToken = Security::generateCsrfToken();

            // 获取栏目树（用于父级下拉框）
            $categoryService = $this->categoryService;
            $categories = $categoryService->getTree();

            $parentId = (int)($this->get('parent_id', 0));

            $this->render('admin/category/edit', [
                'title' => '添加栏目',
                'csrf_token' => $csrfToken,
                'categories' => $categories,
                'category' => null,
                'parent_id' => $parentId,
            ]);
        } catch (\Exception $e) {
            $this->errorJson('加载添加页面失败：' . $e->getMessage());
        }
    }

    /**
     * 编辑栏目
     *
     * @return void
     */
    public function edit(): void
    {
        try {
            $id = (int)($this->get('id') ?? 0);

            if ($id <= 0) {
                $this->errorJson('栏目ID无效');
            }

            // 获取栏目信息
            $categoryModel = $this->categoryModel;
            $category = $categoryModel->getById($id);

            if (!$category) {
                $this->errorJson('栏目不存在');
            }

            // 生成 CSRF Token
            $csrfToken = Security::generateCsrfToken();

            // 获取栏目树（用于父级下拉框）
            $categoryService = $this->categoryService;
            $categories = $categoryService->getTree();

            $this->render('admin/category/edit', [
                'title' => '编辑栏目',
                'csrf_token' => $csrfToken,
                'categories' => $categories,
                'category' => $category,
                'parent_id' => $category['parent_id'],
            ]);
        } catch (\Exception $e) {
            $this->errorJson('加载编辑页面失败：' . $e->getMessage());
        }
    }

    /**
     * 保存栏目
     *
     * @return void
     */
    public function save(): void
    {
        $this->requirePerm('admin:category:save');

        // 只接受POST 请求
        if (!$this->isPost()) {
            $this->errorJson('请求方法错误');
        }

        try {
            // 验证 CSRF Token
            $csrfToken = $this->post('csrf_token', '');
            if (!Security::checkCsrfToken($csrfToken)) {
                $this->errorJson('CSRF Token 验证失败');
            }

            // 获取表单数据
            $id = (int)($this->post('id', 0));
            $name = trim($this->post('name', ''));
            $slug = trim($this->post('slug', ''));
            $typeInput = $this->post('type', 'article');
            $parentId = (int)($this->post('parent_id', 0));
            $sort = (int)($this->post('sort', 0));
            $isHidden = (int)($this->post('is_hidden', 0));

            $template = trim((string)$this->post('template', ''));
            $detailTemplate = trim((string)$this->post('detail_template', ''));
            $banner = trim((string)$this->post('banner', ''));

            // 验证必填字段
            if (empty($name)) {
                $this->errorJson('栏目名称不能为空');
            }

            // 使用枚举验证类型
            $categoryType = CategoryType::tryFrom($typeInput);
            if ($categoryType === null) {
                $this->errorJson('栏目类型无效，支持的类型：' . implode('、', CategoryType::values()));
            }
            $type = $categoryType->value;

            // 如果设置了别名，检查是否重复
            if (!empty($slug)) {
                $categoryModel = $this->categoryModel;
                if ($categoryModel->slugExists($slug, $id > 0 ? $id : null)) {
                    $this->errorJson('别名已存在');
                }
            }

            // 按栏目模型类型处理模板（使用枚举值）
            $typeTemplateMap = [
                CategoryType::Article->value => ['template' => 'list.latte', 'detail_template' => 'article.latte'],
                CategoryType::Page->value => ['template' => '', 'detail_template' => 'pages.latte'],
                CategoryType::Pic->value => ['template' => 'piclist.latte', 'detail_template' => 'pic.latte'],
                CategoryType::Doctor->value => ['template' => 'doctorlist.latte', 'detail_template' => 'doctor.latte'],
            ];

            $template = $template === '' ? ($typeTemplateMap[$type]['template'] ?? '') : $template;
            $detailTemplate = $detailTemplate === '' ? ($typeTemplateMap[$type]['detail_template'] ?? '') : $detailTemplate;

            // 单页内容模型：不允许列表模板
            if ($categoryType === CategoryType::Page) {
                $template = '';
            }

            // 基础白名单校验（防止提交非预期模板）
            $allowedTemplates = ['list.latte', 'piclist.latte', 'doctorlist.latte', ''];
            $allowedDetailTemplates = ['article.latte', 'pages.latte', 'pic.latte', 'doctor.latte'];

            if (!in_array($template, $allowedTemplates, true)) {
                $this->errorJson('列表模板无效');
            }
            if (!in_array($detailTemplate, $allowedDetailTemplates, true)) {
                $this->errorJson('详情模板无效');
            }

            // 验证并处理banner图片路径，确保文件实际存在
            $finalBanner = $banner;
            if (!empty($banner)) {
                // 使用与上传逻辑一致的路径计算方式
                // 修复路径计算逻辑
                $root = dirname(dirname(dirname(__DIR__))); // 从 app/Http/Controllers/Admin 开始
                
                // 确保我们找到的是正确的项目根目录
                if (basename($root) === 'app') {
                    $root = dirname($root); // 上一级才是真正的项目根目录
                }
                $fullPath = $root . '/public' . $banner;
                            
                // 记录调试信息
                error_log("Banner图片路径验证: \$banner = $banner");
                error_log("项目根目录: " . $root);
                error_log("文档根目录: " . ($_SERVER['DOCUMENT_ROOT'] ?? '未设置'));
                error_log("Banner完整路径: \$fullPath = $fullPath");
                error_log("文件是否存在: " . (file_exists($fullPath) ? '是' : '否')); 
                error_log("public目录是否存在: " . (is_dir($root . '/public') ? '是' : '否'));
                error_log("uploads目录是否存在: " . (is_dir($root . '/public/uploads') ? '是' : '否'));
                error_log("images目录是否存在: " . (is_dir($root . '/public/uploads/images') ? '是' : '否'));
                            
                if (!file_exists($fullPath)) {
                    // 如果文件不存在，清空banner字段
                    $finalBanner = '';
                                
                    // 记录日志以便调试
                    LogService::record(
                        $this->getAdminId(), 
                        $this->getAdminUsername(), 
                        'error', 
                        '栏目保存时发现无效的Banner图片路径: ' . $banner . '，文件不存在于: ' . $fullPath
                    );
                }
            }
            
            // 保存数据
            $categoryService = $this->categoryService;
            $categoryId = $categoryService->save([
                'id' => $id > 0 ? $id : null,
                'parent_id' => $parentId,
                'name' => $name,
                'slug' => $slug,
                'type' => $type,
                'template' => $template,
                'detail_template' => $detailTemplate,
                'banner' => $finalBanner,
                'sort' => $sort,
                'is_hidden' => $isHidden,
            ]);

            // 记录日志
            $action = $id > 0 ? 'update' : 'create';
            $message = $id > 0 ? "修改栏目: {$name}" : "添加栏目: {$name}";
            LogService::record($this->getAdminId(), $this->getAdminUsername(), $action, $message);

            $this->successJson('保存成功', $categoryId, '/admin/category');
        } catch (\Exception $e) {
            $this->errorJson('保存失败：' . $e->getMessage());
        }
    }

    /**
     * 删除栏目
     *
     * @return void
     */
    public function delete(): void
    {
        $this->requirePerm('admin:category:delete');

        // 只接受POST 请求
        if (!$this->isPost()) {
            $this->errorJson('请求方法错误');
        }

        try {
            // 验证 CSRF Token
            $csrfToken = $this->post('csrf_token', '');
            if (!Security::checkCsrfToken($csrfToken)) {
                $this->errorJson('CSRF Token 验证失败');
            }

            $id = (int)($this->post('id', 0));

            if ($id <= 0) {
                $this->errorJson('栏目ID无效');
            }

            // 获取栏目信息
            $categoryModel = $this->categoryModel;
            $category = $categoryModel->getById($id);

            if (!$category) {
                $this->errorJson('栏目不存在');
            }

            // 删除栏目
            $categoryService = $this->categoryService;
            $categoryService->delete($id);

            // 记录日志
            LogService::record($this->getAdminId(), $this->getAdminUsername(), 'delete', "删除栏目: {$category['name']}");

            $this->successJson('删除成功', null, '/admin/category');
        } catch (\Exception $e) {
            $this->errorJson('删除失败：' . $e->getMessage());
        }
    }

    /**
     * 获取栏目树（AJAX接口）
     *
     * @return void
     */
    public function tree(): void
    {
        try {
            $categoryService = $this->categoryService;
            $categories = $categoryService->getTree();

            $this->json([
                'code' => 0,
                'data' => $categories,
            ]);
        } catch (\Exception $e) {
            $this->json([
                'code' => 1,
                'message' => '获取栏目树失败：' . $e->getMessage(),
            ]);
        }
    }

    /**
     * 获取模型字段配置（AJAX接口）
     *
     * @return void
     */
    public function getModelFields(): void
    {
        try {
            $typeInput = $this->get('type', '');
            
            if (empty($typeInput)) {
                $this->json([
                    'code' => 0,
                    'data' => [],
                ]);
                return;
            }
            
            // 使用枚举验证类型
            $categoryType = CategoryType::tryFrom($typeInput);
            if ($categoryType === null) {
                $this->json([
                    'code' => 0,
                    'data' => [],
                ]);
                return;
            }
            $type = $categoryType->value;
            
            // 读取模型配置文件
            $modelsFile = \App\Core\App::getConfigPath('models.php');
            
            if (!file_exists($modelsFile)) {
                $this->json([
                    'code' => 0,
                    'data' => [],
                ]);
                return;
            }
            
            $models = require $modelsFile;
            
            // 检查类型是否存在
            if (!isset($models[$type]) || !isset($models[$type]['fields'])) {
                $this->json([
                    'code' => 0,
                    'data' => [],
                ]);
                return;
            }
            
            // 返回字段配置
            $this->json([
                'code' => 0,
                'data' => $models[$type]['fields'],
            ]);
        } catch (\Exception $e) {
            error_log('获取模型字段失败: ' . $e->getMessage());
            $this->json([
                'code' => 1,
                'message' => '获取模型字段失败：' . $e->getMessage(),
                'data' => [],
            ]);
        }
    }

    /**
     * 获取栏目选项列表（用于下拉选择，包含指定栏目及其所有子栏目）
     *
     * @return void
     */
    public function getCategoryOptions(): void
    {
        try {
            $parentId = (int)$this->get('parent_id', 0);
            
            if ($parentId <= 0) {
                $this->json([
                    'code' => 0,
                    'data' => [],
                ]);
                return;
            }
            
            // 获取所有栏目数据
            $categoryModel = $this->categoryModel;
            $allCategories = $categoryModel->getAll();
            
            // 构建选项数组：id => name
            $options = [];
            
            // 递归函数：获取栏目及其子栏目的选项
            $buildOptions = function($currentParentId, $level = 0) use (&$buildOptions, &$options, $allCategories) {
                foreach ($allCategories as $category) {
                    if ((int)$category['parent_id'] === $currentParentId) {
                        $indent = str_repeat('  ', $level);
                        $options[(string)$category['id']] = $indent . $category['name'];
                        // 递归处理子栏目
                        $buildOptions((int)$category['id'], $level + 1);
                    }
                }
            };
            
            // 先添加父栏目本身
            $parentCategory = $categoryModel->getById($parentId);
            if ($parentCategory) {
                $options[(string)$parentId] = $parentCategory['name'];
            }
            
            // 构建子栏目选项
            $buildOptions($parentId, 1);
            
            $this->json([
                'code' => 0,
                'data' => $options,
            ]);
        } catch (\Exception $e) {
            error_log('获取栏目选项失败: ' . $e->getMessage());
            $this->json([
                'code' => 1,
                'message' => '获取栏目选项失败：' . $e->getMessage(),
                'data' => [],
            ]);
        }
    }

    /**
     * 获取栏目下文章选项列表（用于下拉选择，包含指定栏目及其所有子栏目下的文章）
     *
     * @return void
     */
    public function getContentOptions(): void
    {
        try {
            $categoryId = (int)$this->get('category_id', 0);

            if ($categoryId <= 0) {
                $this->json([
                    'code' => 0,
                    'data' => [],
                ]);
                return;
            }

            $categoryIds = [$categoryId];
            $children = $this->categoryService->getAllChildrenIds($categoryId);
            foreach ($children as $cid) {
                $categoryIds[] = (int)$cid;
            }
            $categoryIds = array_values(array_unique(array_filter($categoryIds, fn($v) => $v > 0)));

            if (count($categoryIds) === 0) {
                $this->json([
                    'code' => 0,
                    'data' => [],
                ]);
                return;
            }

            $db = \App\Core\Database::get();

            $list = $db->select('sys_content', [
                'id',
                'title'
            ], [
                'category_id' => $categoryIds,
                'ORDER' => ['id' => 'DESC'],
                'LIMIT' => 1000,
            ]);

            $options = [];
            foreach ($list as $item) {
                $id = (int)($item['id'] ?? 0);
                $title = (string)($item['title'] ?? '');
                if ($id > 0) {
                    $options[(string)$id] = $title !== '' ? $title : ('ID: ' . $id);
                }
            }

            $this->json([
                'code' => 0,
                'data' => $options,
            ]);
        } catch (\Exception $e) {
            error_log('获取文章选项失败: ' . $e->getMessage());
            $this->json([
                'code' => 1,
                'message' => '获取文章选项失败：' . $e->getMessage(),
                'data' => [],
            ]);
        }
    }
}
