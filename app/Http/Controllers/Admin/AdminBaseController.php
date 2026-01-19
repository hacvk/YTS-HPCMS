<?php
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Core\App;
use App\Core\Database;
use App\Core\Security;

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
        $adminId = $_SESSION['admin_id'] ?? null;

        if ($adminId === null || !is_numeric($adminId)) {
            $this->redirect('/admin');
        }

        // 验证 Session 中的其他必要数据
        if (!isset($_SESSION['admin_username']) || !isset($_SESSION['admin_login_time'])) {
            $this->redirect('/admin');
        }

        // 检查登录是否过期（24小时）
        $loginTime = $_SESSION['admin_login_time'] ?? 0;
        if (time() - $loginTime > 86400) {
            // 清空 Session
            session_unset();
            session_destroy();
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
     * 加载快捷导航栏目并创建"快捷内容管理"菜单组
     *
     * @param array<string, mixed> $menu 菜单数组（引用传递）
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
            
            // 创建"快捷内容管理"菜单组，插入到"快捷导航"之后，"内容管理"之前
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
            
            // 如果找到了位置，在指定位置插入；否则追加到末尾
            if ($insertIndex > 0 && $insertIndex < count($menu)) {
                array_splice($menu, $insertIndex, 0, [$shortcutMenu]);
            } else {
                $menu[] = $shortcutMenu;
            }
        } catch (\Throwable $e) {
            // 静默失败，不影响菜单加载
            error_log('加载快捷导航栏目失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 获取当前管理员角色ID列表
     *
     * @return array<int, int>
     */
    protected function getAdminRoleIds(): array
    {
        $adminId = (int)($this->admin['id'] ?? 0);
        if ($adminId <= 0) {
            return [];
        }

        try {
            $db = Database::get();
            $rows = $db->select('sys_admin_role', ['role_id'], [
                'admin_id' => $adminId,
            ]);

            $ids = [];
            foreach ($rows as $row) {
                $ids[] = (int)($row['role_id'] ?? 0);
            }

            $ids = array_values(array_unique(array_filter($ids, fn($v) => $v > 0)));
            sort($ids);
            return $ids;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * 是否超级管理员（拥有 role_id=1）
     */
    protected function isSuperAdmin(): bool
    {
        return in_array(1, $this->getAdminRoleIds(), true);
    }

    /**
     * 获取当前管理员权限key 列表
     *
     * @return array<int, string>
     */
    protected function getAdminPermKeys(): array
    {
        if ($this->isSuperAdmin()) {
            return ['admin:*'];
        }

        $roleIds = $this->getAdminRoleIds();
        if (count($roleIds) === 0) {
            return [];
        }

        try {
            $db = Database::get();

            $rows = $db->select('sys_role_permission', [
                '[>]sys_permission' => ['permission_id' => 'id']
            ], [
                'sys_permission.perm_key',
            ], [
                'sys_role_permission.role_id' => $roleIds,
            ]);

            $keys = [];
            foreach ($rows as $row) {
                $k = (string)($row['perm_key'] ?? '');
                if ($k !== '') {
                    $keys[] = $k;
                }
            }

            $keys = array_values(array_unique($keys));
            sort($keys);
            return $keys;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * 判断是否拥有权限（支持 admin:* 通配符）
     */
    protected function hasPerm(string $permKey): bool
    {
        if ($permKey === '') {
            return true;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        $keys = $this->getAdminPermKeys();
        if (in_array('admin:*', $keys, true)) {
            return true;
        }

        return in_array($permKey, $keys, true);
    }

    /**
     * 权限不足时终止请求
     */
    protected function requirePerm(string $permKey): void
    {
        if ($this->hasPerm($permKey)) {
            return;
        }

        if ($this->isAjax()) {
            $this->errorJson('无权限');
        }

        $this->error('无权限');
    }

    /**
     * 根据管理员权限过滤菜单
     *
     * @param array<string, mixed> $menu
     * @return array<string, mixed>
     */
    private function filterMenuByPerm(array $menu): array
    {
        $filtered = [];

        foreach ($menu as $item) {
            if (isset($item['is_shortcut']) && $item['is_shortcut'] === true) {
                $filteredChildren = [];
                if (isset($item['children']) && is_array($item['children'])) {
                    foreach ($item['children'] as $child) {
                        $childPerm = (string)($child['perm'] ?? '');
                        if ($childPerm === '' || $this->hasPerm($childPerm)) {
                            $filteredChildren[] = $child;
                        }
                    }
                }

                if (count($filteredChildren) > 0) {
                    $item['children'] = $filteredChildren;
                    $filtered[] = $item;
                }
                continue;
            }

            $itemPerm = (string)($item['perm'] ?? '');

            if (isset($item['children']) && is_array($item['children'])) {
                $filteredChildren = [];
                foreach ($item['children'] as $child) {
                    $childPerm = (string)($child['perm'] ?? '');
                    if ($childPerm === '' || $this->hasPerm($childPerm)) {
                        $filteredChildren[] = $child;
                    }
                }

                if (count($filteredChildren) > 0) {
                    $item['children'] = $filteredChildren;
                    $filtered[] = $item;
                }
                continue;
            }

            if ($itemPerm === '' || $this->hasPerm($itemPerm)) {
                $filtered[] = $item;
            }
        }

        return $filtered;
    }

    /**
     * 加载当前管理员信息
     *
     * @return void
     */
    private function loadAdminInfo(): void
    {
        try {
            $adminId = (int)($_SESSION['admin_id'] ?? 0);

            if ($adminId > 0) {
                $db = Database::get();

                $this->admin = $db->get('sys_admin', [
                    'id',
                    'username',
                    'name',
                    'avatar',
                    'email',
                    'phone',
                    'status',
                    'last_login_time',
                    'last_login_ip',
                    'login_count',
                ], [
                    'id' => $adminId,
                    'status' => 1
                ]);

                // 如果管理员不存在或状态异常，清除 Session
                if (!$this->admin) {
                    session_unset();
                    session_destroy();
                    $this->redirect('/admin');
                }
            }
        } catch (\Exception $e) {
            // 数据库查询失败，但不影响流程
            error_log('加载管理员信息失败 ' . $e->getMessage());
            $this->admin = [
                'id' => $_SESSION['admin_id'] ?? 0,
                'username' => $_SESSION['admin_username'] ?? '',
                'name' => $_SESSION['admin_name'] ?? '',
            ];
        }
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
     * 处理菜单，标记需要展开的父级菜单
     *
     * @param array<int, array<string, mixed>> $menu 菜单数组
     * @param string $currentUri 当前URI
     * @return array<int, array<string, mixed>>
     */
    private function processMenu(array $menu, string $currentUri): array
    {
        // 如果是首页（控制台），确保控制台菜单项被标记为active
        $isDashboard = ($currentUri === '/admin/dashboard' || $currentUri === '/admin' || $currentUri === '/admin/');
        
        // 遍历菜单，为控制台项设置激活状态
        foreach ($menu as &$item) {
            if (isset($item['route']) && $item['route'] === '/admin/dashboard' && $isDashboard) {
                $item['is_active'] = true;
            }
        }
        unset($item);
        
        // 第一步：寻找最佳匹配项（Best Match）
        $targetIndex = null;
        foreach ($menu as $index => $item) {
            if (isset($item['children']) && is_array($item['children'])) {
                // 检查是否有子菜单项匹配当前路由
                $hasActiveChild = false;
                foreach ($item['children'] as $child) {
                    if (isset($child['route']) && str_starts_with($currentUri, $child['route'])) {
                        $hasActiveChild = true;
                        break;
                    }
                }
                
                if ($hasActiveChild) {
                    // 如果之前没找到匹配项，或者之前找到的不是快捷菜单而当前这个是，则更新目标
                    if ($targetIndex === null || (!isset($menu[$targetIndex]['is_shortcut']) && isset($item['is_shortcut']) && $item['is_shortcut'])) {
                        $targetIndex = $index;
                    }
                }
            }
        }
        
        // 第二步：应用状态
        foreach ($menu as $index => &$item) {
            if ($index === $targetIndex) {
                $item['should_expand'] = true;
            } else {
                $item['should_expand'] = false;
            }
        }
        unset($item);
        
        return $menu;
    }

    /**
     * 获取当前管理员ID
     *
     * @return int
     */
    protected function getAdminId(): int
    {
        return (int)($this->admin['id'] ?? 0);
    }

    /**
     * 获取当前管理员用户名
     *
     * @return string
     */
    protected function getAdminUsername(): string
    {
        return $this->admin['username'] ?? '';
    }

    /**
     * 获取当前管理员姓名
     *
     * @return string
     */
    protected function getAdminName(): string
    {
        return $this->admin['name'] ?? '';
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
        return str_starts_with($currentUri, $route);
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
     * 简化成功日志记录
     *
     * @param string $operation 操作描述
     * @param string $details 详细信息
     * @param int|null $userId 用户ID
     * @param string|null $username 用户名
     * @return void
     */
    protected function logSuccess(string $operation, string $details = '', int $userId = null, string $username = null): void
    {
        $userId = $userId ?? $this->getAdminId();
        $username = $username ?? $this->getAdminUsername();
    
        \App\Services\LogService::record($userId, $username, 'success', $operation, $details);
    }
    
    /**
     * 简化错误日志记录
     *
     * @param string $operation 操作描述
     * @param string $details 详细信息
     * @param int|null $userId 用户ID
     * @param string|null $username 用户名
     * @return void
     */
    protected function logError(string $operation, string $details = '', int $userId = null, string $username = null): void
    {
        $userId = $userId ?? $this->getAdminId();
        $username = $username ?? $this->getAdminUsername();
    
        \App\Services\LogService::record($userId, $username, 'error', $operation, $details);
    }
    
    /**
     * 统一清理请求数据
     *
     * @param array $data 待清理的数据
     * @param array $options 清理选项
     * @return array 清理后的数据
     */
    protected function sanitizeRequestData(array $data, array $options = []): array
    {
        $sanitizedData = [];
            
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitizedData[$key] = $this->sanitizeRequestData($value, $options);
            } elseif (is_string($value)) {
                $sanitizedData[$key] = \App\Utils\StringSanitizer::sanitize($value, $options);
            } else {
                $sanitizedData[$key] = $value;
            }
        }
            
        return $sanitizedData;
    }
    
    /**
     * 统一响应处理
     *
     * @param string $message 响应消息
     * @param mixed $data 响应数据
     * @param int $code 响应代码
     * @param string|null $redirect 重定向地址
     * @return void
     */
    protected function jsonResponse(string $message = '操作成功', mixed $data = null, int $code = 0, string $redirect = null): void
    {
        $response = [
            'code' => $code,
            'message' => $message,
            'data' => $data,
            'timestamp' => time()
        ];
    
        if ($redirect !== null) {
            $response['redirect'] = $redirect;
        }
    
        $this->json($response);
    }
    
    /**
     * 统一错误响应处理
     *
     * @param string $message 错误消息
     * @param int $code 错误代码
     * @param mixed $data 响应数据
     * @return void
     */
    protected function errorResponse(string $message = '操作失败', int $code = 1, mixed $data = null): void
    {
        $this->jsonResponse($message, $data, $code);
    }
    
    /**
     * 显示错误页面
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
}
