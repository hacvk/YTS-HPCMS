<?php
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\AdminBaseController;
use App\Models\File;
use App\Services\FileService;
use App\Core\Security;

/**
 * 文件上传控制器
 */
class UploadController extends AdminBaseController
{
    public function __construct(
        private readonly File $fileModel
    ) {
        parent::__construct();
    }

    /**
     * 上传文件（用于wangeditor 等编辑器）
     * 
     * 支持参数：
     * - related_type: 关联类型（如：article, category, banner等）
     * - related_id: 关联ID
     * - file_usage: 文件用途（如：cover, content, attachment等）
     * - is_primary: 是否为主图（0/1）
     * - module: 模块标识（如'setting' 或 'banner'）
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
            if (!isset($_FILES['file'])) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'errno' => 1,
                    'message' => FileService::getUploadErrorMessage(UPLOAD_ERR_NO_FILE)
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $uploadedFile = $_FILES['file'];

            // 获取上传选项
            $options = [
                'module' => $this->post('module', ''),
                'related_type' => $this->post('related_type', ''),
                'related_id' => (int)$this->post('related_id', 0),
                'file_usage' => $this->post('file_usage', 'content'),
                'is_primary' => (int)$this->post('is_primary', 0) > 0,
                'uploaded_by' => $this->getAdminId()
            ];

            // 处理文件上传（使用 FileService）
            $result = FileService::handleUpload($uploadedFile, $options);

            if (!$result['success']) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'errno' => 1,
                    'message' => $result['message'] ?? '上传失败'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // 如果指定了更新内容表的参数，则更新 sys_content 表的 cover_img 字段
            $updateTable = $this->post('update_table', '');
            $updateField = $this->post('update_field', '');
            $updateId = (int)$this->post('update_id', 0);

            if ($updateTable === 'sys_content' && $updateField === 'cover_img' && $updateId > 0) {
                try {
                    $db = \App\Core\Database::get();
                    $contentModel = new \App\Models\Content($db);
                    $contentModel->update($updateId, ['cover_img' => $result['url']]);
                } catch (\Exception $e) {
                    error_log('更新内容表封面图片失败: ' . $e->getMessage());
                    // 不中断上传流程，只是记录错误
                }
            }

            // 返回成功响应（wangeditor 4.x 格式）
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'errno' => 0,
                'data' => [$result['url']]  // wangeditor 4.x 期望 data 是图片URL数组
            ], JSON_UNESCAPED_UNICODE);
            exit;

        } catch (\Exception $e) {
            error_log('文件上传失败: ' . $e->getMessage());
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'errno' => 1,
                'message' => '上传失败: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    /**
     * 上传栏目 Banner
     *
     * - 固定上传目录：public/uploads/images
     * - 固定命名：Categorybanner{N}.{ext}
     *
     * @return void
     */
    public function categoryBanner(): void
    {
        $this->requirePerm('admin:category:save');

        if (!$this->isPost()) {
            $this->errorJson('请求方法错误');
        }

        if (!Security::checkCsrfToken((string)$this->post('csrf_token', ''))) {
            $this->errorJson('CSRF Token验证失败');
        }

        if (!isset($_FILES['file'])) {
            $this->errorJson(FileService::getUploadErrorMessage(UPLOAD_ERR_NO_FILE));
        }

        $uploadedFile = $_FILES['file'];

        if (!is_array($uploadedFile) || ($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->errorJson(FileService::getUploadErrorMessage((int)($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE)));
        }

        $ext = strtolower(pathinfo((string)($uploadedFile['name'] ?? ''), PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if ($ext === '' || !in_array($ext, $allowed, true)) {
            $this->errorJson('仅支持jpg/jpeg/png/gif/webp');
        }

        // 修复路径计算逻辑 - 使用项目根目录的正确计算方法
        $root = dirname(dirname(dirname(__DIR__))); // 等同于 dirname(__DIR__, 3)，但更明确
        
        // 如果路径计算不正确，使用一种更可靠的方法来确定项目根目录
        // 从当前目录向上查找直到找到包含 'public' 目录的父目录
        $currentPath = dirname(dirname(dirname(__DIR__))); // 从 app/Http/Controllers/Admin 开始
        
        // 确保我们找到的是正确的项目根目录
        if (basename($currentPath) === 'app') {
            $root = dirname($currentPath); // 上一级才是真正的项目根目录
        }
        
        // 添加调试信息以确定实际路径
        error_log('UploadController - 计算的项目根目录: ' . $root);
        error_log('UploadController - 完整上传路径: ' . $root . '/public/uploads/images');
        error_log('UploadController - 当前工作目录: ' . getcwd());
        error_log('UploadController - __DIR__ 值: ' . __DIR__);
        
        $dir = $root . '/public/uploads/images';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        // 计算下一个序号
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

        $filename = 'Categorybanner' . $next . '.' . $ext;
        $dest = $dir . '/' . $filename;
        
        // 记录上传调试信息
        error_log('准备上传文件: ' . $dest);
        error_log('临时文件路径: ' . ($uploadedFile['tmp_name'] ?? '未设置'));
        error_log('文件大小: ' . ($uploadedFile['size'] ?? 0));
        error_log('文件类型: ' . ($uploadedFile['type'] ?? '未设置'));
        error_log('目录是否存在: ' . (is_dir($dir) ? '是' : '否'));
        error_log('目录是否可写: ' . (is_writable($dir) ? '是' : '否'));
        
        if (!move_uploaded_file((string)$uploadedFile['tmp_name'], $dest)) {
            error_log('移动文件失败: 源文件=' . ($uploadedFile['tmp_name'] ?? '未设置') . ', 目标=' . $dest);
            error_log('错误码: ' . ($uploadedFile['error'] ?? '未知'));
            $this->errorJson('上传失败：保存文件失败');
        }
        
        // 验证文件是否真正写入
        if (!file_exists($dest)) {
            error_log('上传后验证失败: 目标文件不存在 ' . $dest);
            $this->errorJson('上传失败：文件未正确保存');
        }
        
        $url = '/uploads/images/' . $filename;
        error_log('上传成功: ' . $url);
        $this->successJson('上传成功', ['url' => $url]);
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
            $result = $this->fileModel->getList($page, $limit, $where);
            
            // 获取统计信息
            $statistics = $this->fileModel->getStatistics();
            
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
     * 删除封面图片
     * 
     * @return void
     */
    public function deleteCoverImage(): void
    {
        // 只接受POST请求
        if (!$this->isPost()) {
            $this->errorJson('请求方法错误');
            return;
        }

        try {
            // 验证CSRF Token
            $csrfToken = $this->post('csrf_token', '');
            if (!Security::checkCsrfToken($csrfToken)) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'code' => 1,
                    'message' => 'CSRF Token验证失败'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // 获取图片URL
            $imageUrl = $this->post('image_url', '');
            if (empty($imageUrl)) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'code' => 1,
                    'message' => '图片URL不能为空'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // 验证URL格式，确保是上传目录下的图片
            if (!preg_match('/^\/uploads\/images\//', $imageUrl)) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'code' => 1,
                    'message' => '无效的图片URL'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // 调用FileService删除封面图片并记录日志
            $result = FileService::deleteCoverImage(
                $imageUrl,
                $this->getAdminId(),
                $this->getAdminUsername(),
                '文章封面'
            );

            if ($result) {
                // 如果图片URL存在于数据库中，也需要清除相关记录
                try {
                    $db = \App\Core\Database::get();
                    $contentModel = new \App\Models\Content($db);
                    
                    // 查找使用该图片的所有内容记录
                    $contents = $db->select('sys_content', ['id'], ['cover_img' => $imageUrl]);
                    
                    foreach ($contents as $content) {
                        // 清除cover_img字段
                        $contentModel->update($content['id'], ['cover_img' => '']);
                    }
                } catch (\Exception $e) {
                    error_log('更新内容表封面图片字段失败: ' . $e->getMessage());
                    // 不中断删除流程，只记录错误
                }

                // 确保返回JSON格式
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'code' => 0,
                    'message' => '封面图片删除成功'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            } else {
                // 确保返回JSON格式
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'code' => 1,
                    'message' => '封面图片删除失败'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        } catch (\Exception $e) {
            error_log('删除封面图片失败: ' . $e->getMessage());
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'code' => 1,
                'message' => '删除封面图片失败: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
            exit;
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
            $file = $this->fileModel->getById((int)$id);
            if (!$file) {
                $this->errorJson('文件不存在');
                return;
            }

            // 删除物理文件
            FileService::deletePhysicalFile($file['path']);

            // 删除数据库记录
            $result = $this->fileModel->delete((int)$id);

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
