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
     * - update_table: 需要更新的表名
     * - update_field: 需要更新的字段名
     * - update_id: 需要更新的记录ID
     *
     * @return void
     */
    public function upload(): void
    {
        // 只接受POST请求
        if (!$this->isPost()) {
            \App\Services\ResponseService::handleWangEditorUploadResponse(null, false, '请求方法错误');
        }

        try {
            // 检查是否有文件上传
            if (!isset($_FILES['file'])) {
                \App\Services\ResponseService::handleWangEditorUploadResponse(null, false, FileService::getUploadErrorMessage(UPLOAD_ERR_NO_FILE));
            }

            $uploadedFile = $_FILES['file'];

            // 获取上传选项
            $options = [
                'module' => $this->post('module', ''),
                'field_name' => $this->post('field_name', ''),
                'related_type' => $this->post('related_type', ''),
                'related_id' => (int)$this->post('related_id', 0),
                'file_usage' => $this->post('file_usage', 'content'),
                'is_primary' => (int)$this->post('is_primary', 0) > 0,
                'uploaded_by' => $this->getAdminId()
            ];

            // 处理文件上传（使用 FileService 的 WangEditor 专用方法）
            $result = FileService::handleWangEditorUpload($uploadedFile, $options);

            if (!$result['success']) {
                \App\Services\ResponseService::handleWangEditorUploadResponse($result, false);
            }

            // 如果指定了更新内容表的参数，则更新 sys_content 表
            $updateTable = $this->post('update_table', '');
            $updateField = $this->post('update_field', '');
            $updateId = (int)$this->post('update_id', 0);

            if ($updateTable === 'sys_content' && $updateField === 'cover_img' && $updateId > 0) {
                // 使用 FileService 更新内容表封面图片
                FileService::updateContentCoverImage($updateId, $result['url']);
            }

            // 返回成功响应（wangeditor 4.x 格式）
            \App\Services\ResponseService::handleWangEditorUploadResponse($result, true);

        } catch (\Exception $e) {
            error_log('文件上传失败: ' . $e->getMessage());
            \App\Services\ResponseService::handleWangEditorUploadResponse(null, false, '上传失败: ' . $e->getMessage());
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

        // 验证POST请求和CSRF
        if (!$this->validatePostAndCsrf()) {
            return;
        }

        if (!isset($_FILES['file'])) {
            $this->errorJson(FileService::getUploadErrorMessage(UPLOAD_ERR_NO_FILE));
        }

        // 调用 FileService 处理业务逻辑
        $result = FileService::handleCategoryBannerUpload($_FILES['file']);

        if ($result['success']) {
            $this->successJson('上传成功', ['url' => $result['url']]);
        } else {
            $this->errorJson($result['message'] ?? '上传失败');
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
            // 验证POST请求和CSRF
            if (!$this->validatePostAndCsrf()) {
                return;
            }

            // 获取图片URL
            $imageUrl = $this->post('image_url', '');
            if (empty($imageUrl)) {
                $this->errorJson('图片URL不能为空');
                return;
            }

            // 验证URL格式，确保是上传目录下的图片
            if (!preg_match('/^\/uploads\/images\//', $imageUrl)) {
                $this->errorJson('无效的图片URL');
                return;
            }

            // 调用FileService删除封面图片并更新数据库
            $result = FileService::deleteCoverImageWithDbUpdate(
                $imageUrl,
                $this->getAdminId(),
                $this->getAdminUsername(),
                '文章封面'
            );

            if ($result) {
                $this->successJson('封面图片删除成功');
            } else {
                $this->errorJson('封面图片删除失败');
            }
        } catch (\Exception $e) {
            error_log('删除封面图片失败: ' . $e->getMessage());
            $this->errorJson('删除封面图片失败: ' . $e->getMessage());
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

            // 验证POST请求和CSRF
            if (!$this->validatePostAndCsrf()) {
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

    /**
     * 图片裁剪接口
     * 
     * 支持两种模式：
     * 1. 固定尺寸模式：根据配置的 targetWidth/targetHeight 强制输出指定尺寸
     * 2. 自由尺寸模式：直接裁剪，不进行缩放
     * 
     * @return void
     */
    public function crop(): void
    {
        // 验证权限
        $this->requirePerm('admin:upload:crop');
        
        if (!$this->isPost()) {
            $this->errorJson('请求方法错误');
            return;
        }

        if (!$this->validatePostAndCsrf()) {
            return;
        }

        try {
            $sourceUrl = $this->post('source_url', '');
            $x = (int)$this->post('x', 0);
            $y = (int)$this->post('y', 0);
            $width = (int)$this->post('width', 0);
            $height = (int)$this->post('height', 0);
            $targetWidth = (int)$this->post('target_width', 0) ?: null;
            $targetHeight = (int)$this->post('target_height', 0) ?: null;

            if (empty($sourceUrl)) {
                $this->errorJson('请选择要裁剪的图片');
                return;
            }

            if ($width <= 0 || $height <= 0) {
                $this->errorJson('请设置裁剪区域');
                return;
            }

            $result = FileService::cropImage(
                $sourceUrl,
                $x,
                $y,
                $width,
                $height,
                $targetWidth,
                $targetHeight
            );

            if ($result['success']) {
                $this->successJson('裁剪成功', ['url' => $result['url']]);
            } else {
                $this->errorJson($result['message'] ?? '裁剪失败');
            }
        } catch (\Exception $e) {
            error_log('图片裁剪失败: ' . $e->getMessage());
            $this->errorJson('裁剪异常: ' . $e->getMessage());
        }
    }

    /**
     * Base64 图片上传并裁剪接口
     * 
     * @return void
     */
    public function uploadCrop(): void
    {
        // 验证权限
        $this->requirePerm('admin:upload:crop');
        
        if (!$this->isPost()) {
            $this->errorJson('请求方法错误');
            return;
        }

        if (!$this->validatePostAndCsrf()) {
            return;
        }

        try {
            $base64Data = $this->post('image_data', '');
            $x = (int)$this->post('x', 0);
            $y = (int)$this->post('y', 0);
            $width = (int)$this->post('width', 0);
            $height = (int)$this->post('height', 0);
            $targetWidth = (int)$this->post('target_width', 0) ?: null;
            $targetHeight = (int)$this->post('target_height', 0) ?: null;

            if (empty($base64Data)) {
                $this->errorJson('请上传图片');
                return;
            }

            if ($width <= 0 || $height <= 0) {
                $this->errorJson('请设置裁剪区域');
                return;
            }

            $result = FileService::uploadAndCropBase64Image(
                $base64Data,
                $x,
                $y,
                $width,
                $height,
                $targetWidth,
                $targetHeight
            );

            if ($result['success']) {
                $this->successJson('上传并裁剪成功', ['url' => $result['url']]);
            } else {
                $this->errorJson($result['message'] ?? '上传裁剪失败');
            }
        } catch (\Exception $e) {
            error_log('图片上传裁剪失败: ' . $e->getMessage());
            $this->errorJson('上传裁剪异常: ' . $e->getMessage());
        }
    }
}
