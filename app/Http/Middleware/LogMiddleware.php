<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\LogService;

/**
 * 日志中间件
 * 记录请求和响应信息
 */
class LogMiddleware
{
    /**
     * 处理请求
     *
     * @param callable $next 下一个中间件或请求处理函数
     * @return mixed
     */
    public function handle(callable $next)
    {
        // 生成请求ID
        $requestId = uniqid('req_', true);
        $_SERVER['REQUEST_ID'] = $requestId;
        
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        
        try {
            // 执行请求
            $response = $next();
            
            $statusCode = http_response_code();
            $level = $statusCode >= 500 ? LogService::LEVEL_ERROR : ($statusCode >= 400 ? LogService::LEVEL_WARNING : LogService::LEVEL_INFO);
            
            // 记录请求日志
            LogService::record(
                0,
                'system',
                'request',
                $_SERVER['REQUEST_URI'] ?? '',
                sprintf('Request processed: %s %s %d', $_SERVER['REQUEST_METHOD'] ?? '', $_SERVER['REQUEST_URI'] ?? '', $statusCode),
                $level,
                null,
                null,
                null
            );
            
            return $response;
        } catch (\Throwable $e) {
            // 记录异常日志
            LogService::error($e, 'request_failed');
            
            throw $e;
        }
    }
}