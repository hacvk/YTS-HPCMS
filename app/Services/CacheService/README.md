# 缓存服务使用说明

## 概述

`CacheService` 提供简单的文件缓存功能，用于存储统计数据、查询结果等，避免频繁查询数据库。

## 功能特性

- **文件缓存**：使用文件系统存储缓存数据
- **自动过期**：支持设置缓存过期时间（TTL）
- **并发安全**：使用文件锁确保并发写入安全
- **简单易用**：提供 `remember()` 方法，自动处理缓存逻辑

## 使用示例

### 基本用法

```php
use App\Service\CacheService;

$cache = new CacheService();

// 设置缓存（10 分钟过期）
$cache->set('key', 'value', 600);

// 获取缓存
$value = $cache->get('key', 'default');

// 检查缓存是否存在
if ($cache->has('key')) {
    // 缓存存在且未过期
}

// 删除缓存
$cache->delete('key');
```

### 记住缓存（推荐用法）

```php
use App\Service\CacheService;

$cache = new CacheService();

// 如果缓存存在则返回，否则执行回调并缓存结果
$stats = $cache->remember(
    'dashboard:stats',
    function() use ($db) {
        // 这里执行耗时的数据库查询
        return $db->count('sys_log');
    },
    600 // 缓存 10 分钟
);
```

### Dashboard 统计缓存示例

```php
// 文章总数（缓存 30 分钟）
$articles = $cache->remember(
    'dashboard:stats:articles',
    fn() => $db->count('sys_content'),
    1800 // 30 分钟
);

// 今日登录次数（缓存 10 分钟，需要相对实时）
$todayLogins = $cache->remember(
    'dashboard:stats:today_logins',
    fn() => $db->count('sys_log', ['action' => 'login', ...]),
    600 // 10 分钟
);
```

## 缓存过期时间建议

- **统计数据**（文章数、栏目数等）：30 分钟
- **实时数据**（今日登录次数等）：10 分钟
- **静态数据**（系统运行天数等）：60 分钟

## 清空缓存

```php
// 清空所有缓存
$count = $cache->clear();
```

## 扩展为 Redis 缓存

如需使用 Redis 缓存，可以实现 `CacheInterface` 接口：

```php
class RedisCacheService implements CacheInterface
{
    public function get(string $key, mixed $default = null): mixed { }
    public function set(string $key, mixed $value, int $ttl = 600): bool { }
    public function delete(string $key): bool { }
    // ...
}
```

然后在 `DashboardController` 中注入对应的缓存服务。
