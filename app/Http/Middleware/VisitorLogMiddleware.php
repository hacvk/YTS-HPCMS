<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use App\Services\VisitorLogService;

/**
 * 访客日志中间件
 * 记录访客请求信息，异步写入日志
 *
 * 安全特性:
 * - IP头部白名单控制
 * - Session Cookie HttpOnly + Secure + SameSite
 * - 异常隔离: 日志失败不影响主流程
 * - 数据清理: 自动清理过期数据
 *
 * 性能特性:
 * - 异步写入: 使用文件驱动，不阻塞主流程
 * - 内存控制: 只采集必要数据
 * - 异常处理: 日志失败不影响主流程
 */
class VisitorLogMiddleware
{

    /**
     * 处理请求
     *
     * @param callable $next 下一个中间件或请求处理函数
     * @return mixed
     */
    public function handle(callable $next)
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        $requestId = uniqid('req_', true);
        $sessionId = $this->getSessionId();

        $_SERVER['REQUEST_ID'] = $requestId;
        $_SERVER['SESSION_ID'] = $sessionId;

        try {
            // 执行请求
            $response = $next();

            $endTime = microtime(true);
            $duration = ($endTime - $startTime) * 1000;
            $memoryUsed = (memory_get_usage() - $startMemory) / 1024;
            $statusCode = 200;
            if (is_object($response) && method_exists($response, 'getStatusCode')) {
                /** @var int $statusCode */
                $statusCode = (int)$response->getStatusCode();
            } else {
                $statusCode = (int)http_response_code();
            }

            // 异步记录访客日志（不阻塞主流程）
            $this->logVisitorAsync(
                $requestId,
                $sessionId,
                $duration,
                $memoryUsed,
                $statusCode
            );

            return $response;
        } catch (\Throwable $e) {
            // 即使异常也要记录日志
            $this->logVisitorAsync(
                $requestId,
                $sessionId,
                (microtime(true) - $startTime) * 1000,
                (memory_get_usage() - $startMemory) / 1024,
                500
            );

            throw $e;
        }
    }

    /**
     * 获取会话ID
     *
     * 安全特性:
     * - 使用random_bytes生成安全的会话ID
     * - Cookie设置HttpOnly、Secure、SameSite
     * - 有效期30分钟
     *
     * @return string
     */
    private function getSessionId(): string
    {
        // 优先使用Cookie中的会话ID
        if (isset($_COOKIE['visitor_session'])) {
            return $_COOKIE['visitor_session'];
        }

        // 生成新的会话ID (使用random_bytes确保安全性)
        $sessionId = 'sess_' . bin2hex(random_bytes(16));

        // 设置Cookie
        // HttpOnly: 防止XSS攻击
        // Secure: 仅HTTPS传输 (如果支持)
        // SameSite: 防止CSRF攻击
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        setcookie(
            'visitor_session',
            $sessionId,
            [
                'expires' => time() + 1800,  // 30分钟
                'path' => '/',
                'domain' => '',
                'secure' => $secure,  // 仅HTTPS
                'httponly' => true,   // HttpOnly
                'samesite' => 'Lax',  // SameSite Lax
            ]
        );

        return $sessionId;
    }

    /**
     * 异步记录访客日志
     *
     * 异常隔离: 日志失败不影响主流程
     *
     * @param string $requestId 请求ID
     * @param string $sessionId 会话ID
     * @param float $duration 耗时(ms)
     * @param float $memoryUsed 内存使用(KB)
     * @param int $statusCode 状态码
     * @return void
     */
    private function logVisitorAsync(
        string $requestId,
        string $sessionId,
        float $duration,
        float $memoryUsed,
        int $statusCode
    ): void {
        try {
            // 使用VisitorLogService记录日志
            VisitorLogService::record(
                $requestId,
                $sessionId,
                $duration,
                $memoryUsed,
                $statusCode
            );
        } catch (\Exception $e) {
            // 日志记录失败不影响主流程
            // 记录到PHP错误日志
            error_log('[VisitorLogMiddleware] 日志记录失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取客户端IP地址
     *
     * 安全特性:
     * - 支持可信代理白名单
     * - 防止IP伪造攻击
     * - 验证IP格式
     *
     * @return string
     */
    private function getClientIp(): string
    {
        $ip = '';

        // 从配置文件读取可信代理配置
        $enableTrustedProxy = \App\Core\App::getConfig('visitor.ip.enable_trusted_proxy', false);
        $trustedProxies = \App\Core\App::getConfig('visitor.ip.trusted_proxies', []);

        // 检查是否启用可信代理模式
        if ($enableTrustedProxy) {
            // 检查当前IP是否在白名单中
            $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
            if (in_array($remoteAddr, $trustedProxies, true)) {
                // 信任的代理，可以使用X-Forwarded-For
                if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                    // X-Forwarded-For 可能包含多个IP，取第一个
                    $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                    $ip = trim($ips[0]);
                } elseif (isset($_SERVER['HTTP_CLIENT_IP']) && !empty($_SERVER['HTTP_CLIENT_IP'])) {
                    $ip = $_SERVER['HTTP_CLIENT_IP'];
                } elseif (isset($_SERVER['HTTP_X_REAL_IP']) && !empty($_SERVER['HTTP_X_REAL_IP'])) {
                    $ip = $_SERVER['HTTP_X_REAL_IP'];
                }
            }
        }

        // 如果没有从头部获取到IP，使用REMOTE_ADDR
        if (empty($ip)) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }

        // 验证IP格式
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $ip;
        }

        // 如果验证失败，返回REMOTE_ADDR
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * 清理字符串中的控制字符
     *
     * 安全特性:
     * - 移除不可打印字符
     * - 防止注入攻击
     * - 限制长度
     *
     * @param string $str
     * @param int $maxLength 最大长度
     * @return string
     */
    private function sanitizeString(string $str, int $maxLength = 500): string
    {
        // 移除控制字符 (ASCII 0-31, 127)
        $str = preg_replace('/[\x00-\x1F\x7F]/', '', $str);

        // 移除Unicode控制字符
        $str = preg_replace('/\p{C}/u', '', $str);

        // 限制长度
        if (mb_strlen($str) > $maxLength) {
            $str = mb_substr($str, 0, $maxLength);
        }

        return $str;
    }

    /**
     * 获取User-Agent
     *
     * 安全特性:
     * - 清理控制字符
     * - 限制长度
     *
     * @return string
     */
    private function getUserAgent(): string
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return $this->sanitizeString($userAgent, 500);
    }

    /**
     * 获取URL
     *
     * 安全特性:
     * - 清理控制字符
     * - 限制长度
     *
     * @return string
     */
    private function getUrl(): string
    {
        $url = $_SERVER['REQUEST_URI'] ?? '/';
        return $this->sanitizeString($url, 500);
    }

    /**
     * 获取Referrer
     *
     * 安全特性:
     * - 清理控制字符
     * - 限制长度
     *
     * @return string
     */
    private function getReferrer(): string
    {
        $referrer = $_SERVER['HTTP_REFERER'] ?? '';
        return $this->sanitizeString($referrer, 500);
    }

    /**
     * 检测设备类型
     *
     * @param string $userAgent
     * @return string
     */
    private function detectDeviceType(string $userAgent): string
    {
        $userAgent = strtolower($userAgent);

        if (preg_match('/(mobile|android|iphone|ipad|windows phone)/i', $userAgent)) {
            return 'mobile';
        }

        if (preg_match('/(tablet|ipad)/i', $userAgent)) {
            return 'tablet';
        }

        return 'desktop';
    }

    /**
     * 检测浏览器
     *
     * @param string $userAgent
     * @return string
     */
    private function detectBrowser(string $userAgent): string
    {
        $userAgent = strtolower($userAgent);

        if (strpos($userAgent, 'chrome') !== false) return 'chrome';
        if (strpos($userAgent, 'firefox') !== false) return 'firefox';
        if (strpos($userAgent, 'safari') !== false) return 'safari';
        if (strpos($userAgent, 'edge') !== false) return 'edge';
        if (strpos($userAgent, 'msie') !== false || strpos($userAgent, 'trident') !== false) return 'ie';

        return 'unknown';
    }

    /**
     * 检测操作系统
     *
     * @param string $userAgent
     * @return string
     */
    private function detectOS(string $userAgent): string
    {
        $userAgent = strtolower($userAgent);

        if (strpos($userAgent, 'windows') !== false) return 'windows';
        if (strpos($userAgent, 'mac os') !== false) return 'macos';
        if (strpos($userAgent, 'linux') !== false) return 'linux';
        if (strpos($userAgent, 'android') !== false) return 'android';
        if (strpos($userAgent, 'iphone') !== false || strpos($userAgent, 'ipad') !== false) return 'ios';

        return 'unknown';
    }

    /**
     * 检测是否爬虫
     *
     * @param string $userAgent
     * @return bool
     */
    private function isBot(string $userAgent): bool
    {
        $botPatterns = [
            'bot', 'crawler', 'spider', 'scraper', 'curl', 'wget',
            'python', 'java', 'go-http-client', 'php', 'ruby',
            'baiduspider', 'googlebot', 'bingbot', 'yandexbot',
        ];

        $userAgent = strtolower($userAgent);

        foreach ($botPatterns as $pattern) {
            if (strpos($userAgent, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }
}
