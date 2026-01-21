<?php
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

/**
 * 系统相关页面
 */
class SystemController extends AdminBaseController
{
    /**
     * 关于系统
     *
     * @return void
     */
    public function about(): void
    {
        $runtime = '-';
        if (isset($_SERVER['REQUEST_TIME_FLOAT']) && is_numeric($_SERVER['REQUEST_TIME_FLOAT'])) {
            $seconds = (int) max(0, microtime(true) - (float) $_SERVER['REQUEST_TIME_FLOAT']);
            $days = intdiv($seconds, 86400);
            $hours = intdiv($seconds % 86400, 3600);
            $minutes = intdiv($seconds % 3600, 60);
            $secs = $seconds % 60;
            
            if ($days > 0) {
                $runtime = sprintf('%d天 %02d:%02d:%02d', $days, $hours, $minutes, $secs);
            } else {
                $runtime = sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
            }
        }

        $lastRebootAt = '-';
        if (PHP_OS_FAMILY === 'Linux') {
            $uptimeFile = '/proc/uptime';
            if (is_readable($uptimeFile)) {
                $content = @file_get_contents($uptimeFile);
                if ($content !== false) {
                    $parts = preg_split('/\s+/', trim($content));
                    if (isset($parts[0]) && is_numeric($parts[0])) {
                        $bootAtTs = time() - (int) ((float) $parts[0]);
                        $lastRebootAt = date('Y-m-d H:i:s', $bootAtTs);
                    }
                }
            }
        }

        $requiredExtensions = [
            'curl',
            'json',
            'mbstring',
            'pdo',
            'pdo_mysql',
            'openssl',
        ];
        $loadedExtensions = array_map('strtolower', get_loaded_extensions());
        sort($loadedExtensions);

        $requiredExtensionsDisplay = [];
        foreach ($requiredExtensions as $ext) {
            $requiredExtensionsDisplay[] = in_array(strtolower($ext), $loadedExtensions, true)
                ? $ext
                : ($ext . '（缺失）');
        }
        $requiredExtensionsText = implode(', ', $requiredExtensionsDisplay);

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        
        if ($host !== '') {
            $currentUrl = $scheme . '://' . $host . $uri;
        } else {
            $currentUrl = $uri;
        }

        $this->render('admin/system/about', [
            'title' => '关于系统',
            'runtime' => $runtime,
            'last_reboot_at' => $lastRebootAt,
            'required_php_extensions' => $requiredExtensionsText,
            'current_url' => $currentUrl,
        ]);
    }
}
