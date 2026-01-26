<?php
declare(strict_types=1);

namespace App\Http;

use App\Core\App;
use App\Core\Container;
use App\Http\ResponseContext;
use App\Http\ResponseHaltException;
use App\Services\LogService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * 路由器类（基于 Symfony Routing + HttpFoundation）
 */
class Router
{
    public function __construct(
        private readonly Container $container
    ) {
    }

    /**
     * 控制器命名空间
     */
    private string $controllerNamespace = 'App\\Http\\Controllers\\';

    /**
     * 路由参数（用于特殊处理）
     *
     * @var array<string, mixed>
     */
    private array $routeParams = [];

    /**
     * 中间件列表
     *
     * @var array<string>
     */
    private array $middlewares = [];

    private static ?RouteCollection $routeCollection = null;

    public static function clearRouteCache(): void
    {
        self::$routeCollection = null;
    }

    public function registerMiddleware(string $middleware): void
    {
        $this->middlewares[] = $middleware;
    }

    public function dispatch(Request $request): Response
    {
        try {
            $this->routeParams = [];
            $pathInfo = $request->getPathInfo();
            if ($this->isStaticResourceRequest($pathInfo)) {
                return $this->staticResourceNotFoundResponse();
            }

            $collection = $this->getRouteCollection();
            $context = (new RequestContext())->fromRequest($request);
            $matcher = new UrlMatcher($collection, $context);

            $params = $matcher->match($pathInfo);

            $controller = $params['_controller'] ?? null;
            $action = $params['_action'] ?? null;
            $handler = $params['_handler'] ?? null;

            if ($handler === 'category') {
                $resolved = $this->handleCategoryRoute((string)($params['slug'] ?? ''), isset($params['page']) ? (int)$params['page'] : null);
                $controller = $resolved['controller'];
                $action = $resolved['action'];
            }

            if (!is_string($controller) || !is_string($action)) {
                throw new RouterException('路由配置错误');
            }

            foreach ($params as $key => $value) {
                if (str_starts_with($key, '_')) {
                    continue;
                }
                $request->attributes->set($key, $value);
            }

            $pipeline = $this->buildMiddlewarePipeline(function () use ($controller, $action, $request) {
                return $this->executeRoute($controller, $action, $request);
            });

            ob_start();
            try {
                $result = $pipeline();
            } catch (ResponseHaltException $e) {
                $output = ob_get_clean();
                $response = $e->getResponse();
                ResponseContext::clear();
                if ($output !== '') {
                    $response->setContent($response->getContent() . $output);
                }
                return $response;
            }
            $output = ob_get_clean();

            if ($result instanceof Response) {
                ResponseContext::clear();
                if ($output !== '') {
                    $result->setContent($result->getContent() . $output);
                }
                return $result;
            }

            $contextResponse = ResponseContext::consume();
            if ($contextResponse instanceof Response) {
                if ($output !== '') {
                    $contextResponse->setContent($contextResponse->getContent() . $output);
                }
                return $contextResponse;
            }

            if (is_string($result)) {
                $output = $result . $output;
            }

            return new Response($output);
        } catch (ResourceNotFoundException $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            LogService::error($e, 'route_not_found');
            return $this->notFoundResponse();
        } catch (MethodNotAllowedException $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            return $this->methodNotAllowedResponse();
        } catch (ResponseHaltException $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            ResponseContext::clear();
            return $e->getResponse();
        } catch (\Throwable $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            LogService::error($e, 'route_dispatch_error');
            return $this->dispatchErrorResponse($request, $e);
        }
    }

    private function getRouteCollection(): RouteCollection
    {
        if (self::$routeCollection !== null) {
            return self::$routeCollection;
        }

        $collection = new RouteCollection();
        $adminPath = self::getAdminPath();

        foreach ($this->getRoutesFromConfig() as $routeDef) {
            $method = $routeDef['method'];
            $path = $this->applyAdminPath($routeDef['path'], $adminPath);
            $controller = $routeDef['controller'];
            $action = $routeDef['action'];

            $route = new Route(
                $path,
                [
                    '_controller' => $controller,
                    '_action' => $action,
                ],
                $routeDef['requirements'] ?? []
            );
            $route->setMethods([$method]);
            $collection->add($routeDef['name'], $route);
        }

        foreach ($this->getDynamicRoutes() as $routeDef) {
            $path = $this->applyAdminPath($routeDef['path'], $adminPath);
            $route = new Route(
                $path,
                [
                    '_controller' => $routeDef['controller'],
                    '_action' => $routeDef['action'],
                    '_handler' => $routeDef['handler'] ?? null,
                ],
                $routeDef['requirements'] ?? []
            );
            $route->setMethods($routeDef['methods']);
            $collection->add($routeDef['name'], $route);
        }

        self::$routeCollection = $collection;
        return $collection;
    }

