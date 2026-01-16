<?php
declare(strict_types=1);

namespace App\Core\Exception;

/**
 * 记录未找到异常
 * 用于替代返回 false 或 null 的情况
 */
class RecordNotFoundException extends \RuntimeException
{
}
