<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Services\Log\DatabaseLogDriver;
use App\Services\Log\FileLogDriver;
use App\Services\Log\LogDriverInterface;
use App\Services\Log\MonologDriver;

/**
 * 日志服务类
 * 支持多种日志驱动：文件、数据库、Redis等
 */
class LogService
{
    /**
     * 日志表名（用于数据库驱动）
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
    public const LEVEL_CRITICAL = 'critical';

    /**
     * 日志驱动实例（单例）
     */
    private static ?LogDriverInterface $driver = null;

    /**
     * 获取日志驱动实例
     *
     * @return LogDriverInterface
     */
    private static function getDriver(): LogDriverInterface
    {
        if (self::$driver === null) {
            // 从配置读取日志驱动类型，默认使用文件驱动
            $driverType = \App\Core\App::getConfig('log.driver', 'file');
            
            self::$driver = match ($driverType) {
                'file' => new FileLogDriver(
                    \App\Core\App::getConfig('log.file.dir', ''),
                    \App\Core\App::getConfig('log.file.format', 'app-{date}.log')
                ),
                'monolog' => new MonologDriver(
                    \App\Core\App::getConfig('log.file.dir', ''),
                    \App\Core\App::getConfig('log.file.format', 'app-{date}.log')
                ),
                'database', 'db' => new DatabaseLogDriver(),
                default => new FileLogDriver(), // 默认使用文件驱动
            };
        }
        
        return self::$driver;
    }

    /**
     * 设置日志驱动（用于测试或动态切换）
     *
     * @param LogDriverInterface $driver 日志驱动实例
     * @return void
     */
    public static function setDriver(LogDriverInterface $driver): void
    {
        self::$driver = $driver;
    }

