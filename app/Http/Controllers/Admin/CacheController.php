<?php
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Security;
use App\Core\Database;
use App\Services\LogService;
use App\Http\Router;
use App\Services\SearchService;
use App\Services\SearchCacheService;
use App\Services\SearchLogService;
use App\Services\SearchRateLimitService;
use App\Services\CacheService;

/**
 * 缓存管理控制器
 */
class CacheController extends AdminBaseController
{
    /**
     * 缓存管理首页
     *
     * @return void
     */
    public function index(): void
    {
        try {
            // 获取缓存目录信息
            $cacheDir = dirname(__DIR__, 4) . '/storage/cache';
            $latteCacheDir = $cacheDir . '/latte';
            $appCacheDir = $cacheDir . '/app';

            $latteStats = $this->getDirectoryStats($latteCacheDir, true, 200, ['lock']);
            $appStats = $this->getDirectoryStats($appCacheDir, true, 200);

            $searchCacheItems = [];
            $searchCacheCount = 0;
            $searchCacheDisplayLimit = 200;
            try {
                $db = Database::get();
                $searchCacheCount = $db->count('search_cache');
                $searchCacheItems = $db->select('search_cache', [
                    'cache_key',
                    'keyword',
                    'result_count',
                    'expires_at',
                    'updated_at',
                    'created_at',
                ], [
                    'ORDER' => ['expires_at' => 'DESC'],
                    'LIMIT' => $searchCacheDisplayLimit,
                ]);
            } catch (\Exception $e) {
                error_log('加载搜索缓存列表失败: ' . $e->getMessage());
                $searchCacheItems = [];
                $searchCacheCount = 0;
            }

            $now = time();
            $searchCacheItems = array_map(static function (array $item) use ($now): array {
                $expiresAt = isset($item['expires_at']) ? (int)$item['expires_at'] : 0;
                $updatedAt = $item['updated_at'] ?? null;
                $createdAt = $item['created_at'] ?? null;
                $item['expires_formatted'] = $expiresAt > 0 ? date('Y-m-d H:i:s', $expiresAt) : '-';
                $item['is_expired'] = $expiresAt > 0 ? $expiresAt <= $now : false;
                if ($updatedAt) {
                    $item['updated_formatted'] = date('Y-m-d H:i:s', strtotime((string)$updatedAt));
                } elseif ($createdAt) {
                    $item['updated_formatted'] = date('Y-m-d H:i:s', strtotime((string)$createdAt));
                } else {
                    $item['updated_formatted'] = '-';
                }
                $cacheKey = (string)($item['cache_key'] ?? '');
                $item['cache_key_short'] = strlen($cacheKey) > 16 ? substr($cacheKey, 0, 16) . '...' : $cacheKey;
                return $item;
            }, $searchCacheItems);

            $this->render('admin/cache/index', [
                'title' => '缓存管理',
                'latte_files' => $latteStats['files'],
                'latte_file_count' => $latteStats['count'],
                'latte_total_size' => $latteStats['size'],
                'latte_total_size_formatted' => $this->formatSize($latteStats['size']),
                'latte_display_limit' => $latteStats['display_limit'],
                'app_cache_files' => $appStats['files'],
                'app_cache_file_count' => $appStats['count'],
                'app_cache_total_size' => $appStats['size'],
                'app_cache_total_size_formatted' => $this->formatSize($appStats['size']),
                'app_cache_display_limit' => $appStats['display_limit'],
                'search_cache_items' => $searchCacheItems,
                'search_cache_count' => $searchCacheCount,
                'search_cache_display_limit' => $searchCacheDisplayLimit,
            ]);
        } catch (\Exception $e) {
            $this->errorJson('加载缓存管理页面失败: ' . $e->getMessage());
        }
    }

