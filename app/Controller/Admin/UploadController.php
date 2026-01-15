<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\Admin\AdminBaseController;
use App\Core\Security;
use App\Model\File;

/**
 * 文件上传控制器
 */
class UploadController extends AdminBaseController
{
    /**
     * 上传文件（用于 wangeditor 等编辑器）
     * 
     * 支持参数：
     * - related_type: 关联类型（如：article, category, banner等）
     * - related_id: 关联ID
     * - file_usage: 文件用途（如：cover, content, attachment等）
     * - is_primary: 是否为主图（0或1）
     *
     * @return void
     */
    public function upload(): void
    {
        // 只接受POST请求
        if (!$this->isPost()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['errno' => 1, 'message' => '请求方法错误'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            // 检查是否有文件上传
            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                $errorMsg = $this->getUploadErrorMessage($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['errno' => 1, 'message' => $errorMsg], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $uploadedFile = $_FILES['file'];
            
            // 验证文件类型
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            $mimeType = mime_content_type($uploadedFile['tmp_name']);
            if (!in_array($mimeType, $allowedTypes)) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['errno' => 1, 'message' => '不支持的文件类型，仅支持：' . implode(', ', $allowedTypes)], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // 验证文件大小（最大 5MB）
            $maxSize = 5 * 1024 * 1024;
            if ($uploadedFile['size'] > $maxSize) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['errno' => 1, 'message' => '文件大小超过限制，最大 5MB'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // 生成文件hash
            $fileHash = hash_file('sha256', $uploadedFile['tmp_name']);
            
            // 检查文件是否已存在
            $existingFile = File::getByHash($fileHash);
            if ($existingFile) {
                // 文件已存在，直接返回已有文件的URL（wangeditor 4.x 格式）
                $fileUrl = $this->getFileUrl($existingFile['path']);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'errno' => 0,
                    'data' => [$fileUrl]
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // 获取上传目录
            $uploadDir = $this->getUploadDir();
            
            // 获取上传模块标识
            $module = $this->post('module', ''); // 'setting' 或 'banner'
            $fileExtension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
            
            // 根据模块生成不同的文件名和路径
            if ($module === 'setting') {
                // Setting 模块：保存为 logo.{ext}
                $fileName = 'images/logo.' . $fileExtension;
                $filePath = $uploadDir . '/' . $fileName;
                $relativePath = 'uploads/' . $fileName;
                
                // 如果文件已存在，先删除旧文件
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
            } elseif ($module === 'banner') {
                // Banner 模块：保存为 banner{随机数}.{ext}
                $randomNumber = mt_rand(100000, 999999);
                $fileName = 'images/banner' . $randomNumber . '.' . $fileExtension;
                $filePath = $uploadDir . '/' . $fileName;
                $relativePath = 'uploads/' . $fileName;
            } else {
                // 默认：使用原来的按日期+hash命名方式
                $fileName = date('Y/m/d') . '/' . $fileHash . '.' . $fileExtension;
                $filePath = $uploadDir . '/' . $fileName;
                $relativePath = 'uploads/' . $fileName;
            }

            // 创建目录
            $dir = dirname($filePath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            // 移动文件
            if (!move_uploaded_file($uploadedFile['tmp_name'], $filePath)) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['errno' => 1, 'message' => '文件保存失败'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // 获取当前管理员ID
            $adminId = $this->getAdminId();

            // 获取关联信息（可选）
            $relatedType = $this->post('related_type', '');
            $relatedId = (int)$this->post('related_id', 0);
            $fileUsage = $this->post('file_usage', 'content'); // 默认为内容图片
            $isPrimary = (int)$this->post('is_primary', 0);

            // 保存文件记录到数据库
            $fileData = [
                'hash' => $fileHash,
                'original_name' => $uploadedFile['name'],
                'path' => $relativePath,
                'mime_type' => $mimeType,
                'size' => $uploadedFile['size'],
                'uploaded_by' => $adminId,
                'created_at' => date('Y-m-d H:i:s')
            ];

            // 如果有关联信息，添加到数据中
            if (!empty($relatedType) && $relatedId > 0) {
                $fileData['related_type'] = $relatedType;
                $fileData['related_id'] = $relatedId;
                $fileData['file_usage'] = $fileUsage;
                $fileData['is_primary'] = $isPrimary;
            }

            $fileId = File::save($fileData);

            if (!$fileId) {
                // 如果数据库保存失败，删除已上传的文件
                @unlink($filePath);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['errno' => 1, 'message' => '文件记录保存失败'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // 返回成功响应（wangeditor 4.x 格式）
            // wangeditor 4.x 期望直接返回图片URL字符串，或者包含 errno 和 data 的对象
            $fileUrl = $this->getFileUrl($relativePath);
            
            // 直接返回 wangeditor 4.x 期望的格式
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'errno' => 0,
                'data' => [
                    $fileUrl  // wangeditor 4.x 期望 data 是图片URL数组
                ]
            ], JSON_UNESCAPED_UNICODE);
            exit;

        } catch (\Exception $e) {
            error_log('文件上传失败: ' . $e->getMessage());
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['errno' => 1, 'message' => '上传失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    /**
     * 获取上传错误信息
     *
     * @param int $errorCode 错误代码
     * @return string
     */
    private function getUploadErrorMessage(int $errorCode): string
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
     * 获取上传目录
     *
     * @return string
     */
    private function getUploadDir(): string
    {
        $baseDir = dirname(__DIR__, 3) . '/public/uploads';
        return $baseDir;
    }

    /**
     * 获取文件访问URL
     *
     * @param string $path 文件相对路径
     * @return string
     */
    private function getFileUrl(string $path): string
    {
        return '/uploads/' . ltrim($path, 'uploads/');
    }

    /**
     * 文件管理列表页
     *
     * @return void
     */
    public function index(): void
    {
        try {
            $page = (int)($this->get('page', 1));
            $limit = (int)($this->get('limit', 20));
            $mimeType = $this->get('mime_type', '');
            
            // 构建查询条件
            $where = [];
            if (!empty($mimeType)) {
                $where['mime_type'] = $mimeType;
            }
            
            // 获取文件列表
            $result = File::getList($page, $limit, $where);
            
            // 获取统计信息
            $statistics = File::getStatistics();
            
            $this->render('admin/upload/index', [
                'title' => '附件管理',
                'files' => $result['list'],
                'total' => $result['total'],
                'page' => $page,
                'limit' => $limit,
                'mime_type' => $mimeType,
                'statistics' => $statistics
            ]);
        } catch (\Exception $e) {
            error_log('获取文件列表失败: ' . $e->getMessage());
            $this->error('加载页面失败: ' . $e->getMessage());
        }
    }

    /**
     * 删除文件
     *
     * @return void
     */
    public function delete(): void
    {
        // 只接受POST请求
        if (!$this->isPost()) {
            $this->errorJson('请求方法错误');
            return;
        }

        try {
            // 从URL中获取ID参数
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            preg_match('/\/admin\/upload\/delete\/(\d+)/', $uri, $matches);
            $id = $matches[1] ?? 0;

            if ((int)$id <= 0) {
                $this->errorJson('文件ID无效');
                return;
            }

            // 验证CSRF Token
            $csrfToken = $this->post('csrf_token', '');
            if (!Security::checkCsrfToken($csrfToken)) {
                $this->errorJson('CSRF Token验证失败');
                return;
            }

            // 获取文件信息
            $file = File::getById((int)$id);
            if (!$file) {
                $this->errorJson('文件不存在');
                return;
            }

            // 删除物理文件
            $filePath = dirname(__DIR__, 3) . '/public/' . $file['path'];
            if (file_exists($filePath)) {
                @unlink($filePath);
            }

            // 删除数据库记录
            $result = File::delete((int)$id);

            if (!$result) {
                $this->errorJson('删除失败');
                return;
            }

            $this->successJson('删除成功', null, '/admin/upload');
        } catch (\Exception $e) {
            error_log('删除文件失败: ' . $e->getMessage());
            $this->errorJson('删除失败: ' . $e->getMessage());
        }
    }
}
