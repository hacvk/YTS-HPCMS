<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\File;
use App\Core\Database;

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
     * MIME 类型到允许扩展名的映射
     * 用于双重验证：即使MIME 类型正确，扩展名也必须匹配
     *
     * @var array<string, array<int, string>>
     */
    private const MIME_TO_EXTENSIONS = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/jpg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/gif' => ['gif'],
        'image/webp' => ['webp'],
    ];

    /**
     * 禁止的扩展名（无论MIME 类型如何，这些扩展名都必须拒绝）
     * 包括可执行文件、脚本文件等危险扩展名
     *
     * @var array<int, string>
     */
    private const FORBIDDEN_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'phtml', 'phps',
        'exe', 'bat', 'cmd', 'com', 'scr', 'vbs', 'js',
        'jsp', 'asp', 'aspx', 'cgi', 'pl', 'sh', 'py',
        'rb', 'jar', 'war', 'class', 'dll', 'so',
        'svg', // 明确禁止 SVG（可能包含 XSS 向量）
        'html', 'htm', 'xml', 'xhtml',
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
     * 安全特性：
     * - 双重验证：MIME 类型 + 扩展名白名单
     * - 禁止危险扩展名（包括 SVG、脚本文件等）
     * - 文件名清理和路径验证
     * - 只返回相对路径，绝不暴露服务器绝对路径
     *
     * @param array<string, mixed> $uploadedFile $_FILES['file'] 数组
     * @param array<string, mixed> $options 上传选项
     *   - module: string 模块标识（setting', 'banner' 等）
     *   - related_type: string 关联类型
     *   - related_id: int 关联ID
     *   - file_usage: string 文件用途
     *   - is_primary: bool 是否为主
     *   - uploaded_by: int 上传者ID
     *   - allowed_types: array<int, string> 允许的文件类型（可选）
     *   - max_size: int 最大文件大小（可选）
     * @return array{success: bool, file_id?: int, path?: string, url?: string, message?: string, existing_file?: array<string, mixed>}
     *   注意：path & url 都是相对路径，不会暴露服务器绝对路径
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
        $fileModel = new File(Database::get());
        $existingFile = $fileModel->getByHash($fileHash);
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

        // WebP 转换：仅对非 setting/category/banner 模块生效
        $noWebpModules = ['setting', 'category', 'banner'];
        $shouldConvertToWebp = !in_array($module, $noWebpModules, true);

        $isImage = str_starts_with($pathInfo['mime_type'], 'image/');
        $webpSupported = function_exists('imagewebp') && function_exists('imagecreatefromjpeg');

        $converted = false;

        if ($shouldConvertToWebp && $isImage && $webpSupported) {
            // 内存保护：超大图直接跳过转换
            $imgInfo = @getimagesize($uploadedFile['tmp_name']);
            if (is_array($imgInfo)) {
                $w = (int)($imgInfo[0] ?? 0);
                $h = (int)($imgInfo[1] ?? 0);
                if ($w > 5000 || $h > 5000) {
                    $converted = false;
                } else {
                    try {
                        $sourceImage = false;
                        $imgType = (int)($imgInfo[2] ?? 0);

                        if ($imgType === IMAGETYPE_JPEG) {
                            $sourceImage = imagecreatefromjpeg($uploadedFile['tmp_name']);
                        } elseif ($imgType === IMAGETYPE_PNG) {
                            $sourceImage = imagecreatefrompng($uploadedFile['tmp_name']);
                            if ($sourceImage !== false) {
                                imagepalettetotruecolor($sourceImage);
                                // 关键：必须关闭 alpha blending，才能保留透明背景
                                imagealphablending($sourceImage, false);
                                imagesavealpha($sourceImage, true);
                            }
                        } elseif ($imgType === IMAGETYPE_GIF) {
                            $sourceImage = imagecreatefromgif($uploadedFile['tmp_name']);
                        } else {
                            $sourceImage = false;
                        }

                        if ($sourceImage !== false) {
                            $newFullPath = preg_replace('/\.[^.\/]+$/', '.webp', $pathInfo['full_path']);
                            $newRelativePath = preg_replace('/\.[^.\/]+$/', '.webp', $pathInfo['relative_path']);

                            $ok = imagewebp($sourceImage, $newFullPath, 80);
                            imagedestroy($sourceImage);

                            if ($ok && is_file($newFullPath)) {
                                $pathInfo['full_path'] = $newFullPath;
                                $pathInfo['relative_path'] = $newRelativePath;
                                $pathInfo['mime_type'] = 'image/webp';
                                $uploadedFile['size'] = (int)filesize($newFullPath);
                                $converted = true;
                            } else {
                                throw new \RuntimeException('imagewebp returned false');
                            }
                        }
                    } catch (\Throwable $e) {
                        error_log('[FileService] WebP conversion failed: ' . $e->getMessage());
                        $converted = false;
                    }
                }
            }
        }

        // 未转换则保存原文件
        if (!$converted) {
            if (!self::moveUploadedFile($uploadedFile['tmp_name'], $pathInfo['full_path'])) {
                return ['success' => false, 'message' => '文件保存失败'];
            }
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
        $fileId = $fileModel->insert($fileData);
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

        // 获取文件扩展名（小写）
        $originalName = $uploadedFile['name'] ?? '';
        $fileExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        // 第一步：检查禁止的扩展名（最严格的检查）
        if (in_array($fileExtension, self::FORBIDDEN_EXTENSIONS, true)) {
            return [
                'valid' => false,
                'message' => '不允许的文件扩展名：' . $fileExtension
            ];
        }

        // 第二步：验证 MIME 类型
        $allowedTypes = $options['allowed_types'] ?? self::DEFAULT_ALLOWED_TYPES;
        $mimeType = mime_content_type($uploadedFile['tmp_name']);
        if (!in_array($mimeType, $allowedTypes, true)) {
            return [
                'valid' => false,
                'message' => '不支持的文件类型，仅支持 ' . implode(', ', $allowedTypes)
            ];
        }

        // 第三步：验证扩展名是否与 MIME 类型匹配（双重验证）
        $allowedExtensions = self::MIME_TO_EXTENSIONS[$mimeType] ?? [];
        if (empty($allowedExtensions)) {
            // 如果 MIME 类型不在映射中，拒绝（安全第一）
            return [
                'valid' => false,
                'message' => '无法验证文件类型: ' . $mimeType
            ];
        }

        if (!in_array($fileExtension, $allowedExtensions, true)) {
            return [
                'valid' => false,
                'message' => "文件扩展名: {$fileExtension} 与文件类型: {$mimeType} 不匹配"
            ];
        }

        // 第四步：验证文件大小
        $maxSize = $options['max_size'] ?? self::DEFAULT_MAX_SIZE;
        if ($uploadedFile['size'] > $maxSize) {
            $maxSizeMB = round($maxSize / 1024 / 1024, 2);
            return [
                'valid' => false,
                'message' => "文件大小超过限制，最大: {$maxSizeMB}MB"
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
     * 清理文件名，移除危险字符
     * 注意：此方法仅用于清理最终文件名部分，不处理路径分隔符
     *
     * @param string $fileName 原始文件名（不包含路径）
     * @return string 清理后的文件名
     */
    private static function sanitizeFileName(string $fileName): string
    {
        // 移除路径分隔符（防止路径遍历攻击）
        $fileName = basename($fileName);
        // 移除所有非字母数字、点、下划线、连字符、斜杠的字符
        // 保留斜杠用于日期路径结构（如 2024/01/15）
        $fileName = preg_replace('/[^a-zA-Z0-9._\-\/]/', '', $fileName);
        // 移除开头的点和下划线（防止隐藏文件）
        $fileName = ltrim($fileName, '._');
        // 限制文件名长度
        if (strlen($fileName) > 255) {
            $fileName = substr($fileName, 0, 255);
        }
        return $fileName;
    }

    /**
     * 清理文件扩展名
     *
     * @param string $extension 原始扩展名
     * @return string 清理后的扩展名
     */
    private static function sanitizeExtension(string $extension): string
    {
        // 移除所有非字母数字的字符
        $extension = preg_replace('/[^a-zA-Z0-9]/', '', $extension);
        // 限制扩展名长度（通常不超过10 个字符）
        if (strlen($extension) > 10) {
            $extension = substr($extension, 0, 10);
        }
        return strtolower($extension);
    }

    /**
     * 计算文件路径
     * 
     * 安全说明：
     * - full_path: 仅用于内部文件操作，不会返回给调用者
     * - relative_path: 返回给调用者的相对路径（相对于 public 目录）
     * - 所有返回给外部的方法只返回相对路径&URL，绝不暴露绝对路径
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
        
        // 获取并清理扩展名
        $originalExtension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
        $fileExtension = self::sanitizeExtension($originalExtension);
        
        // 验证扩展名是否仍然有效（清理后可能为空）
        if (empty($fileExtension)) {
            throw new \RuntimeException('无效的文件扩展名');
        }
        
        $mimeType = mime_content_type($uploadedFile['tmp_name']);

        $relativePath = '';
        $fileName = '';

        if ($module === 'setting') {
            // Setting 模块：根据字段类型决定文件名
            $fieldName = $options['field_name'] ?? 'logo';
            if ($fieldName === 'site_favicon') {
                $fileName = 'images/favicon.' . $fileExtension;
            } else {
                $fileName = 'images/logo.' . $fileExtension;
            }
            $relativePath = self::UPLOAD_BASE_DIR . '/' . $fileName;
        } elseif ($module === 'banner') {
            // Banner 模块：保存为 banner{随机数}.{ext}（使用清理后的扩展名）
            $randomNumber = mt_rand(100000, 999999);
            $fileName = 'images/banner' . $randomNumber . '.' . $fileExtension;
            $relativePath = self::UPLOAD_BASE_DIR . '/' . $fileName;
        } else {
            // 默认：使用原来的按日hash命名方式（使用清理后的扩展名）
            // 使用哈希作为文件名，确保安全
            // 日期路径格式：YYYY/MM/DD/hash.ext
            $datePath = date('Y/m/d');
            $fileName = $datePath . '/' . $fileHash . '.' . $fileExtension;
            $relativePath = self::UPLOAD_BASE_DIR . '/' . $fileName;
        }

        // 验证路径安全性（防止路径遍历）
        if (strpos($fileName, '..') !== false || strpos($fileName, "\0") !== false) {
            throw new \RuntimeException('检测到不安全的路径字符');
        }

        // full_path 仅用于内部文件操作，不会返回给调用者
        $fullPath = $baseDir . '/' . $fileName;

        return [
            'full_path' => $fullPath,        // 内部使用，不暴露
            'relative_path' => $relativePath, // 返回给调用者
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
     * 删除物理文件（带路径遍历防御）
     *
     * @param string $relativePath 文件相对路径（相对于 public 目录）
     * @return bool
     * @throws \App\Core\Exception\PathTraversalException 当检测到路径遍历攻击时抛出
     */
    public static function deletePhysicalFile(string $relativePath): bool
    {
        $publicDir = dirname(__DIR__, 2) . '/public';
        $uploadsDir = $publicDir . '/' . self::UPLOAD_BASE_DIR;
        
        // 清理路径，移除多余的斜杠和点
        $relativePath = ltrim($relativePath, '/');
        
        // 构建完整路径
        $filePath = $publicDir . '/' . $relativePath;
        
        // 使用 realpath 解析路径，防止路径遍历攻击
        $realPath = realpath($filePath);
        if ($realPath === false) {
            // 文件不存在，视为删除成功
            return true;
        }
        
        // 确保解析后的路径在允许的目录下（必须是public/uploads 或其子目录）
        $allowedBaseDir = realpath($uploadsDir);
        if ($allowedBaseDir === false) {
            throw new \RuntimeException('上传目录不存在或无法访问: ' . $uploadsDir);
        }
        
        // 验证路径：必须位public/uploads 目录
        if (strpos($realPath, $allowedBaseDir) !== 0) {
            throw new \App\Core\Exception\PathTraversalException(
                '路径遍历攻击检测：尝试访问不允许的目录。路径 ' . $relativePath
            );
        }
        
        // 安全删除文件
        if (file_exists($realPath) && is_file($realPath)) {
            return @unlink($realPath);
        }
        
        return true; // 文件不存在，视为删除成功
    }

    /**
     * 删除物理文件（通过完整路径，带路径遍历防御）
     *
     * @param string $fullPath 文件完整路径
     * @return bool
     * @throws \App\Core\Exception\PathTraversalException 当检测到路径遍历攻击时抛出
     */
    public static function deletePhysicalFileByFullPath(string $fullPath): bool
    {
        $publicDir = dirname(__DIR__, 2) . '/public';
        $uploadsDir = $publicDir . '/' . self::UPLOAD_BASE_DIR;
        
        // 使用 realpath 解析路径，防止路径遍历攻击
        $realPath = realpath($fullPath);
        if ($realPath === false) {
            // 文件不存在，视为删除成功
            return true;
        }
        
        // 确保解析后的路径在允许的目录下（必须是public/uploads 或其子目录）
        $allowedBaseDir = realpath($uploadsDir);
        if ($allowedBaseDir === false) {
            throw new \RuntimeException('上传目录不存在或无法访问: ' . $uploadsDir);
        }
        
        // 验证路径：必须位public/uploads 目录
        if (strpos($realPath, $allowedBaseDir) !== 0) {
            throw new \App\Core\Exception\PathTraversalException(
                '路径遍历攻击检测：尝试访问不允许的目录。路径 ' . $fullPath
            );
        }
        
        // 安全删除文件
        if (file_exists($realPath) && is_file($realPath)) {
            return @unlink($realPath);
        }
        
        return true; // 文件不存在，视为删除成功
    }

    /**
     * 删除文件（包含数据库记录和物理文件）
     * 事务一致性：先删除数据库记录，成功后再删除物理文件
     * 如果物理文件删除失败，记录日志但不影响数据库删除结果
     *
     * @param int $fileId 文件ID
     * @param \App\Models\File $fileModel 文件模型实例
     * @param \Medoo\Medoo $db 数据库实例（用于事务）
     * @return bool 删除是否成功
     * @throws \App\Core\Exception\PathTraversalException 当检测到路径遍历攻击时抛出
     * @throws \Exception 当数据库操作失败时抛出
     */
    public static function deleteFile(int $fileId, \App\Models\File $fileModel, \Medoo\Medoo $db): bool
    {
        // 先获取文件信息（在事务外，避免锁定）
        $file = $fileModel->getById($fileId);
        if (!$file) {
            // 文件记录不存在，视为删除成功
            return true;
        }
        
        $relativePath = $file['path'] ?? '';
        if (empty($relativePath)) {
            // 没有物理文件路径，只删除数据库记录
            return $fileModel->delete($fileId);
        }
        
        // 使用数据库事务确保一致性
        try {
            $db->action(function ($db) use ($fileId, $fileModel, $relativePath) {
                // 第一步：删除数据库记录
                $deleteResult = $fileModel->delete($fileId);
                if (!$deleteResult) {
                    throw new \RuntimeException('删除数据库记录失败');
                }
                
                // 第二步：数据库删除成功后，删除物理文件
                // 注意：即使物理文件删除失败，也不回滚数据库删除
                // 因为文件可能已经被手动删除或移动
                try {
                    self::deletePhysicalFile($relativePath);
                } catch (\App\Core\Exception\PathTraversalException $e) {
                    // 路径遍历攻击，记录日志但不回滚数据库删除
                    error_log('[FileService] 路径遍历攻击检测 ' . $e->getMessage());
                    throw $e; // 重新抛出，让调用方知道有安全问题
                } catch (\Exception $e) {
                    // 物理文件删除失败，记录日志但不影响数据库删除
                    error_log('[FileService] 删除物理文件失败 (文件ID: ' . $fileId . '): ' . $e->getMessage());
                    // 不抛出异常，允许数据库删除成功
                }
            });
            
            return true;
        } catch (\App\Core\Exception\PathTraversalException $e) {
            // 路径遍历攻击，重新抛出
            throw $e;
        } catch (\Exception $e) {
            error_log('[FileService] 删除文件失败 (文件ID: ' . $fileId . '): ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 清除内容表封面图片字段
     * 
     * @param string $imageUrl 要清除的图片URL
     * @return bool 是否成功
     */
    public static function clearContentCoverImage(string $imageUrl): bool
    {
        try {
            $db = \App\Core\Database::get();
            $contentModel = new \App\Models\Content($db);
            
            // 查找使用该图片的所有内容记录
            $contents = $db->select('sys_content', ['id'], ['cover_img' => $imageUrl]);
            
            foreach ($contents as $content) {
                // 清除cover_img字段
                $contentModel->update($content['id'], ['cover_img' => '']);
            }
            
            return true;
        } catch (\Exception $e) {
            error_log('清除内容表封面图片字段失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 删除封面图片并更新数据库
     * 
     * @param string $imageUrl 图片URL
     * @param int $adminId 管理员ID
     * @param string $username 管理员用户名
     * @param string $target 删除目标描述
     * @return bool 是否成功
     */
    public static function deleteCoverImageWithDbUpdate(string $imageUrl, int $adminId, string $username, string $target = '封面图片'): bool
    {
        // 1. 删除物理文件
        $deleteResult = self::deleteCoverImage($imageUrl, $adminId, $username, $target);
        
        if (!$deleteResult) {
            return false;
        }
        
        // 2. 清除数据库中的引用
        $clearResult = self::clearContentCoverImage($imageUrl);
        
        return $clearResult;
    }

    /**
     * 删除封面图片（删除物理文件并记录日志）
     * 
     * @param string $imageUrl 图片URL（如: /uploads/images/2024/01/01/xxx.jpg）
     * @param int $adminId 管理员ID
     * @param string $username 管理员用户名
     * @param string $target 删除目标描述（如: "文章封面", "栏目图片"等）
     * @return bool 删除是否成功
     */
    public static function deleteCoverImage(string $imageUrl, int $adminId, string $username, string $target = '封面图片'): bool
    {
        try {
            // 清理URL，确保格式正确
            $imageUrl = ltrim($imageUrl, '/');
            
            // 验证URL格式，确保是上传目录下的文件
            if (!preg_match('/^uploads\/images\//', $imageUrl)) {
                error_log('[FileService] 封面图片删除失败：URL格式不正确 ' . $imageUrl);
                return false;
            }
            
            // 验证文件路径安全性（防止路径遍历攻击）
            if (strpos($imageUrl, '../') !== false || strpos($imageUrl, '..\\') !== false) {
                error_log('[FileService] 封面图片删除失败：检测到路径遍历攻击 ' . $imageUrl);
                return false;
            }
            
            // 删除物理文件
            $result = self::deletePhysicalFile($imageUrl);
            
            if ($result) {
                // 记录删除成功日志
                try {
                    \App\Services\LogService::deleted(
                        $target,
                        "图片路径: {$imageUrl}",
                        $adminId,
                        $username
                    );
                } catch (\Exception $e) {
                    error_log('[FileService] 记录删除日志失败: ' . $e->getMessage());
                }
                
                error_log("[FileService] 封面图片删除成功: {$imageUrl} (管理员: {$username})");
                return true;
            } else {
                // 记录删除失败日志
                try {
                    \App\Services\LogService::record(
                        $adminId,
                        $username,
                        'delete',
                        $target,
                        "封面图片删除失败: {$imageUrl}",
                        \App\Services\LogService::LEVEL_ERROR
                    );
                } catch (\Exception $e) {
                    error_log('[FileService] 记录删除失败日志失败: ' . $e->getMessage());
                }
                
                error_log("[FileService] 封面图片删除失败: {$imageUrl} (管理员: {$username})");
                return false;
            }
        } catch (\Exception $e) {
            // 记录异常日志
            try {
                \App\Services\LogService::error(
                    $e,
                    'delete',
                    $adminId,
                    $username
                );
            } catch (\Exception $logException) {
                error_log('[FileService] 记录异常日志失败: ' . $logException->getMessage());
            }
            
            error_log('[FileService] 删除封面图片异常: ' . $e->getMessage() . ' (图片: ' . $imageUrl . ')');
            return false;
        }
    }

    /**
     * 获取文件访问URL
     *
     * @param string $relativePath 文件相对路径
     * @return string
     */
    public static function getFileUrl(string $relativePath): string
    {
        $relativePath = ltrim($relativePath, '/');
        return '/' . $relativePath;
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

    /**
     * 处理WangEditor上传
     * 对上传文件进行验证、处理并保存
     * 
     * @param array<string, mixed> $uploadedFile $_FILES['file'] 数组
     * @param array<string, mixed> $options 上传选项
     * @return array{success: bool, file_id?: int, path?: string, url?: string, message?: string}
     */
    public static function handleWangEditorUpload(array $uploadedFile, array $options = []): array
    {
        // 验证上传文件
        $validation = self::validateUploadedFile($uploadedFile, $options);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => $validation['message'] ?? '文件验证失败'
            ];
        }

        // 设置WangEditor特定的默认选项
        $wangEditorOptions = array_merge([
            'module' => 'wangeditor',
            'file_usage' => 'content',
            'is_primary' => false
        ], $options);

        // 使用通用的handleUpload方法处理
        return self::handleUpload($uploadedFile, $wangEditorOptions);
    }

    /**
     * 更新内容表封面图片
     * 
     * @param int $contentId 内容ID
     * @param string $imageUrl 图片URL
     * @return bool 更新是否成功
     */
    public static function updateContentCoverImage(int $contentId, string $imageUrl): bool
    {
        try {
            $db = \App\Core\Database::get();
            $contentModel = new \App\Models\Content($db);
            
            $result = $contentModel->update($contentId, ['cover_img' => $imageUrl]);
            
            return $result !== false;
        } catch (\Exception $e) {
            error_log('更新内容表封面图片失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取上传目录的物理路径
     * 自动识别项目根目录，确保路径在 CLI 和 Web 模式下都正确
     *
     * @param string $subDir 子目录（如 'uploads/images'）
     * @return string 完整物理路径
     */
    public static function getUploadDirectory(string $subDir = 'uploads/images'): string
    {
        // 从当前文件所在目录向上查找，直到找到包含 public 的目录
        // app/Services/FileService.php -> app -> project_root
        $root = dirname(__DIR__, 2);
        
        $dir = $root . '/public/' . ltrim($subDir, '/');
        
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        
        return $dir;
    }

    /**
     * 计算下一个 Banner 文件名
     * 格式：Categorybanner{N}.{ext}
     *
     * @param string $dir 目录路径
     * @param string $extension 文件扩展名
     * @return string 新文件名
     */
    public static function calculateNextBannerFilename(string $dir, string $extension): string
    {
        $max = 0;
        $files = glob($dir . '/Categorybanner*.*') ?: [];
        foreach ($files as $f) {
            $base = basename($f);
            if (preg_match('/^Categorybanner(\d+)\./i', $base, $m)) {
                $n = (int)$m[1];
                if ($n > $max) {
                    $max = $n;
                }
            }
        }
        $next = $max + 1;
        return 'Categorybanner' . $next . '.' . ltrim($extension, '.');
    }

    /**
     * 处理栏目 Banner 上传
     * 
     * @param array $uploadedFile $_FILES['file']
     * @return array{success: bool, url?: string, message?: string}
     */
    public static function handleCategoryBannerUpload(array $uploadedFile): array
    {
        try {
            // 1. 验证基础上传
            $errorCode = (int)($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($errorCode !== UPLOAD_ERR_OK) {
                return ['success' => false, 'message' => self::getUploadErrorMessage($errorCode)];
            }

            // 2. 验证扩展名
            $ext = strtolower(pathinfo((string)($uploadedFile['name'] ?? ''), PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if ($ext === '' || !in_array($ext, $allowed, true)) {
                return ['success' => false, 'message' => '仅支持jpg/jpeg/png/gif/webp'];
            }

            // 3. 获取并准备目录
            $dir = self::getUploadDirectory('uploads/images');
            
            // 4. 计算文件名
            $filename = self::calculateNextBannerFilename($dir, $ext);
            $dest = $dir . '/' . $filename;

            // 5. 移动文件
            if (!move_uploaded_file((string)$uploadedFile['tmp_name'], $dest)) {
                error_log("[FileService] 移动 CategoryBanner 失败: " . $dest);
                return ['success' => false, 'message' => '上传失败：保存文件失败'];
            }

            // 6. 验证写入
            if (!file_exists($dest)) {
                return ['success' => false, 'message' => '上传失败：文件未正确保存'];
            }

            return [
                'success' => true,
                'url' => '/uploads/images/' . $filename
            ];
        } catch (\Exception $e) {
            error_log("[FileService] handleCategoryBannerUpload 异常: " . $e->getMessage());
            return ['success' => false, 'message' => '上传异常: ' . $e->getMessage()];
        }
    }

    /**
     * 图片裁剪
     * 
     * 根据提供的坐标和尺寸裁剪图片，支持固定尺寸和自由尺寸两种模式
     * 
     * @param string $sourceImagePath 源图片路径（可以是完整路径或相对路径）
     * @param int $x 裁剪区域左上角 x 坐标
     * @param int $y 裁剪区域左上角 y 坐标
     * @param int $width 裁剪区域宽度
     * @param int $height 裁剪区域高度
     * @param int|null $targetWidth 目标宽度（可选，为 null 则使用裁剪区域宽度）
     * @param int|null $targetHeight 目标高度（可选，为 null 则使用裁剪区域高度）
     * @return array{success: bool, url?: string, message?: string}
     */
    public static function cropImage(
        string $sourceImagePath, 
        int $x, 
        int $y, 
        int $width, 
        int $height,
        ?int $targetWidth = null,
        ?int $targetHeight = null
    ): array {
        try {
            // 解析源图片路径
            $sourcePath = self::resolveFullPath($sourceImagePath);
            if (!file_exists($sourcePath)) {
                return ['success' => false, 'message' => '源图片不存在'];
            }

            // 获取图片信息
            $imageInfo = @getimagesize($sourcePath);
            if ($imageInfo === false) {
                return ['success' => false, 'message' => '无法读取图片信息'];
            }

            $sourceWidth = $imageInfo[0];
            $sourceHeight = $imageInfo[1];
            $imageType = $imageInfo[2];

            // 验证裁剪坐标和尺寸
            if ($x < 0 || $y < 0 || $width <= 0 || $height <= 0) {
                return ['success' => false, 'message' => '无效的裁剪参数'];
            }

            if ($x + $width > $sourceWidth || $y + $height > $sourceHeight) {
                return ['success' => false, 'message' => '裁剪区域超出图片范围'];
            }

            // 创建源图片资源
            $sourceImage = self::createImageFromFile($sourcePath, $imageType);
            if ($sourceImage === false) {
                return ['success' => false, 'message' => '无法创建图片资源'];
            }

            // 目标尺寸
            $dstWidth = $targetWidth ?? $width;
            $dstHeight = $targetHeight ?? $height;

            // 创建目标图片资源
            $dstImage = imagecreatetruecolor($dstWidth, $dstHeight);
            if ($dstImage === false) {
                imagedestroy($sourceImage);
                return ['success' => false, 'message' => '无法创建目标图片'];
            }

            // 处理 PNG 和 GIF 的透明背景
            if ($imageType === IMAGETYPE_PNG) {
                imagealphablending($dstImage, false);
                imagesavealpha($dstImage, true);
                $transparent = imagecolorallocatealpha($dstImage, 0, 0, 0, 127);
                imagefill($dstImage, 0, 0, $transparent);
            } elseif ($imageType === IMAGETYPE_GIF) {
                $transparentIndex = imagecolortransparent($sourceImage);
                if ($transparentIndex >= 0) {
                    $transparentColor = imagecolorsforindex($sourceImage, $transparentIndex);
                    $transparentNew = imagecolorallocate(
                        $dstImage, 
                        $transparentColor['red'], 
                        $transparentColor['green'], 
                        $transparentColor['blue']
                    );
                    imagefill($dstImage, 0, 0, $transparentNew);
                    imagecolortransparent($dstImage, $transparentNew);
                }
            }

            // 执行裁剪和缩放
            $resizeOk = imagecopyresampled(
                $dstImage, 
                $sourceImage, 
                0,            // 目标 x
                0,            // 目标 y
                $x,           // 源 x
                $y,           // 源 y
                $dstWidth,    // 目标宽度
                $dstHeight,   // 目标高度
                $width,       // 源宽度
                $height       // 源高度
            );

            imagedestroy($sourceImage);

            if (!$resizeOk) {
                imagedestroy($dstImage);
                return ['success' => false, 'message' => '裁剪失败'];
            }

            // 生成新文件名
            $fileHash = hash_file('sha256', $sourcePath);
            $extension = self::getExtensionFromImageType($imageType);
            $newFileName = 'crop_' . $fileHash . '_' . $dstWidth . 'x' . $dstHeight . '.' . $extension;
            
            // 保存目录
            $saveDir = self::getUploadDirectory('uploads/images/crop');
            $savePath = $saveDir . '/' . $newFileName;

            // 保存图片
            $saveOk = self::saveImageToFile($dstImage, $savePath, $imageType);
            imagedestroy($dstImage);

            if (!$saveOk) {
                return ['success' => false, 'message' => '保存裁剪图片失败'];
            }

            // 返回相对路径
            $relativePath = 'uploads/images/crop/' . $newFileName;
            return [
                'success' => true,
                'url' => '/' . $relativePath
            ];
        } catch (\Exception $e) {
            error_log('[FileService] cropImage 异常: ' . $e->getMessage());
            return ['success' => false, 'message' => '裁剪异常: ' . $e->getMessage()];
        }
    }

    /**
     * 从图片类型获取扩展名
     * 
     * @param int $imageType 图片类型常量
     * @return string
     */
    private static function getExtensionFromImageType(int $imageType): string
    {
        return match ($imageType) {
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_GIF => 'gif',
            IMAGETYPE_WEBP => 'webp',
            default => 'jpg',
        };
    }

    /**
     * 根据文件创建图片资源
     * 
     * @param string $filePath 文件路径
     * @param int $imageType 图片类型
     * @return \GdImage|false
     */
    private static function createImageFromFile(string $filePath, int $imageType): \GdImage|false
    {
        return match ($imageType) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($filePath),
            IMAGETYPE_PNG => imagecreatefrompng($filePath),
            IMAGETYPE_GIF => imagecreatefromgif($filePath),
            IMAGETYPE_WEBP => imagecreatefromwebp($filePath),
            default => false,
        };
    }

    /**
     * 保存图片资源到文件
     * 
     * @param \GdImage $image 图片资源
     * @param string $filePath 保存路径
     * @param int $imageType 图片类型
     * @return bool
     */
    private static function saveImageToFile(\GdImage $image, string $filePath, int $imageType): bool
    {
        return match ($imageType) {
            IMAGETYPE_JPEG => imagejpeg($image, $filePath, 90),
            IMAGETYPE_PNG => imagepng($image, $filePath, 9),
            IMAGETYPE_GIF => imagegif($image, $filePath),
            IMAGETYPE_WEBP => imagewebp($image, $filePath, 90),
            default => false,
        };
    }

    /**
     * 解析图片完整路径
     * 
     * @param string $imagePath 图片路径
     * @return string
     */
    private static function resolveFullPath(string $imagePath): string
    {
        $publicDir = dirname(__DIR__, 2) . '/public';
        
        // 如果是绝对路径（以 / 开头或 Windows 绝对路径格式）
        if (str_starts_with($imagePath, '/') || strpos($imagePath, ':') !== false) {
            // 如果是网站根目录下的绝对路径（以 / 开头）
            if (str_starts_with($imagePath, '/')) {
                return $publicDir . $imagePath;
            }
            // 否则是 Windows 系统的绝对路径（如 C:\...）
            return $imagePath;
        }
        
        // 如果已经是完整路径
        if (file_exists($imagePath)) {
            return $imagePath;
        }
        
        // 相对路径
        return $publicDir . '/' . ltrim($imagePath, '/');
    }

    /**
     * 处理 Base64 图片上传并裁剪
     * 
     * @param string $base64Data Base64 数据
     * @param int $x 裁剪区域左上角 x 坐标
     * @param int $y 裁剪区域左上角 y 坐标
     * @param int $width 裁剪区域宽度
     * @param int $height 裁剪区域高度
     * @param int|null $targetWidth 目标宽度
     * @param int|null $targetHeight 目标高度
     * @return array{success: bool, url?: string, message?: string}
     */
    public static function uploadAndCropBase64Image(
        string $base64Data,
        int $x,
        int $y,
        int $width,
        int $height,
        ?int $targetWidth = null,
        ?int $targetHeight = null
    ): array {
        // 解析 Base64 数据
        if (!str_starts_with($base64Data, 'data:image/')) {
            return ['success' => false, 'message' => '无效的图片数据'];
        }

        $matches = [];
        if (!preg_match('/data:image\/(\w+);base64,(.+)/', $base64Data, $matches)) {
            return ['success' => false, 'message' => '无效的 Base64 图片格式'];
        }

        $extension = strtolower($matches[1]);
        $base64Content = $matches[2];

        // 验证扩展名
        $allowedExtensions = ['jpeg', 'jpg', 'png', 'gif', 'webp'];
        if (!in_array($extension, $allowedExtensions, true)) {
            return ['success' => false, 'message' => '不支持的图片格式'];
        }

        // 解码 Base64
        $imageData = base64_decode($base64Content);
        if ($imageData === false) {
            return ['success' => false, 'message' => 'Base64 解码失败'];
        }

        // 验证图片数据
        $tempPath = sys_get_temp_dir() . '/upload_' . uniqid() . '.' . $extension;
        if (file_put_contents($tempPath, $imageData) === false) {
            return ['success' => false, 'message' => '临时文件创建失败'];
        }

        // 验证是否为有效图片
        $imageInfo = @getimagesize($tempPath);
        if ($imageInfo === false) {
            @unlink($tempPath);
            return ['success' => false, 'message' => '无效的图片数据'];
        }

        // 将临时文件作为源图片进行裁剪 - 临时文件路径已经是完整路径，不需要通过 resolveFullPath 处理
        $result = self::cropImage($tempPath, $x, $y, $width, $height, $targetWidth, $targetHeight);

        // 删除临时文件
        @unlink($tempPath);

        return $result;
    }
}
