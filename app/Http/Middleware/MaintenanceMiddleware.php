<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\App;
use App\Core\Database;
use App\Core\View;
use App\Models\Setting;
use Symfony\Component\HttpFoundation\Response;

/**
 * 关站维护中间件
 */
class MaintenanceMiddleware
{
    /**
     * 处理请求
     *
     * @param callable $next
     * @return mixed
     */
    public function handle(callable $next)
    {
        $request = App::getRequest();
        $path = $request ? $request->getPathInfo() : ($_SERVER['REQUEST_URI'] ?? '/');
        $path = is_string($path) ? $path : '/';

        try {
            $db = Database::get();
            $setting = new Setting($db);
            $adminPath = $setting->get('admin_path', '/admin');
            if ($this->shouldBypassMaintenance($path, $adminPath)) {
                return $next();
            }

            $enabled = $setting->get('site_maintenance_enabled', '0') === '1';
            if (!$enabled) {
                return $next();
            }

            $message = $setting->get('site_maintenance_message', '');
            $message = trim($message) !== '' ? $message : '网站正在升级维护中，给您带来不便敬请谅解。';
            $siteName = $setting->get('site_name', '网站维护中');
            $icp = $setting->get('site_icp', '');
            $beian = $setting->get('site_beian', '');
            $beianUrl = $setting->get('site_beian_url', 'http://www.beian.gov.cn/portal/registerSystemInfo?recordcode=');
            $updatedAt = $setting->get('site_maintenance_updated_at', '');

            $view = new View();
            $content = $view->renderToString('front/maintenance', [
                'site_name' => $siteName,
                'message' => $message,
                'icp' => $icp,
                'beian' => $beian,
                'beian_url' => $beianUrl,
                'updated_at' => $updatedAt,
            ]);

            return new Response($content, 503, [
                'Content-Type' => 'text/html; charset=utf-8',
                'Retry-After' => '3600',
            ]);
        } catch (\Throwable $e) {
            return $next();
        }
    }

    private function shouldBypassMaintenance(string $path, string $adminPath): bool
    {
        $path = strtok($path, '?') ?: $path;

        if ($path === '' || $path === false) {
            return false;
        }

        $adminPath = '/' . ltrim($adminPath, '/');
        $allowPrefixes = [
            $adminPath,
            '/static',
            '/uploads',
        ];
        foreach ($allowPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        $allowExact = [
            '/favicon.ico',
            '/robots.txt',
            '/sitemap.xml',
        ];
        return in_array($path, $allowExact, true);
    }
}
