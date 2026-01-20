<?php
declare(strict_types=1);

namespace App\Core;

use Medoo\Medoo;
use App\Services\LogService;

/**
 * 数据库连接异常
 */
class DatabaseException extends \Exception
{
}

/**
 * 数据库类（单例模式）
 */
class Database
{
    /**
     * Medoo 实例
     *
     * @var Medoo|null
     */
    private static ?Medoo $instance = null;
    
    /**
     * 慢查询阈值（毫秒）
     */
    public const SLOW_QUERY_THRESHOLD = 100;

    /**
     * 私有构造函数（防止外部实例化）
     */
    private function __construct()
    {
    }

    /**
     * 获取数据库实例（单例模式）
     * 如果连接已断开，自动重新连接
     *
     * @return Medoo
     * @throws DatabaseException
     */
    public static function get(): Medoo
    {
        if (self::$instance === null) {
            self::$instance = self::createConnection();
        } else {
            // 检查连接是否有效（仅在长运行脚本中需要）
            // 对于 Web 请求，连接通常不会超时，所以这里不主动检查
            // 如果连接已断开，PDO 会在执行查询时抛出异常，此时可以调用 reconnect()
        }

        return self::$instance;
    }
    
    /**
     * 记录慢查询
     *
     * @param string $sql SQL语句
     * @param array $params 参数
     * @param float $duration 执行时间（毫秒）
     * @return void
     */
    public static function logSlowQuery(string $sql, array $params, float $duration): void
    {
        if ($duration >= self::SLOW_QUERY_THRESHOLD) {
            try {
                $db = self::get();
                $db->insert('slow_queries', [
                    'request_id' => $_SERVER['REQUEST_ID'] ?? uniqid('req_'),
                    'sql' => $sql,
                    'params' => json_encode($params),
                    'duration' => round($duration, 2),
                    'timestamp' => time(),
                ]);
            } catch (\Exception $e) {
                LogService::error($e, 'slow_query_log_failed');
            }
        }
    }

    /**
     * 检查数据库连接是否有效
     * 用于在长运行脚本中主动检测连接状态
     *
     * @return bool
     */
    public static function isConnected(): bool
    {
        if (self::$instance === null) {
            return false;
        }

        try {
            // 尝试执行一个简单的查询来检测连接
            self::$instance->query("SELECT 1");
            return true;
        } catch (\Exception $e) {
            // 连接已断开
            return false;
        }
    }

    /**
     * 重新连接数据库
     * 用于处理长运行脚本中 MySQL 连接超时（MySQL has gone away）的情况
     *
     * @return Medoo
     * @throws DatabaseException
     */
    public static function reconnect(): Medoo
    {
        // 关闭旧连接
        self::$instance = null;
        
        // 创建新连接
        self::$instance = self::createConnection();
        
        return self::$instance;
    }

    /**
     * 创建数据库连接
     *
     * @return Medoo
     * @throws DatabaseException
     */
    private static function createConnection(): Medoo
    {
        $config = App::getConfig('db');

        if ($config === null) {
            throw new DatabaseException('数据库配置不存在，请检查 config.php');
        }

        $host = $config['host'] ?? '';
        $database = $config['database'] ?? '';
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';
        $charset = $config['charset'] ?? 'utf8mb4';
        $collation = $config['collation'] ?? 'utf8mb4_unicode_ci';

        try {
            $medoo = new Medoo([
                'type' => 'mysql',
                'host' => $host,
                'database' => $database,
                'username' => $username,
                'password' => $password,
                'charset' => $charset,
                'collation' => $collation,
                'option' => [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    // 设置 PDO 连接超时，避免长时间等待
                    \PDO::ATTR_TIMEOUT => 5,
                ],
            ]);

            // 移除 SELECT 1 检测，依靠 PDO 的懒加载特性
            // 连接会在真正执行查询时建立，如果连接失败，PDO 会抛出异常
            // 这样可以减少一次不必要的网络交互，提高性能

            return $medoo;
        } catch (\PDOException $e) {
            // 构建安全的错误信息（不包含密码）
            $errorInfo = $e->errorInfo ?? [];
            $safeMessage = sprintf(
                '数据库连接失败: %s',
                $errorInfo[2] ?? $e->getMessage()
            );

            throw new DatabaseException($safeMessage, (int)$e->getCode(), $e);
        } catch (\Exception $e) {
            throw new DatabaseException('数据库初始化失败: ' . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * 防止克隆
     */
    private function __clone()
    {
    }

    /**
     * 防止序列化
     *
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        throw new DatabaseException('数据库实例不能被序列化');
    }

    /**
     * 防止反序列化
     *
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        throw new DatabaseException('数据库实例不能被反序列化');
    }
}
