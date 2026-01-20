<?php
declare(strict_types=1);

namespace App\Http;

use App\Core\Container;
use App\Core\App;
use App\Services\LogService;

/**
 * 路由器类
 */
class Router
{
    public function __construct(
        private readonly Container $container
    ) {
    }

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
    private string $controllerNamespace = 'App\\Http\\Controllers\\';


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
        if ($scriptName !== '/' && str_starts_with($uri, $scriptName)) {
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
            $this->uri = $this->parseUri();
            $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            
            $route = $this->matchRoute();
            $this->executeRoute($route);
        } catch (RouterException $e) {
            // 检查是否为静态资源请求
            if (str_contains($e->getMessage(), '静态资源请求')) {
                // 静态资源请求，直接返回404，不记录日志
                $this->handleStaticResourceNotFound();
            } else {
                // 路由未找到，记录日志
                LogService::error($e, 'route_not_found');
                $this->handleNotFound();
            }
        } catch (\Throwable $e) {
            LogService::error($e, 'route_dispatch_error');
            $this->handleDispatchError($e);
        }
    }

    /**
     * 检查是否为 AJAX 请求
     *
     * @return bool
     */
    private function isAjaxRequest(): bool
    {
        $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        return $requestedWith !== '' && strtolower($requestedWith) === 'xmlhttprequest';
    }

    /**
     * 处理静态资源未找到
     *
     * @return void
     */
    private function handleStaticResourceNotFound(): void
    {
        http_response_code(404);
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
    <p>资源未找到</p>
</body>
</html>';
        exit;
    }

    /**
     * 处理调度错误
     *
     * @param \Throwable $e
     * @return void
     */
    private function handleDispatchError(\Throwable $e): void
    {
        // 检查是否为 AJAX 请求
        if ($this->isAjaxRequest()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'code' => 1,
                'message' => '服务器内部错误',
                'error' => App::isDebug() ? $e->getMessage() : '请稍后重试'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

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

    /**
     * 获取静态路由配置
     *
     * @return array<string, array{controller: string, action: string}>
     */
    private function getStaticRoutes(): array
    {
        return [
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
            'GET /admin/setting' => ['Admin\SettingController', 'index'],
            'POST /admin/setting/save' => ['Admin\SettingController', 'save'],
            'POST /admin/setting/delete-image' => ['Admin\SettingController', 'deleteImage'],

            // Admin - contact
            'GET /admin/contact' => ['Admin\\ContactController', 'index'],
            'POST /admin/contact/save' => ['Admin\\ContactController', 'save'],

            // Admin - category
            'GET /admin/category' => ['Admin\CategoryController', 'index'],
            'GET /admin/category/add' => ['Admin\CategoryController', 'add'],
            'GET /admin/category/edit' => ['Admin\CategoryController', 'edit'],
            'POST /admin/category/save' => ['Admin\CategoryController', 'save'],
            'POST /admin/category/delete' => ['Admin\CategoryController', 'delete'],
            'GET /admin/category/getModelFields' => ['Admin\CategoryController', 'getModelFields'],
            'GET /admin/category/tree' => ['Admin\CategoryController', 'tree'],
            'GET /admin/category/getCategoryOptions' => ['Admin\CategoryController', 'getCategoryOptions'],
            'GET /admin/category/getContentOptions' => ['Admin\CategoryController', 'getContentOptions'],

            // Admin - page
            'GET /admin/page' => ['Admin\\PageController', 'index'],
            'GET /admin/page/add' => ['Admin\\PageController', 'add'],
            'GET /admin/page/edit' => ['Admin\\PageController', 'edit'],
            'POST /admin/page/save' => ['Admin\\PageController', 'save'],
            'POST /admin/page/delete' => ['Admin\\PageController', 'delete'],

            // Admin - article
            'GET /admin/article' => ['Admin\\ArticleController', 'index'],
            'GET /admin/article/add' => ['Admin\\ArticleController', 'add'],
            'GET /admin/article/edit' => ['Admin\\ArticleController', 'edit'],
            'GET /admin/article/edit/' => ['Admin\\ArticleController', 'edit'],
            'GET /admin/article/edit/{id}' => ['Admin\\ArticleController', 'edit'],
            'POST /admin/article/save' => ['Admin\\ArticleController', 'save'],
            'POST /admin/article/delete' => ['Admin\\ArticleController', 'delete'],
            'POST /admin/article/delete/' => ['Admin\\ArticleController', 'delete'],

            // Admin - attachment
            'GET /admin/attachment' => ['Admin\\AttachmentController', 'index'],
            'GET /admin/banner' => ['Admin\\BannerController', 'index'],
            'GET /admin/banner/edit' => ['Admin\\BannerController', 'edit'],
            'POST /admin/banner/save' => ['Admin\\BannerController', 'save'],
            'POST /admin/banner/delete' => ['Admin\\BannerController', 'delete'],

            // Admin - friendlink
            'GET /admin/friendlink' => ['Admin\\FriendLinkController', 'index'],
            'GET /admin/friendlink/edit' => ['Admin\\FriendLinkController', 'edit'],
            'POST /admin/friendlink/save' => ['Admin\\FriendLinkController', 'save'],
            'POST /admin/friendlink/delete' => ['Admin\\FriendLinkController', 'delete'],
            'POST /admin/attachment/delete' => ['Admin\\AttachmentController', 'delete'],
            'POST /admin/attachment/batchDelete' => ['Admin\\AttachmentController', 'batchDelete'],
            
            // Admin - file manage
            'GET /admin/file' => ['Admin\\FileController', 'index'],
            'POST /admin/file/update' => ['Admin\\FileController', 'update'],
            
            // Admin - upload
            'GET /admin/upload' => ['Admin\\UploadController', 'index'],
            'POST /admin/upload/do' => ['Admin\\UploadController', 'upload'],
            'POST /admin/upload/upload' => ['Admin\\UploadController', 'upload'],
            'POST /admin/upload/categoryBanner' => ['Admin\\UploadController', 'categoryBanner'],
            'POST /admin/upload/deleteCoverImage' => ['Admin\\UploadController', 'deleteCoverImage'],
            'POST /admin/upload/crop' => ['Admin\\UploadController', 'crop'],
            'POST /admin/upload/uploadCrop' => ['Admin\\UploadController', 'uploadCrop'],

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
        $key = $method . ' ' . $uri;

        // 1. 检查是否为静态资源请求（直接返回404，由Web服务器处理）
        if ($this->isStaticResourceRequest($uri)) {
            throw new RouterException("静态资源请求: {$key}");
        }

        // 2. 尝试匹配静态路由
        $staticRoutes = $this->getStaticRoutes();
        if (isset($staticRoutes[$key])) {
            [$controller, $action] = $staticRoutes[$key];
            return ['controller' => $controller, 'action' => $action];
        }

        // 3. 尝试匹配动态路由
        return $this->matchDynamicRoute($uri, $method);
    }

    /**
     * 检查是否为静态资源请求
     *
     * @param string $uri
     * @return bool
     */
    private function isStaticResourceRequest(string $uri): bool
    {
        // 检查是否为 .map 文件请求
        if (str_ends_with($uri, '.map')) {
            return true;
        }

        // 检查是否为其他静态资源（可选）
        $staticExtensions = ['.css', '.js', '.png', '.jpg', '.jpeg', '.gif', '.svg', '.woff', '.woff2', '.ttf', '.eot'];
        foreach ($staticExtensions as $ext) {
            if (str_ends_with($uri, $ext)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 匹配动态路由
     *
     * @param string $uri
     * @param string $method
     * @return array{controller: string, action: string}
     * @throws RouterException
     */
    private function matchDynamicRoute(string $uri, string $method): array
    {
        // GET 请求的动态路由
        if ($method === 'GET') {
            // Admin - page edit (GET /admin/page/edit/{id})
            if (preg_match('#^/admin/page/edit/([0-9]+)/?$#', $uri, $matches)) {
                $_GET['category_id'] = (int)$matches[1];
                $_GET['id'] = (int)$matches[1];
                return ['controller' => 'Admin\\PageController', 'action' => 'edit'];
            }

            // Admin - article edit (GET /admin/article/edit/{id})
            if (preg_match('#^/admin/article/edit/([0-9]+)/?$#', $uri, $matches)) {
                $_GET['id'] = (int)$matches[1];
                return ['controller' => 'Admin\\ArticleController', 'action' => 'edit'];
            }

            // Front - article detail (GET /article/{id})
            if (preg_match('#^/article/([1-9][0-9]*)$#', $uri, $matches)) {
                $_GET['id'] = (int)$matches[1];
                return ['controller' => 'ArticleController', 'action' => 'index'];
            }
            
            // Front - file download (GET /file/download/{id})
            if (preg_match('#^/file/download/([0-9]+)$#', $uri, $matches)) {
                $_GET['id'] = (int)$matches[1];
                return ['controller' => 'FileController', 'action' => 'download'];
            }
            
            // Front - category list with pagination (GET /c/{slug}/page/{page})
            if (preg_match('#^/c/([^/]+)/page/([1-9][0-9]*)$#', $uri, $matches)) {
                return $this->handleCategoryRoute($matches[1], (int)$matches[2]);
            }

            // Front - category list (GET /c/{slug})
            if (preg_match('#^/c/([^/]+)$#', $uri, $matches)) {
                $slug = $matches[1];
                if ($slug !== '' && !preg_match('#[<>"]#', $slug)) {
                    return $this->handleCategoryRoute($slug);
                }
            }
        }

        // POST 请求的动态路由
        if ($method === 'POST') {
            // Admin - article delete (POST /admin/article/delete/{id})
            if (preg_match('#^/admin/article/delete/([0-9]+)$#', $uri, $matches)) {
                $_POST['id'] = (int)$matches[1];
                return ['controller' => 'Admin\\ArticleController', 'action' => 'delete'];
            }
        
            // Admin - page delete (POST /admin/page/delete/{id})
            if (preg_match('#^/admin/page/delete/([0-9]+)$#', $uri, $matches)) {
                $_POST['id'] = (int)$matches[1];
                return ['controller' => 'Admin\\PageController', 'action' => 'delete'];
            }
        
            // Admin - file delete (POST /admin/file/delete/{id})
            if (preg_match('#^/admin/file/delete/([0-9]+)$#', $uri, $matches)) {
                $_POST['id'] = (int)$matches[1];
                return ['controller' => 'Admin\\FileController', 'action' => 'delete'];
            }
        }

        throw new RouterException("路由未配置: {$method} {$uri}");
    }

    /**
     * 处理栏目路由
     *
     * @param string $slug
     * @param int|null $page
     * @return array{controller: string, action: string}
     */
    private function handleCategoryRoute(string $slug, ?int $page = null): array
    {
        if ($page !== null) {
            $_GET['page'] = $page;
        }
        $_GET['slug'] = $slug;

        // 根据栏目类型决定使用哪个控制器
        $db = \App\Core\Database::get();
        $category = $db->get('sys_category', ['type'], [
            'slug' => $slug,
            'is_hidden' => 0
        ]);

        $controller = ($category && isset($category['type']) && $category['type'] === 'page')
            ? 'PageController'
            : 'CategoryController';

        return ['controller' => $controller, 'action' => 'index'];
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

        if (!class_exists($controllerName)) {
            throw new RouterException("控制器不存在: {$controllerName}");
        }

        $controller = $this->container->get($controllerName);

        if (!method_exists($controller, $actionName)) {
            throw new RouterException("方法不存在: {$controllerName}::{$actionName}()");
        }

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
        $this->recordNotFoundLog();
        
        $isDebug = App::isDebug();
        $uri = htmlspecialchars($this->uri, ENT_QUOTES, 'UTF-8');
        $method = htmlspecialchars($this->method, ENT_QUOTES, 'UTF-8');
        
        if ($isDebug) {
            echo $this->renderNotFoundDebugPage($uri, $method);
        } else {
            echo $this->renderNotFoundPage();
        }
        
        exit;
    }

    /**
     * 记录 404 日志
     *
     * @return void
     */
    private function recordNotFoundLog(): void
    {
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
            error_log('[Router] 记录404日志失败: ' . $e->getMessage());
        }
    }

    /**
     * 渲染 404 调试页面
     *
     * @param string $uri
     * @param string $method
     * @return string
     */
    private function renderNotFoundDebugPage(string $uri, string $method): string
    {
        return <<<HTML
<!DOCTYPE html>
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
    <p>URI: <code>{$uri}</code></p>
    <p>Method: <code>{$method}</code></p>
</body>
</html>
HTML;
    }

    /**
     * 渲染 404 页面
     *
     * @return string
     */
    private function renderNotFoundPage(): string
    {
        return <<<HTML
<!DOCTYPE html>
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
</html>
HTML;
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
        $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        return $requestedWith !== '' && strtolower($requestedWith) === 'xmlhttprequest';
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
