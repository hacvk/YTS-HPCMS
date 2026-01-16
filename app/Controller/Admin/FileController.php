<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\Admin\AdminBaseController;
use App\Core\Security;
use App\Model\File;

/**
 * 文件管理控制器
 */
class FileController extends AdminBaseController
{
    /**
     * 文件列表页
     *
     * @return void
     */
    public function index(): void
    {
        try {
            $page = (int)($this->get('page', 1));
            $limit = (int)($this->get('limit', 20));
            $mimeType = $this->get('mime_type', '');
            $relatedType = $this->get('related_type', '');
            $fileUsage = $this->get('file_usage', '');
            
            // 构建查询条件
            $where = [];
            if (!empty($mimeType)) {
                $where['mime_type'] = $mimeType;
            }
            if (!empty($relatedType)) {
                $where['related_type'] = $relatedType;
            }
            if (!empty($fileUsage)) {
                $where['file_usage'] = $fileUsage;
            }
            
            // 获取文件列表
            $result = File::getList($page, $limit, $where);
            
            // 获取统计信息
            $statistics = File::getStatistics();
            
            $this->render('admin/file/index', [
                'title' => '附件管理',
                'files' => $result['list'],
                'total' => $result['total'],
                'page' => $page,
                'limit' => $limit,
                'mime_type' => $mimeType,
                'related_type' => $relatedType,
                'file_usage' => $fileUsage,
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
            preg_match('/\/admin\/file\/delete\/(\d+)/', $uri, $matches);
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

            $this->successJson('删除成功', null, '/admin/file');
        } catch (\Exception $e) {
            error_log('删除文件失败: ' . $e->getMessage());
            $this->errorJson('删除失败: ' . $e->getMessage());
        }
    }

    /**
     * 更新文件信息（关联信息、用途等）
     *
     * @return void
     */
    public function update(): void
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
                $this->errorJson('CSRF Token验证失败');
                return;
            }

            $id = (int)$this->post('id', 0);
            if ($id <= 0) {
                $this->errorJson('文件ID无效');
                return;
            }

            // 获取更新数据
            $relatedType = $this->post('related_type', '');
            $relatedId = (int)$this->post('related_id', 0);
            $fileUsage = $this->post('file_usage', '');
            $isPrimary = (int)$this->post('is_primary', 0);
            $sort = (int)$this->post('sort', 0);
            $description = $this->post('description', '');

            // 更新文件信息
            $result = File::updateRelated($id, $relatedType, $relatedId, $fileUsage, $isPrimary > 0);
            
            if (!$result) {
                $this->errorJson('更新失败');
                return;
            }

            // 如果有其他字段需要更新
            if ($sort > 0 || !empty($description)) {
                $db = Database::get();
                $updateData = [];
                if ($sort > 0) {
                    $updateData['sort'] = $sort;
                }
                if (!empty($description)) {
                    $updateData['description'] = Security::clean($description);
                }
                if (!empty($updateData)) {
                    $db = Database::get();
                    $db->update('sys_files', $updateData, ['id' => $id]);
                }
            }

            $this->successJson('更新成功');
        } catch (\Exception $e) {
            error_log('更新文件信息失败: ' . $e->getMessage());
            $this->errorJson('更新失败: ' . $e->getMessage());
        }
    }
}
