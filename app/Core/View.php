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
     */
    private Engine $engine;

    /**
     * 构造函数（使用 PHP 8.0 构造函数属性提升）
     *
     * @param string|null $templateDir 模板目录
     * @param string|null $cacheDir 缓存目录
     */
    public function __construct(
        private string $templateDir = '',
        private string $cacheDir = ''
    ) {
        // 如果未提供参数，使用默认值
        if ($this->templateDir === '') {
            $this->templateDir = dirname(__DIR__, 2) . '/app/View';
        }
        if ($this->cacheDir === '') {
            $this->cacheDir = dirname(__DIR__, 2) . '/cache/latte';
        }

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

        // 配置 Latte 引擎安全设置
        $isDebug = App::isDebug();
        
        // 开发模式：启用自动刷新（检测模板文件变化）
        // 生产模式：严格关闭自动刷新，避免文件系统 I/O 损耗
        $this->engine->setAutoRefresh($isDebug);
        
        // 开启 Latte Strict 模式（如果版本支持）
        // Strict 模式会启用更严格的类型检查和错误报告
        if (method_exists($this->engine, 'setStrictTypes')) {
            $this->engine->setStrictTypes(true);
        }
        
        // 生产环境：禁用调试模式相关功能
        if (!$isDebug) {
            // 确保生产环境关闭所有调试相关功能
            if (method_exists($this->engine, 'setStrictParsing')) {
                // 如果支持，启用严格解析模式
                $this->engine->setStrictParsing(true);
            }
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
            try {
                $db = \App\Core\Database::get();
                $setting = new \App\Model\Setting($db);
                $prefix = $setting->get('static_url', '/static/front');
            } catch (\Throwable $e) {
                $prefix = '/static/front';
            }
            return rtrim($prefix, '/').'/'.ltrim($path, '/');
        });
        
        // 注册 setting 函数（读取系统设置）
        $this->engine->addFunction('setting', function (string $key, mixed $default = null): mixed {
            try {
                $db = \App\Core\Database::get();
                $setting = new \App\Model\Setting($db);
                return $setting->get($key, $default);
            } catch (\Throwable $e) {
                return $default;
            }
        });
        
        // 注册 repeat 过滤器
        $this->engine->addFilter('repeat', fn($string, $count) => str_repeat((string)$string, (int)$count));
        
        // 注册 escapejs 过滤器（用于JavaScript字符串转义）
        // 使用 json_encode 标准方法，这是 PHP 输出数据到 JavaScript 上下文的行业标准做法
        // JSON_HEX_TAG: 将 < 和 > 转换为 \u003C 和 \u003E
        // JSON_HEX_AMP: 将 & 转换为 \u0026
        // JSON_HEX_APOS: 将 ' 转换为 \u0027
        // JSON_HEX_QUOT: 将 " 转换为 \u0022
        // 
        // 注意：此过滤器用于在 JavaScript 字符串字面量中使用（如：var str = "{$value|escapejs}";）
        // json_encode 会返回带引号的 JSON 字符串，需要去掉首尾引号才能嵌入到 JavaScript 字符串中
        $this->engine->addFilter('escapejs', function ($value): string {
            // 将值转换为字符串
            $stringValue = (string)$value;
            
            // 使用 json_encode 进行安全转义（行业标准做法）
            $encoded = json_encode($stringValue, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
            
            // json_encode 返回的格式是 "..."（带引号），需要去掉首尾引号
            // 这样可以直接嵌入到 JavaScript 字符串字面量中
            if (strlen($encoded) >= 2 && $encoded[0] === '"' && $encoded[-1] === '"') {
                return substr($encoded, 1, -1);
            }
            
            // 如果格式不符合预期（理论上不应该发生），返回原编码结果
            return $encoded;
        });
        
        // 注册 json_encode 过滤器（用于JavaScript变量）
        $this->engine->addFilter('json_encode', fn($value) => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT));

        // 注册 friendLinks 函数（用于前台页脚）
        $this->engine->addFunction('friendLinks', function (): string {
            try {
                $db = \App\Core\Database::get();
                $links = $db->select('sys_friend_link', 
                    ['title', 'url'], 
                    ['status' => 1, 'ORDER' => ['sort' => 'ASC', 'id' => 'ASC']]
                ) ?: [];

                $html = '';
                foreach ($links as $link) {
                    $url = htmlspecialchars((string)($link['url'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $title = htmlspecialchars((string)($link['title'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $html .= "<a href=\"$url\" target=\"_blank\" rel=\"nofollow\">$title</a>";
                }
                return $html;
            } catch (\Throwable $e) {
                // 生产环境静默失败，仅记录日志
                error_log('Failed to render friend links: ' . $e->getMessage());
                return '';
            }
        });
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
