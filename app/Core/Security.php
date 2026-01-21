<?php
declare(strict_types=1);

namespace App\Core;

/**
 * 安全工具�?
 */
class Security
{
    /**
     * CSRF Token Session 键名
     */
    private const CSRF_TOKEN_KEY = '_csrf_token';

    /**
     * 生成 CSRF Token
     *
     * @return string 生成�?Token
     */
    public static function generateCsrfToken(): string
    {
        // 如果 Session 中已�?token，直接返�?
        if (isset($_SESSION[self::CSRF_TOKEN_KEY]) && !empty($_SESSION[self::CSRF_TOKEN_KEY])) {
            return $_SESSION[self::CSRF_TOKEN_KEY];
        }

        // 生成�?token
        $token = bin2hex(random_bytes(32));
        $_SESSION[self::CSRF_TOKEN_KEY] = $token;

        return $token;
    }

    /**
     * 获取当前 CSRF Token
     *
     * @return string|null 返回 Token �?null
     */
    public static function getCsrfToken(): ?string
    {
        return $_SESSION[self::CSRF_TOKEN_KEY] ?? null;
    }

    /**
     * 验证 CSRF Token
     *
     * @param string $token 待验证的 Token
     * @param bool $regenerate 验证成功后是否重新生�?Token（默�?false�?
     * @return bool 验证是否成功
     */
    public static function checkCsrfToken(string $token, bool $regenerate = false): bool
    {
        $sessionToken = $_SESSION[self::CSRF_TOKEN_KEY] ?? null;

        if ($sessionToken === null) {
            return false;
        }

        // 使用 hash_equals 防止时序攻击
        if (!hash_equals($sessionToken, $token)) {
            return false;
        }

        // 验证成功后重新生�?token（可选的安全措施�?
        if ($regenerate) {
            $newToken = bin2hex(random_bytes(32));
            $_SESSION[self::CSRF_TOKEN_KEY] = $newToken;
        }

        return true;
    }

    /**
     * 哈希密码（使�?Argon2ID�?
     *
     * @param string $password 明文密码
     * @return string 哈希后的密码
     * @throws \Exception
     */
    public static function passwordHash(string $password): string
    {
        if (empty($password)) {
            throw new \Exception('密码不能为空');
        }

        // 检�?Argon2ID 是否可用
        if (!defined('PASSWORD_ARGON2ID')) {
            // 回退到默认算�?
            return password_hash($password, PASSWORD_DEFAULT);
        }

        // 使用 Argon2ID 算法
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,    // 64 MB
            'time_cost' => 4,          // 迭代次数
            'threads' => 3,            // 线程�?
        ]);
    }

    /**
     * 验证密码
     *
     * @param string $password 明文密码
     * @param string $hash 哈希后的密码
     * @return bool 验证是否成功
     */
    public static function passwordVerify(string $password, string $hash): bool
    {
        if (empty($password) || empty($hash)) {
            return false;
        }

        return password_verify($password, $hash);
    }

    /**
     * 检查密码是否需要重新哈�?
     *
     * @param string $hash 哈希后的密码
     * @return bool 是否需要重新哈�?
     */
    public static function passwordNeedsRehash(string $hash): bool
    {
        if (!defined('PASSWORD_ARGON2ID')) {
            return password_needs_rehash($hash, PASSWORD_DEFAULT);
        }

        return password_needs_rehash($hash, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3,
        ]);
    }

    /**
     * 递归过滤输入数据（XSS 防护�?
     *
     * @param mixed $data 输入数据
     * @param int $flags htmlspecialchars 标志
     * @return mixed 过滤后的数据
     */
    public static function clean(mixed $data, int $flags = ENT_QUOTES | ENT_HTML5): mixed
    {
        if (is_array($data)) {
            return array_map(fn($item) => self::clean($item, $flags), $data);
        }

        if (is_string($data)) {
            return htmlspecialchars($data, $flags, 'UTF-8');
        }

        return $data;
    }

    /**
     * 生成随机字符�?
     *
     * @param int $length 字符串长�?
     * @return string
     */
    public static function randomString(int $length = 32): string
    {
        return bin2hex(random_bytes((int)ceil($length / 2)));
    }

    /**
     * 生成 UUID v4
     *
     * @return string
     */
    public static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // version 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // variant

        return sprintf(
            '%s%s-%s-%s-%s-%s%s%s',
            bin2hex(substr($data, 0, 4)),
            bin2hex(substr($data, 4, 2)),
            bin2hex(substr($data, 6, 2)),
            bin2hex(substr($data, 8, 2)),
            bin2hex(substr($data, 10, 4)),
            bin2hex(substr($data, 14, 2))
        );
    }

    public static function sanitizeHtml(string $html): string
    {
        // 确保 HTMLPurifier 类可用（项目内置�?app/Lib/HTMLPurifier�?
        if (!class_exists('HTMLPurifier')) {
            $autoload = dirname(__DIR__, 1) . '/Lib/HTMLPurifier/HTMLPurifier.auto.php';
            $autoload2 = dirname(__DIR__, 1) . '/Lib/HTMLPurifier/HTMLPurifier.autoload.php';

            if (is_file($autoload)) {
                require_once $autoload;
            } elseif (is_file($autoload2)) {
                require_once $autoload2;
            }
        }

        if (!class_exists('HTMLPurifier') || !class_exists('HTMLPurifier_Config')) {
            throw new \RuntimeException('HTMLPurifier 未安装或自动加载失败');
        }

        // 配置 HTMLPurifier
        $config = \HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', 'div,p,span,b,i,u,strong,em,a[href|title],img[src|alt|title],ul,ol,li,h1,h2,h3,h4,h5,h6,br,hr,blockquote,pre,code,table,tr,td,th,thead,tbody');
        $config->set('HTML.TargetBlank', true);
        $config->set('URI.SafeIframeRegexp', ''); // 禁止 iframe

        // 定义缓存目录（避免每次重新解析定义，提升性能�?
        $cacheDir = dirname(__DIR__, 2) . '/storage/cache/htmlpurifier';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        $config->set('Cache.SerializerPath', $cacheDir);

        // 初始化并净�?HTML
        $purifier = new \HTMLPurifier($config);
        return $purifier->purify($html);
    }
}