    /**
     * 清空 Latte 缓存
     *
     * @return void
     */
    public function clearLatte(): void
    {
        // 只接受POST请求
        if (!$this->isPost()) {
            $this->errorJson('请求方法错误');
        }

        try {
            // 验证 CSRF Token
            $csrfToken = $this->post('csrf_token', '');
            if (!Security::checkCsrfToken($csrfToken)) {
                $this->errorJson('CSRF Token 验证失败');
            }

            $cacheDir = dirname(__DIR__, 4) . '/storage/cache/latte';

            if (!is_dir($cacheDir)) {
                $this->successJson('Latte缓存目录不存在或已清空', null, '/admin/cache');
            }

            // 清空目录
            $this->clearDirectory($cacheDir);

            // 记录日志
            LogService::record(
                $this->getAdminId(),
                $this->getAdminUsername(),
                'cache_clear',
                '清空 Latte 模板缓存'
            );

            $this->successJson('Latte 缓存清空成功', null, '/admin/cache');
        } catch (\Exception $e) {
            $this->errorJson('清空缓存失败: ' . $e->getMessage());
        }
    }

    /**
     * 清空所有缓存
     *
     * @return void
     */
    public function clearAll(): void
    {
        // 只接受POST 请求
        if (!$this->isPost()) {
            $this->errorJson('请求方法错误');
        }

        try {
            // 验证 CSRF Token
            $csrfToken = $this->post('csrf_token', '');
            if (!Security::checkCsrfToken($csrfToken)) {
                $this->errorJson('CSRF Token 验证失败');
            }

            $cacheDir = dirname(__DIR__, 4) . '/storage/cache';

            if (!is_dir($cacheDir)) {
                $this->successJson('缓存目录不存在或已清空', null, '/admin/cache');
            }

            // 清空所有缓存子目录
            $subDirs = scandir($cacheDir);
            foreach ($subDirs as $dir) {
                if ($dir !== '.' && $dir !== '..' && is_dir($cacheDir . '/' . $dir)) {
                    $this->clearDirectory($cacheDir . '/' . $dir);
                }
            }

            // 清理应用缓存（Symfony Cache）
            try {
                $cacheService = new CacheService();
                $cacheService->clear();
            } catch (\Exception $e) {
                error_log('清理应用缓存时出错: ' . $e->getMessage());
            }
            
            // 清除路由缓存文件
            Router::clearRouteCache();

            // 记录日志
            LogService::record(
                $this->getAdminId(),
                $this->getAdminUsername(),
                'cache_clear',
                '清空所有缓存'
            );
            
            // 同时清理搜索缓存
            try {
                $db = Database::get();
                $cacheService ??= new CacheService();
                $searchCacheService = new SearchCacheService($db, $cacheService);
                $searchLogService = new SearchLogService($db);
                $searchRateLimitService = new SearchRateLimitService($db, $cacheService);
                $searchService = new SearchService($db, $searchCacheService, $searchRateLimitService, $searchLogService);
                $searchService->cleanup();
            } catch (\Exception $e) {
                error_log('清理搜索缓存时出错: ' . $e->getMessage());
            }

            $this->successJson('所有缓存清空成功', null, '/admin/cache');
        } catch (\Exception $e) {
            $this->errorJson('清空缓存失败: ' . $e->getMessage());
        }
    }

