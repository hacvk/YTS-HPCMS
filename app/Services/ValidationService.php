<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Security;

/**
 * 验证服务类
 * 提供静态方法进行各种数据验证和清理
 */
class ValidationService
{
    /**
     * 验证必填字段
     *
     * @param array $data 待验证的数据数组
     * @param array $fields 必填字段列表
     * @return array|bool 验证成功返回 true，失败返回包含错误信息的数组
     */
    public static function validateRequired(array $data, array $fields): array|bool
    {
        $missing = [];
        foreach ($fields as $field) {
            if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            return [
                'status' => false,
                'message' => '缺少必填字段: ' . implode(', ', $missing),
                'fields' => $missing
            ];
        }

        return true;
    }

    /**
     * 验证数值范围
     *
     * @param mixed $value 待验证的值
     * @param float|int|null $min 最小值
     * @param float|int|null $max 最大值
     * @return bool 验证是否通过
     */
    public static function validateNumeric(mixed $value, float|int $min = null, float|int $max = null): bool
    {
        if (!is_numeric($value)) {
            return false;
        }

        $num = (float)$value;

        if ($min !== null && $num < $min) {
            return false;
        }

        if ($max !== null && $num > $max) {
            return false;
        }

        return true;
    }

    /**
     * 清理输入数据（防止 XSS）
     *
     * @param mixed $input 输入数据
     * @return mixed 清理后的数据
     */
    public static function sanitizeInput(mixed $input): mixed
    {
        return Security::clean($input);
    }

    /**
     * 验证文件上传
     *
     * @param array $file $_FILES 中的文件项
     * @param array $allowedExtensions 允许的扩展名列表，如 ['jpg', 'png', 'gif']
     * @param int $maxSize 最大字节数，默认 2MB
     * @return array|bool 验证成功返回 true，失败返回错误信息数组
     */
    public static function validateFileUpload(array $file, array $allowedExtensions = [], int $maxSize = 2097152): array|bool
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            return ['status' => false, 'message' => '无效的文件上传参数'];
        }

        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                return ['status' => false, 'message' => '没有文件被上传'];
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return ['status' => false, 'message' => '文件超过最大限制'];
            default:
                return ['status' => false, 'message' => '未知的文件上传错误'];
        }

        // 检查文件大小
        if ($file['size'] > $maxSize) {
            return ['status' => false, 'message' => '文件超过允许的大小'];
        }

        // 检查扩展名
        if (!empty($allowedExtensions)) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExtensions)) {
                return ['status' => false, 'message' => '不支持的文件格式: ' . $ext];
            }
        }

        return true;
    }

    /**
     * 验证URL格式
     *
     * @param string $url 待验证的URL
     * @return bool 是否为有效的URL
     */
    public static function validateUrlFormat(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}
