# YYCMS 数据库表缺失修复说明

## 1. 问题概述

YYCMS 系统目前存在多个关键数据库表缺失的问题，导致系统监控和错误跟踪功能无法正常工作。

## 2. 缺失的表及影响

### 2.1 performance_metrics 表
- **用途**：存储性能监控指标
- **影响模块**：PerformanceMiddleware（性能监控中间件）
- **缺失影响**：无法记录和分析系统性能数据，包括请求时长、内存使用、访问统计等

### 2.2 slow_queries 表
- **用途**：存储慢查询日志
- **影响模块**：PerformanceMiddleware（性能监控中间件）
- **缺失影响**：无法记录和分析慢查询，难以进行性能优化

### 2.3 error_logs 表
- **用途**：存储系统错误日志
- **影响模块**：App.php（全局异常处理器）
- **缺失影响**：系统异常无法持久化存储，难以排查生产环境问题

## 3. 现有日志系统分析

### 3.1 sys_log 表
- **状态**：已存在，字段完整
- **功能**：记录用户操作日志（登录、登出、增删改查等）
- **使用位置**：
  - [LogController.php](file:///wsl$/Ubuntu/var/www/yycms.com/app/Http/Controllers/Admin/LogController.php) - 操作日志管理
  - [LogService.php](file:///wsl$/Ubuntu/var/www/yycms.com/app/Services/LogService.php) - 日志服务层
  - [AdminBaseController.php](file:///wsl$/Ubuntu/var/www/yycms.com/app/Http/Controllers/Admin/AdminBaseController.php) - 基础控制器

### 3.2 LogController.php 限制
当前的 [LogController.php](file:///wsl$/Ubuntu/var/www/yycms.com/app/Http/Controllers/Admin/LogController.php) 只能显示 `sys_log` 表中的操作日志，无法显示以下数据：
- 性能监控数据
- 慢查询日志
- 系统错误日志

## 4. 修复方案

### 4.1 创建缺失的表
已通过 `create_missing_tables.php` 脚本解决此问题

### 4.2 增强日志管理系统
建议扩展日志系统以提供对不同类型日志的统一访问：

```php
// 在 LogController.php 中增加以下方法
public function performanceLogs(): void {
    // 显示性能监控日志
}

public function errorLogs(): void {
    // 显示错误日志
}

public function slowQueryLogs(): void {
    // 显示慢查询日志
}
```

## 5. 验证步骤

1. 运行 `php create_missing_tables.php` 创建缺失的表
2. 检查数据库中是否已创建以下表：
   - performance_metrics
   - slow_queries
   - error_logs
3. 测试系统各项功能，确认不再出现数据库表不存在的错误
4. 验证性能监控和错误日志功能是否正常工作

## 6. 后续改进建议

1. 在后台界面中增加专门的性能监控页面
2. 统一日志查看界面，支持按类型筛选不同日志
3. 增加日志清理策略，避免日志表无限增长
4. 考虑引入日志轮转机制