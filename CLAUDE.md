# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**YYCMS** - A PHP 8.2 CMS system with a built-in visitor log system for tracking and analyzing website traffic.

**Key Characteristics:**
- PHP 8.2 only with strict types (`declare(strict_types=1);`)
- No Composer / No Node.js / No NPM (manual autoloading)
- Uses Medoo (lightweight database library), Latte (templating), and WangEditor (WYSIWYG editor)
- Visitor log system with async file-based logging + batch processing

## Architecture Overview

### Request Flow
```
Browser → public/index.php → Router → Middleware Chain → Controller → Response
```

### Middleware Chain (in order)
1. **LogMiddleware** - Basic request logging
2. **PerformanceMiddleware** - Performance metrics tracking
3. **SecurityMiddleware** - Security headers and debug script protection
4. **VisitorLogMiddleware** - Visitor log collection (async file write)

### Visitor Log System Architecture

**Async Write Path (Request Thread):**
```
VisitorLogMiddleware → VisitorLogService → FileLogDriver → storage/logs/visitor/visitor-{date}.log
```

**Batch Processing Path (Cron/CLI):**
```
VisitorAggregate.php → Read log file → Parse JSON → Batch insert → Database → Update stats → Detect anomalies → Cleanup
```

**Data Flow:**
```
Raw Logs (30 days) → Daily Stats (Permanent) → Aggregated Reports
```

## Key Files & Locations

### Core Visitor Log System
- **Middleware**: `app/Http/Middleware/VisitorLogMiddleware.php` (~360 lines)
- **Services**:
  - `app/Services/VisitorLogService.php` (~600 lines) - Log recording & cleanup
  - `app/Services/VisitorStatsService.php` (~810 lines) - Statistical calculations
  - `app/Services/AnomalyDetectionService.php` (~530 lines) - Anomaly detection
- **Log Driver**: `app/Services/Log/FileLogDriver.php` (~220 lines)
- **Console Commands**:
  - `app/Console/Commands/VisitorAggregate.php` (~360 lines) - Main aggregation command
  - `app/Console/Commands/VisitorVerify.php` (~555 lines) - System verification

### Database Schema
- **SQL File**: `sql/visitor_log_tables.sql` (~840 lines)
- **Tables**:
  - `visitor_logs` - Raw request logs (30 days, partitioned by month)
  - `visitor_stats_daily` - Daily aggregates (permanent)
  - `visitor_sessions` - Session tracking (30 days)
  - `visitor_alerts` - Anomaly alerts (90 days)
  - `visitor_session_cleanup` - Session cleanup helper
  - `visitor_cleanup_config` - Cleanup configuration

### Configuration
- **Main Config**: `config.php` - Database and app settings
- **Routes**: `config/routes.php` - URL routing definitions
- **Entry Point**: `public/index.php` - Application bootstrap

### Admin Interface
- **Routes**: `config/routes.php` (lines 137-142)
  - `/admin/visitor/logs` - View raw logs
  - `/admin/visitor/stats` - View statistics
  - `/admin/visitor/alerts` - View alerts
  - `/admin/visitor/anomaly-report` - View anomaly reports

## Common Development Tasks

### Running the Application
```bash
# Start PHP built-in server (development)
php -S localhost:8000 -t public/

# Or use your web server (Apache/Nginx)
# Document root: /var/www/yycms.com/public/
```

### Database Operations
```bash
# Test database connection
php public/index.php --test-db

# Create visitor log tables
mysql -h 127.0.0.1 -u root -p yycms < sql/visitor_log_tables.sql

# Verify system installation
php public/index.php visitor:verify
php public/index.php visitor:verify --quick  # Fast verification
```

### Visitor Log Commands
```bash
# Aggregate logs (run every 5 minutes via cron)
php public/index.php visitor:aggregate
php public/index.php visitor:aggregate --date=2026-01-22
php public/index.php visitor:aggregate --force  # Force re-aggregation

# View database stats
php public/index.php visitor:aggregate --stats

# Cleanup (run daily via cron)
php public/index.php visitor:aggregate  # Includes cleanup
```

### Cron Configuration
Add to crontab (`crontab -e`):
```bash
# Aggregate logs every 5 minutes
*/5 * * * * php /var/www/yycms.com/public/index.php visitor:aggregate

# Daily stats (runs after aggregation)
0 2 * * * php /var/www/yycms.com/public/index.php visitor:daily-stats

# Anomaly detection (runs after aggregation)
0 3 * * * php /var/www/yycms.com/public/index.php visitor:detect-anomalies

# Cleanup (runs daily at 4 AM)
0 4 * * * php /var/www/yycms.com/public/index.php visitor:cleanup
```

