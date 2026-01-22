<?php
declare(strict_types=1);

namespace App\Services;

use Medoo\Medoo;

/**
 * 搜索防CC限流服务
 * 提供搜索请求的频率限制和拦截功能
 */
class SearchRateLimitService
{
    /**
     * 默认配置
     */
    private const DEFAULT_CONFIG = [
        'max_requests' => 60,      // 默认每分钟60次
        'time_window' => 60,       // 时间窗口60秒
        'block_duration' => 300,   // 拦截时长300秒
        'cache_ttl' => 300,        // 缓存TTL
    ];

    /**
     * 构造函数
     *
     * @param Medoo $db 数据库实例
     * @param CacheService $cache 缓存服务
     */
    public function __construct(
        private readonly Medoo $db,
        private readonly CacheService $cache
    ) {
    }

    /**
     * 检查搜索请求是否被限制
     *
     * @param string $ip IP地址
     * @param string $userAgent User-Agent
     * @return array{allowed: bool, reason: string|null, block_until: int|null}
     */
    public function checkRateLimit(string $ip, string $userAgent): array
    {
        // 1. 检查IP黑名单
        if ($this->isIpBlacklisted($ip)) {
            return [
                'allowed' => false,
                'reason' => 'ip_blacklist',
                'block_until' => null,
            ];
        }

        // 2. 检查是否已被拦截
        $blockInfo = $this->getBlockedInfo($ip);
        if ($blockInfo && $blockInfo['block_until'] > time()) {
            return [
                'allowed' => false,
                'reason' => 'rate_limit',
                'block_until' => $blockInfo['block_until'],
            ];
        }

        // 3. 检查频率限制
        $rateLimit = $this->getRateLimitConfig($ip);
        $cacheKey = $this->getCacheKey($ip, $rateLimit['time_window']);

        // 从缓存获取请求计数
        $requestCount = $this->cache->get($cacheKey, 0);

        // 如果超过限制，拦截请求
        if ($requestCount >= $rateLimit['max_requests']) {
            $this->blockIp($ip, $rateLimit['block_duration'], 'rate_limit');
            return [
                'allowed' => false,
                'reason' => 'rate_limit',
                'block_until' => time() + $rateLimit['block_duration'],
            ];
        }

        // 4. 增加请求计数
        $this->cache->set($cacheKey, $requestCount + 1, $rateLimit['time_window']);

        return [
            'allowed' => true,
            'reason' => null,
            'block_until' => null,
        ];
    }

    /**
     * 检查关键词是否被黑名单拦截
     *
     * @param string $keyword 搜索关键词
     * @return array{allowed: bool, reason: string|null}
     */
    public function checkKeywordBlacklist(string $keyword): array
    {
        // 从缓存获取黑名单
        $cacheKey = 'search_keyword_blacklist';
        $blacklist = $this->cache->get($cacheKey);

        if ($blacklist === null) {
            // 从数据库加载黑名单
            $blacklist = $this->db->select('search_keyword_blacklist', [
                'keyword',
                'match_type',
            ], [
                'enabled' => 1,
            ]);

            // 缓存5分钟
            $this->cache->set($cacheKey, $blacklist, 300);
        }

        // 检查关键词是否匹配黑名单
        foreach ($blacklist as $item) {
            $blackKeyword = $item['keyword'];
            $matchType = $item['match_type'];

            switch ($matchType) {
                case 'exact':
                    if ($keyword === $blackKeyword) {
                        return ['allowed' => false, 'reason' => 'keyword_blacklist'];
                    }
                    break;
                case 'prefix':
                    if (str_starts_with($keyword, $blackKeyword)) {
                        return ['allowed' => false, 'reason' => 'keyword_blacklist'];
                    }
                    break;
                case 'suffix':
                    if (str_ends_with($keyword, $blackKeyword)) {
                        return ['allowed' => false, 'reason' => 'keyword_blacklist'];
                    }
                    break;
                case 'contains':
                    if (str_contains($keyword, $blackKeyword)) {
                        return ['allowed' => false, 'reason' => 'keyword_blacklist'];
                    }
                    break;
            }
        }

        return ['allowed' => true, 'reason' => null];
    }

