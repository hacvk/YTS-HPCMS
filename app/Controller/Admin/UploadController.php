<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\Admin\AdminBaseController;
use App\Core\Security;
use App\Model\File;
use App\Service\FileService;

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
     * - module: 模块标识（'setting' 或 'banner'）
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
            FileService::deletePhysicalFile($file['path']);

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
