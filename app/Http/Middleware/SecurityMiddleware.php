<?php
// app/Http/Middleware/SecurityMiddleware.php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * 安全中间件 - 防止调试脚本暴露
 */
class SecurityMiddleware
{
    /**
     * 处理请求
     *
     * @param callable $next 下一个中间件或控制器
     * @return mixed
     */
    public function handle(callable $next)
    {
        $nonce = base64_encode(random_bytes(16));
        $request = App::getRequest();
        if ($request !== null) {
            $request->attributes->set('_csp_nonce', $nonce);
        }
        $_SERVER['CSP_NONCE'] = $nonce;

        // 可选强制跳转 HTTPS（设置环境变量 FORCE_HTTPS=true 生效）
        $forceHttps = filter_var(getenv('FORCE_HTTPS') ?: 'false', FILTER_VALIDATE_BOOLEAN);
        if ($forceHttps && $request !== null && !$request->isSecure()) {
            $httpsUrl = $request->getSchemeAndHttpHost();
            $httpsUrl = preg_replace('#^http:#i', 'https:', $httpsUrl ?? '');
            $httpsUrl .= $request->getRequestUri();
            return new Response('', 301, ['Location' => $httpsUrl]);
        }

        // 检查是否为调试脚本
        $scriptName = basename($_SERVER['SCRIPT_NAME'] ?? '');
        $debugScripts = [
            'phpinfo_check.php',
            'debug_info.php',
            'test_sidebar_logic.php',
            'test_timezone.php',
            'db_test.php',
            'simple_router_test.php',
        ];

        if (in_array($scriptName, $debugScripts, true)) {
            // 生产环境直接拒绝
            if (!App::isDebug()) {
                return new Response(App::renderErrorPageHtml(404, '页面不存在', '访问的页面可能已被删除或地址输入有误。'), 404);
            }

            // 开发环境检查 IP
            $allowedIps = ['127.0.0.1', '::1'];
            $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';

            if (!in_array($clientIp, $allowedIps, true)) {
                return new Response(App::renderErrorPageHtml(403, '无权限访问', '当前请求未被授权访问该资源。'), 403);
            }
        }

        // 添加安全 HTTP 头
        $this->addSecurityHeaders($nonce);

        // 继续执行
        return $next();
    }

    /**
     * 添加安全 HTTP 头
     */
    private function addSecurityHeaders(string $nonce): void
    {
        // 防止点击劫持
        header('X-Frame-Options: DENY');

        // 防止 MIME 类型嗅探
        header('X-Content-Type-Options: nosniff');

        // Referrer 策略
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // 权限策略
        header('Permissions-Policy: geolocation=(), microphone=(), camera=(), clipboard-read=(), clipboard-write=()');

        // HSTS（仅在 HTTPS/代理 HTTPS 场景下开启，避免 HTTP 环境误触）
        $isHttps = (
            (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') ||
            (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
        );
        if ($isHttps) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        // CSP (根据环境调整)
        if (App::isDebug()) {
            // 开发环境 CSP（允许 unsafe-eval，移除 script unsafe-inline）
            header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:");
        } else {
            // 生产环境 CSP（移除 script unsafe-inline）
            header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self'");
        }
    }
}
