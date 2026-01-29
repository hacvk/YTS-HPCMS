<?php
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\AdminBaseController;
use App\Core\Security;
use App\Models\File;
use App\Services\LogService;
use App\Services\FileService;

/**
 * 附件管理控制器
 */
class AttachmentController extends AdminBaseController
{
    public function __construct(
        private readonly File $fileModel,
        private readonly \Medoo\Medoo $db
    ) {
        parent::__construct();
    }

    /**
     * 附件列表页
     *
     * @return void
     */
    public function index(): void
    {
        $this->requirePerm('admin:attachment:view');
        try {
            $page = (int)($this->get('page', 1));
            $limit = (int)($this->get('limit', 20));
            $mimeType = $this->get('mime_type', '');
            $relatedType = $this->get('related_type', '');
            $fileUsage = $this->get('file_usage', '');
            $keyword = $this->get('keyword', '');
            
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
            
            // 验证关键词参数
            if (!empty($keyword)) {
                // 限制长度
                if (strlen($keyword) > 100) {
                    $this->errorJson('关键词过长');
                    return;
                }
                // 转义特殊字符
                $keyword = htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8');
            }
            
            
            // 获取附件列表
            $result = $this->fileModel->getList($page, $limit, $where);
            
            // 如果有关键词搜索，进行过滤
            if (!empty($keyword)) {
                $filteredList = [];
                foreach ($result['list'] as $file) {
                    $searchText = strtolower($keyword);
                    $fileName = strtolower($file['name'] ?? '');
                    $filePath = strtolower($file['path'] ?? '');
                    $fileDescription = strtolower($file['description'] ?? '');
                    
                    if (strpos($fileName, $searchText) !== false 
                        || strpos($filePath, $searchText) !== false
                        || strpos($fileDescription, $searchText) !== false) {
                        $filteredList[] = $file;
                    }
                }
                $result['list'] = $filteredList;
                $result['total'] = count($filteredList);
            }
            
            // 获取统计信息
            $statistics = $this->fileModel->getStatistics();
            
            // 获取所有文件类型用于筛选
            $db = $this->db;
            $mimeTypes = $db->select('sys_files', [
                'mime_type',
                'count' => $db->raw('COUNT(*)')
            ], [
                'GROUP' => 'mime_type',
                'ORDER' => ['count' => 'DESC']
            ]);
            
            $this->render('admin/attachment/index', [
                'title' => '附件管理',
                'files' => $result['list'],
                'total' => $result['total'],
                'page' => $page,
                'limit' => $limit,
                'mime_type' => $mimeType,
                'related_type' => $relatedType,
                'file_usage' => $fileUsage,
                'keyword' => $keyword,
                'statistics' => $statistics,
                'mime_types' => $mimeTypes ?: []
            ]);
        } catch (\Exception $e) {
            error_log('获取附件列表失败: ' . $e->getMessage());
            $this->error('加载页面失败: ' . $e->getMessage());
        }
    }

    /**
     * 删除附件
     *
     * @return void
     */
    public function delete(): void
    {
        $this->requirePerm('admin:attachment:delete');

        try {
            // 验证POST请求和CSRF
            if (!$this->validatePostAndCsrf()) {
                return;
            }
            
            // 从URL参数中获取ID
            $id = (int)$this->get('id', 0);
            
            // 如果URL参数没有ID，尝试从POST数据中获取
            if ($id <= 0) {
                $id = (int)$this->post('id', 0);
            }
            
            // 如果还是没有ID，尝试从路径中解析（兼容旧版本）
            if ($id <= 0) {
                $uri = $_SERVER['REQUEST_URI'] ?? '';
                preg_match('/\/admin\/attachment\/delete\/(\d+)/', $uri, $matches);
                $id = (int)($matches[1] ?? 0);
            }

            if ((int)$id <= 0) {
                $this->errorJson('附件ID无效');
                return;
            }

            // 获取附件信息
            $file = $this->fileModel->getById((int)$id);
            if (!$file) {
                $this->errorJson('附件不存在');
                return;
            }

            $fileName = $file['name'] ?? '未知';

            // 删除物理文件
            if (!FileService::deletePhysicalFile($file['path'])) {
                error_log('删除附件文件失败: ' . $file['path']);
            }

            // 删除数据库记录
            $result = $this->fileModel->delete((int)$id);

            if (!$result) {
                // 记录删除失败日志
                LogService::record(
                    $this->getAdminId(),
                    $this->getAdminUsername(),
                    'error',
                    "附件删除失败: {$fileName} (ID: {$id})",
                    "删除附件失败，文件名: {$fileName}"
                );
                $this->errorJson('删除失败');
                return;
            }

            // 记录删除成功日志
            LogService::record(
                $this->getAdminId(),
                $this->getAdminUsername(),
                'delete',
                "附件ID: {$id}",
                "删除附件: {$fileName} (ID: {$id})"
            );

            $this->successJson('删除成功');
        } catch (\Exception $e) {
            error_log('删除附件失败: ' . $e->getMessage());
            $this->errorJson('删除失败: ' . $e->getMessage());
        }
    }

