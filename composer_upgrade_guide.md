# YYCMS Composer 升级指南

## 概述

本文档将指导您如何为 YYCMS 项目引入 Composer 并升级现有的第三方库，以提高项目的可维护性、安全性和性能。

## 当前项目状态

YYCMS 目前采用手动自动加载的方式，使用以下第三方库：
- **Medoo** (2.2.0) - 数据库框架
- **Latte** (3.1.1) - 模板引擎
- **HTMLPurifier** (4.19.0) - HTML 净化库

## 推荐的 Composer 包升级

### 1. 数据库相关

#### Medoo 替代方案
- **推荐**: 使用 `illuminate/database` (Laravel Database)
- **优势**: 更强大的查询构建器，更好的迁移系统
- **安装**: `composer require illuminate/database`

#### 原有 Medoo 升级
- **当前版本**: 2.2.0 (2024年发布)
- **建议**: 保持使用，但考虑迁移到 illuminate/database

### 2. 模板引擎

#### Latte 替代方案
- **推荐**: 使用 `twig/twig`
- **优势**: 更广泛的社区支持，更丰富的功能
- **安装**: `composer require twig/twig`

#### 原有 Latte 升级
- **当前版本**: 3.1.1 (2024年发布)
- **建议**: 保持使用，但考虑迁移到 Twig

### 3. HTML 净化

#### HTMLPurifier 升级
- **当前版本**: 4.19.0 (2024年发布)
- **建议**: 保持使用，这是目前最安全的 HTML 净化库

### 4. 新增推荐包

#### 安全相关
- **firebase/php-jwt**: JWT 认证 (如果需要)
- **vlucas/phpdotenv**: 环境变量管理 (项目已有，但可升级)
- **monolog/monolog**: 日志记录 (可替代当前日志系统)

#### 路由和HTTP相关
- **nikic/fast-route**: 高性能路由解析器 (可替代当前自定义路由)
- **symfony/http-foundation**: HTTP 基础设施 (Request/Response 处理)
- **symfony/routing**: 完整的路由系统
- **guzzlehttp/guzzle**: HTTP 客户端 (项目已有，可升级版本)

#### 缓存相关
- **cache/psr6-implementation**: PSR-6 缓存接口实现
- **cache/redis-adapter**: Redis 缓存支持
- **cache/filesystem-adapter**: 文件系统缓存
- **cache/tag-interop**: 缓存标签支持

#### 日志相关
- **monolog/monolog**: 日志记录 (项目已有，可升级)
- **psr/log**: 日志接口 (PSR-3 标准)
- **monolog/psr-log-matcher**: 日志匹配器

#### 数据验证
- **symfony/validator**: 强大的数据验证框架
- **respect/validation**: 灵活的验证库
- **illuminate/validation**: Laravel 验证组件

#### 文件处理
- **league/flysystem**: 文件系统抽象层
- **league/glide**: 图像处理和调整
- **spatie/image**: 图像处理 (如果需要)
- **guzzlehttp/psr7**: HTTP 消息实现

#### 搜索相关
- **algolia/algoliasearch-client-php**: Algolia 搜索引擎
- **meilisearch/meilisearch-php**: MeiliSearch 搜索引擎
- **sphinxsearch/sphinxsearch**: Sphinx 搜索引擎

#### 会话管理
- **symfony/http-foundation**: 会话处理 (如果需要)
- **cache/session-handler**: 会话缓存支持

#### 队列和任务
- **illuminate/queue**: 队列系统
- **symfony/lock**: 锁机制
- **predis/predis**: Redis 客户端

#### 其他工具
- **symfony/var-dumper**: 调试工具
- **symfony/console**: 命令行工具
- **symfony/process**: 进程执行
- **symfony/filesystem**: 文件系统操作
- **symfony/finder**: 文件查找
- **league/csv**: CSV 处理
- **vlucas/spreadsheet-reader**: 电子表格读取

## 实施步骤

### 步骤 1: 初始化 Composer

在项目根目录创建 `composer.json` 文件：

```json
{
    "name": "yycms/yycms",
    "description": "YYCMS Content Management System",
    "type": "project",
    "require": {
        "php": "^8.3",
        "illuminate/database": "^10.0",
        "twig/twig": "^3.0",
        "firebase/php-jwt": "^6.0",
        "vlucas/phpdotenv": "^5.0",
        "monolog/monolog": "^3.0",
        "guzzlehttp/guzzle": "^7.0",
        "nikic/fast-route": "^1.3",
        "symfony/http-foundation": "^6.0",
        "symfony/routing": "^6.0",
        "symfony/validator": "^6.0",
        "league/flysystem": "^3.0",
        "league/glide": "^2.0",
        "cache/psr6-implementation": "^2.0",
        "cache/filesystem-adapter": "^2.0",
        "psr/log": "^3.0",
        "symfony/var-dumper": "^6.0",
        "symfony/console": "^6.0",
        "symfony/filesystem": "^6.0",
        "symfony/finder": "^6.0",
        "league/csv": "^9.0",
        "vlucas/spreadsheet-reader": "^2.0",
        "respect/validation": "^2.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.0",
        "phpstan/phpstan": "^1.0",
        "algolia/algoliasearch-client-php": "^4.0"
    },
    "autoload": {
        "psr-4": {
            "App\\": "app/"
        }
    },
    "minimum-stability": "stable",
    "prefer-stable": true
}
```

### 步骤 2: 安装 Composer

```bash
# 下载 composer.phar
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
php -r "unlink('composer-setup.php');"

# 安装依赖
php composer.phar install
```

### 步骤 3: 更新自动加载

```bash
php composer.phar dump-autoload
```

