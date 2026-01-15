<?php
declare(strict_types=1);

namespace App\Core;

use Latte\Engine;

/**
 * 视图类（封装 Latte 模板引擎）
 */
class View
{
    /**
     * Latte 引擎实例
     *
     * @var Engine
     */
    private Engine $engine;

    /**
     * 模板基础目录
     *
     * @var string
     */
    private string $templateDir;

    /**
     * 缓存目录
     *
     * @var string
     */
    private string $cacheDir;

    /**
     * 构造函数
     *
     * @param string|null $templateDir 模板目录
     * @param string|null $cacheDir 缓存目录
     */
    public function __construct(?string $templateDir = null, ?string $cacheDir = null)
    {
        $this->templateDir = $templateDir ?? dirname(__DIR__, 2) . '/app/View';
        $this->cacheDir = $cacheDir ?? dirname(__DIR__, 2) . '/data/cache/latte';

        $this->initEngine();
    }

    /**
     * 初始化 Latte 引擎
     *
     * @return void
     */
    private function initEngine(): void
    {
        // 预加载 Latte 必要的类，避免自动加载问题
        require_once dirname(__DIR__, 2) . '/app/Lib/Latte/src/Latte/exceptions.php';
        require_once dirname(__DIR__, 2) . '/app/Lib/Latte/src/Latte/PositionAwareException.php';

        // 创建缓存目录（如果不存在）
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0777, true);
        }

        $this->engine = new Engine();

        // 设置模板目录
        $this->engine->setTempDirectory($this->cacheDir);

        // 开发模式下禁用缓存
        if (App::isDebug()) {
            $this->engine->setAutoRefresh(true);
        }

        // 注册自定义函数和过滤器
        $this->registerHelpers();
    }

    /**
     * 注册自定义函数和过滤器
     *
     * @return void
     */
    private function registerHelpers(): void
    {
        // 注册安全输出函数
        $this->engine->addFunction('e', fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'));

        // 注册 CSRF token 函数
        $this->engine->addFunction('csrf_token', fn() => Security::getCsrfToken());

        // 注册 URL 生成函数
        $this->engine->addFunction('url', fn($path = '') => '/' . ltrim($path, '/'));

        // 注册 asset 路径函数
        $this->engine->addFunction('asset', function (string $path): string {
            // 读取系统设置中的 static_url，如果没有则使用默认前缀
            $prefix = \App\Model\Setting::get('static_url', '/static/front');
            return rtrim($prefix, '/').'/'.ltrim($path, '/');
        });
        
        // 注册 repeat 过滤器
        $this->engine->addFilter('repeat', fn($string, $count) => str_repeat((string)$string, (int)$count));
        
        // 注册 escapejs 过滤器（用于JavaScript字符串转义）
        $this->engine->addFilter('escapejs', fn($string) => str_replace(
            ['\n', '\r', '\t', '"', "'", '\\', '<', '>'],
            ['\\n', '\\r', '\\t', '\\"', "\\'", '\\\\', '\\x3C', '\\x3E'],
            (string)$string
        ));
        
        // 注册 json_encode 过滤器（用于JavaScript变量）
        $this->engine->addFilter('json_encode', fn($value) => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT));
    }

    /**
     * 渲染模板
     *
     * @param string $name 模板名称（不含 .latte 扩展名）
     * @param array<string, mixed> $params 模板参数
     * @return void
     */
    public function render(string $name, array $params = []): void
    {
        // 使用输出缓冲区捕获模板内容
        ob_start();
        
        try {
            // 自动添加扩展名
            if (!str_ends_with($name, '.latte')) {
                $name .= '.latte';
            }

            // 构建完整模板路径
            $templatePath = $this->templateDir . '/' . ltrim($name, '/\\');

            // 检查模板是否存在
            if (!file_exists($templatePath)) {
                throw new \RuntimeException("模板文件不存在: {$templatePath}");
            }

            // 先合并传入的参数（优先级更高）
            $params = array_merge($params, [
                'view' => $this,
                'app' => App::class,
                'config' => App::getAllConfig(),
                'request' => [
                    'uri' => $_SERVER['REQUEST_URI'] ?? '/',
                    'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
                ],
            ]);

            // 渲染模板
            $this->engine->render($templatePath, $params);
            
            // 输出缓冲区内容
            echo ob_get_clean();
        } catch (\Exception $e) {
            // 清除缓冲区
            ob_clean();
            
            // 重新抛出异常
            throw $e;
        }
    }

    /**
     * 渲染模板并返回字符串
     *
     * @param string $name 模板名称
     * @param array<string, mixed> $params 模板参数
     * @return string
     */
    public function renderToString(string $name, array $params = []): string
    {
        ob_start();
        $this->render($name, $params);
        return ob_get_clean() ?: '';
    }

    /**
     * 设置模板变量
     *
     * @param string $key 变量名
     * @param mixed $value 变量值
     * @return self
     */
    public function assign(string $key, mixed $value): self
    {
        // Latte 使用 render 时传入参数，这里提供兼容接口
        return $this;
    }

    /**
     * 批量设置模板变量
     *
     * @param array<string, mixed> $data 变量数组
     * @return self
     */
    public function assignAll(array $data): self
    {
        // Latte 使用 render 时传入参数，这里提供兼容接口
        return $this;
    }

    /**
     * 获取 Latte 引擎实例
     *
     * @return Engine
     */
    public function getEngine(): Engine
    {
        return $this->engine;
    }

    /**
     * 设置模板目录
     *
     * @param string $directory 模板目录
     * @return self
     */
    public function setTemplateDirectory(string $directory): self
    {
        $this->templateDir = rtrim($directory, '/\\');
        return $this;
    }

    /**
     * 设置缓存目录
     *
     * @param string $directory 缓存目录
     * @return self
     */
    public function setCacheDirectory(string $directory): self
    {
        $this->cacheDir = rtrim($directory, '/\\');
        $this->engine->setTempDirectory($this->cacheDir);
        return $this;
    }

    /**
     * 清空模板缓存
     *
     * @return self
     */
    public function clearCache(): self
    {
        $this->engine->getTempDirectory();
        $cacheDir = $this->cacheDir;
        
        if (is_dir($cacheDir)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($cacheDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            
            foreach ($files as $file) {
                if ($file->isDir()) {
                    @rmdir($file->getPathname());
                } else {
                    @unlink($file->getPathname());
                }
            }
        }
        
        return $this;
    }
}
