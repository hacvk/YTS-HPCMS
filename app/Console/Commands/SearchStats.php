<?php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SearchService;
use App\Services\SearchLogService;

/**
 * 搜索统计命令
 * 生成搜索统计报告
 */
class SearchStats
{
    /**
     * 命令名称
     */
    private const COMMAND_NAME = 'search:stats';

    /**
     * 命令描述
     */
    private const COMMAND_DESCRIPTION = '生成搜索统计报告';

    /**
     * 构造函数
     *
     * @param SearchService $searchService 搜索服务
     * @param SearchLogService $logService 搜索日志服务
     */
    public function __construct(
        private readonly SearchService $searchService,
        private readonly SearchLogService $logService
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

        echo "=== 搜索统计报告 ===\n";
        echo "生成时间: " . date('Y-m-d H:i:s') . "\n\n";

        try {
            // 1. 获取最近7天的统计
            echo "=== 最近7天统计 ===\n";
            $stats7 = $this->searchService->getStats(7);

            foreach ($stats7 as $stat) {
                echo "日期: {$stat['date']}\n";
                echo "  总搜索次数: {$stat['total_searches']}\n";
                echo "  独立用户数: {$stat['unique_searchers']}\n";
                echo "  被拦截次数: {$stat['blocked_searches']}\n";
                echo "  平均结果数: {$stat['avg_results']}\n";
                echo "\n";
            }

            // 2. 获取搜索成功率
            echo "=== 搜索成功率 ===\n";
            $successRate = $this->searchService->getSuccessRate(7);
            echo "总搜索次数: {$successRate['total']}\n";
            echo "成功次数: {$successRate['success']}\n";
            echo "被拦截次数: {$successRate['blocked']}\n";
            echo "失败次数: {$successRate['failed']}\n";
            echo "成功率: {$successRate['success_rate']}%\n";
            echo "拦截率: {$successRate['blocked_rate']}%\n";
            echo "失败率: {$successRate['failed_rate']}%\n\n";

            // 3. 获取热门搜索关键词
            echo "=== 热门搜索关键词（最近7天） ===\n";
            $hotKeywords = $this->searchService->getHotKeywords(7, 10);

            if (empty($hotKeywords)) {
                echo "暂无数据\n";
            } else {
                foreach ($hotKeywords as $index => $keyword) {
                    echo ($index + 1) . ". {$keyword['keyword']} - {$keyword['count']} 次\n";
                }
            }
            echo "\n";

            // 4. 获取热门搜索IP
            echo "=== 热门搜索IP（最近7天） ===\n";
            $topIps = $this->logService->getTopIps(7, 10);

            if (empty($topIps)) {
                echo "暂无数据\n";
            } else {
                foreach ($topIps as $index => $ip) {
                    echo ($index + 1) . ". {$ip['ip']} - {$ip['count']} 次\n";
                }
            }
            echo "\n";

            // 5. 获取拦截统计
            echo "=== 拦截统计 ===\n";
            $blockStats = $this->rateLimitService->getBlockStats(7);

            if (empty($blockStats)) {
                echo "暂无数据\n";
            } else {
                foreach ($blockStats as $stat) {
                    echo "类型: {$stat['block_type']}\n";
                    echo "  拦截次数: {$stat['count']}\n";
                    echo "  独立IP数: {$stat['unique_ips']}\n";
                    echo "\n";
                }
            }

            $duration = microtime(true) - $startTime;

            echo "=== 统计报告完成 ===\n";
            echo "耗时: " . round($duration, 2) . " 秒\n";

            return 0;
        } catch (\\Exception $e) {
            error_log('搜索统计任务失败: ' . $e->getMessage());

            echo "=== 统计任务失败 ===\n";
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
搜索统计命令

用法:
  php run.php search:stats

描述:
  生成搜索统计报告，包括：
  - 最近7天的搜索统计
  - 搜索成功率
  - 热门搜索关键词
  - 热门搜索IP
  - 拦截统计

示例:
  php run.php search:stats

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
