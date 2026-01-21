<?php
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Core\App;
use App\Core\Database;
use App\Core\Security;
use App\Core\View;
use App\Services\SessionService;

/**
 * 后台控制器基类
 */
abstract class AdminBaseController extends BaseController
{
    /**
     * 当前登录管理员信息
     *
     * @var array<string, mixed>|null
     */
    protected ?array $admin = null;

    /**
     * 菜单配置
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $menu = [];

    /**
     * 构造函数
     */
    public function __construct()
    {
        parent::__construct();

        // 检查登录状态
        $this->checkLogin();

        // 获取当前管理员信息
        $this->loadAdminInfo();

        // 加载菜单配置
        $this->loadMenu();

        // 注入全局视图数据
        $this->injectViewData();
    }

    /**
     * 检查登录状态
     *
     * @return void
     */
    private function checkLogin(): void
    {
        // 检查管理员 ID（这是登录的唯一标识）
        $adminId = SessionService::get('admin_id');
        
        // 如果没有管理员 ID，说明未登录
        if ($adminId === null || !is_numeric($adminId)) {
            $this->redirect('/admin');
        }

        // 验证 Session 中的其他必要数据
        if (!SessionService::has('admin_username') || !SessionService::has('admin_login_time')) {
            SessionService::destroy();
            $this->redirect('/admin');
        }

        // 检查登录是否过期（24小时）
        $loginTime = SessionService::get('admin_login_time', 0);
        if (time() - $loginTime > 86400) {
            // 安全地销毁 Session
            SessionService::destroy();
            $this->redirect('/admin');
        }
    }

    /**
     * 获取当前登录管理员信息
     *
     * @return void
     */
    private function loadAdminInfo(): void
    {
        $adminId = SessionService::get('admin_id');

        if ($adminId === null) {
            return;
        }

        $db = Database::get();
        $this->admin = $db->get('sys_admin', [
            'id',
            'username',
            'name',
            'status',
        ], ['id' => $adminId]);

        if (!$this->admin) {
            // 管理员不存在，清除 Session 并跳转
            SessionService::destroy();
            $this->redirect('/admin');
        }
    }

    /**
     * 加载菜单配置
     *
     * @return void
     */
    private function loadMenu(): void
    {
        $menuFile = \App\Core\App::getConfigPath('menu.php');

        if (file_exists($menuFile)) {
            $menu = require $menuFile;
        } else {
            $menu = [];
        }
        
        // 动态加载快捷导航栏目
        $this->loadShortcutCategories($menu);
        
        // 根据管理员权限过滤菜单
        $this->menu = $this->filterMenuByPerm($menu);
    }

    /**
     * 注入全局视图数据
     *
     * @return void
     */
    private function injectViewData(): void
    {
        $currentUri = $_SERVER['REQUEST_URI'] ?? '/';
        
        // 处理菜单，标记需要展开的父级菜单
        $processedMenu = $this->processMenu($this->menu, $currentUri);
        
        $this->assign('menu', $processedMenu);
        $this->assign('user', $this->admin);
        $this->assign('current_uri', $currentUri);
        $this->assign('csrf_token', Security::generateCsrfToken());

        // 注入系统信息（用于后台页面关于等展示）
        $this->assign('system', App::getConfig('system', []));
    }

    /**
     * 检查是否启用权限过滤
     * 如果管理员有角色配置或权限表中有数据，则启用权限过滤
     *
     * @param int $adminId
     * @return bool
     */
    /**
     * 检查是否有权限
     *
     * @param string $perm 权限标识
     * @return bool
     */
    protected function hasPerm(string $perm): bool
    {
        if (empty($perm)) {
            return true;
        }

        // 从Session获取当前管理员的权限列表
        $permissions = $this->getAdminPermissions();
        
        // 检查权限是否存在
        return in_array($perm, $permissions, true);
    }
    
