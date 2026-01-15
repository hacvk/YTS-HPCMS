<?php
declare(strict_types=1);

namespace App\Service;

use App\Core\Database;

/**
 * 日志服务类
 */
class LogService
{
    /**
     * 日志表名
     */
    private const TABLE_NAME = 'sys_log';

    /**
     * 日志级别常量
     */
    public const LEVEL_INFO = 'info';
    public const LEVEL_SUCCESS = 'success';
    public const LEVEL_ERROR = 'error';
    public const LEVEL_DEBUG = 'debug';
    public const LEVEL_WARNING = 'warning';

    /**
     * 记录日志
     *
     * @param int $adminId 管理员ID
     * @param string $username 用户名
     * @param string $action 操作类型（如: login, logout, create, update, delete）
     * @param string|null $target 操作目标（如: 用户名、ID、具体内容等）
     * @param string|null $message 自定义消息（默认根据操作类型生成）
     * @param string $level 日志级别（默认: info）
     * @param string|null $exceptionTrace 异常堆栈追踪
     * @param string|null $file 错误文件路径
     * @param int|null $line 错误行号
     * @return bool 是否记录成功
     */
    public static function record(
        int $adminId,
        string $username,
        string $action,
        ?string $target = null,
        ?string $message = null,
        string $level = self::LEVEL_INFO,
        ?string $exceptionTrace = null,
        ?string $file = null,
        ?int $line = null
    ): bool {
        try {
            $db = Database::get();

            // 如果没有提供消息，则根据操作类型和目标自动生成
            if ($message === null) {
                $message = self::generateMessage($action, $target);
            }

            // 获取客户端IP和User-Agent
            $ip = self::getClientIp();
            $userAgent = self::getUserAgent();

            // 插入日志
            $result = $db->insert(self::TABLE_NAME, [
                'admin_id' => $adminId,
                'admin_name' => $username,
                'action' => $action,
                'level' => $level,
                'message' => $message,
                'target' => $target,
                'exception_trace' => $exceptionTrace,
                'file' => $file,
                'line' => $line,
                'ip' => $ip,
                'user_agent' => $userAgent,
                'create_time' => date('Y-m-d H:i:s'),
            ]);

            return $result !== false;
        } catch (\Exception $e) {
            // 日志记录失败不应影响主流程，记录到错误日志
            error_log('[LogService] 日志记录失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 记录错误日志（自动从异常对象提取信息）
     *
     * @param \Throwable $e 异常对象
     * @param string $action 操作类型（默认: error）
     * @param int|null $adminId 管理员ID（如果为null，则尝试从Session获取）
     * @param string|null $username 用户名（如果为null，则尝试从Session获取）
     * @return bool 是否记录成功
     */
    public static function error(\Throwable $e, string $action = 'error', ?int $adminId = null, ?string $username = null): bool
    {
        // 如果没有提供管理员信息，尝试从Session获取
        if ($adminId === null || $username === null) {
            $sessionInfo = self::getAdminInfoFromSession();
            $adminId = $adminId ?? $sessionInfo['admin_id'];
            $username = $username ?? $sessionInfo['username'];
        }

        $message = $e->getMessage();
        $file = $e->getFile();
        $line = $e->getLine();
        $trace = $e->getTraceAsString();

        return self::record(
            $adminId ?? 0,
            $username ?? 'system',
            $action,
            $file . ':' . $line,
            $message,
            self::LEVEL_ERROR,
            $trace,
            $file,
            $line
        );
    }

    /**
     * 记录调试日志
     *
     * @param string $message 调试消息
     * @param array<string, mixed> $context 上下文数据（数组或对象）
     * @param int|null $adminId 管理员ID（如果为null，则尝试从Session获取）
     * @param string|null $username 用户名（如果为null，则尝试从Session获取）
     * @return bool 是否记录成功
     */
    public static function debug(string $message, array $context = [], ?int $adminId = null, ?string $username = null): bool
    {
        // 如果没有提供管理员信息，尝试从Session获取
        if ($adminId === null || $username === null) {
            $sessionInfo = self::getAdminInfoFromSession();
            $adminId = $adminId ?? $sessionInfo['admin_id'];
            $username = $username ?? $sessionInfo['username'];
        }

        // 将上下文数据转换为字符串
        $contextStr = '';
        if (!empty($context)) {
            $contextStr = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        $fullMessage = $message;
        if ($contextStr) {
            $fullMessage .= "\n上下文数据:\n" . $contextStr;
        }

        return self::record(
            $adminId ?? 0,
            $username ?? 'system',
            'debug',
            null,
            $fullMessage,
            self::LEVEL_DEBUG
        );
    }

    /**
     * 记录新建操作日志
     *
     * @param string $target 操作目标（如: "栏目: 通知公告"）
     * @param string $detail 详细信息（如: "ID: 1"）
     * @param int|null $adminId 管理员ID（如果为null，则尝试从Session获取）
     * @param string|null $username 用户名（如果为null，则尝试从Session获取）
     * @return bool 是否记录成功
     */
    public static function created(string $target, string $detail = '', ?int $adminId = null, ?string $username = null): bool
    {
        // 如果没有提供管理员信息，尝试从Session获取
        if ($adminId === null || $username === null) {
            $sessionInfo = self::getAdminInfoFromSession();
            $adminId = $adminId ?? $sessionInfo['admin_id'];
            $username = $username ?? $sessionInfo['username'];
        }

        $message = "创建: {$target}";
        if ($detail) {
            $message .= " ({$detail})";
        }

        return self::record(
            $adminId ?? 0,
            $username ?? 'system',
            'create',
            $target,
            $message,
            self::LEVEL_SUCCESS
        );
    }

    /**
     * 记录编辑操作日志
     *
     * @param string $target 操作目标（如: "栏目: 通知公告"）
     * @param string $detail 详细信息（如: "ID: 1"）
     * @param int|null $adminId 管理员ID（如果为null，则尝试从Session获取）
     * @param string|null $username 用户名（如果为null，则尝试从Session获取）
     * @return bool 是否记录成功
     */
    public static function updated(string $target, string $detail = '', ?int $adminId = null, ?string $username = null): bool
    {
        // 如果没有提供管理员信息，尝试从Session获取
        if ($adminId === null || $username === null) {
            $sessionInfo = self::getAdminInfoFromSession();
            $adminId = $adminId ?? $sessionInfo['admin_id'];
            $username = $username ?? $sessionInfo['username'];
        }

        $message = "更新: {$target}";
        if ($detail) {
            $message .= " ({$detail})";
        }

        return self::record(
            $adminId ?? 0,
            $username ?? 'system',
            'update',
            $target,
            $message,
            self::LEVEL_SUCCESS
        );
    }

    /**
     * 记录删除操作日志
     *
     * @param string $target 操作目标（如: "栏目: 通知公告"）
     * @param string $detail 详细信息（如: "ID: 1"）
     * @param int|null $adminId 管理员ID（如果为null，则尝试从Session获取）
     * @param string|null $username 用户名（如果为null，则尝试从Session获取）
     * @return bool 是否记录成功
     */
    public static function deleted(string $target, string $detail = '', ?int $adminId = null, ?string $username = null): bool
    {
        // 如果没有提供管理员信息，尝试从Session获取
        if ($adminId === null || $username === null) {
            $sessionInfo = self::getAdminInfoFromSession();
            $adminId = $adminId ?? $sessionInfo['admin_id'];
            $username = $username ?? $sessionInfo['username'];
        }

        $message = "删除: {$target}";
        if ($detail) {
            $message .= " ({$detail})";
        }

        return self::record(
            $adminId ?? 0,
            $username ?? 'system',
            'delete',
            $target,
            $message,
            self::LEVEL_WARNING
        );
    }

    /**
     * 从Session获取管理员信息
     *
     * @return array{admin_id: int, username: string}
     */
    private static function getAdminInfoFromSession(): array
    {
        // 确保Session已启动
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        return [
            'admin_id' => (int)($_SESSION['admin_id'] ?? 0),
            'username' => $_SESSION['admin_username'] ?? 'system',
        ];
    }

    /**
     * 生成日志消息
     *
     * @param string $action 操作类型
     * @param string|null $target 操作目标
     * @return string
     */
    private static function generateMessage(string $action, ?string $target = null): string
    {
        $messages = [
            'login' => '登录成功',
            'logout' => '退出登录',
            'create' => $target ? "创建: {$target}" : '创建记录',
            'update' => $target ? "更新: {$target}" : '更新记录',
            'delete' => $target ? "删除: {$target}" : '删除记录',
            'view' => $target ? "查看: {$target}" : '查看记录',
            'export' => $target ? "导出: {$target}" : '导出数据',
            'import' => $target ? "导入: {$target}" : '导入数据',
            'upload' => $target ? "上传: {$target}" : '上传文件',
            'download' => $target ? "下载: {$target}" : '下载文件',
            'approve' => $target ? "审核: {$target}" : '审核记录',
            'reject' => $target ? "拒绝: {$target}" : '拒绝请求',
            'recover' => $target ? "恢复: {$target}" : '恢复数据',
            'backup' => '备份数据',
            'restore' => '恢复数据',
            'config' => '修改配置',
            'password' => '修改密码',
        ];

        return $messages[$action] ?? "执行操作: {$action}";
    }

    /**
     * 获取客户端IP地址
     *
     * @return string
     */
    private static function getClientIp(): string
    {
        $ip = '';

        // 检查是否通过代理
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // X-Forwarded-For 可能包含多个IP，取第一个
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (isset($_SERVER['HTTP_CLIENT_IP']) && !empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_REAL_IP']) && !empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        // 验证IP格式
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $ip;
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * 获取 User-Agent
     *
     * @return string
     */
    private static function getUserAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    /**
     * 获取管理员日志列表
     *
     * @param int $adminId 管理员ID
     * @param int $limit 限制数量
     * @param int $offset 偏移量
     * @return array<array<string, mixed>>
     */
    public static function getAdminLogs(int $adminId, int $limit = 50, int $offset = 0): array
    {
        try {
            $db = Database::get();

            return $db->select(self::TABLE_NAME, '*', [
                'admin_id' => $adminId,
                'ORDER' => ['id' => 'DESC'],
                'LIMIT' => [$offset, $limit],
            ]);
        } catch (\Exception $e) {
            error_log('[LogService] 获取日志失败: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取所有日志列表
     *
     * @param array<string, mixed> $conditions 查询条件
     * @param int $limit 限制数量
     * @param int $offset 偏移量
     * @return array<array<string, mixed>>
     */
    public static function getLogs(array $conditions = [], int $limit = 50, int $offset = 0): array
    {
        try {
            $db = Database::get();

            $where = array_merge($conditions, [
                'ORDER' => ['id' => 'DESC'],
                'LIMIT' => [$offset, $limit],
            ]);

            return $db->select(self::TABLE_NAME, '*', $where);
        } catch (\Exception $e) {
            error_log('[LogService] 获取日志失败: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取日志总数
     *
     * @param array<string, mixed> $conditions 查询条件
     * @return int
     */
    public static function getLogsCount(array $conditions = []): int
    {
        try {
            $db = Database::get();

            return $db->count(self::TABLE_NAME, $conditions);
        } catch (\Exception $e) {
            error_log('[LogService] 获取日志总数失败: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * 清理过期日志
     *
     * @param int $days 保留天数（默认30天）
     * @return int 删除的记录数
     */
    public static function cleanExpiredLogs(int $days = 30): int
    {
        try {
            $db = Database::get();

            $expireDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

            return $db->delete(self::TABLE_NAME, [
                'create_time[<]' => $expireDate,
            ]);
        } catch (\Exception $e) {
            error_log('[LogService] 清理过期日志失败: ' . $e->getMessage());
            return 0;
        }
    }
}
