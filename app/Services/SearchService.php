<?php
declare(strict_types=1);

namespace App\Services;

use Medoo\Medoo;

/**
 * 搜索服务
 * 提供内容搜索功能，性能优先，资源占用低
 * 支持防CC攻击、缓存、限流等安全特性
 */
class SearchService
{
    /**
     * 搜索配置
     */
    private const CONFIG = [
        'cache_enabled' => true,      // 是否启用缓存
        'cache_ttl' => 300,           // 缓存TTL（秒）
        'rate_limit_enabled' => true, // 是否启用限流
        'log_enabled' => true,        // 是否启用日志
        'max_results' => 100,         // 最大结果数
        'suggestion_limit' => 5,      // 搜索建议数量
    ];

    public function __construct(
        private readonly Medoo $db,
        private readonly SearchCacheService $cacheService,
        private readonly SearchRateLimitService $rateLimitService,
        private readonly SearchLogService $logService
    ) {
    }

    /**
     * 搜索内容
     * 性能优化：
     * 1. 使用数据库索引进行搜索
     * 2. 限制结果数量
     * 3. 简单的LIKE查询，避免复杂全文搜索
     * 4. 只查询必要字段
     * 5. 支持缓存和防CC攻击
     *
     * @param string $keyword 搜索关键词
     * @param int $page 页码
     * @param int $limit 每页数量
     * @param string|null $ip IP地址（用于限流）
     * @param string|null $userAgent User-Agent（用于限流）
     * @return array{list: array, total: int, blocked: bool, reason: string|null}
     */
    public function search(string $keyword, int $page = 1, int $limit = 10, ?string $ip = null, ?string $userAgent = null): array
    {
        $startTime = microtime(true);

        // 清理关键词，防止SQL注入
        $cleanKeyword = $this->sanitizeKeyword($keyword);

        if (empty($cleanKeyword)) {
            // 记录空关键词搜索
            if (self::CONFIG['log_enabled'] && $ip && $userAgent) {
                $this->logService->log($ip, $userAgent, $keyword, 0, 0, 'failed', 'empty_keyword');
            }

            return ['list' => [], 'total' => 0, 'blocked' => false, 'reason' => null];
        }

        // 1. 检查关键词黑名单
        if (self::CONFIG['rate_limit_enabled'] && $ip) {
            $keywordCheck = $this->rateLimitService->checkKeywordBlacklist($cleanKeyword);
            if (!$keywordCheck['allowed']) {
                // 记录被拦截的搜索
                if (self::CONFIG['log_enabled'] && $userAgent) {
                    $this->logService->log($ip, $userAgent, $cleanKeyword, 0, 0, 'blocked', $keywordCheck['reason']);
                }

                return ['list' => [], 'total' => 0, 'blocked' => true, 'reason' => $keywordCheck['reason']];
            }
        }

        // 2. 检查频率限制
        if (self::CONFIG['rate_limit_enabled'] && $ip && $userAgent) {
            $rateLimitCheck = $this->rateLimitService->checkRateLimit($ip, $userAgent);
            if (!$rateLimitCheck['allowed']) {
                // 记录被拦截的搜索
                if (self::CONFIG['log_enabled']) {
                    $this->logService->log($ip, $userAgent, $cleanKeyword, 0, 0, 'blocked', $rateLimitCheck['reason']);
                }

                return ['list' => [], 'total' => 0, 'blocked' => true, 'reason' => $rateLimitCheck['reason']];
            }
        }

        // 3. 检查缓存
        if (self::CONFIG['cache_enabled']) {
            $cachedResult = $this->cacheService->get($cleanKeyword, $page, $limit);
            if ($cachedResult !== null) {
                // 记录缓存命中
                if (self::CONFIG['log_enabled'] && $ip && $userAgent) {
                    $duration = (microtime(true) - $startTime) * 1000;
                    $this->logService->log($ip, $userAgent, $cleanKeyword, $cachedResult['total'], $duration, 'success');
                }

                return [
                    'list' => $cachedResult['list'],
                    'total' => $cachedResult['total'],
                    'blocked' => false,
                    'reason' => null,
                ];
            }
        }

        // 4. 执行搜索
        $offset = ($page - 1) * $limit;

        // 构建搜索条件（使用LIKE进行模糊匹配）
        // 只搜索标题和摘要，避免搜索大字段content
        $searchCondition = [
            'OR' => [
                'title[~]' => $cleanKeyword,
                'summary[~]' => $cleanKeyword,
            ],
            'status' => 1, // 只搜索已发布的内容
        ];

        try {
            // 获取总数（性能优化：只计算一次）
            $total = $this->db->count('sys_content', $searchCondition);

            if ($total === 0) {
                // 记录空结果
                if (self::CONFIG['log_enabled'] && $ip && $userAgent) {
                    $duration = (microtime(true) - $startTime) * 1000;
                    $this->logService->log($ip, $userAgent, $cleanKeyword, 0, $duration, 'success');
                }

                return ['list' => [], 'total' => 0, 'blocked' => false, 'reason' => null];
            }

            // 限制最大结果数
            if ($total > self::CONFIG['max_results']) {
                $total = self::CONFIG['max_results'];
            }

            // 获取搜索结果（只查询必要字段，避免大字段content）
            $results = $this->db->select('sys_content', [
                'id',
                'title',
                'summary',
                'category_id',
                'published_at',
                'views',
            ], [
                $searchCondition,
                'ORDER' => ['published_at' => 'DESC'],
                'LIMIT' => [$offset, min($limit, self::CONFIG['max_results'])],
            ]);

            // 处理结果，添加URL等信息
            $processedResults = [];
            foreach ($results as $result) {
                $processedResults[] = [
                    'id' => $result['id'],
                    'title' => $result['title'],
                    'summary' => $this->highlightKeyword($result['summary'] ?? '', $cleanKeyword),
                    'url' => '/article/' . $result['id'],
                    'published_at' => $result['published_at'],
                    'views' => $result['views'] ?? 0,
                ];
            }

            $result = [
                'list' => $processedResults,
                'total' => $total,
                'blocked' => false,
                'reason' => null,
            ];

            // 5. 缓存结果
            if (self::CONFIG['cache_enabled']) {
                $this->cacheService->set($cleanKeyword, $result, $page, $limit, self::CONFIG['cache_ttl']);
            }

            // 6. 记录搜索日志
            if (self::CONFIG['log_enabled'] && $ip && $userAgent) {
                $duration = (microtime(true) - $startTime) * 1000;
                $this->logService->log($ip, $userAgent, $cleanKeyword, $total, $duration, 'success');
            }

            return $result;
        } catch (\\Exception $e) {
            error_log('搜索失败: ' . $e->getMessage());

            // 记录失败日志
            if (self::CONFIG['log_enabled'] && $ip && $userAgent) {
                $duration = (microtime(true) - $startTime) * 1000;
                $this->logService->log($ip, $userAgent, $cleanKeyword, 0, $duration, 'failed', $e->getMessage());
            }

            return ['list' => [], 'total' => 0, 'blocked' => false, 'reason' => 'search_failed'];
        }
    }

