<?php
declare(strict_types=1);

namespace App\Utils;

use App\Core\Security;

/**
 * 字符串清理工具类
 * 提供字符串清理功能
 */
class StringSanitizer
{
    /**
     * 通用字符串清理
     *
     * @param string $input 输入字符串
     * @param array $options 清理选项
     * @return string 清理后的字符串
     */
    public static function sanitize(string $input, array $options = []): string
    {
        // 默认选项
        $defaults = [
            'trim' => true,           // 是否去除首尾空白
            'strip_tags' => false,    // 是否移除HTML标签
            'allowed_tags' => '',     // 允许的HTML标签（当strip_tags为false时有效）
            'encode_special_chars' => true, // 是否编码特殊字符
            'max_length' => 0,        // 最大长度限制，0表示不限制
            'replace_control_chars' => true, // 是否替换控制字符
        ];

        $options = array_merge($defaults, $options);

        $output = $input;

        // 替换控制字符（除了换行符和回车符）
        if ($options['replace_control_chars']) {
            $output = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', ' ', $output);
        }

        // 编码或移除特殊字符
        if ($options['encode_special_chars']) {
            $output = htmlspecialchars($output, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // 移除或允许HTML标签
        if ($options['strip_tags']) {
            $output = strip_tags($output);
        } elseif (!empty($options['allowed_tags'])) {
            $output = strip_tags($output, $options['allowed_tags']);
        }

        // 去除首尾空白
        if ($options['trim']) {
            $output = trim($output);
        }

        // 限制最大长度
        if ($options['max_length'] > 0 && strlen($output) > $options['max_length']) {
            $output = mb_substr($output, 0, $options['max_length'], 'UTF-8');
        }

        return $output;
    }

    /**
     * HTML内容清理（包装 Security::sanitizeHtml）
     *
     * @param string $html HTML内容
     * @param array $allowedTags 允许的HTML标签
     * @return string 清理后的HTML内容
     */
    public static function sanitizeHtml(string $html, array $allowedTags = []): string
    {
        // 如果没有指定允许的标签，使用默认的安全标签
        if (empty($allowedTags)) {
            $allowedTags = [
                'p', 'br', 'strong', 'em', 'u', 'ol', 'ul', 'li', 
                'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
                'blockquote', 'pre', 'code', 'hr',
                'a', 'img', 'div', 'span', 'table', 'thead', 'tbody', 
                'tr', 'th', 'td', 'caption', 'colgroup', 'col'
            ];
        }

        // 使用 Security 类进行HTML清理
        return Security::sanitizeHtml($html, $allowedTags);
    }

    /**
     * 清理SQL注入风险的字符串
     *
     * @param string $input 输入字符串
     * @return string 清理后的字符串
     */
    public static function sanitizeForSql(string $input): string
    {
        // 移除潜在的SQL注入字符
        $dangerousChars = [
            "'", '"', '\\', ';', '--', '/*', '*/', 'xp_', 'sp_',
            'exec', 'execute', 'select', 'insert', 'update', 'delete',
            'drop', 'create', 'alter', 'union', 'order', 'by', 'group',
            'having', 'distinct', 'truncate', 'replace', 'declare', 'cast',
            'convert', 'call', 'eval', 'system', 'shell'
        ];

        $cleanInput = $input;
        foreach ($dangerousChars as $char) {
            // 注意：这里仅作为额外保护，实际SQL查询应该使用参数化查询
            $cleanInput = str_ireplace($char, '', $cleanInput);
        }

        // 使用通用清理方法进一步处理
        return self::sanitize($cleanInput, [
            'strip_tags' => true,
            'encode_special_chars' => true
        ]);
    }

    /**
     * 清理用于文件名的字符串
     *
     * @param string $input 输入字符串
     * @return string 适合用作文件名的字符串
     */
    public static function sanitizeForFilename(string $input): string
    {
        // 移除文件系统不允许的字符
        $forbiddenChars = ['<', '>', ':', '"', '/', '\\', '|', '?', '*'];
        $cleanInput = str_replace($forbiddenChars, '_', $input);

        // 使用通用清理方法进一步处理
        return self::sanitize($cleanInput, [
            'strip_tags' => true,
            'encode_special_chars' => false, // 文件名不需要HTML实体编码
            'max_length' => 255 // 文件名长度限制
        ]);
    }

    /**
     * 清理用于URL的字符串
     *
     * @param string $input 输入字符串
     * @param string $separator 分隔符，默认为短横线
     * @return string 适合用作URL的字符串
     */
    public static function sanitizeForUrl(string $input, string $separator = '-'): string
    {
        // 转换为小写
        $cleanInput = mb_strtolower($input, 'UTF-8');

        // 移除非字母数字字符，将其替换为分隔符
        $cleanInput = preg_replace('/[^a-z0-9\s-]/', $separator, $cleanInput);

        // 将空格替换为分隔符
        $cleanInput = preg_replace('/\s+/', $separator, $cleanInput);

        // 移除连续的分隔符
        $cleanInput = preg_replace('/' . preg_quote($separator, '/') . '+/', $separator, $cleanInput);

        // 移除首尾的分隔符
        $cleanInput = trim($cleanInput, $separator);

        // 使用通用清理方法进一步处理
        return self::sanitize($cleanInput, [
            'trim' => true,
            'strip_tags' => true,
            'encode_special_chars' => false, // URL不需要HTML实体编码
            'max_length' => 200 // URL段长度限制
        ]);
    }

    /**
     * 清理电子邮件地址
     *
     * @param string $email 电子邮件地址
     * @return string 清理后的电子邮件地址
     */
    public static function sanitizeEmail(string $email): string
    {
        // 去除首尾空白并转换为小写
        $cleanEmail = trim(strtolower($email));

        // 使用PHP内置函数验证和清理
        $cleanEmail = filter_var($cleanEmail, FILTER_SANITIZE_EMAIL);

        return $cleanEmail ?: '';
    }
}