<?php
declare(strict_types=1);

namespace App\Utils;

use App\Core\Security;

/**
 * 文件操作工具类
 * 提供文件操作辅助方法
 */
class FileUtil
{
    /**
     * 验证上传文件
     *
     * @param array $file 上传的文件信息 ($_FILES 数组)
     * @param array $allowedTypes 允许的 MIME 类型
     * @param int $maxSize 最大文件大小（字节）
     * @return array 验证结果 ['valid' => bool, 'error' => string]
     */
    public static function validateFile(array $file, array $allowedTypes = [], int $maxSize = 0): array
    {
        // 检查是否有上传错误
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $errorMessage = self::getUploadErrorMessage($file['error'] ?? UPLOAD_ERR_NO_FILE);
            return [
                'valid' => false,
                'error' => $errorMessage
            ];
        }

        // 检查文件是否存在
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return [
                'valid' => false,
                'error' => '文件未上传或临时文件不存在'
            ];
        }

        // 检查文件大小
        if ($maxSize > 0 && $file['size'] > $maxSize) {
            return [
                'valid' => false,
                'error' => '文件大小超过限制'
            ];
        }

        // 检查 MIME 类型
        if (!empty($allowedTypes)) {
            $mimeType = $file['type'] ?? '';

            // 如果浏览器没有提供 MIME 类型，则尝试检测
            if (empty($mimeType)) {
                $mimeType = mime_content_type($file['tmp_name']);
            }

            if (!in_array($mimeType, $allowedTypes, true)) {
                return [
                    'valid' => false,
                    'error' => '不允许的文件类型: ' . $mimeType
                ];
            }
        }

        return [
            'valid' => true,
            'error' => ''
        ];
    }

    /**
     * 清理文件名
     *
     * @param string $fileName 原始文件名
     * @param bool $preserveExtension 是否保留扩展名
     * @return string 清理后的文件名
     */
    public static function sanitizeFileName(string $fileName, bool $preserveExtension = true): string
    {
        if (empty($fileName)) {
            return '';
        }

        $cleanName = $fileName;

        if ($preserveExtension) {
            // 分离文件名和扩展名
            $pathInfo = pathinfo($fileName);
            $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
            $basename = $pathInfo['filename'];

            // 清理文件名部分
            $basename = preg_replace('/[^a-zA-Z0-9_\-\.\s]/', '_', $basename);
            $basename = trim($basename);
            $basename = preg_replace('/\s+/', '_', $basename); // 将空格替换为下划线

            $cleanName = $basename . $extension;
        } else {
            // 清理整个文件名
            $cleanName = preg_replace('/[^a-zA-Z0-9_\-\.\s]/', '_', $fileName);
            $cleanName = trim($cleanName);
            $cleanName = preg_replace('/\s+/', '_', $cleanName);
        }

        // 限制长度
        if (strlen($cleanName) > 255) {
            $cleanName = substr($cleanName, 0, 255);
        }

        return $cleanName;
    }

    /**
     * 计算下一个序列号
     *
     * @param string $table 表名
     * @param string $field 字段名
     * @param string $prefix 序列号前缀
     * @param \Medoo\Medoo $database 数据库连接对象
     * @return string 下一个序列号
     */
    public static function calculateNextSequence(string $table, string $field, string $prefix, \Medoo\Medoo $database): string
    {
        try {
            // 查询当前最大序列号
            $maxValue = $database->max($table, $field, [
                'LIKE' => $prefix . '%'
            ]);

            $sequenceNumber = 1; // 默认从1开始
            
            if ($maxValue && strpos($maxValue, $prefix) === 0) {
                // 提取数字部分
                $numericPart = substr($maxValue, strlen($prefix));
                if (is_numeric($numericPart)) {
                    $sequenceNumber = (int)$numericPart + 1;
                }
            }

            // 格式化序列号，保持固定位数（例如3位）
            return $prefix . str_pad((string)$sequenceNumber, 3, '0', STR_PAD_LEFT);
        } catch (\Exception $e) {
            // 如果查询失败，返回默认值
            return $prefix . '001';
        }
    }

    /**
     * 获取上传错误消息
     *
     * @param int $errorCode 上传错误代码
     * @return string 错误消息
     */
    public static function getUploadErrorMessage(int $errorCode): string
    {
        $messages = [
            UPLOAD_ERR_INI_SIZE => '文件大小超出服务器限制',
            UPLOAD_ERR_FORM_SIZE => '文件大小超出表单限制',
            UPLOAD_ERR_PARTIAL => '文件只有部分被上传',
            UPLOAD_ERR_NO_FILE => '没有文件被上传',
            UPLOAD_ERR_NO_TMP_DIR => '找不到临时文件夹',
            UPLOAD_ERR_CANT_WRITE => '文件写入失败',
            UPLOAD_ERR_EXTENSION => '文件上传被扩展程序中断'
        ];

        return $messages[$errorCode] ?? '未知上传错误';
    }

    /**
     * 获取文件扩展名
     *
     * @param string $fileName 文件名
     * @return string 文件扩展名（小写）
     */
    public static function getFileExtension(string $fileName): string
    {
        $pathInfo = pathinfo($fileName);
        return isset($pathInfo['extension']) ? strtolower($pathInfo['extension']) : '';
    }

    /**
     * 生成唯一文件名
     *
     * @param string $originalName 原始文件名
     * @param bool $includeTimestamp 是否包含时间戳
     * @return string 唯一文件名
     */
    public static function generateUniqueFileName(string $originalName, bool $includeTimestamp = true): string
    {
        $pathInfo = pathinfo($originalName);
        $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
        $basename = $pathInfo['filename'];

        // 清理基础文件名
        $sanitizedBasename = self::sanitizeFileName($basename, false);

        // 生成唯一标识符
        $uniquePart = uniqid();

        // 如果需要时间戳，将其加入
        $timestamp = $includeTimestamp ? date('YmdHis') . '_' : '';

        // 限制总长度
        $maxLength = 200 - strlen($extension) - strlen($timestamp) - 10; // 保留一些空间给唯一标识符
        $truncatedBasename = substr($sanitizedBasename, 0, $maxLength);

        return $timestamp . $truncatedBasename . '_' . $uniquePart . $extension;
    }
}