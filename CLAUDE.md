# Claude Code 开发约束（YYCMS 访客日志系统）v1.0

> 适用范围：YYCMS 访客日志系统（Visitor Log System）全部模块
> 依据：YYCMS 访客日志系统设计方案 v1.0&#x20;

---

## 1. 项目硬约束（必须遵守）

1. **PHP 8.2 only**：所有新增/修改 PHP 文件必须包含：

* `<?php`
* `declare(strict_types=1);`&#x20;

2. **无 Composer / 无 Node/NPM**：禁止新增依赖、禁止引入前端构建链。
3. 允许使用既有组件（按现有项目约束）：Medoo、Latte、wangeditor；其他库除非明确手动引入且审计通过。

---

## 2. 架构边界（不得偏离设计）

### 2.1 请求链路职责（强制）

* **VisitorLogMiddleware**：仅做轻量采集 + 异步落地（默认文件驱动）。不得在请求线程内做重 IO 或复杂计算。
* **日志写入层（多驱动架构）**：必须保留“可插拔”概念（File/Redis/Queue），即使当前只实现 File Driver。
* **Cron/批处理**：日志文件解析、数据清洗、统计聚合、异常检测、清理过期数据必须在命令行任务中完成。

### 2.2 禁止事项（高频跑偏点）

* ❌ 禁止在中间件里直接写 `visitor_logs` 明细表（必须异步写文件→批处理入库）。
* ❌ 禁止为了“实时”而在后台页面扫描大范围明细表（必须以统计表为主）。
* ❌ 禁止一次性将日志文件全量读入内存（必须流式/批量）。

---

## 3. 性能指标（实现必须对齐）

### 3.1 写入性能目标

* 默认文件写入路径，目标：**>1000 QPS**（设计目标）。
* 写入失败不得影响主流程：任何异常必须吞掉，仅记录 `error_log`。

### 3.2 查询性能目标

* 后台统计查询目标：**99% < 500ms**。
* 查询必须优先读取聚合表 `visitor_stats_daily`，仅在明确场景下分页查明细。

### 3.3 批处理性能约束

* 批处理必须支持 **batchSize（建议 1000）**，并采用流式读取（生成器/逐行 fgets）。
* 必须在批处理中限制内存峰值（设计目标：常驻 < 50MB）。

---

## 4. 数据采集规范（字段、长度、清洗）

### 4.1 字段范围（最小化采集）

中间件采集字段必须限定在设计文档范围内：IP、UA、URL、Referrer、时间、停留时长、内存、状态码、设备/浏览器/OS、是否 bot、新老访客、session\_id 等。

### 4.2 日志注入/污染防护（必须实现）

对来自 `$_SERVER` / headers / cookie 的字段必须：

* 去除控制字符（`\0`、`\r`、`\n` 等）
* 强制长度上限（建议：UA/URL/Referrer ≤ 500；超出截断）
* 任何写日志序列化必须为 JSON（避免自定义分隔符被注入）

---

## 5. 安全红线（必须落实到代码）

### 5.1 SQL 注入防护

* 所有 DB 操作必须参数化/Medoo 参数化；禁止拼接 SQL。
* ORDER BY / 字段名等无法参数化的必须白名单映射。

### 5.2 Cookie 与会话安全

* `visitor_session` Cookie 必须 `HttpOnly=true`（设计示例如此）。
* 在 HTTPS 环境应启用 `Secure=true`，并设置 `SameSite=Lax`（或更严格，按站点兼容性）。
* SessionId 生成必须使用 `random_bytes()`（设计示例如此）。

### 5.3 客户端 IP 信任边界（必须加开关）

设计示例读取了 `X-Forwarded-For`/`X-Real-IP`。实现时必须：

* 增加“可信代理开关/白名单”，仅在可信代理环境才读取 XFF/X-Real-IP；
* 否则默认使用 `REMOTE_ADDR`。

### 5.4 异常隔离

* 日志链路（采集/写入/聚合）任何失败不得影响主请求响应。
* 中间件必须在**正常与异常**两条路径都记录（异常按 500）。

---

## 6. 隐私与合规约束（必须可配置）

1. 必须提供 IP 隐私策略配置：

* 存原文 / 掩码（如 IPv4 /24）/ hash（三选一或可组合）

2. 后台默认展示脱敏 IP；查看原文（若保存）必须权限控制。
3. 数据保留策略必须严格执行（见下一节）。

---

## 7. 数据库与生命周期（强制执行）

### 7.1 分层存储（必须按设计）

* 明细表 `visitor_logs`：**30 天保留**，按月分区（Range partition by timestamp）。
* 日统计 `visitor_stats_daily`：永久保留。
* 会话 `visitor_sessions`：30 天。
* 告警 `visitor_alerts`：90 天（或方案值）。

### 7.2 清理要求

* 必须提供清理命令/任务：按分区 drop 或按 timestamp 删除，并确保不会锁表/阻塞。

---

## 8. 定时任务（Cron）实现约束

必须提供并保持可运行的命令：

* `visitor:aggregate`（每 5 分钟聚合）
* `visitor:daily-stats`（每日统计）
* `visitor:detect-anomalies`（异常检测）
* `visitor:cleanup`（过期清理）

任务实现必须：

* 支持指定日期（如 `--date=`）
* 输出可审计日志（处理条数、耗时、失败原因）
* 批量入库，避免单条 insert 的极端低效（必要时使用事务/批插策略）

---

## 9. 目录与文件落点（新增代码必须放在这些位置）

必须遵循设计文档给出的关键文件路径（可增补同目录辅助类，但不得散落到 public/）：

* `app/Http/Middleware/VisitorLogMiddleware.php`
* `app/Services/VisitorLogService.php`
* `app/Services/VisitorStatsService.php`
* `app/Services/AnomalyDetectionService.php`
* `app/Console/Commands/VisitorAggregate.php`

---

## 10. Claude Code 输出规范（每次交付必须包含）

Claude Code 每次生成代码/改动时，必须输出以下四块内容：

1. **变更文件清单**（新增/修改路径）
2. **关键实现说明**（≤10 条，必须覆盖：性能、异常隔离、IP 信任边界、注入防护、Cookie 安全、数据保留/清理）
3. **手动验证步骤**（如何触发写入、如何跑聚合命令、如何验证 DB 统计表更新）
4. **回滚方式**（删除哪些文件 / 关闭哪些开关即可恢复）

---

## 11. 默认参数建议（可作为 config 初始值）

* 日志目录：`storage/logs/visitor`
* 单文件上限：100MB
* 文件保留：7 天（文件层），数据库明细保留 30 天（表层）
* 聚合 batchSize：1000
* 高频告警阈值：requests\_per\_hour=300（按文档）

---

