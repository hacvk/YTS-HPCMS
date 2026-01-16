<?php
declare(strict_types=1);

namespace App\Enum;

/**
 * 内容状态枚举
 */
enum ContentStatus: int
{
    case Draft = 0;
    case Published = 1;

    /**
     * 获取所有支持的状态值
     *
     * @return array<int, int>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * 获取所有支持的状态标签
     *
     * @return array<int, string>
     */
    public static function labels(): array
    {
        return [
            self::Draft->value => '草稿',
            self::Published->value => '已发布',
        ];
    }

    /**
     * 获取状态标签
     *
     * @return string
     */
    public function label(): string
    {
        return self::labels()[$this->value] ?? (string)$this->value;
    }
}