### Testing Visitor Log System
```bash
# 1. Check if middleware is registered
grep -r "VisitorLogMiddleware" app/Core/App.php

# 2. View live logs
tail -f /var/www/yycms.com/storage/logs/visitor/visitor-$(date +%Y-%m-%d).log

# 3. Test log writing
curl http://localhost:8000/

# 4. Check database
mysql -h 127.0.0.1 -u root -p yycms -e "SELECT COUNT(*) FROM visitor_logs;"
mysql -h 127.0.0.1 -u root -p yycms -e "SELECT * FROM visitor_stats_daily ORDER BY date DESC LIMIT 5;"
```

## Visitor Log System Details

### Data Collection (VisitorLogMiddleware)
**Collected Fields:**
- `request_id` - Unique request identifier
- `session_id` - Visitor session ID (30 min expiry)
- `ip` - Client IP (with trusted proxy support)
- `user_agent` - Browser/device info
- `url` - Requested URL
- `referrer` - Referring URL
- `method` - HTTP method (GET/POST/etc)
- `status_code` - HTTP status code
- `duration` - Response time (ms)
- `memory_used` - Memory usage (KB)
- `device_type` - mobile/desktop/tablet
- `browser` - Browser type
- `os` - Operating system
- `is_bot` - Bot detection flag
- `timestamp` - Unix timestamp