    /**
     * @return array<int, array{method: string, path: string, controller: string, action: string, name: string, requirements?: array<string, string>}>
     */
    private function getRoutesFromConfig(): array
    {
        $routes = [];
        $configFile = App::getConfigPath('routes.php');
        $config = file_exists($configFile) ? require $configFile : [];

        foreach ($config as $key => $controllerAction) {
            if (!is_string($key) || !is_array($controllerAction)) {
                continue;
            }
            [$method, $path] = explode(' ', $key, 2);
            $routes[] = [
                'method' => strtoupper($method),
                'path' => $path,
                'controller' => $controllerAction[0],
                'action' => $controllerAction[1],
                'name' => md5($key),
            ];
        }

        return $routes;
    }

    /**
     * @return array<int, array{methods: array<int, string>, path: string, controller: string|null, action: string, name: string, requirements?: array<string, string>, handler?: string}>
     */
    private function getDynamicRoutes(): array
    {
        return [
            [
                'methods' => ['GET'],
                'path' => '/admin/page/edit/{id}',
                'controller' => 'Admin\\PageController',
                'action' => 'edit',
                'requirements' => ['id' => '\\d+'],
                'name' => 'admin_page_edit',
            ],
            [
                'methods' => ['GET'],
                'path' => '/admin/article/edit/{id}',
                'controller' => 'Admin\\ArticleController',
                'action' => 'edit',
                'requirements' => ['id' => '\\d+'],
                'name' => 'admin_article_edit',
            ],
            [
                'methods' => ['GET'],
                'path' => '/article/{id}',
                'controller' => 'ArticleController',
                'action' => 'index',
                'requirements' => ['id' => '[1-9][0-9]*'],
                'name' => 'front_article_detail',
            ],
            [
                'methods' => ['GET'],
                'path' => '/file/download/{id}',
                'controller' => 'FileController',
                'action' => 'download',
                'requirements' => ['id' => '\\d+'],
                'name' => 'file_download',
            ],
            [
                'methods' => ['GET'],
                'path' => '/c/{slug}/page/{page}',
                'controller' => null,
                'action' => 'index',
                'requirements' => ['page' => '[1-9][0-9]*'],
                'handler' => 'category',
                'name' => 'category_page',
            ],
            [
                'methods' => ['GET'],
                'path' => '/c/{slug}',
                'controller' => null,
                'action' => 'index',
                'handler' => 'category',
                'name' => 'category_list',
            ],
            [
                'methods' => ['POST'],
                'path' => '/admin/article/delete/{id}',
                'controller' => 'Admin\\ArticleController',
                'action' => 'delete',
                'requirements' => ['id' => '\\d+'],
                'name' => 'admin_article_delete',
            ],
            [
                'methods' => ['POST'],
                'path' => '/admin/page/delete/{id}',
                'controller' => 'Admin\\PageController',
                'action' => 'delete',
                'requirements' => ['id' => '\\d+'],
                'name' => 'admin_page_delete',
            ],
            [
                'methods' => ['POST'],
                'path' => '/admin/file/delete/{id}',
                'controller' => 'Admin\\FileController',
                'action' => 'delete',
                'requirements' => ['id' => '\\d+'],
                'name' => 'admin_file_delete',
            ],
        ];
    }

    private function applyAdminPath(string $path, string $adminPath): string
    {
        if ($adminPath === '/admin') {
            return $path;
        }
        if (str_starts_with($path, '/admin')) {
            return preg_replace('#^/admin#', $adminPath, $path) ?? $path;
        }
        return $path;
    }

    private function buildMiddlewarePipeline(callable $destination): callable
    {
        $next = $destination;
        foreach (array_reverse($this->middlewares) as $middlewareClass) {
            $next = function () use ($middlewareClass, $next) {
                $middleware = $this->container->get($middlewareClass);
                return $middleware->handle($next);
            };
        }
        return $next;
    }