    /**
     * 检查IP是否在黑名单中
     *
     * @param string $ip IP地址
     * @return bool
     */
    private function isIpBlacklisted(string $ip): bool
    {
        $cacheKey = 'search_ip_blacklist';
        $blacklist = $this->cache->get($cacheKey);

        if ($blacklist === null) {
            // 从数据库加载黑名单
            $blacklist = $this->db->select('search_ip_blacklist', [
                'ip',
                'expired_at',
            ], [
                'enabled' => 1,
                'OR' => [
                    'expired_at[>=]' => date('Y-m-d H:i:s'),
                    'expired_at' => null,
                ],
            ]);

            // 缓存5分钟
            $this->cache->set($cacheKey, $blacklist, 300);
        }

        // 检查IP是否匹配黑名单
        foreach ($blacklist as $item) {
            if ($this->ipMatchesCidr($ip, $item['ip'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * 检查IP是否匹配CIDR
     *
     * @param string $ip IP地址
     * @param string $cidr CIDR格式
     * @return bool
     */
    private function ipMatchesCidr(string $ip, string $cidr): bool
    {
        // 如果是精确匹配
        if ($ip === $cidr) {
            return true;
        }

        // 如果是CIDR格式
        if (str_contains($cidr, '/')) {
            [$network, $bits] = explode('/', $cidr);
            $ipLong = ip2long($ip);
            $networkLong = ip2long($network);
            $mask = -1 << (32 - (int)$bits);

            return ($ipLong & $mask) === ($networkLong & $mask);
        }

        return false;
    }

    /**
     * 获取被拦截的信息
     *
     * @param string $ip IP地址
     * @return array|null
     */
    private function getBlockedInfo(string $ip): ?array
    {
        $cacheKey = 'search_blocked_' . $ip;
        $blockedInfo = $this->cache->get($cacheKey);

        if ($blockedInfo === null) {
            // 从数据库获取最近的拦截记录
            // 注意：search_blocks表中没有block_duration字段，需要从配置获取
            $blockedInfo = $this->db->get('search_blocks', [
                'ip',
                'last_blocked',
                'block_type',
            ], [
                'ip' => $ip,
                'ORDER' => ['last_blocked' => 'DESC'],
                'LIMIT' => 1,
            ]);

            if ($blockedInfo) {
                // 从配置获取block_duration
                $config = $this->getRateLimitConfig($ip);
                $blockDuration = $config['block_duration'] ?? 300; // 默认5分钟
                $blockedInfo['block_until'] = (int)$blockedInfo['last_blocked'] + $blockDuration;
            }

            if ($blockedInfo) {
                // 缓存到下次拦截结束
                $blockUntil = $blockedInfo['block_until'];
                $ttl = max(0, $blockUntil - time());
                if ($ttl > 0) {
                    $this->cache->set($cacheKey, $blockedInfo, $ttl);
                }
            }
        }

        return $blockedInfo;
    }

    /**
     * 拦截IP
     *
     * @param string $ip IP地址
     * @param int $duration 拦截时长（秒）
     * @param string $reason 拦截原因
     * @return void
     */
    private function blockIp(string $ip, int $duration, string $reason): void
    {
        $blockUntil = time() + $duration;

        // 更新或插入拦截记录
        $existing = $this->db->get('search_blocks', ['id'], ['ip' => $ip, 'block_type' => $reason]);

        if ($existing) {
            $this->db->update('search_blocks', [
                'block_count' => $this->db->raw('block_count + 1'),
                'last_blocked' => time(),
            ], [
                'id' => $existing['id'],
            ]);
        } else {
            $this->db->insert('search_blocks', [
                'ip' => $ip,
                'block_type' => $reason,
                'block_reason' => 'Rate limit exceeded',
                'first_blocked' => time(),
                'last_blocked' => time(),
                'block_count' => 1,
            ]);
        }

        // 缓存拦截信息
        $cacheKey = 'search_blocked_' . $ip;
        $this->cache->set($cacheKey, [
            'block_until' => $blockUntil,
        ], $duration);
    }

    /**
     * 获取频率限制配置
     *
     * @param string $ip IP地址
     * @return array
     */
    private function getRateLimitConfig(string $ip): array
    {
        // 从缓存获取配置
        $cacheKey = 'search_rate_limit_config';
        $configs = $this->cache->get($cacheKey);

        if ($configs === null) {
            // 从数据库加载配置
            $configs = $this->db->select('search_rate_limits', [
                'ip_range',
                'max_requests',
                'time_window',
                'block_duration',
            ], [
                'enabled' => 1,
            ]);

            // 缓存5分钟
            $this->cache->set($cacheKey, $configs, 300);
        }

        // 查找匹配的配置
        foreach ($configs as $config) {
            if ($this->ipMatchesCidr($ip, $config['ip_range'])) {
                return [
                    'max_requests' => (int)$config['max_requests'],
                    'time_window' => (int)$config['time_window'],
                    'block_duration' => (int)$config['block_duration'],
                ];
            }
        }

        // 返回默认配置
        return self::DEFAULT_CONFIG;
    }

    /**
     * 获取缓存键
     *
     * @param string $ip IP地址
     * @param int $timeWindow 时间窗口（秒）
     * @return string
     */
    private function getCacheKey(string $ip, int $timeWindow): string
    {
        // 按时间窗口分组，例如：每分钟一个计数器
        $timeSlot = floor(time() / $timeWindow);
        return "search_rate_{$ip}_{$timeSlot}";
    }

    /**
     * 清理过期的拦截记录
     *
     * @return int 清理的记录数
     */
    public function cleanupExpiredBlocks(): int
    {
        $cutoff = time() - 86400 * 90; // 保留90天

        $deleted = $this->db->delete('search_blocks', [
            'last_blocked[<]' => $cutoff,
        ]);

        // 清理缓存
        $this->cache->delete('search_ip_blacklist');
        $this->cache->delete('search_rate_limit_config');

        return $deleted;
    }

    /**
     * 获取拦截统计
     *
     * @param int $days 统计天数
     * @return array
     */
    public function getBlockStats(int $days = 7): array
    {
        $startTime = time() - $days * 86400;

        $sql = "SELECT block_type, 
                       COUNT(*) AS count, 
                       COUNT(DISTINCT ip) AS unique_ips 
                FROM search_blocks 
                WHERE last_blocked >= :start_time 
                GROUP BY block_type";

        $stmt = $this->db->pdo->prepare($sql);
        $stmt->bindParam(':start_time', $startTime, \PDO::PARAM_INT);
        $stmt->execute();
        
        $stats = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $stats;
    }
}
