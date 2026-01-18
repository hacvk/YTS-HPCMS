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
            $this->errorJson('请求方法错误', 1);
        }

        try {
            // 检查是否有文件上传
            if (!isset($_FILES['file'])) {
                $this->errorJson(FileService::getUploadErrorMessage(UPLOAD_ERR_NO_FILE), 1);
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

            // 处理文件上传（使用FileService）
            $result = FileService::handleUpload($uploadedFile, $options);

            if (!$result['success']) {
                $this->errorJson($result['message'] ?? '上传失败', 1);
            }

            // 返回成功响应（wangeditor 4.x 格式）
            $this->successJson('上传成功', ['errno' => 0, 'data' => [$result['url']]]);

        } catch (\Exception $e) {
            error_log('文件上传失败: ' . $e->getMessage());
            $this->errorJson('上传失败: ' . $e->getMessage(), 1);
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

        // 使用FileService处理上传，指定module为'banner'
        $options = [
            'module' => 'banner',
            'uploaded_by' => $this->getAdminId()
        ];

        $result = FileService::handleUpload($uploadedFile, $options);

        if (!$result['success']) {
            $this->errorJson($result['message'] ?? '上传失败');
        }

        $this->successJson('上传成功', ['url' => $result['url']]);
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
