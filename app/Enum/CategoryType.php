<?php
declare(strict_types=1);

namespace App\Enum;

/**
 * 栏目类型枚举
 */
enum CategoryType: string
{
    case Article = 'article';
    case Page = 'page';
    case Pic = 'pic';
    case Doctor = 'doctor';

    /**
     * 获取所有支持的类型值
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * 获取所有支持的类型标签
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::Article->value => '文章',
            self::Page->value => '单页',
            self::Pic->value => '图文',
            self::Doctor->value => '专家',
        ];
    }

    /**
     * 获取类型标签
     *
     * @return string
     */
    public function label(): string
    {
        return self::labels()[$this->value] ?? $this->value;
    }
}
