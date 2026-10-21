# YTS-HPCMS

[![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?logo=php&logoColor=white)](https://www.php.net/releases/8_2/en.php)
[![MySQL](https://img.shields.io/badge/MySQL-8.0%2B-4479A1?logo=mysql&logoColor=white)](https://dev.mysql.com/)
[![Version](https://img.shields.io/badge/version-1.9.1-brightgreen)](config/system.php)

轻量级内容管理与门户系统，面向医疗行业站点（科室 / 医生 / 设备 / 资讯 / 单页）场景，内置**访客日志采集、统计聚合与异常告警系统**。

- **零第三方包管理**：无 Composer、无 Node / NPM，自带 PSR-4 风格自动加载器
- **纯 PHP 8.2**，全量 `declare(strict_types=1)`
- **配置与代码分离**：敏感配置全部走 `.env`，仓库内只提供 `.env.example` 模板

> 当前版本 v1.9.1（2026-01-23），开发者：研昌技服 <https://lingxian.tech>

---

## 目录

- [主要特性](#主要特性)
- [技术栈](#技术栈)
- [环境要求](#环境要求)
- [目录结构](#目录结构)
- [快速开始](#快速开始)
- [路由说明](#路由说明)
- [后台功能](#后台功能)
- [访客日志系统](#访客日志系统)
- [定时任务](#定时任务)
- [常用命令与排错](#常用命令与排错)
- [安全说明](#安全说明)
- [版权](#版权)

---

## 主要特性

**内容管理**
- 栏目 / 文章 / 单页 / 标签 / 友情链接 / 轮播图 / 附件管理
- 后台列表页搜索、缓存清理、系统信息面板、操作日志审计
- 角色权限（RBAC）与管理员账号管理，登录验证码
- 富文本编辑器（WangEditor）+ HTMLPurifier 输入清洗，附件上传与 PDF 在线预览（pdf.js）

**前台**
- 首页、栏目列表、文章详情、单页、站内搜索（含搜索建议接口）
- SEO：`/seo/sitemap`、`/robots.txt`、关键词提取接口
- 医疗场景模板：科室列表（kslist）、医生列表（doctorlist）、设备列表（shebei / eqlist）等
- 静态资源、图片与 PDF 文档统一由 `public/` 对外输出

**访客日志与统计（核心模块）**
- 请求级异步文件日志 → 批量入库 → 按日聚合 → 异常告警，四段式架构
- 采集 IP、会话、UA、来源页、状态码、耗时、设备 / 浏览器 / 系统识别、爬虫标记
- 后台可视化：原始日志、日统计、告警、异常报告四个页面
- 数据保留策略自动清理（明细 30 天 / 告警 90 天 / 日报表永久）

**安全**
- Medoo 参数化查询防 SQL 注入，字段与排序白名单
- Session Cookie：`HttpOnly + Secure + SameSite=Lax`
- 安全响应头、调试脚本保护、控制字符与超长输入清洗
- 可选受信代理模式读取真实 IP（默认关闭，直接使用 `REMOTE_ADDR`）

## 技术栈

| 层级 | 选型 | 说明 |
|---|---|---|
| 语言 | PHP 8.2 | 严格类型，构造器属性提升、readonly 属性 |
| 数据库 | MySQL 8.0+ | 开发环境 8.4，`visitor_logs` 使用 RANGE 分区 |
| 数据库层 | Medoo | 轻量 ORM，全参数化查询 |
| 模板 | Latte | 前台 `views/front`、后台 `views/admin` |
| 编辑器 | WangEditor | 后台富文本 |
| 输入净化 | HTMLPurifier | `app/Lib/HTMLPurifier` |
| 文档预览 | pdf.js | `public/pdfjs` |
| Web 服务器 | Nginx / Apache | 文档根指向 `public/` |

## 环境要求

- PHP **8.2 及以上**，扩展：`pdo_mysql`、`mbstring`、`json`、`openssl`（HTTPS）、`fileinfo`（文件上传）、`session`
- MySQL **8.0+**（需支持分区表）
- 可写目录权限：`storage/`（日志与缓存）、`public/uploads/`（上传）
- 不依赖 Composer、Node.js

## 目录结构

```
YTS-HPCMS/
├── app/
│   ├── Console/Commands/     # 控制台任务（访客聚合、校验、搜索统计/清理）
│   ├── Core/                 # 内核：App、Router、Autoloader、Database、Security
│   ├── Enums/  Models/       # 枚举与数据模型
│   ├── Http/
│   │   ├── Controllers/      # 前台 + Admin/ 后台控制器
│   │   ├── Middleware/       # Log / Performance / Security / VisitorLog
│   │   └── Router.php        # 静态路由 + 动态路由（带缓存）
│   ├── Lib/                  # Medoo、Latte、HTMLPurifier
│   ├── Services/             # 业务服务（访客日志、统计、告警、缓存、安全…）
│   └── Support/  Utils/      # Trait 与工具类
├── config/
│   ├── routes/               # 路由按前台 front/ 与后台 admin/ 拆分
│   ├── system.php            # 系统名称、版本、版权
│   ├── visitor.php           # 访客日志配置
│   └── menu.php / models.php / search.php
├── public/                   # 唯一对外目录
│   ├── index.php             # 应用入口（加载 .env → 自动加载 → 启动）
│   ├── static/  uploads/  pdfjs/
├── views/                    # Latte 模板（front/ 、admin/）
├── config.php                # 主配置（读取环境变量，含 --test-db 自检）
├── run_visitor_tasks.php     # 访客系统命令行任务入口
├── .env.example              # 环境变量模板（真实 .env 不入库）
└── CLAUDE.md                 # 详细开发文档
```

## 快速开始

### 1. 获取代码

```bash
git clone https://github.com/hacvk/YTS-HPCMS.git
cd YTS-HPCMS
```

### 2. 配置环境变量

```bash
cp .env.example .env
```

编辑 `.env`，按实际环境填写：

```ini
DB_HOST=127.0.0.1
DB_DATABASE=yycms
DB_USERNAME=yycms
DB_PASSWORD=你的数据库密码
DB_PORT=3306

APP_ENV=production
APP_DEBUG=false        # 生产环境务必关闭
APP_URL=https://example.com
```

> `.env` 已在 `.gitignore` 中，真实配置**永远不会被提交**；提交前请只修改 `.env.example`。

### 3. 初始化数据库

```bash
mysql -uroot -p -e "CREATE DATABASE yycms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -h 127.0.0.1 -uyycms -p yycms < 数据库初始化.sql
```

> **说明**：出于体积与敏感数据（全量备份曾包含账号密码哈希）考虑，`*.sql`、`*.log`、`*.rar`、`database/`、`sql/` 等目录不纳入版本库。数据库结构与初始数据请向项目管理员索取，或从既有运行环境导出后导入。

### 4. 准备可写目录

```bash
mkdir -p storage/logs/visitor
chmod -R 755 storage/logs
chown -R www-data:www-data storage/logs   # 按实际运行用户调整
```

### 5. 配置 Web 服务器

Nginx（文档根必须指向 `public/`，防止源码外泄）：

```nginx
server {
    listen 80;
    server_name example.com;
    root /var/www/YTS-HPCMS/public;
    index index.php;

    location / { try_files $uri $uri/ /index.php?$query_string; }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # 禁止直接访问上传目录中的可执行文件
    location ~* ^/uploads/.*\.php$ { deny all; }
}
```

本地开发也可直接启动：

```bash
php -S localhost:8000 -t public/
```

### 6. 验证安装

```bash
php config.php --test-db     # 数据库连通性自检
```

浏览器访问 `http://localhost:8000/`（前台）与 `http://localhost:8000/admin`（后台，使用数据库中的管理员账号登录）。

## 路由说明

路由按前台 / 后台拆分在 `config/routes/` 下，由 [config/routes.php](config/routes.php) 合并：

| 方法 | 路径 | 说明 |
|---|---|---|
| GET | `/` | 首页 |
| GET | `/search`、`/search/` | 站内搜索页 |
| POST | `/search/suggestions` | 搜索联想 |
| GET | `/seo/sitemap(.xml)` | 站点地图 |
| GET | `/robots.txt` | robots 协议输出 |
| POST | `/api/seo/extract-keywords` | 关键词提取 |
| GET | `/admin`、`/admin/login/captcha` | 后台登录 |
| POST | `/admin/login/login` | 登录提交 |
| GET | `/admin/dashboard` | 后台首页 |

栏目、文章等地址由 [app/Http/Router.php](app/Http/Router.php) 的**动态路由**提供（基于栏目 / 内容规则生成，带缓存，可通过后台清空路由缓存刷新）。

## 后台功能

后台入口 `/admin`，主要模块：

| 模块 | 控制器 |
|---|---|
| 控制台 / 概览 | `Admin/DashboardController` |
| 文章管理 | `Admin/ArticleController`、`Admin/CategoryController`、`Admin/PageController` |
| 轮播图 / 友链 / 联系方式 | `Admin/BannerController`、`Admin/FriendLinkController`、`Admin/ContactController` |
| 附件与上传 | `Admin/AttachmentController`、`Admin/UploadController`、`Admin/FileController` |
| 角色与账号 | `Admin/RoleController`、`Admin/AdminController`、`Admin/LoginController` |
| 站点设置 / 缓存 | `Admin/SettingController`、`Admin/CacheController`、`Admin/SystemController` |
| 操作日志 | `Admin/LogController` |
| 访客统计 | `Admin/VisitorController`（`/admin/visitor/logs`、`/stats`、`/alerts`、`/anomaly-report`） |

## 访客日志系统

### 数据流

```
请求线程（异步、不阻塞响应）
  VisitorLogMiddleware → VisitorLogService → FileLogDriver
      → storage/logs/visitor/visitor-{YYYY-MM-DD}.log

定时任务（批量入库）
  run_visitor_tasks.php aggregate → 流式读取日志 → 每批 1000 条事务写入
      → 更新日统计 → 异常检测 → 按保留策略清理
```

原始明细（30 天）→ 每日统计（永久）→ 后台报表。

### 数据表

| 表 | 用途 | 保留期 |
|---|---|---|
| `visitor_logs` | 原始请求明细（按月 RANGE 分区） | 30 天 |
| `visitor_sessions` | 会话追踪（30 分钟过期） | 30 天 |
| `visitor_stats_daily` | 每日聚合统计 | 永久 |
| `visitor_alerts` | 异常告警 | 90 天 |
| `visitor_cleanup_config` | 清理配置 | — |

### 关键指标（设计目标）

- 异步文件写入 > 1000 QPS，日志写失败不影响业务响应（异常隔离）
- 99% 统计查询 < 500ms（读聚合表而非明细表）
- 批量处理内存占用 < 50MB，`fgets` 流式读取，单批 1000 条

### 配置

在 `.env` / `config/visitor.php` 中可调整：

```ini
LOG_CHANNEL=file
LOG_LEVEL=info
```

异常阈值（`config/visitor.php`）：每分钟 60 次、每小时 300 次触发高频告警；错误率 > 50% 且样本 ≥ 10 次触发可疑 IP 告警。

## 定时任务

编辑 crontab（`crontab -e`）：

```cron
# 每 5 分钟聚合访客日志（含增量清理）
*/5 * * * * php /var/www/YTS-HPCMS/run_visitor_tasks.php aggregate

# 每日统计
0 2 * * * php /var/www/YTS-HPCMS/run_visitor_tasks.php daily-stats

# 每日异常检测
0 3 * * * php /var/www/YTS-HPCMS/run_visitor_tasks.php detect-anomalies

# 每日数据清理
0 4 * * * php /var/www/YTS-HPCMS/run_visitor_tasks.php cleanup
```

可用任务一览：

```bash
php run_visitor_tasks.php help
php run_visitor_tasks.php aggregate  --date=2026-01-22   # 指定日期聚合
php run_visitor_tasks.php aggregate  --force             # 强制重新聚合
php run_visitor_tasks.php daily-stats --date=2026-01-22
php run_visitor_tasks.php detect-anomalies --date=2026-01-22
php run_visitor_tasks.php cleanup
php run_visitor_tasks.php verify --quick                 # 安装自检
```

> 访客日志相关的命令类位于 `app/Console/Commands/`（`visitor:aggregate`、`visitor:verify`、`search:stats`、`search:cleanup`），`run_visitor_tasks.php` 是当前可直接执行的调度入口。

## 常用命令与排错

```bash
# 数据库连接自检
php config.php --test-db

# 查看实时访客日志
tail -f storage/logs/visitor/visitor-$(date +%F).log

# 查看统计表
mysql -h127.0.0.1 -uyycms -p yycms -e "SELECT * FROM visitor_stats_daily ORDER BY date DESC LIMIT 5;"

# 后台“系统 → 缓存”可一键清理；路由缓存文件也可手动删除后重建
```

| 问题 | 排查方向 |
|---|---|
| 日志不写入 | 检查 `storage/logs/visitor/` 是否存在且可写；确认 `VisitorLogMiddleware` 已在 `app/Core/App.php` 注册 |
| 数据库连不上 | `php config.php --test-db`；核对 `.env` 中账号密码 |
| 统计不更新 | 手动执行 `php run_visitor_tasks.php aggregate --force`；确认 crontab 生效 |
| 路由 404 | 清空路由缓存（`clear_route_cache.php` 或后台缓存清理） |

## 安全说明

1. **敏感配置不入库**：数据库密码、密钥等只保存在 `.env`，仓库仅提供 `.env.example`；`*.sql`、`*.log`、`*.rar`、备份文件均被忽略。
2. **生产环境**：`APP_DEBUG=false`，并关闭 `public/index.php` 中的 `display_errors`（上线时改为 `0`）。
3. **数据库账号**：仅授予业务所需权限，避免使用 root 运行站点；建议定期更换密码。
4. **受信代理**：仅在确经反向代理 / 负载均衡时开启 `visitor.ip.enable_trusted_proxy`，并把代理 IP 加入 `visitor.ip.trusted_proxies`，否则伪造 `X-Forwarded-For` 会污染统计。
5. **上传目录**：禁止执行 PHP（Nginx 见上方 `deny all` 规则）。
6. **历史仓库**：本仓库外发前已对全部提交做过敏感信息清洗（数据库密码、内网 IP、全量 SQL 备份、日志、压缩包均已移除）。

## 版权

© 2015-2026 Yanchang Tech-Service. All rights reserved.（研昌技服 · <https://lingxian.tech>）

本仓库未附带开源许可证文件，未经作者授权不得用于商业分发或二次商用。
