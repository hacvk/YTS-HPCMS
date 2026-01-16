<?php
declare(strict_types=1);

namespace App\Core\Exception;

/**
 * 数据库操作异常
 * 用于替代返回 false 的情况，提供更清晰的错误信息
 */
class DatabaseOperationException extends \RuntimeException
{
}