    /**
     * 批量删除附件
     *
     * @return void
     */
    public function batchDelete(): void
    {
        $this->requirePerm('admin:attachment:delete');

        try {
            // 验证POST请求和CSRF
            if (!$this->validatePostAndCsrf()) {
                return;
            }

            $ids = $this->post('ids', '');
            if (empty($ids)) {
                $this->errorJson('请选择要删除的附件');
                return;
            }

            // 解析ID列表
            $idArray = is_array($ids) ? $ids : explode(',', $ids);
            $idArray = array_filter(array_map('intval', $idArray), fn($id) => $id > 0);

            if (empty($idArray)) {
                $this->errorJson('附件ID无效');
                return;
            }

            $successCount = 0;
            $failCount = 0;

            foreach ($idArray as $id) {
                $file = $this->fileModel->getById($id);
                if (!$file) {
                    $failCount++;
                    continue;
                }

                // 删除物理文件
                FileService::deletePhysicalFile($file['path']);

                // 删除数据库记录
                if ($this->fileModel->delete($id)) {
                    $successCount++;
                } else {
                    $failCount++;
                }
            }

            // 记录批量删除日志
            LogService::record(
                $this->getAdminId(),
                $this->getAdminUsername(),
                'delete',
                "批量删除附件",
                "批量删除附件: 成功 {$successCount} 个，失败 {$failCount} 个"
            );

            if ($failCount > 0) {
                $this->errorJson("删除完成：成功 {$successCount} 个，失败 {$failCount} 个");
                return;
            }

            $adminPath = $this->getAdminPath();
            $this->successJson("成功删除 {$successCount} 个附件", null, $adminPath . '/attachment');

        } catch (\Exception $e) {
            error_log('批量删除附件失败: ' . $e->getMessage());
            $this->errorJson('批量删除失败: ' . $e->getMessage());
        }
    }

    /**
     * 更新附件信息
     *
     * @return void
     */
    public function update(): void
    {
        $this->requirePerm('admin:attachment:update');

        try {
            // 验证POST请求和CSRF
            if (!$this->validatePostAndCsrf()) {
                return;
            }

            $id = (int)$this->post('id', 0);
            if ($id <= 0) {
                $this->errorJson('附件ID无效');
                return;
            }

            // 获取更新数据
            $relatedType = $this->post('related_type', '');
            $relatedId = (int)$this->post('related_id', 0);
            $fileUsage = $this->post('file_usage', '');
            $isPrimary = (int)$this->post('is_primary', 0);
            $sort = (int)$this->post('sort', 0);
            $description = $this->post('description', '');

            // 更新附件信息
            $result = $this->fileModel->updateRelated($id, $relatedType, $relatedId, $fileUsage, $isPrimary > 0);
            
            if (!$result) {
                $this->errorJson('更新失败');
                return;
            }

            // 如果有其他字段需要更新
            if ($sort > 0 || !empty($description)) {
                $db = \App\Core\Database::get();
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

            // 记录更新日志
            LogService::record(
                $this->getAdminId(),
                $this->getAdminUsername(),
                'update',
                "附件ID: {$id}",
                "更新附件信息: ID {$id}"
            );

            $this->successJson('更新成功');
        } catch (\Exception $e) {
            error_log('更新附件信息失败: ' . $e->getMessage());
            $this->errorJson('更新失败: ' . $e->getMessage());
        }
    }
}
