<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Session 安全服务
 */
class SessionService
{
    /**
     * Session 配置
     *
     * @var array<string, mixed>
     */
    private static array $config = [
        'cookie_httponly' => true,
        'cookie_secure' => false, // 生产环境应设为 true
        'use_strict_mode' => true,
        'cookie_samesite' => 'Lax',
        'cookie_lifetime' => 0, // 浏览器关闭时过期
        'gc_maxlifetime' => 1440, // 24分钟
    ];

    /**
     * 初始化 Session 配置
     *
     * @return void
     */
    public static function init(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $cookieSecure = self::resolveCookieSecure();
            $cookieSameSite = self::resolveCookieSameSite();

            // 设置 Session 配置
            ini_set('session.cookie_httponly', self::$config['cookie_httponly'] ? '1' : '0');
            ini_set('session.cookie_secure', $cookieSecure ? '1' : '0');
            ini_set('session.use_strict_mode', self::$config['use_strict_mode'] ? '1' : '0');
            ini_set('session.cookie_samesite', $cookieSameSite);
            ini_set('session.cookie_lifetime', (string)self::$config['cookie_lifetime']);
            ini_set('session.gc_maxlifetime', (string)self::$config['gc_maxlifetime']);

            // 使用更安全的 Session ID 生成方式
            if (function_exists('random_bytes')) {
                ini_set('session.entropy_length', '64');
            }

            session_start();
        }
    }

    /**
     * 解析 cookie_secure（环境变量优先，其次自动检测 HTTPS）
     *
     * @return bool
     */
    private static function resolveCookieSecure(): bool
    {
        $env = getenv('SESSION_COOKIE_SECURE');
        if ($env !== false) {
            return filter_var($env, FILTER_VALIDATE_BOOLEAN);
        }

        if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
            return true;
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            return true;
        }

        return self::$config['cookie_secure'];
    }

    /**
     * 解析 cookie_samesite（环境变量优先）
     *
     * @return string
     */
    private static function resolveCookieSameSite(): string
    {
        $env = getenv('SESSION_COOKIE_SAMESITE');
        if ($env !== false && $env !== '') {
            return (string)$env;
        }

        return (string)self::$config['cookie_samesite'];
    }

    /**
     * 重新生成 Session ID（防止会话固定攻击）
     *
     * @param bool $destroyOld 是否销毁旧会话
     * @return void
     */
    public static function regenerateId(bool $destroyOld = true): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id($destroyOld);
        }
    }

    /**
     * 安全地获取 Session 值
     *
     * @param string $key 键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * 安全地设置 Session 值
     *
     * @param string $key 键名
     * @param mixed $value 值
     * @return void
     */
    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * 检查 Session 键是否存在
     *
     * @param string $key 键名
     * @return bool
     */
    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    /**
     * 删除 Session 值
     *
     * @param string $key 键名
     * @return void
     */
    public static function delete(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * 清空 Session（保留某些关键值）
     *
     * @param array<string> $keep 保留的键名列表
     * @return void
     */
    public static function clear(array $keep = []): void
    {
        foreach ($_SESSION as $key => $value) {
            if (!in_array($key, $keep, true)) {
                unset($_SESSION[$key]);
            }
        }
    }

    /**
     * 安全地销毁 Session
     *
     * @return void
     */
    public static function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            // 清空所有 Session 数据
            $_SESSION = [];
            
            // 销毁 Session
            session_destroy();
            
            // 删除 Session Cookie
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }
        }
    }

    /**
     * 获取 Session ID（用于日志记录）
     *
     * @return string
     */
    public static function getId(): string
    {
        return session_id() ?: '';
    }

    /**
     * 检查是否已登录
     *
     * @param string $key 登录标识键名
     * @return bool
     */
    public static function isLoggedIn(string $key = 'admin_id'): bool
    {
        $id = self::get($key);
        return !empty($id) && is_numeric($id);
    }

    /**
     * 获取客户端 IP 地址（考虑代理）
     *
     * @return string
     */
    public static function getClientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // 检查代理头（不可信，仅用于日志）
        $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP'];
        foreach ($headers as $header) {
            if (isset($_SERVER[$header]) && !empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $candidate = trim($ips[0]);
                if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    $ip = $candidate;
                    break;
                }
            }
        }

        return $ip;
    }

    /**
     * 验证 Session 是否存在
     *
     * @return bool
     */
    public static function validate(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }

        // 检查 Session ID 是否有效
        $sessionId = session_id();
        if (empty($sessionId)) {
            return false;
        }

        return true;
    }

    /**
     * 获取 Session 统计信息（用于调试）
     *
     * @return array<string, mixed>
     */
    public static function getStats(): array
    {
        return [
            'status' => session_status(),
            'id' => self::getId(),
            'name' => session_name(),
            'cookie_params' => session_get_cookie_params(),
            'size' => strlen(serialize($_SESSION)),
        ];
    }
}
