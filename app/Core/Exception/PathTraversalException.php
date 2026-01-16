<?php
declare(strict_types=1);

namespace App\Core\Exception;

/**
 * 路径遍历异常
 * 当检测到路径遍历攻击时抛出
 */
class PathTraversalException extends \RuntimeException
{
}