    /**
     * 获取当前管理员的权限列表
     * 从Session获取，如果不存在则从数据库查询并缓存
     *
     * @return array<string>
     */
    private function getAdminPermissions(): array
    {
        // 尝试从Session获取
        $permissions = SessionService::get('admin_permissions', []);
        if (!empty($permissions) && is_array($permissions)) {
            return $permissions;
        }
        
        // 从数据库查询
        $adminId = $this->getAdminId();
        if ($adminId <= 0) {
            return [];
        }
        
        $db = Database::get();
        
        // 获取管理员的角色ID
        $roleIds = $db->select('sys_admin_role', ['role_id'], ['admin_id' => $adminId]);
        $roleIds = array_map(fn($item) => (int)$item['role_id'], $roleIds);
        
        if (empty($roleIds)) {
            return [];
        }
        
        // 获取角色的权限ID
        $permissionIds = $db->select('sys_role_permission', ['permission_id'], ['role_id' => $roleIds]);
        $permissionIds = array_map(fn($item) => (int)$item['permission_id'], $permissionIds);
        
        if (empty($permissionIds)) {
            return [];
        }
        
        // 获取权限标识列表
        $permissions = $db->select('sys_permission', ['perm_key'], ['id' => $permissionIds]);
        $permissions = array_map(fn($item) => (string)$item['perm_key'], $permissions);
        
        // 去重并缓存到Session
        $permissions = array_values(array_unique($permissions));
        SessionService::set('admin_permissions', $permissions);
        
        return $permissions;
    }

    /**
     * 验证权限（无权限则跳转）
     *
     * @param string $perm 权限标识
     * @return void
     */
    protected function requirePerm(string $perm): void
    {
        if (!$this->hasPerm($perm)) {
            $this->error('无权限访问');
        }
    }

    /**
     * 获取当前登录管理员ID
     *
     * @return int
     */
    protected function getAdminId(): int
    {
        return (int)SessionService::get('admin_id', 0);
    }

    /**
     * 获取当前登录管理员用户名
     *
     * @return string
     */
    protected function getAdminUsername(): string
    {
        return SessionService::get('admin_username', '');
    }

    /**
     * 获取当前登录管理员姓名
     *
     * @return string
     */
    protected function getAdminName(): string
    {
        return SessionService::get('admin_name', '');
    }

