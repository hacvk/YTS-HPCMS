<?php
declare(strict_types=1);

namespace App\Utils;

/**
 * 日期时间工具类
 * 提供日期时间格式转换功能
 */
class DateUtil
{
    /**
     * 将前端日期格式转换为数据库格式
     *
     * @param string $date 前端日期格式（如：Y-m-d H:i:s, Y-m-d, Y-m-d\TH:i 等）
     * @return string 数据库日期格式（Y-m-d H:i:s）
     */
    public static function formatDateForDatabase(string $date): string
    {
        if (empty($date)) {
            return date('Y-m-d H:i:s');
        }

        // 处理 datetime-local 格式（Y-m-d\TH:i）
        $normalizedDate = str_replace('T', ' ', $date);
        
        // 如果没有时间部分，添加默认时间
        if (strlen($normalizedDate) === 10) { // Y-m-d 格式
            $normalizedDate .= ' 00:00:00';
        } elseif (strlen($normalizedDate) === 16) { // Y-m-d H:i 格式
            $normalizedDate .= ':00';
        }

        // 尝试解析日期
        $timestamp = strtotime($normalizedDate);
        if ($timestamp === false) {
            // 如果解析失败，返回当前时间
            return date('Y-m-d H:i:s');
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * 将数据库日期格式转换为显示格式
     *
     * @param string $date 数据库日期格式（Y-m-d H:i:s）
     * @param string $format 输出格式，默认为 Y-m-d H:i
     * @return string 显示格式的日期
     */
    public static function formatDateForDisplay(string $date, string $format = 'Y-m-d H:i'): string
    {
        if (empty($date) || $date === '0000-00-00 00:00:00') {
            return '';
        }

        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return '';
        }

        return date($format, $timestamp);
    }

    /**
     * 获取当前时间的数据库格式
     *
     * @return string 当前时间（Y-m-d H:i:s）
     */
    public static function getCurrentDateTime(): string
    {
        return date('Y-m-d H:i:s');
    }

    /**
     * 获取当前日期的数据库格式
     *
     * @return string 当前日期（Y-m-d）
     */
    public static function getCurrentDate(): string
    {
        return date('Y-m-d');
    }
}