<?php
declare(strict_types=1);

namespace App\Core;

use App\Services\LogService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 应用核心类
 */
class App
{
    /**
     * 应用配置（静态属性，在初始化后不应直接修改）
     * 注意：由于是静态属性，无法使用 readonly，但通过 private 访问控制保护
     *
     * @var array<string, mixed>
     */
    private static array $config = [];
    private static ?Request $request = null;

    /**
     * 构造函数（使用 PHP 8.0 构造函数属性提升）
     * 注意：实例属性$initialConfig 与静态属性$config 不同名，避免冲突
     *
     * @param array<string, mixed> $initialConfig 初始配置数组
     */
    public function __construct(
        private readonly array $initialConfig = []
    ) {
        // 如果传入配置，则保存到静态属性
        if (!empty($this->initialConfig)) {
            self::$config = $this->initialConfig;
        }
    }

    /**
     * 运行应用
     *
     * @return void
     */
    public function run(): void
    {
        // 注册全局异常处理器
        set_exception_handler([self::class, 'handleException']);
        set_error_handler([self::class, 'handleError']);
        register_shutdown_function([self::class, 'handleShutdown']);
        
        // 初始化依赖注入容器
        $container = new Container();

        // 注册数据库单例（Medoo）
        $container->singleton(\Medoo\Medoo::class, Database::get());
        
        // 注册安全服务
        $container->singleton(\App\Services\SecurityService::class, new \App\Services\SecurityService());

        // 初始化 Request
        $request = Request::createFromGlobals();
        self::setRequest($request);
        $container->singleton(Request::class, $request);

        // 实例化路由器并分发请求（通过容器解析控制器依赖）
        $router = new \App\Http\Router($container);
        
        // 注册中间件
        $router->registerMiddleware(\App\Http\Middleware\LogMiddleware::class);
        $router->registerMiddleware(\App\Http\Middleware\PerformanceMiddleware::class);
        $router->registerMiddleware(\App\Http\Middleware\MaintenanceMiddleware::class);
        $router->registerMiddleware(\App\Http\Middleware\SecurityMiddleware::class);
        $router->registerMiddleware(\App\Http\Middleware\VisitorLogMiddleware::class);
        
        $response = $router->dispatch($request);
        if ($response instanceof Response) {
            $response->send();
        }
    }
    
    /**
     * 异常处理方法
     *
     * @param \Throwable $e 异常对象
     * @return void
     */
    public static function handleException(\Throwable $e): void
    {
        $statusCode = 500;
        $message = 'Internal Server Error';
        
        if (self::isDebug()) {
            $message = $e->getMessage();
        }
        
        // 记录错误日志
        $errorData = [
            'request_id' => $_SERVER['REQUEST_ID'] ?? uniqid('req_'),
            'error_type' => get_class($e),
            'error_message' => $e->getMessage(),
            'error_stack' => $e->getTraceAsString(),
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'admin_id' => $_SESSION['admin_id'] ?? null,
            'timestamp' => time(),
        ];
        
        try {
            $db = Database::get();
            $db->insert('error_logs', $errorData);
        } catch (\Exception $dbEx) {
            LogService::error($dbEx, 'error_logging_failed');
        }
        
        // 记录到文件日志
        LogService::error($e, 'exception');
        
        // 返回错误响应
        http_response_code($statusCode);
        
        if (self::isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode([
                'code' => $statusCode,
                'message' => $message,
            ]);
        } else {
            // 使用简单的错误页面，不依赖模板文件
            if (self::isDebug()) {
                echo '<!DOCTYPE html><html><head><title>Error</title><style>body{font-family:Arial,sans-serif;margin:40px;background-color:#f5f5f5;}h1{color:#e74c3c;}pre{background:#fff;padding:20px;border-radius:5px;box-shadow:0 2px 5px rgba(0,0,0,0.1);overflow:auto;}code{font-family:Monaco,Consolas,"Courier New",monospace;}</style></head><body>';
                echo '<h1>Error: ' . htmlspecialchars($message) . '</h1>';
                echo '<h2>Details:</h2>';
                echo '<pre><code>';
                echo 'Type: ' . htmlspecialchars(get_class($e)) . '<br>';
                echo 'Message: ' . htmlspecialchars($e->getMessage()) . '<br>';
                echo 'File: ' . htmlspecialchars($e->getFile()) . ':' . (int)$e->getLine() . '<br>';
                echo 'Trace:<br>';
                echo htmlspecialchars($e->getTraceAsString());
                echo '</code></pre>';
                echo '</body></html>';
            } else {
                echo self::renderErrorPageHtml(
                    500,
                    '服务器开小差了',
                    '我们已记录该问题，请稍后再试。',
                    '返回首页',
                    '/'
                );
            }
        }
        
        exit;
    }
    
