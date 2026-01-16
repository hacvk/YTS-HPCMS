# 日志驱动系统

## 概述

日志服务已重构为支持多种驱动，避免在高并发场景下同步写入数据库导致的性能问题。

## 支持的驱动

### 1. FileLogDriver（文件驱动，推荐用于生产环境）

- **优点**：异步写入，不阻塞主流程，性能高
- **缺点**：需要定期清理日志文件
- **适用场景**：高并发生产环境

### 2. DatabaseLogDriver（数据库驱动）

- **优点**：便于查询和管理
- **缺点**：在高并发下可能锁表或占用连接数
- **适用场景**：低并发环境或需要实时查询日志的场景

## 配置方法

在应用初始化时（如 `index.php` 或 `bootstrap.php`）设置配置：

```php
use App\Core\App;

// 配置日志驱动为文件
App::setConfig('log.driver', 'file');
App::setConfig('log.file.dir', '/path/to/logs'); // 可选，默认：data/logs
App::setConfig('log.file.format', 'app-{date}.log'); // 可选，默认：app-{date}.log

// 或配置为数据库驱动
App::setConfig('log.driver', 'database');
```

## 使用示例

```php
use App\Service\LogService;

// 记录日志（自动使用配置的驱动）
LogService::record(
    adminId: 1,
    username: 'admin',
    action: 'create',
    target: '文章',
    message: '创建文章成功',
    level: LogService::LEVEL_SUCCESS
);

// 快捷方法
LogService::created('文章', 'ID: 123');
LogService::updated('文章', 'ID: 123');
LogService::deleted('文章', 'ID: 123');
LogService::error($exception);
```

## 日志文件格式

文件驱动生成的日志格式：

```
[2024-01-01 12:00:00] SUCCESS: 创建文章成功 {"admin_id":1,"admin_name":"admin","action":"create","target":"文章","ip":"127.0.0.1","user_agent":"..."}
```

## 性能优化建议

1. **生产环境**：使用 `file` 驱动
2. **开发环境**：可以使用 `database` 驱动便于调试
3. **定期清理**：使用文件驱动时，建议设置定时任务清理过期日志文件

## 扩展驱动

如需添加新的驱动（如 Redis、Monolog），实现 `LogDriverInterface` 接口：

```php
use App\Service\Log\LogDriverInterface;

class RedisLogDriver implements LogDriverInterface
{
    public function record(array $logData): bool
    {
        // 实现日志记录逻辑
    }

    public function recordBatch(array $logDataList): int
    {
        // 实现批量日志记录逻辑
    }
}
```

然后在 `LogService::getDriver()` 方法中添加对应的 case。
