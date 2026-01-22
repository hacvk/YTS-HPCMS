<?php
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Security;
use App\Services\LogService;
use App\Http\Router;
use App\Services\SearchService;
use App\Services\SearchCacheService;
use App\Services\SearchLogService;
use App\Services\SearchRateLimitService;

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

            $latteFiles = [];
            $latteTotalSize = 0;
            $latteFileCount = 0;

            if (is_dir($latteCacheDir)) {
                $files = scandir($latteCacheDir);
                foreach ($files as $file) {
                    if ($file !== '.' && $file !== '..') {
                        $filepath = $latteCacheDir . '/' . $file;
                        if (is_file($filepath) && !str_ends_with($file, '.lock')) {
                            $size = filesize($filepath);
                            $mtime = filemtime($filepath);
                            $latteFiles[] = [
                                'name' => $file,
                                'size' => $size,
                                'size_formatted' => $this->formatSize($size),
                                'modified' => $mtime,
                                'modified_formatted' => date('Y-m-d H:i:s', $mtime),
                            ];
                            $latteTotalSize += $size;
                            $latteFileCount++;
                        }
                    }
                }
            }

            $this->render('admin/cache/index', [
                'title' => '缓存管理',
                'latte_files' => $latteFiles,
                'latte_file_count' => $latteFileCount,
                'latte_total_size' => $latteTotalSize,
                'latte_total_size_formatted' => $this->formatSize($latteTotalSize),
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
                $searchService = $this->container->get(SearchService::class);
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
            $searchCacheService = $this->container->get(SearchCacheService::class);
            $searchLogService = $this->container->get(SearchLogService::class);
            $searchRateLimitService = $this->container->get(SearchRateLimitService::class);

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
}