    /**
     * 获取搜索建议
     * 性能优化：
     * 1. 只搜索标题字段
     * 2. 限制结果数量
     * 3. 使用数据库索引
     * 4. 支持防CC攻击
     *
     * @param string $keyword 搜索关键词
     * @param int $limit 限制数量
     * @param string|null $ip IP地址（用于限流）
     * @param string|null $userAgent User-Agent（用于限流）
     * @return array
     */
    public function getSuggestions(string $keyword, int $limit = 5, ?string $ip = null, ?string $userAgent = null): array
    {
        $startTime = microtime(true);

        $cleanKeyword = $this->sanitizeKeyword($keyword);

        if (empty($cleanKeyword) || mb_strlen($cleanKeyword) < 2) {
            return [];
        }

        // 1. 检查频率限制（搜索建议也受限制）
        if (self::CONFIG['rate_limit_enabled'] && $ip && $userAgent) {
            $rateLimitCheck = $this->rateLimitService->checkRateLimit($ip, $userAgent);
            if (!$rateLimitCheck['allowed']) {
                // 记录被拦截的搜索建议
                if (self::CONFIG['log_enabled']) {
                    $this->logService->log($ip, $userAgent, $cleanKeyword, 0, 0, 'blocked', $rateLimitCheck['reason']);
                }

                return [];
            }
        }

        // 2. 检查缓存
        if (self::CONFIG['cache_enabled']) {
            $cacheKey = "suggestions_{$cleanKeyword}_{$limit}";
            $cachedSuggestions = $this->cacheService->get($cacheKey, 1, $limit);
            if ($cachedSuggestions !== null) {
                // 记录缓存命中
                if (self::CONFIG['log_enabled'] && $ip && $userAgent) {
                    $duration = (microtime(true) - $startTime) * 1000;
                    $this->logService->log($ip, $userAgent, $cleanKeyword, count($cachedSuggestions['list']), $duration, 'success');
                }

                return $cachedSuggestions['list'];
            }
        }

        try {
            // 只搜索标题，使用LIKE进行模糊匹配
            $results = $this->db->select('sys_content', [
                'id',
                'title',
            ], [
                'title[~]' => $cleanKeyword,
                'status' => 1,
                'ORDER' => ['published_at' => 'DESC'],
                'LIMIT' => $limit,
            ]);

            $suggestions = [];
            foreach ($results as $result) {
                $suggestions[] = [
                    'id' => $result['id'],
                    'title' => $result['title'],
                    'url' => '/article/' . $result['id'],
                ];
            }

            // 3. 缓存结果
            if (self::CONFIG['cache_enabled']) {
                $cacheKey = "suggestions_{$cleanKeyword}_{$limit}";
                $this->cacheService->set($cacheKey, ['list' => $suggestions, 'total' => count($suggestions)], 1, $limit, self::CONFIG['cache_ttl']);
            }

            // 4. 记录搜索日志
            if (self::CONFIG['log_enabled'] && $ip && $userAgent) {
                $duration = (microtime(true) - $startTime) * 1000;
                $this->logService->log($ip, $userAgent, $cleanKeyword, count($suggestions), $duration, 'success');
            }

            return $suggestions;
        } catch (\\Exception $e) {
            error_log('搜索建议失败: ' . $e->getMessage());

            // 记录失败日志
            if (self::CONFIG['log_enabled'] && $ip && $userAgent) {
                $duration = (microtime(true) - $startTime) * 1000;
                $this->logService->log($ip, $userAgent, $cleanKeyword, 0, $duration, 'failed', $e->getMessage());
            }

            return [];
        }
    }

