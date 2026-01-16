<?php
declare(strict_types=1);

namespace App\Service;

/**
 * 缓存服务类
 * 提供简单的文件缓存功能，用于存储统计数据等
 * 注意：如需 Redis 缓存，可以实现 CacheInterface 接口
 */
class CacheService
{
    /**
     * 缓存目录
     */
    private readonly string $cacheDir;

    /**
     * 默认缓存过期时间（秒）
     */
    private const DEFAULT_TTL = 600; // 10 分钟

    /**
     * 构造函数
     *
     * @param string|null $cacheDir 缓存目录（如果为null，使用默认目录）
     */
    public function __construct(?string $cacheDir = null)
    {
        $this->cacheDir = $cacheDir ?? dirname(__DIR__, 2) . '/cache/app';
        
        // 确保缓存目录存在
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * 获取缓存
     *
     * @param string $key 缓存键
     * @param mixed $default 默认值（如果缓存不存在或已过期）
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $filePath = $this->getCacheFilePath($key);
        
        if (!file_exists($filePath)) {
            return $default;
        }

        try {
            $data = unserialize(file_get_contents($filePath), ['allowed_classes' => false]);
            
            if (!is_array($data) || !isset($data['expires_at']) || !isset($data['value'])) {
                // 缓存文件格式错误，删除并返回默认值
                @unlink($filePath);
                return $default;
            }

            // 检查是否过期
            if (time() > $data['expires_at']) {
                @unlink($filePath);
                return $default;
            }

            return $data['value'];
        } catch (\Exception $e) {
            // 反序列化失败，删除损坏的缓存文件
            error_log('[CacheService] 缓存读取失败: ' . $e->getMessage());
            @unlink($filePath);
            return $default;
        }
    }

    /**
     * 设置缓存
     *
     * @param string $key 缓存键
     * @param mixed $value 缓存值
     * @param int $ttl 过期时间（秒），默认 10 分钟
     * @return bool 是否设置成功
     */
    public function set(string $key, mixed $value, int $ttl = self::DEFAULT_TTL): bool
    {
        $filePath = $this->getCacheFilePath($key);
        
        $data = [
            'expires_at' => time() + $ttl,
            'value' => $value,
        ];

        try {
            // 使用文件锁确保并发安全
            $result = file_put_contents(
                $filePath,
                serialize($data),
                LOCK_EX
            );
            
            return $result !== false;
        } catch (\Exception $e) {
            error_log('[CacheService] 缓存写入失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 删除缓存
     *
     * @param string $key 缓存键
     * @return bool 是否删除成功
     */
    public function delete(string $key): bool
    {
        $filePath = $this->getCacheFilePath($key);
        
        if (file_exists($filePath)) {
            return @unlink($filePath);
        }
        
        return true; // 文件不存在，视为删除成功
    }

    /**
     * 清空所有缓存
     *
     * @return int 删除的缓存文件数量
     */
    public function clear(): int
    {
        $count = 0;
        
        if (!is_dir($this->cacheDir)) {
            return 0;
        }

        $files = glob($this->cacheDir . '/*.cache');
        foreach ($files as $file) {
            if (is_file($file) && @unlink($file)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * 检查缓存是否存在且未过期
     *
     * @param string $key 缓存键
     * @return bool
     */
    public function has(string $key): bool
    {
        $filePath = $this->getCacheFilePath($key);
        
        if (!file_exists($filePath)) {
            return false;
        }

        try {
            $data = unserialize(file_get_contents($filePath), ['allowed_classes' => false]);
            
            if (!is_array($data) || !isset($data['expires_at'])) {
                return false;
            }

            // 检查是否过期
            if (time() > $data['expires_at']) {
                @unlink($filePath);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 记住缓存：如果缓存存在则返回，否则执行回调并缓存结果
     *
     * @param string $key 缓存键
     * @param callable $callback 回调函数（用于生成缓存值）
     * @param int $ttl 过期时间（秒）
     * @return mixed
     */
    public function remember(string $key, callable $callback, int $ttl = self::DEFAULT_TTL): mixed
    {
        $value = $this->get($key);
        
        if ($value !== null) {
            return $value;
        }

        // 执行回调生成值
        $value = $callback();
        
        // 缓存结果
        $this->set($key, $value, $ttl);
        
        return $value;
    }

    /**
     * 获取缓存文件路径
     *
     * @param string $key 缓存键
     * @return string
     */
    private function getCacheFilePath(string $key): string
    {
        // 使用 MD5 哈希确保文件名安全
        $safeKey = md5($key);
        return $this->cacheDir . '/' . $safeKey . '.cache';
    }
}