    /**
     * 返回成功响应
     *
     * @param string $message 消息
     * @param string|null $url 跳转URL
     * @return void
     */
    protected function success(string $message, ?string $url = null): void
    {
        if ($this->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode([
                'code' => 0,
                'message' => $message,
                'url' => $url ?? ''
            ], JSON_UNESCAPED_UNICODE);
        } else {
            if ($url) {
                $this->redirect($url);
            } else {
                echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>提示</title>
    <style>
        body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; background: #f5f5f5; }
        .box { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; }
        h2 { color: #28a745; margin-bottom: 20px; }
        p { color: #666; }
        a { color: #007bff; text-decoration: none; margin-top: 20px; display: inline-block; }
    </style>
</head>
<body>
    <div class="box">
        <h2>成功：' . htmlspecialchars($message) . '</h2>';
                if ($url) {
                    echo '<p>正在跳转...</p>
        <script>setTimeout(function() { window.location.href = "' . htmlspecialchars($url) . '"; }, 1000);</script>';
                }
                echo '</div>
</body>
</html>';
            }
        }
        exit;
    }

    /**
     * 返回错误响应
     *
     * @param string $message 错误消息
     * @return void
     */
    protected function error(string $message): void
    {
        if ($this->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode([
                'code' => 1,
                'message' => $message
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>错误</title>
    <style>
        body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; background: #f5f5f5; }
        .box { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; }
        h2 { color: #dc3545; margin-bottom: 20px; }
        a { color: #007bff; text-decoration: none; }
    </style>
</head>
<body>
    <div class="box">
        <h2>错误：' . htmlspecialchars($message) . '</h2>
        <p><a href="javascript:history.back()">返回</a></p>
    </div>
</body>
</html>';
        }
        exit;
    }

    /**
     * 返回成功 JSON 响应
     *
     * @param string $message 消息
     * @param mixed $data 数据
     * @param string|null $url 跳转URL
     * @return void
     */
    protected function successJson(string $message, mixed $data = null, ?string $url = null): void
    {
        $response = [
            'code' => 0,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        if ($url !== null) {
            $response['url'] = $url;
        }

        $this->json($response);
    }

    /**
     * 返回错误 JSON 响应
     *
     * @param string $message 错误消息
     * @param int $code 错误码
     * @return void
     */
    protected function errorJson(string $message, int $code = 1): void
    {
        $this->json([
            'code' => $code,
            'message' => $message,
        ]);
    }

    /**
     * 页面跳转
     *
     * @param string $url 目标URL
     * @param int $statusCode HTTP状态码
     * @return void
     */
    protected function redirect(string $url, int $statusCode = 302): void
    {
        header("Location: {$url}", true, $statusCode);
        exit;
    }

    /**
     * 记录操作日志
     *
     * @param string $message 日志消息
     * @param string $action 操作类型
     * @return void
     */
    protected function log(string $message, string $action = ''): void
    {
        try {
            $db = Database::get();
            $db->insert('sys_log', [
                'admin_id' => $this->getAdminId(),
                'admin_name' => $this->getAdminName(),
                'action' => $action,
                'message' => $message,
                'ip' => SessionService::getClientIp(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'create_time' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            // 日志记录失败不影响主流程
            error_log('日志记录失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取客户端IP
     *
     * @return string
     */
    protected function getClientIp(): string
    {
        return SessionService::getClientIp();
    }

    /**
     * 检查当前页面是否激活
     *
     * @param string $route 路由
     * @return bool
     */
    protected function isActive(string $route): bool
    {
        $currentUri = $_SERVER['REQUEST_URI'] ?? '/';
        return $this->isRouteMatch($currentUri, $route);
    }
    
    /**
     * 检查路由是否匹配（支持参数化路由匹配）
     *
     * @param string $currentUri 当前URI
     * @param string $route 路由
     * @return bool
     */
    private function isRouteMatch(string $currentUri, string $route): bool
    {
        // 解析当前URI和目标路由
        $currentPath = parse_url($currentUri, PHP_URL_PATH) ?: '';
        $routeParams = parse_url($route, PHP_URL_QUERY) ? explode('?', $route, 2) : [$route, null];
        $routePath = $routeParams[0];
        $routeQuery = $routeParams[1] ?? '';
        
        // 基本路径匹配
        if (!str_starts_with($currentPath, $routePath)) {
            return false;
        }
        
        // 如果路由没有参数，只需检查路径前缀
        if (empty($routeQuery)) {
            // 特殊处理：对于带参数的路径，例如 /admin/article/add?cid=5 应该匹配 /admin/article?cid=5
            if (strpos($currentUri, '?') !== false && strpos($route, '?') === false) {
                $currentQuery = parse_url($currentUri, PHP_URL_QUERY) ?: '';
                if (!empty($currentQuery)) {
                    // 检查是否为编辑页面，如 /admin/article/edit/78
                    // 如果目标路由是带分类参数的文章列表页（如 /admin/article?cid=5），而当前是编辑页面，则匹配
                    if (preg_match('#^/admin/article/edit/#', $currentPath) && $routePath === '/admin/article' && strpos($route, 'cid=') !== false) {
                        // 解析目标路由的分类ID
                        parse_str(parse_url($route, PHP_URL_QUERY) ?: '', $routeQueryParams);
                        $targetCid = $routeQueryParams['cid'] ?? null;
                        
                        // 由于编辑页面URL中不包含cid，我们只能通过路径模式判断
                        // 如果目标路由是特定分类的文章列表，则认为匹配
                        return $targetCid !== null;
                    }
                    // 检查是否为添加页面，如 /admin/article/add?cid=5
                    if (preg_match('#^/admin/article/add#', $currentPath) && $routePath === '/admin/article' && strpos($currentUri, 'cid=') !== false) {
                        return true;
                    }
                }
            }
            return str_starts_with($currentUri, $route);
        }
        
        // 如果路由有参数，解析参数进行比较
        parse_str($routeQuery, $routeParamsArray);
        $currentQuery = parse_url($currentUri, PHP_URL_QUERY) ?: '';
        parse_str($currentQuery, $currentParamsArray);
        
        // 检查路由参数是否匹配
        foreach ($routeParamsArray as $key => $value) {
            if (!isset($currentParamsArray[$key]) || $currentParamsArray[$key] !== $value) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * 统一验证POST请求和CSRF
     *
     * @param string $customMessage 自定义错误消息
     * @return bool 验证结果
     */
    protected function validatePostAndCsrf(string $customMessage = '请求方法错误或CSRF验证失败'): bool
    {
        if (!$this->isPost()) {
            $this->errorJson($customMessage);
            return false;
        }
    
        $csrfToken = $this->post('csrf_token', '');
        if (!Security::checkCsrfToken($csrfToken)) {
            $this->errorJson('CSRF Token验证失败');
            return false;
        }
    
        return true;
    }

    /**
     * 加载快捷导航栏目并创建"快捷内容管理"菜单组
     *
     * @param array &$menu 菜单数组（引用传递）
     * @return void
     */
    private function loadShortcutCategories(array &$menu): void
    {
        try {
            $db = Database::get();
            
            // 查询所有 is_shortcut = 1 且 is_hidden = 0 的栏目
            $shortcutCategories = $db->select('sys_category', [
                'id',
                'name',
                'slug'
            ], [
                'is_shortcut' => 1,
                'is_hidden' => 0,
                'ORDER' => ['sort' => 'ASC', 'id' => 'ASC']
            ]) ?: [];
            
            // 构建快捷栏目的子菜单项
            $shortcutChildren = [];
            
            // 添加单页管理到快捷内容管理组
            $shortcutChildren[] = [
                'title' => '单页管理',
                'route' => '/admin/page',
                'perm' => 'admin:page:view'
            ];
            
            // 添加快捷导航栏目
            foreach ($shortcutCategories as $category) {
                $shortcutChildren[] = [
                    'title' => $category['name'],
                    'route' => '/admin/article?cid=' . (int)$category['id'],
                    'perm' => 'admin:article:view'
                ];
            }
            
            // 创建"快捷内容管理"菜单组
            $shortcutMenu = [
                'title' => '快捷内容管理',
                'icon' => 'lightning',
                'children' => $shortcutChildren,
                'is_shortcut' => true // 标识为快捷导航组
            ];
            
            // 查找"快捷导航"菜单项的位置，在其后插入
            $insertIndex = -1;
            foreach ($menu as $index => $item) {
                if (isset($item['title']) && $item['title'] === '快捷导航') {
                    $insertIndex = $index + 1;
                    break;
                }
            }
            
            // 如果找到"快捷导航"，在其后插入；否则插入到开头
            if ($insertIndex > 0) {
                array_splice($menu, $insertIndex, 0, [$shortcutMenu]);
            } else {
                array_unshift($menu, $shortcutMenu);
            }
            
        } catch (\Exception $e) {
            // 加载快捷菜单失败不影响主流程
            error_log('加载快捷菜单失败: ' . $e->getMessage());
        }
    }

    /**
     * 根据管理员权限过滤菜单
     *
     * @param array $menu 菜单数组
     * @return array 过滤后的菜单
     */
    private function filterMenuByPerm(array $menu): array
    {
        $filtered = [];

        foreach ($menu as $item) {
            // 确保有 title 字段（兼容旧代码）
            if (!isset($item['title']) && isset($item['name'])) {
                $item['title'] = $item['name'];
            }

            // 检查是否有子菜单
            if (isset($item['children']) && is_array($item['children'])) {
                $item['children'] = $this->filterMenuByPerm($item['children']);
                
                // 如果子菜单为空，跳过此菜单项
                if (empty($item['children'])) {
                    continue;
                }
            }

            // 检查权限
            $itemPerm = $item['perm'] ?? '';
            if ($itemPerm === '' || $this->hasPerm($itemPerm)) {
                $filtered[] = $item;
            }
        }

        return $filtered;
    }

    /**
     * 处理菜单，标记需要展开的父级菜单
     *
     * @param array<int, array<string, mixed>> $menu 菜单数组
     * @param string $currentUri 当前URI
     * @return array<int, array<string, mixed>>
     */
    private function processMenu(array $menu, string $currentUri): array
    {
        $processedMenu = [];
        
        // 首先查找需要展开的菜单项
        $targetIndex = null;
        $isShortcutExpanded = false; // 标记快捷导航或快捷内容管理是否展开
        
        // 第一步：寻找最佳匹配项（优先级：快捷导航 > 快捷内容管理 > 其他）
        foreach ($menu as $index => $item) {
            $shouldExpand = false;
            
            if (isset($item['children']) && is_array($item['children'])) {
                // 检查子菜单项是否有匹配的激活项
                $hasActiveChild = false;
                
                foreach ($item['children'] as $child) {
                    if (isset($child['route']) && $this->isActive($child['route'])) {
                        $hasActiveChild = true;
                        break;
                    }
                }
                
                if ($hasActiveChild) {
                    $shouldExpand = true;
                } else {
                    // 如果当前 URI 匹配父级菜单项的路由前缀，也需要展开
                    if (isset($item['route']) && $this->isRouteMatch($currentUri, $item['route'])) {
                        $shouldExpand = true;
                    }
                }
                
                // 如果需要展开
                if ($shouldExpand) {
                    // 检查是否是快捷导航或快捷内容管理
                    $isShortcut = isset($item['is_shortcut']) && $item['is_shortcut'];
                    $isShortcutContent = isset($item['title']) && $item['title'] === '快捷内容管理';
                    
                    // 如果是快捷内容管理，优先级最高
                    if ($isShortcutContent && !$isShortcutExpanded) {
                        $targetIndex = $index;
                        $isShortcutExpanded = true;
                    } elseif ($isShortcut && !$isShortcutExpanded) {
                        // 如果是快捷导航，优先级次之
                        $targetIndex = $index;
                        $isShortcutExpanded = true;
                    } elseif (!$isShortcutExpanded) {
                        // 如果还没有快捷导航展开，则设置普通菜单
                        if ($targetIndex === null) {
                            $targetIndex = $index;
                        }
                    }
                }
            } elseif (isset($item['route']) && $this->isActive($item['route'])) {
                // 顶级菜单项激活的情况
                $isShortcut = isset($item['is_shortcut']) && $item['is_shortcut'];
                $isShortcutContent = isset($item['title']) && $item['title'] === '快捷内容管理';
                
                // 如果是快捷内容管理，优先级最高
                if ($isShortcutContent && !$isShortcutExpanded) {
                    $targetIndex = $index;
                    $isShortcutExpanded = true;
                } elseif ($isShortcut && !$isShortcutExpanded) {
                    // 如果是快捷导航，优先级次之
                    $targetIndex = $index;
                    $isShortcutExpanded = true;
                } elseif (!$isShortcutExpanded) {
                    if ($targetIndex === null) {
                        $targetIndex = $index;
                    }
                }
            }
        }
        
        // 第二步：应用展开状态
        foreach ($menu as $index => $item) {
            $processedItem = $item;
            
            // 如果有子菜单，先处理子菜单中的激活状态
            if (isset($processedItem['children']) && is_array($processedItem['children'])) {
                $processedItem['children'] = $this->processMenu($processedItem['children'], $currentUri);
                
                // 检查子菜单是否有激活项
                $hasActiveChild = false;
                foreach ($processedItem['children'] as $child) {
                    if (isset($child['is_active']) && $child['is_active']) {
                        $hasActiveChild = true;
                        break;
                    }
                }
                
                // 如果子菜单有激活项，父菜单也应标记为激活
                if ($hasActiveChild) {
                    $processedItem['is_active'] = true;
                }
            }
            
            // 检查是否是顶级菜单项并激活
            if (isset($item['route']) && $this->isActive($item['route'])) {
                $processedItem['is_active'] = true;
            }
            
            // 设置展开状态
            if ($index === $targetIndex) {
                $processedItem['should_expand'] = true;
            } else {
                // 其他菜单组关闭
                $processedItem['should_expand'] = false;
            }
            
            $processedMenu[] = $processedItem;
        }
        
        return $processedMenu;
    }
}