    /**
     * 记录日志
     *
     * @param int $adminId 管理员ID
     * @param string $username 用户名
     * @param string $action 操作类型（如: login, logout, create, update, delete等）
     * @param string|null $target 操作目标（如: 用户名、ID、具体内容等）
     * @param string|null $message 自定义消息（默认根据操作类型生成）
     * @param string $level 日志级别（默认 info）
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
            // 验证 action 参数不能为空
            if (empty($action)) {
                $action = 'unknown';
            }
                
            // 如果没有提供消息，则根据操作类型和目标自动生成?
            if ($message === null) {
                $message = self::generateMessage($action, $target);
            }
    
            // 获取客户端IP和User-Agent
            $ip = self::getClientIp();
            $userAgent = self::getUserAgent();

            // 构建日志数据
            $logData = [
                'admin_id' => $adminId,
                'admin_name' => $username,
                'username' => $username,  // 保持与数据库表结构一致
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
            ];

            // 使用配置的日志驱动记录日志（异步写入，不阻塞主流程）
            return self::getDriver()->record($logData);
        } catch (\Exception $e) {
            // 日志记录失败不应影响主流程，记录到错误日?
            error_log('[LogService] 日志记录失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 记录错误日志（自动从异常对象提取信息）
     *
     * @param \Throwable $e 异常对象
     * @param string $action 操作类型（默认 error）
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
            $fullMessage .= "\n上下文数?\n" . $contextStr;
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
            'create',  // 确保 action 不为空
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
            'update',  // 确保 action 不为空
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
            'delete',  // 确保 action 不为空
            $target,
            $message,
            self::LEVEL_WARNING
        );
    }
    
    /**
     * 记录严重错误日志
     *
     * @param string $message 错误消息
     * @param array<string, mixed> $context 上下文数据
     * @param int|null $adminId 管理员ID（如果为null，则尝试从Session获取）
     * @param string|null $username 用户名（如果为null，则尝试从Session获取）
     * @return bool 是否记录成功
     */
    public static function critical(string $message, array $context = [], ?int $adminId = null, ?string $username = null): bool
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
            $fullMessage .= "\n上下文数据\n" . $contextStr;
        }

        return self::record(
            $adminId ?? 0,
            $username ?? 'system',
            'critical',  // 确保 action 不为空
            null,
            $fullMessage,
            self::LEVEL_CRITICAL
        );
    }

    /**
     * 从Session获取管理员信息
     *
     * @return array{admin_id: int, username: string}
     */
    private static function getAdminInfoFromSession(): array
    {
        // 确保Session已启?
        if (session_status() === PHP_SESSION_NONE) {
            $savePath = ini_get('session.save_path');
            $path = $savePath;
            if (is_string($savePath) && strpos($savePath, ';') !== false) {
                $parts = explode(';', $savePath);
                $path = $parts[0] ?? '';
            }
            if (empty($path)) {
                $path = sys_get_temp_dir();
            }
            if (!empty($path) && !is_dir($path)) {
                @mkdir($path, 0777, true);
            }
            if (!empty($path) && is_dir($path) && is_writable($path)) {
                session_save_path($path);
            } else {
                session_save_path(sys_get_temp_dir());
            }
            @session_start();
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
            // X-Forwarded-For 可能包含多个IP，取第一?
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
     * 注意：此方法仅在使用数据库驱动时有效
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
     * 注意：此方法仅在使用数据库驱动时有效
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
     * 从日志文件读取操作日志（Monolog 格式）
     *
     * @param array<string, mixed> $conditions
     * @param int $limit
     * @param int $offset
     * @param string $startDate
     * @param string $endDate
     * @return array<int, array<string, mixed>>
     */
    public static function getFileLogs(array $conditions = [], int $limit = 50, int $offset = 0, string $startDate = '', string $endDate = ''): array
    {
        $entries = self::collectFileLogs($conditions, $startDate, $endDate);
        if (empty($entries)) {
            return [];
        }

        return array_slice($entries, $offset, $limit);
    }

    /**
     * 获取日志文件中的记录总数（Monolog 格式）
     *
     * @param array<string, mixed> $conditions
     * @param string $startDate
     * @param string $endDate
     * @return int
     */
    public static function getFileLogsCount(array $conditions = [], string $startDate = '', string $endDate = ''): int
    {
        $entries = self::collectFileLogs($conditions, $startDate, $endDate);
        return count($entries);
    }

    /**
     * 获取日志总数
     * 注意：此方法仅在使用数据库驱动时有效
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
     * 读取并过滤日志文件
     *
     * @param array<string, mixed> $conditions
     * @param string $startDate
     * @param string $endDate
     * @return array<int, array<string, mixed>>
     */
    private static function collectFileLogs(array $conditions, string $startDate, string $endDate): array
    {
        $logDir = \App\Core\App::getConfig('log.file.dir', '');
        if ($logDir === '') {
            $logDir = dirname(__DIR__, 2) . '/storage/logs';
        }
        $format = \App\Core\App::getConfig('log.file.format', 'app-{date}.log');
        $pattern = str_replace('{date}', '*', $format);
        $files = glob($logDir . '/' . $pattern) ?: [];
        if (empty($files)) {
            return [];
        }

        $startTs = $startDate !== '' ? strtotime($startDate . ' 00:00:00') : null;
        $endTs = $endDate !== '' ? strtotime($endDate . ' 23:59:59') : null;

        $files = array_values(array_filter($files, function (string $file) use ($startTs, $endTs): bool {
            if ($startTs === null && $endTs === null) {
                return true;
            }
            if (!preg_match('/\d{4}-\d{2}-\d{2}/', basename($file), $m)) {
                return true;
            }
            $fileTs = strtotime($m[0] . ' 00:00:00');
            if ($startTs !== null && $fileTs < $startTs) {
                return false;
            }
            if ($endTs !== null && $fileTs > $endTs) {
                return false;
            }
            return true;
        }));

        rsort($files);

        $actionFilter = (string)($conditions['action'] ?? '');
        $adminIdFilter = (int)($conditions['admin_id'] ?? 0);

        $entries = [];
        foreach ($files as $file) {
            $handle = fopen($file, 'r');
            if ($handle === false) {
                continue;
            }
            while (($line = fgets($handle)) !== false) {
                $parsed = self::parseMonologLine($line);
                if ($parsed === null) {
                    continue;
                }
                $timestamp = $parsed['timestamp'];

                if ($startTs !== null && $timestamp < $startTs) {
                    continue;
                }
                if ($endTs !== null && $timestamp > $endTs) {
                    continue;
                }

                $context = $parsed['context'];
                $action = (string)($context['action'] ?? '');
                $adminId = (int)($context['admin_id'] ?? 0);

                if ($actionFilter !== '' && $action !== $actionFilter) {
                    continue;
                }
                if ($adminIdFilter > 0 && $adminId !== $adminIdFilter) {
                    continue;
                }

                $entries[] = [
                    'timestamp' => $timestamp,
                    'admin_id' => $adminId,
                    'admin_name' => (string)($context['admin_name'] ?? $context['username'] ?? 'system'),
                    'action' => $action,
                    'message' => (string)($context['message'] ?? $parsed['message']),
                    'target' => $context['target'] ?? '',
                    'ip' => (string)($context['ip'] ?? ''),
                    'create_time' => $parsed['create_time'],
                ];
            }
            fclose($handle);
        }

        usort($entries, function (array $a, array $b): int {
            return $b['timestamp'] <=> $a['timestamp'];
        });

        $total = count($entries);
        foreach ($entries as $index => &$entry) {
            $entry['id'] = $total - $index;
        }
        unset($entry);

        return $entries;
    }

    /**
     * 解析 Monolog 日志行
     *
     * @param string $line
     * @return array<string, mixed>|null
     */
    private static function parseMonologLine(string $line): ?array
    {
        $line = trim($line);
        if ($line === '') {
            return null;
        }

        $timeEnd = strpos($line, '] ');
        if ($timeEnd === false || $line[0] !== '[') {
            return null;
        }

        $timeStr = substr($line, 1, $timeEnd - 1);
        try {
            $dt = new \DateTime($timeStr);
        } catch (\Exception $e) {
            return null;
        }

        $rest = substr($line, $timeEnd + 2);
        $contextPos = strrpos($rest, ' {');
        $extraPos = strrpos($rest, ' [');
        if ($contextPos === false || $extraPos === false || $extraPos < $contextPos) {
            return null;
        }

        $messagePart = substr($rest, 0, $contextPos);
        $contextJson = substr($rest, $contextPos + 1, $extraPos - ($contextPos + 1));

        $colonPos = strpos($messagePart, ': ');
        if ($colonPos === false) {
            return null;
        }

        $left = substr($messagePart, 0, $colonPos);
        $message = substr($messagePart, $colonPos + 2);

        $dotPos = strrpos($left, '.');
        $level = $dotPos !== false ? substr($left, $dotPos + 1) : '';

        $context = json_decode($contextJson, true);
        if (!is_array($context)) {
            $context = [];
        }

        return [
            'timestamp' => $dt->getTimestamp(),
            'create_time' => $dt->format('Y-m-d H:i:s'),
            'level' => strtolower($level),
            'message' => $message,
            'context' => $context,
        ];
    }

    /**
     * 清理过期日志
     * 注意：此方法仅在使用数据库驱动时有效
     *
     * @param int $days 保留天数（默认30天）
     * @return int 删除的记录数
     */
    public static function cleanExpiredLogs(int $days = 30): int
    {
        try {
            $db = Database::get();

            $expireDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

            $result = $db->delete(self::TABLE_NAME, [
                'create_time[<]' => $expireDate,
            ]);
            
            // 检查删除操作是否成功并返回受影响的行数
            if ($result !== null) {
                return $result->rowCount();
            }
            
            return 0;
        } catch (\Exception $e) {
            error_log('[LogService] 清理过期日志失败: ' . $e->getMessage());
            return 0;
        }
    }
}





