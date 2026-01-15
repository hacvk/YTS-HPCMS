<?php
declare(strict_types=1);

namespace App\Core;

use App\Service\LogService;

/**
 * 路由异常
 */
class RouterException extends \Exception
{
}

/**
 * 路由器类
 */
class Router
{
    /**
     * 当前 URI
     *
     * @var string
     */
    private string $uri;

    /**
     * 当前 HTTP 方法
     *
     * @var string
     */
    private string $method;

    /**
     * 控制器命名空间
     *
     * @var string
     */
    private string $controllerNamespace = 'App\\Controller\\';

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->uri = $this->parseUri();
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    /**
     * 解析 URI
     *
     * @return string
     */
    private function parseUri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        // 移除查询字符串
        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }

        // 移除脚本名称（如 /index.php/xxx）
        $scriptName = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
        if ($scriptName !== '/' && strpos($uri, $scriptName) === 0) {
            $uri = substr($uri, strlen($scriptName));
        }

        // 标准化：移除开头和结尾的斜杠
        $uri = trim($uri, '/');

        // 如果为空，返回根路径
        return $uri === '' ? '/' : '/' . $uri;
    }

    /**
     * 分发请求
     *
     * @return void
     */
    public function dispatch(): void
    {
        try {
            $route = $this->matchRoute();
            $this->executeRoute($route);
        } catch (RouterException $e) {
            // 记录路由异常日志
            LogService::error($e, 'route_not_found');
            $this->handleNotFound();
        } catch (\Throwable $e) {
            // 记录全局异常日志
            LogService::error($e, 'route_dispatch_error');

            // 调试模式直接输出真实异常，避免所有错误都被伪装成 404
            if (App::isDebug()) {
                http_response_code(500);
                $system = App::getConfig('system', []);
                $copyright = $system['copyright'] ?? '';

                echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Route Error</title>'
                    . '<style>body{font-family:Arial,sans-serif;max-width:1100px;margin:40px auto;padding:20px;}h1{color:#e74c3c;}pre{background:#f6f8fa;padding:12px;border-radius:6px;overflow:auto;}footer{margin-top:24px;padding-top:12px;border-top:1px solid #eee;color:#999;font-size:12px;text-align:center;}</style>'
                    . '</head><body>'
                    . '<h1>Route Dispatch Error</h1>'
                    . '<p><b>URI:</b> ' . htmlspecialchars($this->uri) . '</p>'
                    . '<p><b>Method:</b> ' . htmlspecialchars($this->method) . '</p>'
                    . '<p><b>Exception:</b> ' . htmlspecialchars(get_class($e)) . '</p>'
                    . '<p><b>Message:</b> ' . htmlspecialchars($e->getMessage()) . '</p>'
                    . '<p><b>File:</b> ' . htmlspecialchars($e->getFile()) . ':' . (int)$e->getLine() . '</p>'
                    . '<h2>Trace</h2><pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>'
                    . '<footer>' . $copyright . '</footer>'
                    . '</body></html>';
                exit;
            }

            $this->handleNotFound();
        }
    }

    /**
     * 匹配路由
     *
     * @return array{controller: string, action: string}
     * @throws RouterException
     */
    private function matchRoute(): array
    {
        $uri = $this->uri;
        $method = $this->method;

        /**
         * 静态路由表（不再做任何自动推断）
         * key: "METHOD /path"，例如 "GET /admin"
         * value: [controller, action]
         */
        $routes = [
            // Front
            'GET /' => ['HomeController', 'index'],

            // Admin - auth & dashboard
            'GET /admin' => ['Admin\\LoginController', 'index'],
            'GET /admin/login/captcha' => ['Admin\\LoginController', 'captcha'],
            'POST /admin/login/login' => ['Admin\\LoginController', 'login'],
            'GET /admin/logout' => ['Admin\\LoginController', 'logout'],
            'GET /admin/dashboard' => ['Admin\\DashboardController', 'index'],

            // Admin - system
            'GET /admin/system/about' => ['Admin\\SystemController', 'about'],

            // Admin - setting
            'GET /admin/setting' => ['Admin\\SettingController', 'index'],
            'POST /admin/setting/save' => ['Admin\\SettingController', 'save'],

            // Admin - category
            'GET /admin/category' => ['Admin\\CategoryController', 'index'],
            'GET /admin/category/edit' => ['Admin\\CategoryController', 'edit'],
            'POST /admin/category/save' => ['Admin\\CategoryController', 'save'],
            'POST /admin/category/delete' => ['Admin\\CategoryController', 'delete'],

            // Admin - page
            'GET /admin/page' => ['Admin\\PageController', 'index'],
            'GET /admin/page/edit' => ['Admin\\PageController', 'edit'],
            'POST /admin/page/save' => ['Admin\\PageController', 'save'],
            'POST /admin/page/delete' => ['Admin\\PageController', 'delete'],

            // Admin - article
            'GET /admin/article' => ['Admin\\ArticleController', 'index'],
            'GET /admin/article/edit' => ['Admin\\ArticleController', 'edit'],
            'POST /admin/article/save' => ['Admin\\ArticleController', 'save'],
            'POST /admin/article/delete' => ['Admin\\ArticleController', 'delete'],

            // Admin - attachment
            'GET /admin/attachment' => ['Admin\\AttachmentController', 'index'],
            'GET /admin/banner' => ['Admin\\BannerController', 'index'],
            'GET /admin/banner/edit' => ['Admin\\BannerController', 'edit'],
            'POST /admin/banner/save' => ['Admin\\BannerController', 'save'],
            'POST /admin/banner/delete' => ['Admin\\BannerController', 'delete'],
            'POST /admin/attachment/delete' => ['Admin\\AttachmentController', 'delete'],
            // Admin - file
            'GET /admin/file' => ['Admin\\FileController', 'index'],
            'POST /admin/file/delete' => ['Admin\\FileController', 'delete'],

            // Admin - upload
            'GET /admin/upload' => ['Admin\\UploadController', 'index'],
            'POST /admin/upload/do' => ['Admin\\UploadController', 'upload'],

            // Admin - admin user
            'GET /admin/admin' => ['Admin\\AdminController', 'index'],
            'GET /admin/admin/edit' => ['Admin\\AdminController', 'edit'],
            'POST /admin/admin/save' => ['Admin\\AdminController', 'save'],
            'POST /admin/admin/delete' => ['Admin\\AdminController', 'delete'],

            // Admin - role
            'GET /admin/role' => ['Admin\\RoleController', 'index'],
            'GET /admin/role/edit' => ['Admin\\RoleController', 'edit'],
            'POST /admin/role/save' => ['Admin\\RoleController', 'save'],
            'POST /admin/role/delete' => ['Admin\\RoleController', 'delete'],

            // Admin - log
            'GET /admin/log' => ['Admin\\LogController', 'index'],

            // Admin - cache
            'GET /admin/cache' => ['Admin\\CacheController', 'index'],
            'POST /admin/cache/clear-latte' => ['Admin\\CacheController', 'clearLatte'],
            'POST /admin/cache/clear-all' => ['Admin\\CacheController', 'clearAll'],
        ];

        $key = $method . ' ' . $uri;
        if (isset($routes[$key])) {
            [$controller, $action] = $routes[$key];
            return [
                'controller' => $controller,
                'action' => $action,
            ];
        }
        
        // 动态路由匹配 - 处理文章详情页
        if ($method === 'GET') {
            if (preg_match('#^/article/([0-9]+)$#', $uri, $matches)) {
                $_GET['id'] = (int)$matches[1];
                return [
                    'controller' => 'HomeController',
                    'action' => 'article',
                ];
            }
            
            // 动态路由匹配 - 处理栏目列表页
            if (preg_match('#^/c/([^/]+)$#', $uri, $matches)) {
                $_GET['slug'] = $matches[1];
                return [
                    'controller' => 'HomeController',
                    'action' => 'category',
                ];
            }
            
            // 动态路由匹配 - 处理栏目列表页（带分页）
            if (preg_match('#^/c/([^/]+)/page/([0-9]+)$#', $uri, $matches)) {
                $_GET['slug'] = $matches[1];
                $_GET['page'] = (int)$matches[2];
                return [
                    'controller' => 'HomeController',
                    'action' => 'category',
                ];
            }
        }
        
        throw new RouterException("路由未配置: {$key}");
    }

    /**
     * 格式化控制器名称
     *
     * @param string $name 控制器名称
     * @return string
     */
    private function formatControllerName(string $name): string
    {
        // 转换为 PascalCase
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', strtolower($name))));
    }

    /**
     * 格式化动作名称
     *
     * @param string $name 动作名称
     * @return string
     */
    private function formatActionName(string $name): string
    {
        // 转换为 camelCase
        $name = str_replace(['-', '_'], ' ', strtolower($name));
        $name = lcfirst(str_replace(' ', '', ucwords($name)));
        
        return $name ?: 'index';
    }

    /**
     * 执行路由
     *
     * @param array{controller: string, action: string} $route 路由信息
     * @return void
     * @throws RouterException
     */
    private function executeRoute(array $route): void
    {
        $controllerName = $this->controllerNamespace . $route['controller'];
        $actionName = $route['action'];

        // 检查控制器是否存在
        if (!class_exists($controllerName)) {
            throw new RouterException("控制器不存在: {$controllerName}");
        }

        $controller = new $controllerName();

        // 检查方法是否存在
        if (!method_exists($controller, $actionName)) {
            throw new RouterException("方法不存在: {$controllerName}::{$actionName}()");
        }

        // 调用控制器方法
        $controller->$actionName();
    }

    /**
     * 处理 404 错误
     *
     * @return void
     */
    private function handleNotFound(): void
    {
        http_response_code(404);
        
        // 记录 404 警告日志
        try {
            LogService::record(
                0,
                'system',
                '404_not_found',
                $this->uri,
                "404 - 页面未找到: {$this->uri} (Method: {$this->method})",
                LogService::LEVEL_WARNING
            );
        } catch (\Exception $e) {
            // 日志记录失败不应影响 404 页面显示
            error_log('[Router] 记录404日志失败: ' . $e->getMessage());
        }
        
        // 开发模式显示详细错误，生产模式显示友好页面
        if (App::isDebug()) {
            echo '<!DOCTYPE html>
<html>
<head>
    <title>404 Not Found</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        h1 { color: #e74c3c; }
        p { color: #666; line-height: 1.6; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>404 - 页面未找到</h1>
    <p>请求的 URI 不存在或无法访问。</p>
    <p>URI: <code>' . htmlspecialchars($this->uri) . '</code></p>
    <p>Method: <code>' . htmlspecialchars($this->method) . '</code></p>
</body>
</html>';
        } else {
            echo '<!DOCTYPE html>
<html>
<head>
    <title>404 Not Found</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; text-align: center; }
        h1 { color: #e74c3c; }
    </style>
</head>
<body>
    <h1>404</h1>
    <p>页面未找到</p>
</body>
</html>';
        }
        
        exit;
    }

    /**
     * 获取当前 URI
     *
     * @return string
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * 获取当前 HTTP 方法
     *
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * 检查是否为 AJAX 请求
     *
     * @return bool
     */
    public function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) 
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * 检查是否为 POST 请求
     *
     * @return bool
     */
    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    /**
     * 检查是否为 GET 请求
     *
     * @return bool
     */
    public function isGet(): bool
    {
        return $this->method === 'GET';
    }
}
