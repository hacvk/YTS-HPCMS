<?php
declare(strict_types=1);

namespace App\Core;

use Medoo\Medoo;

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
     * 私有构造函数（防止外部实例化）
     */
    private function __construct()
    {
    }

    /**
     * 获取数据库实例（单例）
     *
     * @return Medoo
     * @throws DatabaseException
     */
    public static function get(): Medoo
    {
        if (self::$instance === null) {
            self::$instance = self::createConnection();
        }

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
                ],
            ]);

            // 🔽 修改点：使用原生 SQL 进行简单的连接测试，代替原来的 information_schema 查询
            $medoo->query("SELECT 1");

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