### 步骤 4: 更新代码以使用 Composer 包

#### 4.1 数据库连接更新

将 `app/Core/Database.php` 中的 Medoo 替换为 Illuminate\Database：

```php
// 原来的 Medoo 代码
use Medoo\Medoo;

// 替换为
use Illuminate\Database\Capsule\Manager as Capsule;

// 在 __construct 中初始化
$capsule = new Capsule;
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => $config['host'],
    'database' => $config['database'],
    'username' => $config['username'],
    'password' => $config['password'],
    'charset' => $config['charset'],
    'collation' => $config['collation'],
    'prefix' => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();
```

#### 4.2 模板引擎更新

将 `app/Core/View.php` 中的 Latte 替换为 Twig：

```php
// 原来的 Latte 代码
use Latte\Engine;

// 替换为
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

// 初始化
$loader = new FilesystemLoader([
    APP_ROOT . '/views',
    APP_ROOT . '/views/admin',
    APP_ROOT . '/views/front'
]);
$twig = new Environment($loader, [
    'cache' => APP_ROOT . '/storage/cache/twig',
    'debug' => App::isDebug(),
    'auto_reload' => true,
]);
```

#### 4.3 路由系统更新

将 `app/Http/Router.php` 替换为 Symfony Routing：

```php
// 原来的自定义路由代码
use App\Http\Router;

// 替换为
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;

// 在 App::run() 中初始化
$routes = new RouteCollection();
// 添加路由...
$context = new RequestContext();
$matcher = new UrlMatcher($routes, $context);
```

#### 4.4 缓存系统更新

将 `app/Services/CacheService.php` 替换为 PSR-6 标准：

```php
// 原来的自定义缓存代码
use App\Services\CacheService;

// 替换为
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Adapter;

// 初始化
$cache = new Psr16Adapter(
    new FilesystemAdapter('yycms', 0, APP_ROOT . '/storage/cache')
);
```

#### 4.5 日志系统更新

将 `app/Services/LogService.php` 替换为 Monolog：

```php
// 原来的自定义日志代码
use App\Services\LogService;

// 替换为
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;

// 初始化
$log = new Logger('yycms');
$log->pushHandler(new RotatingFileHandler(
    APP_ROOT . '/storage/logs/app.log',
    0,
    Logger::DEBUG
));
```

#### 4.6 验证系统更新

将 `app/Services/ValidationService.php` 替换为 Symfony Validator：

```php
// 原来的自定义验证代码
use App\Services\ValidationService;

// 替换为
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints as Assert;

// 初始化验证器
$validator = Validation::createValidatorBuilder()
    ->enableAnnotationMapping()
    ->getValidator();
```

#### 4.7 文件上传更新

将 `app/Services/FileService.php` 替换为 Flysystem：

```php
// 原来的自定义文件服务代码
use App\Services\FileService;

// 替换为
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

// 初始化
$adapter = new LocalFilesystemAdapter(APP_ROOT . '/public/uploads');
$filesystem = new Filesystem($adapter);
```

#### 4.8 搜索服务更新

将 `app/Services/SearchService.php` 替换为 Algolia 或 MeiliSearch：

```php
// 原来的自定义搜索代码
use App\Services\SearchService;

// 替换为
use Algolia\Algoliasearch\Client as AlgoliaClient;
use Algolia\Algoliasearch\Index as AlgoliaIndex;

// 初始化
$client = new AlgoliaClient('APP_ID', 'API_KEY');
$index = $client->initIndex('yycms_index');
```

### 步骤 5: 更新 Autoloader

修改 `app/Core/Autoloader.php` 以兼容 Composer：

```php
// 在 __construct 中添加
if (file_exists(APP_ROOT . '/vendor/autoload.php')) {
    require_once APP_ROOT . '/vendor/autoload.php';
}
```

### 步骤 6: 更新环境变量管理

将 `.env` 文件的处理从手动替换改为使用 vlucas/phpdotenv：

```php
// 在 config.php 开头添加
$dotenv = Dotenv\Dotenv::createImmutable(APP_ROOT);
$dotenv->load();

// 使用 $_ENV 或 $_SERVER 获取变量
```

## 安全性改进

引入 Composer 后，可以通过以下方式提高安全性：

1. **自动更新**: 使用 `composer update` 定期更新依赖
2. **安全扫描**: 使用 `composer audit` 检查已知安全漏洞
3. **依赖锁定**: 使用 `composer.lock` 确保生产环境依赖一致

## 性能优化

1. **OPcache**: 确保 PHP OPcache 启用
2. **Composer 优化**: 使用 `composer install --optimize-autoloader` 优化自动加载
3. **缓存**: 启用 Twig 模板缓存

## 迁移检查清单

- [ ] 创建 `composer.json` 文件
- [ ] 运行 `composer install`
- [ ] 更新数据库连接代码
- [ ] 更新模板引擎代码
- [ ] 更新环境变量处理
- [ ] 更新自动加载机制
- [ ] 测试所有功能
- [ ] 运行安全扫描
- [ ] 优化自动加载
- [ ] 更新部署脚本

## 风险评估

1. **兼容性**: 某些自定义代码可能需要调整
2. **学习曲线**: 团队需要了解 Composer 和新包的使用
3. **测试**: 需要充分测试确保迁移成功

## 后续维护

1. **定期更新**: 每月运行 `composer update`
2. **安全检查**: 每月运行 `composer audit`
3. **依赖审查**: 定期审查不需要的依赖

## 结论

通过引入 Composer，YYCMS 项目将获得更好的依赖管理、安全性、性能和可维护性。建议按照上述步骤逐步迁移，确保系统稳定运行。