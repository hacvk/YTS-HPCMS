<?php
declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\Response;

/**
 * 响应上下文：用于在控制器内部记录 Response
 */
final class ResponseContext
{
    private static ?Response $response = null;

    public static function set(Response $response): void
    {
        self::$response = $response;
    }

    public static function get(): ?Response
    {
        return self::$response;
    }

    public static function clear(): void
    {
        self::$response = null;
    }

    public static function consume(): ?Response
    {
        $response = self::$response;
        self::$response = null;
        return $response;
    }
}