    private function handleCategoryRoute(string $slug, ?int $page = null): array
    {
        $this->routeParams['slug'] = $slug;
        if ($page !== null) {
            $this->routeParams['page'] = $page;
        }

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

    private function executeRoute(string $controller, string $action, Request $request): mixed
    {
        $controllerName = $this->controllerNamespace . $controller;
        $actionName = $action;

        if (!class_exists($controllerName)) {
            throw new RouterException("控制器不存在: {$controllerName}");
        }

        $controllerInstance = $this->container->get($controllerName);

        $contextResponse = ResponseContext::get();
        if ($contextResponse instanceof Response) {
            ResponseContext::clear();
            return $contextResponse;
        }

        if (!method_exists($controllerInstance, $actionName)) {
            throw new RouterException("方法不存在: {$controllerName}::{$actionName}()");
        }

        if (!empty($this->routeParams) && method_exists($controllerInstance, 'setRouteParams')) {
            $controllerInstance->setRouteParams($this->routeParams);
            foreach ($this->routeParams as $key => $value) {
                $request->attributes->set($key, $value);
            }
        }

        return $controllerInstance->$actionName();
    }

    private function isStaticResourceRequest(string $uri): bool
    {
        if (str_ends_with($uri, '.map')) {
            return true;
        }

        $staticExtensions = ['.css', '.js', '.png', '.jpg', '.jpeg', '.gif', '.svg', '.woff', '.woff2', '.ttf', '.eot'];
        foreach ($staticExtensions as $ext) {
            if (str_ends_with($uri, $ext)) {
                return true;
            }
        }

        return false;
    }

    private function staticResourceNotFoundResponse(): Response
    {
        $html = App::renderErrorPageHtml(404, '资源未找到', '该静态资源不存在或已被移除。');
        return new Response($html, 404);
    }

    private function notFoundResponse(): Response
    {
        $html = App::renderErrorPageHtml(404, '页面不存在', '访问的页面可能已被删除或地址输入有误。');
        return new Response($html, 404);
    }

    private function methodNotAllowedResponse(): Response
    {
        $html = App::renderErrorPageHtml(405, '请求方式不被允许', '请检查请求方法或稍后重试。');
        return new Response($html, 405);
    }

    private function dispatchErrorResponse(Request $request, \Throwable $e): Response
    {
        if ($request->isXmlHttpRequest()) {
            return new Response(
                json_encode([
                    'code' => 1,
                    'message' => '服务器内部错误',
                    'error' => App::isDebug() ? $e->getMessage() : '请稍后重试'
                ], JSON_UNESCAPED_UNICODE),
                500,
                ['Content-Type' => 'application/json; charset=utf-8']
            );
        }

        if (App::isDebug()) {
            $system = App::getConfig('system', []);
            $copyright = $system['copyright'] ?? '';
            $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Route Error</title>'
                . '<style>body{font-family:Arial,sans-serif;max-width:1100px;margin:40px auto;padding:20px;}h1{color:#e74c3c;}pre{background:#f6f8fa;padding:12px;border-radius:6px;overflow:auto;}footer{margin-top:24px;padding-top:12px;border-top:1px solid #eee;color:#999;font-size:12px;text-align:center;}</style>'
                . '</head><body>'
                . '<h1>Route Dispatch Error</h1>'
                . '<p><b>URI:</b> ' . htmlspecialchars($request->getPathInfo()) . '</p>'
                . '<p><b>Method:</b> ' . htmlspecialchars($request->getMethod()) . '</p>'
                . '<p><b>Exception:</b> ' . htmlspecialchars(get_class($e)) . '</p>'
                . '<p><b>Message:</b> ' . htmlspecialchars($e->getMessage()) . '</p>'
                . '<p><b>File:</b> ' . htmlspecialchars($e->getFile()) . ':' . (int)$e->getLine() . '</p>'
                . '<h2>Trace</h2><pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>'
                . '<footer>' . $copyright . '</footer>'
                . '</body></html>';
            return new Response($html, 500);
        }

        $html = App::renderErrorPageHtml(500, '服务器内部错误', '我们已记录该问题，请稍后再试。');
        return new Response($html, 500);
    }

    private static function getAdminPath(): string
    {
        try {
            $db = \App\Core\Database::get();
            $settingModel = new \App\Models\Setting($db);
            $settings = $settingModel->getAll();
            $customPath = $settings['admin_path'] ?? null;

            if ($customPath && preg_match('/^\/[\_a-zA-Z0-9-]+$/', $customPath)) {
                return $customPath;
            }
        } catch (\Exception $e) {
            error_log('Failed to get admin path from settings: ' . $e->getMessage());
        }

        $customAdminPath = \App\Core\App::getConfig('system.admin_path');
        if ($customAdminPath && is_string($customAdminPath) && preg_match('/^\/[\_a-zA-Z0-9-]+$/', $customAdminPath)) {
            return $customAdminPath;
        }

        return '/admin';
    }
}
