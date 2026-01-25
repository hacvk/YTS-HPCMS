<?php
declare(strict_types=1);

namespace App\Core;

/**
 * PSR-4 自动加载器
 */
class Autoloader
{
    /**
     * 命名空间前缀映射
     */
    private array $prefixes = [];

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->registerNamespace('App\\', __DIR__ . '/../');

        // 如果 composer 已加载对应类，则不注册内置库映射
        if (!class_exists('Medoo\\Medoo')) {
            // 注意：Medoo 这里直接指向文件
            $this->registerNamespace('Medoo\\', __DIR__ . '/../Lib/Medoo.php');
        }

        if (!class_exists('Latte\\Engine')) {
            // 注意：Latte 指向目录
            $this->registerNamespace('Latte\\', __DIR__ . '/../Lib/Latte/src/Latte/');
        }
    }

    /**
     * 注册命名空间前缀
     */
    public function registerNamespace(string $prefix, string $baseDir): self
    {
        // 标准化命名空间前缀
        $prefix = trim($prefix, '\\') . '\\';
        
        // 修复点：如果是.php 文件，不要加目录分隔符
        if (str_ends_with($baseDir, '.php')) {
            // 文件映射：保持原样
            $this->prefixes[$prefix] = $baseDir;
        } else {
            // 目录映射：确保以 / \ 结尾
            $baseDir = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR;
            $this->prefixes[$prefix] = $baseDir;
        }
        
        return $this;
    }

    /**
     * 注册自动加载器到 SPL
     */
    public function register(bool $prepend = false): self
    {
        spl_autoload_register([$this, 'loadClass'], true, $prepend);
        return $this;
    }

    /**
     * 自动加载器
     */
    public function loadClass(string $class): bool
    {
        $prefix = $class;
        
        while (false !== ($pos = strrpos($prefix, '\\'))) {
            $prefix = substr($class, 0, $pos + 1);
            $relativeClass = substr($class, $pos + 1);
            
            if (isset($this->prefixes[$prefix])) {
                if ($this->loadMappedFile($prefix, $relativeClass)) {
                    return true;
                }
            }
            $prefix = rtrim($prefix, '\\');
        }
        return false;
    }

    /**
     * 加载映射文件
     */
    private function loadMappedFile(string $prefix, string $relativeClass): bool
    {
        $baseDir = $this->prefixes[$prefix];
        
        // 情况1：单文件映射 (Medoo)
        // 如果 baseDir 是个文件，且命名空间匹配，直接加载该文件
        if (str_ends_with($baseDir, '.php')) {
            if (file_exists($baseDir)) {
                require $baseDir;
                return true;
            }
            return false;
        }
        
        // 情况2：目录映射(App, Latte)
        $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass);
        $file = $baseDir . $relativePath . '.php';
        
        if (file_exists($file)) {
            require $file;
            return true;
        }
        
        return false;
    }
}
