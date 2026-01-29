<?php
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\AdminBaseController;
use App\Core\Database;
use App\Core\Exception\PathTraversalException;
use App\Core\Security;
use App\Models\File;
use App\Services\FileService;

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
        $this->requirePerm('admin:file:view');
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
            $db = Database::get();
            $fileModel = new File($db);
            $result = $fileModel->getList($page, $limit, $where);
            
            // 获取统计信息
            $statistics = $fileModel->getStatistics();
            
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
        $this->requirePerm('admin:file:delete');
        try {
            // 验证POST请求和CSRF
            if (!$this->validatePostAndCsrf()) {
                return;
            }
            
            // 从URL中获取ID参数
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            $adminPath = $this->getAdminPath();
            $pattern = '#'.preg_quote($adminPath, '#').'/file/delete/(\\d+)#';
            preg_match($pattern, $uri, $matches);
            $id = $matches[1] ?? 0;

            if ((int)$id <= 0) {
                $this->errorJson('文件ID无效');
                return;
            }

            // 使用 FileService 删除文件（包含路径遍历防御和事务一致性）
            $db = Database::get();
            $fileModel = new File($db);
            
            try {
                $result = FileService::deleteFile((int)$id, $fileModel, $db);
                
                if (!$result) {
                    $this->errorJson('删除失败');
                    return;
                }
                
                $this->successJson('删除成功', null, $adminPath . '/file');
            } catch (PathTraversalException $e) {
                // 路径遍历攻击，记录日志并返回错误
                error_log('[FileController] 路径遍历攻击检测 ' . $e->getMessage());
                $this->errorJson('安全验证失败：非法路径');
            } catch (\Exception $e) {
                error_log('删除文件失败: ' . $e->getMessage());
                $this->errorJson('删除失败: ' . $e->getMessage());
            }
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
        $this->requirePerm('admin:file:update');
        try {
            // 验证POST请求和CSRF
            if (!$this->validatePostAndCsrf()) {
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
            $db = Database::get();
            $fileModel = new File($db);
            $result = $fileModel->updateRelated($id, $relatedType, $relatedId, $fileUsage, $isPrimary > 0);
            
            if (!$result) {
                $this->errorJson('更新失败');
                return;
            }

            // 如果有其他字段需要更新
            if ($sort > 0 || !empty($description)) {
                $updateData = [];
                if ($sort > 0) {
                    $updateData['sort'] = $sort;
                }
                if (!empty($description)) {
                    $updateData['description'] = Security::clean($description);
                }
                if (!empty($updateData)) {
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