**Security Features:**
- IP header whitelist (trusted proxy mode)
- Session cookie: HttpOnly + Secure + SameSite
- String sanitization (removes control characters)
- Length limits (URL/UA/Referrer: 500 chars)
- Exception isolation (log failures don't affect response)

### Log Format
```
[2026-01-22 10:30:45] INFO: Visitor log {"request_id":"req_123","session_id":"sess_456","ip":"127.0.0.1",...}
```

### Performance Targets
- **Write Performance**: > 1000 QPS (async file write)
- **Query Performance**: 99% < 500ms (reads from stats table)
- **Memory Usage**: < 50MB during batch processing
- **Batch Size**: 1000 records per batch

### Data Lifecycle
| Table | Retention | Cleanup Method |
|-------|-----------|----------------|
| `visitor_logs` | 30 days | Delete by timestamp |
| `visitor_sessions` | 30 days | Delete by last_visit |
| `visitor_alerts` | 90 days | Delete by created_at |
| `visitor_stats_daily` | Permanent | N/A (archived) |

## Security Considerations

### IP Trust Boundary
**Default: OFF** - Uses `REMOTE_ADDR` directly
```php
// Enable trusted proxy mode in config
'visitor.ip.enable_trusted_proxy' => true,
'visitor.ip.trusted_proxies' => ['127.0.0.1', '127.0.0.1'],
```

### Session Cookie Security
```php
setcookie('visitor_session', $sessionId, [
    'expires' => time() + 1800,  // 30 minutes
    'path' => '/',
    'secure' => true,      // HTTPS only
    'httponly' => true,    // Prevent XSS
    'samesite' => 'Lax',   // CSRF protection
]);
```

### SQL Injection Protection
- All database operations use Medoo parameterized queries
- No raw SQL concatenation
- ORDER BY / field names use whitelist mapping

### Data Sanitization
- Removes control characters (ASCII 0-31, 127)
- Removes Unicode control characters
- Enforces length limits (500 chars max for URL/UA/Referrer)
- JSON encoding for all log data

## Configuration

### Database Configuration
Located in `config.php`:
```php
'db' => [
    'host' => '127.0.0.1',
    'database' => 'yycms',
    'username' => 'yycms',
    'password' => 'YOUR_DB_PASSWORD',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
],
```

### Visitor Log Configuration
Default values (can be customized):
```php
'visitor.log' => [
    'dir' => 'storage/logs/visitor',
    'format' => 'visitor-{date}.log',
],
'visitor.anomaly' => [
    'high_frequency' => [
        'requests_per_minute' => 60,
        'requests_per_hour' => 300,
    ],
    'suspicious_ip' => [
        'error_rate' => 0.5,
        'min_requests' => 10,
    ],
],
```

## Troubleshooting

### Common Issues

**1. Logs not being written**
```bash
# Check directory permissions
ls -la /var/www/yycms.com/storage/logs/visitor/
chmod -R 755 /var/www/yycms.com/storage/logs/visitor/

# Check if middleware is registered
grep "VisitorLogMiddleware" app/Core/App.php
```

**2. Database connection fails**
```bash
# Test connection
php public/index.php --test-db

# Check config
cat config.php | grep -A 10 "db"
```

**3. Aggregation not working**
```bash
# Check if log file exists
ls -la /var/www/yycms.com/storage/logs/visitor/

# Run aggregation manually
php public/index.php visitor:aggregate --date=2026-01-22

# Check for errors
tail -f /var/log/php-fpm/error.log
```

**4. Statistics not updating**
```bash
# Check if stats table has data
mysql -h 127.0.0.1 -u root -p yycms -e "SELECT * FROM visitor_stats_daily ORDER BY date DESC LIMIT 5;"

# Force re-aggregation
php public/index.php visitor:aggregate --force
```

### Debug Commands
```bash
# View all visitor log tables
mysql -h 127.0.0.1 -u root -p yycms -e "SHOW TABLES LIKE 'visitor_%';"

# Check table sizes
mysql -h 127.0.0.1 -u root -p yycms -e "
SELECT table_name, ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
FROM information_schema.tables
WHERE table_schema = DATABASE() AND table_name LIKE 'visitor_%'
ORDER BY size_mb DESC;
"

# View recent logs
tail -n 50 /var/www/yycms.com/storage/logs/visitor/visitor-$(date +%Y-%m-%d).log

# Check cron jobs
crontab -l
grep "visitor" /etc/crontab
```

## Performance Optimization

### File Log Driver
- Uses `file_put_contents()` with `FILE_APPEND | LOCK_EX` for atomic writes
- Single file per day for easy parsing
- Automatic rotation at 100MB
- 30-day retention for log files

### Batch Processing
- Stream-based reading (fgets) to avoid memory issues
- Batch inserts (1000 records per batch)
- Transaction wrapping for data consistency
- Memory monitoring with GC collection

### Database Indexes
```sql
-- visitor_logs
INDEX `idx_timestamp`(`timestamp`)
INDEX `idx_ip`(`ip`)
INDEX `idx_url`(`url`(100))
INDEX `idx_session`(`session_id`)
INDEX `idx_referrer`(`referrer`(100))

-- visitor_stats_daily
PRIMARY KEY (`date`)

-- visitor_sessions
UNIQUE KEY `uk_session`(`session_id`)
INDEX `idx_ip_ua`(`ip`, `user_agent_hash`)
INDEX `idx_last_visit`(`last_visit`)

-- visitor_alerts
INDEX `idx_ip_alert`(`ip`, `alert_type`)
INDEX `idx_status_risk`(`status`, `risk_level`)
```

### Partitioning
`visitor_logs` table uses RANGE partitioning by `timestamp` for:
- Fast deletion of old data (DROP PARTITION)
- Query performance (partition pruning)
- Efficient storage management

## Code Quality Notes

### PHP 8.2 Features Used
- `declare(strict_types=1);` - Strict type checking
- Readonly properties - `private readonly string $logDir`
- Constructor property promotion
- Named arguments (where applicable)

### Error Handling
- All database operations wrapped in try-catch
- Errors logged to `error_log()` (PHP error log)
- Exception isolation in middleware chain
- Graceful degradation (log failures don't break requests)

### Code Style
- PSR-4 autoloading via `app/Core/Autoloader.php`
- No external dependencies (zero Composer)
- Single responsibility per class
- Comprehensive inline documentation

## Deployment Checklist

1. **Database Setup**
   ```bash
   mysql -h 127.0.0.1 -u root -p yycms < sql/visitor_log_tables.sql
   ```

2. **Configuration**
   - Update `config.php` with correct database credentials
   - Configure trusted proxy settings if behind load balancer
   - Set up cron jobs

3. **Permissions**
   ```bash
   chmod -R 755 storage/logs/visitor/
   chown -R www-data:www-data storage/logs/visitor/
   ```

4. **Verification**
   ```bash
   php public/index.php visitor:verify
   ```

5. **Testing**
   - Visit any page to generate logs
   - Check log file: `tail -f storage/logs/visitor/visitor-$(date +%Y-%m-%d).log`
   - Run aggregation: `php public/index.php visitor:aggregate`
   - Verify stats: `mysql -h 127.0.0.1 -u root -p yycms -e "SELECT * FROM visitor_stats_daily LIMIT 5;"`

## Important Notes for Claude

### When Modifying Visitor Log System
1. **Never** write directly to `visitor_logs` table in middleware - always use async file logging
2. **Always** use parameterized queries (Medoo) for database operations
3. **Always** sanitize user input (IP, UA, URL, Referrer) before logging
4. **Always** wrap database operations in try-catch with error logging
5. **Always** maintain batch processing for performance (1000 records/batch)
6. **Never** load entire log files into memory - use streaming/fgets
7. **Always** respect data retention policies (30/90 days)

### When Adding New Features
1. Check if feature requires database changes (add to `sql/visitor_log_tables.sql`)
2. Update configuration if needed (add to `config.php`)
3. Add console commands if batch processing required
4. Update admin routes if new views needed
5. Document in inline comments

### Performance Testing
- Use `ab` (Apache Bench) for load testing
- Monitor memory usage: `ps aux | grep php`
- Check query performance with `EXPLAIN`
- Verify cron execution: `tail -f /var/log/cron`

## Related Documentation
- `doc/claude/20260122_yycms_visitor_log_final_summary.md` - Complete implementation summary
- `sql/visitor_log_tables.sql` - Database schema with detailed comments
- `app/Services/Log/README.md` - Log driver documentation
