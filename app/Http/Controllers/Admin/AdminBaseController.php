<?php
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Core\App;
use App\Core\Database;
use App\Core\Security;
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

        // 暂时返回 true，等添加 role_id 字段后再完善
        // TODO: 实现完整的权限检查逻辑
        return true;
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
                $exactMatch = false;           // 完全匹配：路径和参数都相同
                $routeParamsMatch = false;     // 路由参数匹配：路径相同且参数相同
                $partialMatch = false;         // 前缀匹配：路径前缀匹配
                
                // 分析当前 URI 的基本路径和查询参数
                $currentPath = parse_url($currentUri, PHP_URL_PATH) ?: '';
                $currentQuery = parse_url($currentUri, PHP_URL_QUERY) ?: '';
                parse_str($currentQuery, $currentParams);
                
                foreach ($item['children'] as $child) {
                    if (!isset($child['route'])) {
                        continue;
                    }
                    
                    $childRoute = $child['route'];
                    $childPath = parse_url($childRoute, PHP_URL_PATH) ?: '';
                    $childQuery = parse_url($childRoute, PHP_URL_QUERY) ?: '';
                    parse_str($childQuery, $childParams);
                    
                    // 完全匹配
                    if ($childRoute === $currentUri) {
                        $exactMatch = true;
                        break;
                    }
                    
                    // 路由参数匹配（路径相同，参数也相同）
                    if ($childPath === $currentPath && $childParams === $currentParams) {
                        $routeParamsMatch = true;
                        continue;
                    }
                    
                    // 前缀匹配（路径前缀匹配）
                    if ($childPath !== '' && str_starts_with($currentPath, $childPath)) {
                        $partialMatch = true;
                    }
                }
                
                // 确定是否需要展开此菜单
                if ($exactMatch || $routeParamsMatch || $partialMatch) {
                    $targetIndex = $index;
                    break;
                }
            }
        }
        
        // 第二步：标记激活状态
        if ($targetIndex !== null) {
            $menu[$targetIndex]['is_active'] = true;
            $menu[$targetIndex]['is_open'] = true;
            
            // 标记子菜单激活状态
            if (isset($menu[$targetIndex]['children'])) {
                foreach ($menu[$targetIndex]['children'] as &$child) {
                    if (isset($child['route'])) {
                        // 精确匹配
                        if ($child['route'] === $currentUri) {
                            $child['is_active'] = true;
                        } else {
                            // 检查是否为前缀匹配
                            $childPath = parse_url($child['route'], PHP_URL_PATH) ?: '';
                            $currentPath = parse_url($currentUri, PHP_URL_PATH) ?: '';
                            
                            if ($childPath !== '' && str_starts_with($currentPath, $childPath)) {
                                $child['is_active'] = true;
                            }
                        }
                    }
                }
            }
        }
        
        return $menu;
    }
}
