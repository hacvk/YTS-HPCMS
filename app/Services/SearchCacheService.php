<?php
declare(strict_types=1);

namespace App\Services;

use Medoo\Medoo;

/**
 * 搜索缓存服务
 * 提供搜索结果的缓存功能，减少数据库查询
 */
class SearchCacheService
{
    /**
     * 默认缓存TTL（秒）
     */
    private const DEFAULT_TTL = 300; // 5分钟

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
     * 获取搜索结果缓存
     *
     * @param string $keyword 搜索关键词
     * @param int $page 页码
     * @param int $limit 每页数量
     * @return array|null 缓存结果或null
     */
    public function get(string $keyword, int $page = 1, int $limit = 10): ?array
    {
        $cacheKey = $this->getCacheKey($keyword, $page, $limit);

        // 1. 先从内存缓存获取
        $result = $this->cache->get($cacheKey);
        if ($result !== null) {
            // 避免因为旧缓存(尤其是空结果)导致“明明有数据却始终搜不到”
            // 有结果才直接返回；空结果让流程继续走数据库实时查询
            if (!empty($result['total']) || !empty($result['list'])) {
                return $result;
            }
        }

        // 2. 从数据库缓存获取
        $cacheData = $this->db->get('search_cache', [
            'results',
            'result_count',
            'expires_at',
        ], [
            'cache_key' => md5($cacheKey),
        ]);

        if ($cacheData && $cacheData['expires_at'] > time()) {
            // 缓存有效，同步到内存缓存
            $result = [
                'list' => json_decode($cacheData['results'], true) ?? [],
                'total' => $cacheData['result_count'],
            ];

            // 计算剩余TTL
            $ttl = $cacheData['expires_at'] - time();
            $this->cache->set($cacheKey, $result, $ttl);

            return $result;
        }

        return null;
    }

    /**
     * 设置搜索结果缓存
     *
     * @param string $keyword 搜索关键词
     * @param array $result 搜索结果
     * @param int $page 页码
     * @param int $limit 每页数量
     * @param int $ttl 过期时间（秒）
     * @return bool 是否设置成功
     */
    public function set(string $keyword, array $result, int $page = 1, int $limit = 10, int $ttl = self::DEFAULT_TTL): bool
    {
        $cacheKey = $this->getCacheKey($keyword, $page, $limit);
        $expiresAt = time() + $ttl;

        // 1. 设置内存缓存
        $this->cache->set($cacheKey, $result, $ttl);

        // 2. 设置数据库缓存
        try {
            // 检查是否已存在该缓存记录
            $existingRecord = $this->db->get('search_cache', 'id', [
                'cache_key' => md5($cacheKey)
            ]);
            
            if ($existingRecord) {
                // 如果存在，则更新
                $this->db->update('search_cache', [
                    'keyword' => $keyword,
                    'results' => json_encode($result['list'] ?? []),
                    'result_count' => $result['total'] ?? 0,
                    'expires_at' => $expiresAt,
                    'updated_at' => date('Y-m-d H:i:s'),
                ], [
                    'cache_key' => md5($cacheKey)
                ]);
            } else {
                // 如果不存在，则插入
                $this->db->insert('search_cache', [
                    'cache_key' => md5($cacheKey),
                    'keyword' => $keyword,
                    'results' => json_encode($result['list'] ?? []),
                    'result_count' => $result['total'] ?? 0,
                    'expires_at' => $expiresAt,
                ]);
            }

            return true;
        } catch (\Exception $e) {
            error_log('[SearchCacheService] 缓存写入失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 删除搜索结果缓存
     *
     * @param string $keyword 搜索关键词
     * @param int $page 页码
     * @param int $limit 每页数量
     * @return bool 是否删除成功
     */
    public function delete(string $keyword, int $page = 1, int $limit = 10): bool
    {
        $cacheKey = $this->getCacheKey($keyword, $page, $limit);

        // 1. 删除内存缓存
        $this->cache->delete($cacheKey);

        // 2. 删除数据库缓存
        try {
            $this->db->delete('search_cache', [
                'cache_key' => md5($cacheKey),
            ]);

            return true;
        } catch (\Exception $e) {
            error_log('[SearchCacheService] 缓存删除失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 清理过期的缓存
     *
     * @return int 清理的记录数
     */
    public function cleanupExpired(): int
    {
        $cutoff = time();

        try {
            $deleted = $this->db->delete('search_cache', [
                'expires_at[<]' => $cutoff,
            ]);

            // 清理内存缓存（通过CacheService的clear方法）
            // 注意：这里只清理数据库缓存，内存缓存会自动过期

            return $deleted->rowCount(); // 返回受影响的行数
        } catch (\Exception $e) {
            error_log('[SearchCacheService] 清理过期缓存失败: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * 清理指定关键词的缓存
     *
     * @param string $keyword 关键词
     * @return int 清理的记录数
     */
    public function cleanupByKeyword(string $keyword): int
    {
        try {
            $deleted = $this->db->delete('search_cache', [
                'keyword' => $keyword,
            ]);

            // 清理内存缓存（通过CacheService的clear方法）
            // 注意：这里只清理数据库缓存，内存缓存会自动过期

            return $deleted->rowCount(); // 返回受影响的行数
        } catch (\Exception $e) {
            error_log('[SearchCacheService] 清理关键词缓存失败: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * 获取缓存统计信息
     *
     * @return array
     */
    public function getStats(): array
    {
        $sql = "SELECT COUNT(*) AS total, 
                       COUNT(CASE WHEN expires_at > UNIX_TIMESTAMP() THEN 1 END) AS valid, 
                       COUNT(CASE WHEN expires_at <= UNIX_TIMESTAMP() THEN 1 END) AS expired, 
                       SUM(result_count) AS total_results, 
                       AVG(result_count) AS avg_results 
                FROM search_cache";

        $stmt = $this->db->pdo->prepare($sql);
        $stmt->execute();
        
        $stats = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $stats[0] ?? [];
    }

    /**
     * 获取热门缓存关键词
     *
     * @param int $limit 数量限制
     * @return array
     */
    public function getHotKeywords(int $limit = 10): array
    {
        $sql = "SELECT keyword, 
                       COUNT(*) AS count, 
                       MAX(created_at) AS last_used 
                FROM search_cache 
                WHERE expires_at > :current_time 
                GROUP BY keyword 
                ORDER BY count DESC 
                LIMIT :limit";

        $stmt = $this->db->pdo->prepare($sql);
        $stmt->bindParam(':current_time', time(), \PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        $keywords = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $keywords;
    }

    /**
     * 获取缓存键
     *
     * @param string $keyword 搜索关键词
     * @param int $page 页码
     * @param int $limit 每页数量
     * @return string
     */
    private function getCacheKey(string $keyword, int $page, int $limit): string
    {
        return "search_{$keyword}_{$page}_{$limit}";
    }

    /**
     * 批量清理缓存
     *
     * @param array $keywords 关键词列表
     * @return int 清理的记录数
     */
    public function cleanupBatch(array $keywords): int
    {
        $totalDeleted = 0;

        foreach ($keywords as $keyword) {
            $totalDeleted += $this->cleanupByKeyword($keyword);
        }

        return $totalDeleted;
    }

    /**
     * 预热缓存（用于热门搜索）
     *
     * @param string $keyword 关键词
     * @param array $result 搜索结果
     * @param int $ttl 过期时间（秒）
     * @return bool
     */
    public function warmup(string $keyword, array $result, int $ttl = self::DEFAULT_TTL * 2): bool
    {
        // 只缓存第一页
        return $this->set($keyword, $result, 1, 10, $ttl);
    }
}
