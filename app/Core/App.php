<?php
declare(strict_types=1);

namespace App\Core;

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

    /**
     * 构造函数（使用 PHP 8.0 构造函数属性提升）
     *
     * @param array<string, mixed> $config 配置数组
     */
    public function __construct(
        private readonly array $config = []
    ) {
        // 如果传入配置，则保存到静态属性
        if (!empty($this->config)) {
            self::$config = $this->config;
        }
    }

    /**
     * 运行应用
     *
     * @return void
     */
    public function run(): void
    {
        // 实例化路由器并分发请求
        $router = new Router();
        $router->dispatch();
    }

    /**
     * 获取配置
     *
     * 支持点语法访问嵌套配置，如 getConfig('db.host')
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

    /**
     * 判断是否为调试模式
     *
     * @return bool
     */
    public static function isDebug(): bool
    {
        return (bool)self::getConfig('app.debug', false);
    }
}
