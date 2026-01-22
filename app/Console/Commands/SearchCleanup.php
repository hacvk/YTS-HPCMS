<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SearchService;
use App\Services\SearchLogService;
use App\Services\SearchCacheService;
use App\Services\SearchRateLimitService;

/**
 * 搜索清理命令
 * 清理过期的搜索日志、缓存和拦截记录
 */
class SearchCleanup
{
    /**
     * 命令名称
     */
    private const COMMAND_NAME = 'search:cleanup';

    /**
     * 命令描述
     */
    private const COMMAND_DESCRIPTION = '清理过期的搜索数据（日志、缓存、拦截记录）';

    /**
     * 构造函数
     *
     * @param SearchService $searchService 搜索服务
     * @param SearchLogService $logService 搜索日志服务
     * @param SearchCacheService $cacheService 搜索缓存服务
     * @param SearchRateLimitService $rateLimitService 搜索限流服务
     */
    public function __construct(
        private readonly SearchService $searchService,
        private readonly SearchLogService $logService,
        private readonly SearchCacheService $cacheService,
        private readonly SearchRateLimitService $rateLimitService
    ) {
    }

    /**
     * 执行命令
     *
     * @param array $argv 命令行参数
     * @return int 退出码
     */
    public function execute(array $argv = []): int
    {
        $startTime = microtime(true);

        echo "=== 搜索数据清理任务开始 ===\n";
        echo "开始时间: " . date('Y-m-d H:i:s') . "\n\n";

        try {
            // 1. 清理过期的搜索日志
            echo "正在清理过期的搜索日志...\n";
            $logsDeleted = $this->logService->cleanupExpired(30);
            echo "已清理 {$logsDeleted} 条过期搜索日志\n\n";

            // 2. 清理过期的缓存
            echo "正在清理过期的缓存...\n";
            $cacheDeleted = $this->cacheService->cleanupExpired();
            echo "已清理 {$cacheDeleted} 条过期缓存\n\n";

            // 3. 清理过期的拦截记录
            echo "正在清理过期的拦截记录...\n";
            $blocksDeleted = $this->rateLimitService->cleanupExpiredBlocks();
            echo "已清理 {$blocksDeleted} 条过期拦截记录\n\n";

            // 4. 清理过期的搜索拦截记录
            echo "正在清理过期的搜索拦截记录...\n";
            $searchBlocksDeleted = $this->rateLimitService->cleanupExpiredBlocks();
            echo "已清理 {$searchBlocksDeleted} 条过期搜索拦截记录\n\n";

            // 5. 统计清理结果
            $totalDeleted = $logsDeleted + $cacheDeleted + $blocksDeleted + $searchBlocksDeleted;

            $duration = microtime(true) - $startTime;

            echo "=== 清理任务完成 ===\n";
            echo "总清理记录数: {$totalDeleted}\n";
            echo "耗时: " . round($duration, 2) . " 秒\n";
            echo "结束时间: " . date('Y-m-d H:i:s') . "\n";

            return 0;
        } catch (\\Exception $e) {
            error_log('搜索清理任务失败: ' . $e->getMessage());

            echo "=== 清理任务失败 ===\n";
            echo "错误: " . $e->getMessage() . "\n";

            return 1;
        }
    }

    /**
     * 获取命令帮助信息
     *
     * @return string
     */
    public function getHelp(): string
    {
        return <<<HELP
搜索数据清理命令

用法:
  php run.php search:cleanup

描述:
  清理过期的搜索数据，包括：
  - 过期的搜索日志（保留30天）
  - 过期的缓存
  - 过期的拦截记录

示例:
  php run.php search:cleanup

HELP;
    }

    /**
     * 获取命令名称
     *
     * @return string
     */
    public function getName(): string
    {
        return self::COMMAND_NAME;
    }

    /**
     * 获取命令描述
     *
     * @return string
     */
    public function getDescription(): string
    {
        return self::COMMAND_DESCRIPTION;
    }
}