    /**
     * 清空指定目录
     *
     * @param string $directory 目录路径
     * @return void
     * @throws \Exception
     */
    private function clearDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return; // 如果目录不存在，直接返回
        }

        $files = scandir($directory);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $filepath = $directory . '/' . $file;

            if (is_file($filepath)) {
                // 检查文件是否可写
                if (is_writable($filepath)) {
                    if (!unlink($filepath)) {
                        // 记录无法删除的文件，但不抛出异常，避免中断整个清理过程
                        error_log('无法删除缓存文件: ' . $filepath);
                    }
                }
            } elseif (is_dir($filepath)) {
                $this->clearDirectory($filepath);
                // 尝试删除空目录
                if (is_writable($filepath)) {
                    if (!rmdir($filepath)) {
                        error_log('无法删除缓存目录: ' . $filepath);
                    }
                }
            }
        }
    }

    /**
     * 清空搜索缓存
     *
     * @return void
     */
    public function clearSearch(): void
    {
        // 只接受POST请求
        if (!$this->isPost()) {
            $this->errorJson('请求方法错误');
        }

        try {
            // 验证 CSRF Token
            $csrfToken = $this->post('csrf_token', '');
            if (!Security::checkCsrfToken($csrfToken)) {
                $this->errorJson('CSRF Token 验证失败');
            }

            // 获取服务实例
            $db = Database::get();
            $cacheService = new CacheService();
            $searchCacheService = new SearchCacheService($db, $cacheService);
            $searchLogService = new SearchLogService($db);
            $searchRateLimitService = new SearchRateLimitService($db, $cacheService);

            // 清理各类搜索缓存
            $cacheResult = $searchCacheService->cleanupExpired();
            $logResult = $searchLogService->cleanupExpired(30); // 清理30天前的日志
            $blockResult = $searchRateLimitService->cleanupExpiredBlocks();

            // 记录日志
            LogService::record(
                $this->getAdminId(),
                $this->getAdminUsername(),
                'cache_clear',
                sprintf('清空搜索缓存 - 缓存项: %d, 日志: %d, 拦截记录: %d', $cacheResult, $logResult, $blockResult)
            );

            $this->successJson(sprintf('搜索缓存清空成功 - 缓存项: %d, 日志: %d, 拦截记录: %d', $cacheResult, $logResult, $blockResult), null, '/admin/cache');
        } catch (\Exception $e) {
            $this->errorJson('清空搜索缓存失败: ' . $e->getMessage());
        }
    }

    /**
     * 清空应用缓存（Symfony Cache）
     *
     * @return void
     */
    public function clearApp(): void
    {
        if (!$this->isPost()) {
            $this->errorJson('请求方法错误');
        }

        try {
            $csrfToken = $this->post('csrf_token', '');
            if (!Security::checkCsrfToken($csrfToken)) {
                $this->errorJson('CSRF Token 验证失败');
            }

            (new CacheService())->clear();

            $appCacheDir = dirname(__DIR__, 4) . '/storage/cache/app';
            if (is_dir($appCacheDir)) {
                $this->clearDirectory($appCacheDir);
            }

            LogService::record(
                $this->getAdminId(),
                $this->getAdminUsername(),
                'cache_clear',
                '清空应用缓存'
            );

            $this->successJson('应用缓存清空成功', null, '/admin/cache');
        } catch (\Exception $e) {
            $this->errorJson('清空应用缓存失败: ' . $e->getMessage());
        }
    }

    /**
     * 格式化文件大小
     *
     * @param int $size 字节数
     * @return string
     */
    private function formatSize(int $size): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }
        return round($size, 2) . ' ' . $units[$i];
    }

    /**
     * 统计目录大小与文件数
     *
     * @param string $directory
     * @param bool $listFiles
     * @param int $limit
     * @param array<int, string> $excludeExtensions
     * @return array{count:int,size:int,files:array<int,array<string,mixed>>,display_limit:int}
     */
    private function getDirectoryStats(string $directory, bool $listFiles = false, int $limit = 200, array $excludeExtensions = []): array
    {
        $count = 0;
        $size = 0;
        $files = [];

        if (!is_dir($directory)) {
            return [
                'count' => 0,
                'size' => 0,
                'files' => [],
                'display_limit' => $limit,
            ];
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }
            $ext = strtolower(pathinfo($fileInfo->getFilename(), PATHINFO_EXTENSION));
            if ($ext !== '' && in_array($ext, $excludeExtensions, true)) {
                continue;
            }
            $count++;
            $size += $fileInfo->getSize();
            if ($listFiles) {
                $files[] = [
                    'name' => str_replace($directory . '/', '', $fileInfo->getPathname()),
                    'size' => $fileInfo->getSize(),
                    'size_formatted' => $this->formatSize($fileInfo->getSize()),
                    'modified' => $fileInfo->getMTime(),
                    'modified_formatted' => date('Y-m-d H:i:s', $fileInfo->getMTime()),
                ];
            }
        }

        if ($listFiles && !empty($files)) {
            usort($files, fn($a, $b) => $b['modified'] <=> $a['modified']);
            if ($limit > 0) {
                $files = array_slice($files, 0, $limit);
            }
        }

        return [
            'count' => $count,
            'size' => $size,
            'files' => $files,
            'display_limit' => $limit,
        ];
    }
}
