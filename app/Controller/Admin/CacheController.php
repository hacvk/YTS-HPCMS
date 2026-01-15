<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Core\Security;
use App\Service\LogService;

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
            $cacheDir = dirname(__DIR__, 3) . '/data/cache';
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
            $this->errorJson('加载缓存管理页面失败：' . $e->getMessage());
        }
    }

    /**
     * 清空 Latte 缓存
     *
     * @return void
     */
    public function clearLatte(): void
    {
        // 只接受 POST 请求
        if (!$this->isPost()) {
            $this->errorJson('请求方法错误');
        }

        try {
            // 验证 CSRF Token
            $csrfToken = $this->post('csrf_token', '');
            if (!Security::checkCsrfToken($csrfToken)) {
                $this->errorJson('CSRF Token 验证失败');
            }

            $cacheDir = dirname(__DIR__, 3) . '/data/cache/latte';

            if (!is_dir($cacheDir)) {
                $this->successJson('缓存目录不存在', null, '/admin/cache');
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
            $this->errorJson('清空缓存失败：' . $e->getMessage());
        }
    }

    /**
     * 清空所有缓存
     *
     * @return void
     */
    public function clearAll(): void
    {
        // 只接受 POST 请求
        if (!$this->isPost()) {
            $this->errorJson('请求方法错误');
        }

        try {
            // 验证 CSRF Token
            $csrfToken = $this->post('csrf_token', '');
            if (!Security::checkCsrfToken($csrfToken)) {
                $this->errorJson('CSRF Token 验证失败');
            }

            $cacheDir = dirname(__DIR__, 3) . '/data/cache';

            if (!is_dir($cacheDir)) {
                $this->successJson('缓存目录不存在', null, '/admin/cache');
            }

            // 清空所有缓存子目录
            $subDirs = scandir($cacheDir);
            foreach ($subDirs as $dir) {
                if ($dir !== '.' && $dir !== '..' && is_dir($cacheDir . '/' . $dir)) {
                    $this->clearDirectory($cacheDir . '/' . $dir);
                }
            }

            // 记录日志
            LogService::record(
                $this->getAdminId(),
                $this->getAdminUsername(),
                'cache_clear',
                '清空所有缓存'
            );

            $this->successJson('所有缓存清空成功', null, '/admin/cache');
        } catch (\Exception $e) {
            $this->errorJson('清空缓存失败：' . $e->getMessage());
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
            throw new \Exception('目录不存在: ' . $directory);
        }

        $files = scandir($directory);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $filepath = $directory . '/' . $file;

            if (is_file($filepath)) {
                if (!unlink($filepath)) {
                    throw new \Exception('无法删除文件: ' . $file);
                }
            } elseif (is_dir($filepath)) {
                $this->clearDirectory($filepath);
                if (!rmdir($filepath)) {
                    throw new \Exception('无法删除目录: ' . $file);
                }
            }
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
