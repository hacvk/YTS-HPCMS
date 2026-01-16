<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\File;

/**
 * 文件服务类
 * 处理文件上传、验证、移动、删除等核心逻辑
 */
class FileService
{
    /**
     * 默认允许的文件类型
     *
     * @var array<int, string>
     */
    private const DEFAULT_ALLOWED_TYPES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif',
        'image/webp'
    ];

    /**
     * 默认最大文件大小（5MB）
     */
    private const DEFAULT_MAX_SIZE = 5 * 1024 * 1024;

    /**
     * 上传目录基础路径（相对于 public 目录）
     */
    private const UPLOAD_BASE_DIR = 'uploads';

    /**
     * 处理文件上传
     *
     * @param array<string, mixed> $uploadedFile $_FILES['file'] 数组
     * @param array<string, mixed> $options 上传选项
     *   - module: string 模块标识（'setting', 'banner' 等）
     *   - related_type: string 关联类型
     *   - related_id: int 关联ID
     *   - file_usage: string 文件用途
     *   - is_primary: bool 是否为主图
     *   - uploaded_by: int 上传者ID
     *   - allowed_types: array<int, string> 允许的文件类型（可选）
     *   - max_size: int 最大文件大小（可选）
     * @return array{success: bool, file_id?: int, path?: string, url?: string, message?: string, existing_file?: array<string, mixed>}
     * @throws \RuntimeException 当文件处理失败时抛出异常
     */
    public static function handleUpload(array $uploadedFile, array $options = []): array
    {
        // 验证上传文件
        $validation = self::validateUploadedFile($uploadedFile, $options);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => $validation['message'] ?? '文件验证失败'
            ];
        }

        // 计算文件哈希
        $fileHash = self::calculateFileHash($uploadedFile['tmp_name']);

        // 检查文件是否已存在
        $existingFile = File::getByHash($fileHash);
        if ($existingFile) {
            $fileUrl = self::getFileUrl($existingFile['path']);
            return [
                'success' => true,
                'file_id' => (int)$existingFile['id'],
                'path' => $existingFile['path'],
                'url' => $fileUrl,
                'existing_file' => $existingFile,
                'message' => '文件已存在，返回已有文件'
            ];
        }

        // 计算文件路径
        $pathInfo = self::calculateFilePath($uploadedFile, $fileHash, $options);
        
        // 对于 setting 模块，如果文件已存在，先删除旧文件
        $module = $options['module'] ?? '';
        if ($module === 'setting' && file_exists($pathInfo['full_path'])) {
            self::deletePhysicalFileByFullPath($pathInfo['full_path']);
        }
        
        // 创建目录
        if (!self::ensureDirectoryExists($pathInfo['full_path'])) {
            return [
                'success' => false,
                'message' => '创建目录失败'
            ];
        }

        // 移动文件
        if (!self::moveUploadedFile($uploadedFile['tmp_name'], $pathInfo['full_path'])) {
            return [
                'success' => false,
                'message' => '文件保存失败'
            ];
        }

        // 准备数据库记录数据
        $fileData = [
            'hash' => $fileHash,
            'original_name' => $uploadedFile['name'],
            'path' => $pathInfo['relative_path'],
            'mime_type' => $pathInfo['mime_type'],
            'size' => $uploadedFile['size'],
            'uploaded_by' => $options['uploaded_by'] ?? 0,
            'created_at' => date('Y-m-d H:i:s')
        ];

        // 添加关联信息（如果提供）
        if (!empty($options['related_type']) && !empty($options['related_id'])) {
            $fileData['related_type'] = $options['related_type'];
            $fileData['related_id'] = (int)$options['related_id'];
            $fileData['file_usage'] = $options['file_usage'] ?? 'content';
            $fileData['is_primary'] = ($options['is_primary'] ?? false) ? 1 : 0;
        }

        // 保存到数据库
        $fileId = File::save($fileData);
        if (!$fileId) {
            // 如果数据库保存失败，删除已上传的文件
            self::deletePhysicalFile($pathInfo['full_path']);
            return [
                'success' => false,
                'message' => '文件记录保存失败'
            ];
        }

        $fileUrl = self::getFileUrl($pathInfo['relative_path']);

        return [
            'success' => true,
            'file_id' => $fileId,
            'path' => $pathInfo['relative_path'],
            'url' => $fileUrl
        ];
    }

    /**
     * 验证上传的文件
     *
     * @param array<string, mixed> $uploadedFile $_FILES['file'] 数组
     * @param array<string, mixed> $options 选项
     * @return array{valid: bool, message?: string}
     */
    public static function validateUploadedFile(array $uploadedFile, array $options = []): array
    {
        // 检查文件是否上传成功
        $errorCode = (int)($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode !== UPLOAD_ERR_OK) {
            return [
                'valid' => false,
                'message' => self::getUploadErrorMessage($errorCode)
            ];
        }

        // 验证文件类型
        $allowedTypes = $options['allowed_types'] ?? self::DEFAULT_ALLOWED_TYPES;
        $mimeType = mime_content_type($uploadedFile['tmp_name']);
        if (!in_array($mimeType, $allowedTypes, true)) {
            return [
                'valid' => false,
                'message' => '不支持的文件类型，仅支持：' . implode(', ', $allowedTypes)
            ];
        }

        // 验证文件大小
        $maxSize = $options['max_size'] ?? self::DEFAULT_MAX_SIZE;
        if ($uploadedFile['size'] > $maxSize) {
            $maxSizeMB = round($maxSize / 1024 / 1024, 2);
            return [
                'valid' => false,
                'message' => "文件大小超过限制，最大 {$maxSizeMB}MB"
            ];
        }

        return ['valid' => true];
    }

    /**
     * 计算文件哈希
     *
     * @param string $filePath 文件路径
     * @return string
     */
    public static function calculateFileHash(string $filePath): string
    {
        return hash_file('sha256', $filePath);
    }

    /**
     * 计算文件路径
     *
     * @param array<string, mixed> $uploadedFile 上传的文件信息
     * @param string $fileHash 文件哈希
     * @param array<string, mixed> $options 选项
     * @return array{full_path: string, relative_path: string, mime_type: string}
     */
    public static function calculateFilePath(array $uploadedFile, string $fileHash, array $options = []): array
    {
        $baseDir = self::getUploadBaseDir();
        $module = $options['module'] ?? '';
        $fileExtension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
        $mimeType = mime_content_type($uploadedFile['tmp_name']);

        $relativePath = '';
        $fileName = '';

        if ($module === 'setting') {
            // Setting 模块：保存为 logo.{ext}
            $fileName = 'images/logo.' . $fileExtension;
            $relativePath = self::UPLOAD_BASE_DIR . '/' . $fileName;
        } elseif ($module === 'banner') {
            // Banner 模块：保存为 banner{随机数}.{ext}
            $randomNumber = mt_rand(100000, 999999);
            $fileName = 'images/banner' . $randomNumber . '.' . $fileExtension;
            $relativePath = self::UPLOAD_BASE_DIR . '/' . $fileName;
        } else {
            // 默认：使用原来的按日期+hash命名方式
            $fileName = date('Y/m/d') . '/' . $fileHash . '.' . $fileExtension;
            $relativePath = self::UPLOAD_BASE_DIR . '/' . $fileName;
        }

        $fullPath = $baseDir . '/' . $fileName;

        return [
            'full_path' => $fullPath,
            'relative_path' => $relativePath,
            'mime_type' => $mimeType
        ];
    }

    /**
     * 确保目录存在
     *
     * @param string $filePath 文件完整路径
     * @return bool
     */
    public static function ensureDirectoryExists(string $filePath): bool
    {
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            return mkdir($dir, 0755, true);
        }
        return true;
    }

    /**
     * 移动上传的文件
     *
     * @param string $tmpPath 临时文件路径
     * @param string $targetPath 目标路径
     * @return bool
     */
    public static function moveUploadedFile(string $tmpPath, string $targetPath): bool
    {
        return move_uploaded_file($tmpPath, $targetPath);
    }

    /**
     * 删除物理文件
     *
     * @param string $relativePath 文件相对路径（相对于 public 目录）
     * @return bool
     */
    public static function deletePhysicalFile(string $relativePath): bool
    {
        $publicDir = dirname(__DIR__, 2) . '/public';
        $filePath = $publicDir . '/' . ltrim($relativePath, '/');
        
        if (file_exists($filePath)) {
            return @unlink($filePath);
        }
        
        return true; // 文件不存在，视为删除成功
    }

    /**
     * 删除物理文件（通过完整路径）
     *
     * @param string $fullPath 文件完整路径
     * @return bool
     */
    public static function deletePhysicalFileByFullPath(string $fullPath): bool
    {
        if (file_exists($fullPath)) {
            return @unlink($fullPath);
        }
        
        return true; // 文件不存在，视为删除成功
    }

    /**
     * 获取文件访问URL
     *
     * @param string $relativePath 文件相对路径
     * @return string
     */
    public static function getFileUrl(string $relativePath): string
    {
        return '/uploads/' . ltrim(str_replace('uploads/', '', $relativePath), '/');
    }

    /**
     * 获取上传基础目录
     *
     * @return string
     */
    public static function getUploadBaseDir(): string
    {
        return dirname(__DIR__, 2) . '/public/' . self::UPLOAD_BASE_DIR;
    }

    /**
     * 获取上传错误信息
     *
     * @param int $errorCode 错误代码
     * @return string
     */
    public static function getUploadErrorMessage(int $errorCode): string
    {
        $messages = [
            UPLOAD_ERR_INI_SIZE => '文件大小超过服务器限制',
            UPLOAD_ERR_FORM_SIZE => '文件大小超过表单限制',
            UPLOAD_ERR_PARTIAL => '文件只有部分被上传',
            UPLOAD_ERR_NO_FILE => '没有文件被上传',
            UPLOAD_ERR_NO_TMP_DIR => '找不到临时文件夹',
            UPLOAD_ERR_CANT_WRITE => '文件写入失败',
            UPLOAD_ERR_EXTENSION => '文件上传被扩展程序阻止',
        ];

        return $messages[$errorCode] ?? '未知错误';
    }
}