    /**
     * 错误处理方法
     *
     * @param int $errno 错误号
     * @param string $errstr 错误信息
     * @param string $errfile 错误文件
     * @param int $errline 错误行号
     * @return bool
     */
    public static function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        // 忽略警告和通知，只处理错误
        if (!(error_reporting() & $errno)) {
            return false;
        }
        
        $e = new \ErrorException($errstr, 0, $errno, $errfile, $errline);
        self::handleException($e);
        
        return true;
    }
    
    /**
     * 脚本终止处理
     *
     * @return void
     */
    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $e = new \ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            );
            self::handleException($e);
        }
    }
    
    /**
     * 检查是否为 AJAX 请求
     *
     * @return bool
     */
    private static function isAjaxRequest(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    public static function renderErrorPageHtml(int $statusCode, string $title, string $message, string $actionText = '返回首页', string $actionUrl = '/'): string
    {
        $statusCodeText = htmlspecialchars((string)$statusCode);
        $titleText = htmlspecialchars($title);
        $messageText = htmlspecialchars($message);
        $actionTextEscaped = htmlspecialchars($actionText);
        $actionUrlEscaped = htmlspecialchars($actionUrl);

        return '<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $statusCodeText . ' - ' . $titleText . '</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background: #f6f8fb; color: #1f2a44; }
        .wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 32px; }
        .card { width: 100%; max-width: 860px; background: #fff; border-radius: 18px; box-shadow: 0 18px 40px rgba(15, 23, 42, 0.12); padding: 36px; display: flex; gap: 28px; }
        .badge { width: 88px; height: 88px; border-radius: 20px; background: linear-gradient(135deg, #4f8cff, #67c2ff); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 32px; font-weight: 700; }
        .content { flex: 1; }
        h1 { margin: 0 0 8px; font-size: 28px; }
        p { margin: 0 0 20px; color: #5b667a; line-height: 1.6; }
        .meta { font-size: 13px; color: #99a3b3; margin-bottom: 18px; }
        .actions a { display: inline-block; padding: 10px 18px; border-radius: 10px; text-decoration: none; font-weight: 600; }
        .actions a.primary { background: #2f6fed; color: #fff; margin-right: 12px; }
        .actions a.ghost { border: 1px solid #d6dbe6; color: #5b667a; }
        @media (max-width: 720px) {
            .card { flex-direction: column; align-items: flex-start; }
            .badge { width: 72px; height: 72px; font-size: 24px; }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <div class="badge">' . $statusCodeText . '</div>
            <div class="content">
                <div class="meta">请求已停止处理</div>
                <h1>' . $titleText . '</h1>
                <p>' . $messageText . '</p>
                <div class="actions">
                    <a class="primary" href="' . $actionUrlEscaped . '">' . $actionTextEscaped . '</a>
                    <a class="ghost" href="javascript:history.back()">返回上一页</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>';
    }

    /**
     * 获取配置
     *
     * 支持点语法访问嵌套配置，如getConfig('db.host')
     *
     * @param string $key 配置键名（支持点语法）
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function getConfig(string $key, mixed $default = null): mixed
    {
        // 支持点语法访问嵌套配置
        $segments = explode('.', $key);
        $value = self::$config;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * 设置配置
     *
     * @param string $key 配置键名
     * @param mixed $value 配置值
     * @return void
     */
    public static function setConfig(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $config = &self::$config;

        foreach ($segments as $i => $segment) {
            if (!isset($config[$segment]) || !is_array($config[$segment])) {
                $config[$segment] = [];
            }
            if ($i === count($segments) - 1) {
                $config[$segment] = $value;
                break;
            }
            $config = &$config[$segment];
        }
    }

    /**
     * 获取所有配置
     *
     * @return array<string, mixed>
     */
    public static function getAllConfig(): array
    {
        return self::$config;
    }

    public static function setRequest(Request $request): void
    {
        self::$request = $request;
    }

    public static function getRequest(): ?Request
    {
        return self::$request;
    }

    /**
     * 判断是否为调试模式
     *
     * @return bool
     */
    public static function isDebug(): bool
    {
        return (bool)self::getConfig('app.debug', false);
    }

    /**
     * 获取配置文件路径
     * 避免硬编码路径，提高代码可维护性
     *
     * @param string $filename 配置文件名（如：menu.php, models.php）
     * @return string 完整的配置文件路径
     */
    public static function getConfigPath(string $filename): string
    {
        // 移除可能的路径分隔符和相对路径
        $filename = basename($filename);
        
        // 配置文件统一放在 config 目录
        return dirname(__DIR__, 2) . '/config/' . $filename;
    }
}