    /**
     * 清理关键词
     * 防止SQL注入和特殊字符
     *
     * @param string $keyword
     * @return string
     */
    private function sanitizeKeyword(string $keyword): string
    {
        // 移除控制字符
        $keyword = preg_replace('/[\\x00-\\x1F\\x7F]/', '', $keyword);

        // 移除Unicode控制字符
        $keyword = preg_replace('/\\p{C}/u', '', $keyword);

        // 限制长度
        if (mb_strlen($keyword) > 100) {
            $keyword = mb_substr($keyword, 0, 100);
        }

        // 去除首尾空格
        $keyword = trim($keyword);

        return $keyword;
    }

    /**
     * 高亮搜索关键词
     * 在摘要中高亮显示关键词
     *
     * @param string $text 文本
     * @param string $keyword 关键词
     * @return string
     */
    private function highlightKeyword(string $text, string $keyword): string
    {
        if (empty($text) || empty($keyword)) {
            return $text;
        }

        // 限制摘要长度
        $maxLength = 200;
        if (mb_strlen($text) > $maxLength) {
            $text = mb_substr($text, 0, $maxLength) . '...';
        }

        // 高亮关键词（简单实现，避免复杂正则）
        $escapedKeyword = preg_quote($keyword, '/');
        $text = preg_replace(
            \"/($escapedKeyword)/i\",
            '<mark class=\"search-highlight\">$1</mark>',
            $text
        );

        return $text;
    }

    /**
     * 获取热门搜索关键词
     *
     * @param int $days 统计天数
     * @param int $limit 数量限制
     * @return array
     */
    public function getHotKeywords(int $days = 7, int $limit = 10): array
    {
        return $this->logService->getTopKeywords($days, $limit);
    }

    /**
     * 获取搜索统计信息
     *
     * @param int $days 统计天数
     * @return array
     */
    public function getStats(int $days = 7): array
    {
        return $this->logService->getDailyStats($days);
    }

    /**
     * 获取搜索成功率
     *
     * @param int $days 统计天数
     * @return array
     */
    public function getSuccessRate(int $days = 7): array
    {
        return $this->logService->getSuccessRate($days);
    }

    /**
     * 清理过期数据
     *
     * @return array 清理结果
     */
    public function cleanup(): array
    {
        $results = [];

        // 清理过期日志
        $results['logs'] = $this->logService->cleanupExpired(30);

        // 清理过期缓存
        $results['cache'] = $this->cacheService->cleanupExpired();

        // 清理过期拦截记录
        $results['blocks'] = $this->rateLimitService->cleanupExpiredBlocks();

        return $results;
    }
}
