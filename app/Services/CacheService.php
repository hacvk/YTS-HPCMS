<?php
declare(strict_types=1);

namespace App\Services;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * 缓存服务类
 * 提供简单的文件缓存功能，用于存储统计数据等
 * 注意：底层已替换为 Symfony Cache
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
     * Symfony Cache 适配器
     */
    private FilesystemAdapter $cache;

    /**
     * 构造函数
     *
     * @param string|null $cacheDir 缓存目录（如果为null，使用默认目录）
     */
    public function __construct(?string $cacheDir = null)
    {
        $this->cacheDir = $cacheDir ?? dirname(__DIR__, 2) . '/storage/cache/app';
        $this->cache = new FilesystemAdapter('yycms', self::DEFAULT_TTL, $this->cacheDir);
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
        $cacheKey = $this->normalizeKey($key);
        $item = $this->cache->getItem($cacheKey);

        if (!$item->isHit()) {
            return $default;
        }

        return $item->get();
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
        $cacheKey = $this->normalizeKey($key);
        $item = $this->cache->getItem($cacheKey);
        $item->set($value);
        $item->expiresAfter($ttl);

        return $this->cache->save($item);
    }

    /**
     * 删除缓存
     *
     * @param string $key 缓存键
     * @return bool 是否删除成功
     */
    public function delete(string $key): bool
    {
        $cacheKey = $this->normalizeKey($key);
        return $this->cache->deleteItem($cacheKey);
    }

    /**
     * 清空所有缓存
     *
     * @return int 删除的缓存文件数量
     */
    public function clear(): int
    {
        $this->cache->clear();
        return 0;
    }

    /**
     * 检查缓存是否存在且未过期
     *
     * @param string $key 缓存键
     * @return bool
     */
    public function has(string $key): bool
    {
        $cacheKey = $this->normalizeKey($key);
        return $this->cache->hasItem($cacheKey);
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
        $cacheKey = $this->normalizeKey($key);
        $item = $this->cache->getItem($cacheKey);

        if ($item->isHit()) {
            return $item->get();
        }

        $value = $callback();
        $item->set($value);
        $item->expiresAfter($ttl);
        $this->cache->save($item);

        return $value;
    }

    /**
     * 规范化缓存键（避免非法字符）
     *
     * @param string $key 缓存键
     * @return string
     */
    private function normalizeKey(string $key): string
    {
        return md5($key);
    }
}
