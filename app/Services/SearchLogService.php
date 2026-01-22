<?php
declare(strict_types=1);

namespace App\Services;

use Medoo\Medoo;

/**
 * 搜索日志服务
 * 提供搜索日志记录和统计功能
 */
class SearchLogService
{
    /**
     * 构造函数
     *
     * @param Medoo $db 数据库实例
     */
    public function __construct(
        private readonly Medoo $db
    ) {
    }

    /**
     * 记录搜索日志
     *
     * @param string $ip IP地址
     * @param string $userAgent User-Agent
     * @param string $keyword 搜索关键词
     * @param int $resultCount 结果数量
     * @param float $duration 搜索耗时（毫秒）
     * @param string $status 状态：success/failed/blocked
     * @param string|null $blockedReason 拦截原因
     * @return bool 是否记录成功
     */
    public function log(
        string $ip,
        string $userAgent,
        string $keyword,
        int $resultCount = 0,
        float $duration = 0,
        string $status = 'success',
        ?string $blockedReason = null
    ): bool {
        try {
            $this->db->insert('search_logs', [
                'ip' => $ip,
                'user_agent' => $userAgent,
                'keyword' => $keyword,
                'result_count' => $resultCount,
                'duration' => $duration,
                'status' => $status,
                'blocked_reason' => $blockedReason,
                'timestamp' => time(),
            ]);

            return true;
        } catch (\Exception $e) {
            error_log('[SearchLogService] 日志记录失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 批量记录搜索日志
     *
     * @param array $logs 日志数组
     * @return int 成功记录的数量
     */
    public function logBatch(array $logs): int
    {
        $successCount = 0;

        foreach ($logs as $log) {
            if ($this->log(
                $log['ip'] ?? '',
                $log['user_agent'] ?? '',
                $log['keyword'] ?? '',
                $log['result_count'] ?? 0,
                $log['duration'] ?? 0,
                $log['status'] ?? 'success',
                $log['blocked_reason'] ?? null
            )) {
                $successCount++;
            }
        }

        return $successCount;
    }

    /**
     * 获取搜索统计（按天）
     *
     * @param int $days 统计天数
     * @return array
     */
    public function getDailyStats(int $days = 7): array
    {
        $startTime = time() - $days * 86400;

        $stats = $this->db->select('search_logs', [
            [new \Medoo\Raw('DATE(FROM_UNIXTIME(timestamp))'), 'date'],
            [new \Medoo\Raw('COUNT(*)'), 'total_searches'],
            [new \Medoo\Raw('COUNT(DISTINCT ip)'), 'unique_searchers'],
            [new \Medoo\Raw('SUM(CASE WHEN status = "blocked" THEN 1 ELSE 0 END)'), 'blocked_searches'],
            [new \Medoo\Raw('AVG(result_count)'), 'avg_results'],
            [new \Medoo\Raw('AVG(duration)'), 'avg_duration'],
        ], [
            'timestamp[>=]' => $startTime,
            'GROUP' => [new \Medoo\Raw('DATE(FROM_UNIXTIME(timestamp))')],
            'ORDER' => [new \Medoo\Raw('DATE(FROM_UNIXTIME(timestamp))') => 'DESC'],
        ]);

        return $stats;
    }

    /**
     * 获取热门搜索关键词
     *
     * @param int $days 统计天数
     * @param int $limit 数量限制
     * @return array
     */
    public function getTopKeywords(int $days = 7, int $limit = 10): array
    {
        $startTime = time() - $days * 86400;

        $keywords = $this->db->select('search_logs', [
            'keyword',
            [new \Medoo\Raw('COUNT(*)'), 'count'],
            [new \Medoo\Raw('MAX(timestamp)'), 'last_search'],
        ], [
            'timestamp[>=]' => $startTime,
            'status' => 'success',
            'GROUP' => ['keyword'],
            'ORDER' => ['count' => 'DESC'],
            'LIMIT' => $limit,
        ]);

        return $keywords;
    }

    /**
     * 获取热门搜索IP
     *
     * @param int $days 统计天数
     * @param int $limit 数量限制
     * @return array
     */
    public function getTopIps(int $days = 7, int $limit = 10): array
    {
        $startTime = time() - $days * 86400;

        $ips = $this->db->select('search_logs', [
            'ip',
            [new \Medoo\Raw('COUNT(*)'), 'count'],
            [new \Medoo\Raw('MAX(timestamp)'), 'last_search'],
        ], [
            'timestamp[>=]' => $startTime,
            'GROUP' => ['ip'],
            'ORDER' => ['count' => 'DESC'],
            'LIMIT' => $limit,
        ]);

        return $ips;
    }

    /**
     * 获取搜索成功率
     *
     * @param int $days 统计天数
     * @return array
     */
    public function getSuccessRate(int $days = 7): array
    {
        $startTime = time() - $days * 86400;

        $stats = $this->db->select('search_logs', [
            [new \Medoo\Raw('COUNT(*)'), 'total'],
            [new \Medoo\Raw('SUM(CASE WHEN status = "success" THEN 1 ELSE 0 END)'), 'success'],
            [new \Medoo\Raw('SUM(CASE WHEN status = "blocked" THEN 1 ELSE 0 END)'), 'blocked'],
            [new \Medoo\Raw('SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END)'), 'failed'],
        ], [
            'timestamp[>=]' => $startTime,
        ]);

        $result = $stats[0] ?? [];
        $total = $result['total'] ?? 0;

        if ($total > 0) {
            $result['success_rate'] = round(($result['success'] ?? 0) / $total * 100, 2);
            $result['blocked_rate'] = round(($result['blocked'] ?? 0) / $total * 100, 2);
            $result['failed_rate'] = round(($result['failed'] ?? 0) / $total * 100, 2);
        } else {
            $result['success_rate'] = 0;
            $result['blocked_rate'] = 0;
            $result['failed_rate'] = 0;
        }

        return $result;
    }

    /**
     * 获取搜索性能统计
     *
     * @param int $days 统计天数
     * @return array
     */
    public function getPerformanceStats(int $days = 7): array
    {
        $startTime = time() - $days * 86400;

        $stats = $this->db->select('search_logs', [
            [new \Medoo\Raw('COUNT(*)'), 'total'],
            [new \Medoo\Raw('AVG(duration)'), 'avg_duration'],
            [new \Medoo\Raw('MIN(duration)'), 'min_duration'],
            [new \Medoo\Raw('MAX(duration)'), 'max_duration'],
            [new \Medoo\Raw('PERCENTILE_CONT(0.95) WITHIN GROUP (ORDER BY duration)'), 'p95_duration'],
            [new \Medoo\Raw('PERCENTILE_CONT(0.99) WITHIN GROUP (ORDER BY duration)'), 'p99_duration'],
        ], [
            'timestamp[>=]' => $startTime,
            'status' => 'success',
        ]);

        return $stats[0] ?? [];
    }

    /**
     * 清理过期的日志
     *
     * @param int $days 保留天数
     * @return int 清理的记录数
     */
    public function cleanupExpired(int $days = 30): int
    {
        $cutoff = time() - $days * 86400;

        try {
            $deleted = $this->db->delete('search_logs', [
                'timestamp[<]' => $cutoff,
            ]);

            return $deleted;
        } catch (\Exception $e) {
            error_log('[SearchLogService] 清理过期日志失败: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * 获取搜索日志统计信息
     *
     * @return array
     */
    public function getStats(): array
    {
        $stats = $this->db->select('search_logs', [
            [new \Medoo\Raw('COUNT(*)'), 'total'],
            [new \Medoo\Raw('COUNT(DISTINCT ip)'), 'unique_ips'],
            [new \Medoo\Raw('COUNT(DISTINCT keyword)'), 'unique_keywords'],
            [new \Medoo\Raw('MIN(timestamp)'), 'first_search'],
            [new \Medoo\Raw('MAX(timestamp)'), 'last_search'],
        ]);

        return $stats[0] ?? [];
    }

    /**
     * 获取搜索日志详情
     *
     * @param int $page 页码
     * @param int $limit 每页数量
     * @param array $filters 过滤条件
     * @return array
     */
    public function getLogs(int $page = 1, int $limit = 20, array $filters = []): array
    {
        $offset = ($page - 1) * $limit;

        $where = [];
        if (!empty($filters['ip'])) {
            $where['ip'] = $filters['ip'];
        }
        if (!empty($filters['keyword'])) {
            $where['keyword[~]'] = $filters['keyword'];
        }
        if (!empty($filters['status'])) {
            $where['status'] = $filters['status'];
        }
        if (!empty($filters['start_time'])) {
            $where['timestamp[>=]'] = $filters['start_time'];
        }
        if (!empty($filters['end_time'])) {
            $where['timestamp[<=]'] = $filters['end_time'];
        }

        $logs = $this->db->select('search_logs', [
            'id',
            'ip',
            'user_agent',
            'keyword',
            'result_count',
            'duration',
            'status',
            'blocked_reason',
            'timestamp',
        ], [
            $where,
            'ORDER' => ['timestamp' => 'DESC'],
            'LIMIT' => [$offset, $limit],
        ]);

        $total = $this->db->count('search_logs', $where);

        return [
            'list' => $logs,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    /**
     * 获取搜索趋势
     *
     * @param int $days 统计天数
     * @return array
     */
    public function getSearchTrend(int $days = 7): array
    {
        $startTime = time() - $days * 86400;

        $trend = $this->db->select('search_logs', [
            [new \Medoo\Raw('DATE(FROM_UNIXTIME(timestamp))'), 'date'],
            [new \Medoo\Raw('HOUR(FROM_UNIXTIME(timestamp))'), 'hour'],
            [new \Medoo\Raw('COUNT(*)'), 'count'],
        ], [
            'timestamp[>=]' => $startTime,
            'GROUP' => [new \Medoo\Raw('DATE(FROM_UNIXTIME(timestamp))'), new \Medoo\Raw('HOUR(FROM_UNIXTIME(timestamp))')],
            'ORDER' => [new \Medoo\Raw('DATE(FROM_UNIXTIME(timestamp))') => 'ASC', new \Medoo\Raw('HOUR(FROM_UNIXTIME(timestamp))') => 'ASC'],
        ]);

        return $trend;
    }

    /**
     * 获取搜索关键词详情
     *
     * @param string $keyword 关键词
     * @param int $days 统计天数
     * @return array
     */
    public function getKeywordDetails(string $keyword, int $days = 7): array
    {
        $startTime = time() - $days * 86400;

        $details = $this->db->select('search_logs', [
            [new \Medoo\Raw('COUNT(*)'), 'total'],
            [new \Medoo\Raw('COUNT(DISTINCT ip)'), 'unique_ips'],
            [new \Medoo\Raw('AVG(result_count)'), 'avg_results'],
            [new \Medoo\Raw('AVG(duration)'), 'avg_duration'],
            [new \Medoo\Raw('MIN(timestamp)'), 'first_search'],
            [new \Medoo\Raw('MAX(timestamp)'), 'last_search'],
        ], [
            'keyword' => $keyword,
            'timestamp[>=]' => $startTime,
        ]);

        return $details[0] ?? [];
    }
}
