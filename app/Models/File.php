<?php
declare(strict_types=1);

namespace App\Models;

use Medoo\Medoo;

/**
 * 文件模型（数据访问对象）
 * 只负责数据库 CRUD 操作，不包含业务逻辑
 */
class File
{
    /**
     * 表名
     */
    private const TABLE = 'sys_files';

    /**
     * 构造函数（使用 PHP 8.0 构造函数属性提升）
     *
     * @param Medoo $db 数据库实例
     */
    public function __construct(
        private readonly Medoo $db
    ) {
    }

    /**
     * 插入新记录
     *
     * @param array<string, mixed> $data 文件数据
     * @return int|false 返回文件ID，失败返回false
     */
    public function insert(array $data): int|false
    {
        try {
            // 设置创建时间
            if (!isset($data['created_at'])) {
                $data['created_at'] = date('Y-m-d H:i:s');
            }
            
            // 设置默认值
            if (!isset($data['sort'])) {
                $data['sort'] = 0;
            }
            if (!isset($data['is_primary'])) {
                $data['is_primary'] = 0;
            }
            
            // 插入文件记录
            $result = $this->db->insert(self::TABLE, $data);
            
            if ($result) {
                return (int)$this->db->id();
            }
            return false;
        } catch (\Exception $e) {
            error_log('保存文件记录失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 根据ID获取文件
     *
     * @param int $id 文件ID
     * @return array<string, mixed>|false
     */
    public function getById(int $id): array|false
    {
        try {
            $file = $this->db->get(
                self::TABLE,
                '*',
                ['id' => $id]
            );
            
            return $file ?: false;
        } catch (\Exception $e) {
            error_log('获取文件失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 根据hash获取文件
     *
     * @param string $hash 文件hash
     * @return array<string, mixed>|false
     */
    public function getByHash(string $hash): array|false
    {
        try {
            $file = $this->db->get(
                self::TABLE,
                '*',
                ['hash' => $hash]
            );
            
            return $file ?: false;
        } catch (\Exception $e) {
            error_log('获取文件失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取文件列表
     *
     * @param int $page 页码
     * @param int $limit 每页条数
     * @param array<string, mixed> $where 查询条件
     * @return array{list: array<int, array<string, mixed>>, total: int}
     */
    public function getList(int $page, int $limit, array $where = []): array
    {
        try {
            $offset = ($page - 1) * $limit;
            
            // 构建查询条件
            $conditions = [];
            if (isset($where['uploaded_by']) && $where['uploaded_by'] > 0) {
                $conditions['uploaded_by'] = $where['uploaded_by'];
            }
            if (isset($where['mime_type']) && !empty($where['mime_type'])) {
                $conditions['mime_type[~]'] = $where['mime_type'];
            }
            if (isset($where['related_type']) && !empty($where['related_type'])) {
                $conditions['related_type'] = $where['related_type'];
            }
            if (isset($where['related_id']) && $where['related_id'] > 0) {
                $conditions['related_id'] = $where['related_id'];
            }
            if (isset($where['file_usage']) && !empty($where['file_usage'])) {
                $conditions['file_usage'] = $where['file_usage'];
            }
            if (isset($where['is_primary']) && $where['is_primary'] !== '') {
                $conditions['is_primary'] = $where['is_primary'];
            }
            
            // 查询列表
            $list = $this->db->select(
                self::TABLE,
                '*',
                array_merge($conditions, [
                    'ORDER' => ['sort' => 'ASC', 'id' => 'DESC'],
                    'LIMIT' => [$offset, $limit]
                ])
            );
            
            // 获取总数
            $total = $this->db->count(self::TABLE, $conditions);
            
            return [
                'list' => $list ?: [],
                'total' => $total
            ];
        } catch (\Exception $e) {
            error_log('获取文件列表失败: ' . $e->getMessage());
            return [
                'list' => [],
                'total' => 0
            ];
        }
    }

    /**
     * 根据关联信息获取文件列表
     *
     * @param string $relatedType 关联类型
     * @param int $relatedId 关联ID
     * @param string|null $fileUsage 文件用途（可选）
     * @return array<int, array<string, mixed>>
     */
    public function getByRelated(string $relatedType, int $relatedId, ?string $fileUsage = null): array
    {
        try {
            $conditions = [
                'related_type' => $relatedType,
                'related_id' => $relatedId
            ];
            
            if ($fileUsage !== null) {
                $conditions['file_usage'] = $fileUsage;
            }
            
            $list = $this->db->select(
                self::TABLE,
                '*',
                array_merge($conditions, [
                    'ORDER' => ['is_primary' => 'DESC', 'sort' => 'ASC', 'id' => 'ASC']
                ])
            );
            
            return $list ?: [];
        } catch (\Exception $e) {
            error_log('获取关联文件失败: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取主图/封面
     *
     * @param string $relatedType 关联类型
     * @param int $relatedId 关联ID
     * @return array<string, mixed>|false
     */
    public function getPrimary(string $relatedType, int $relatedId): array|false
    {
        try {
            $file = $this->db->get(
                self::TABLE,
                '*',
                [
                    'related_type' => $relatedType,
                    'related_id' => $relatedId,
                    'is_primary' => 1,
                    'ORDER' => ['sort' => 'ASC', 'id' => 'ASC']
                ]
            );
            
            return $file ?: false;
        } catch (\Exception $e) {
            error_log('获取主图失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 更新文件关联信息
     *
     * @param int $fileId 文件ID
     * @param string $relatedType 关联类型
     * @param int $relatedId 关联ID
     * @param string|null $fileUsage 文件用途
     * @param bool $isPrimary 是否为主图
     * @return bool
     */
    public function updateRelated(int $fileId, string $relatedType, int $relatedId, ?string $fileUsage = null, bool $isPrimary = false): bool
    {
        try {
            $data = [
                'related_type' => $relatedType,
                'related_id' => $relatedId,
                'is_primary' => $isPrimary ? 1 : 0
            ];
            
            if ($fileUsage !== null) {
                $data['file_usage'] = $fileUsage;
            }
            
            $this->db->update(self::TABLE, $data, ['id' => $fileId]);
            
            return true;
        } catch (\Exception $e) {
            error_log('更新文件关联信息失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 删除文件记录
     *
     * @param int $id 文件ID
     * @return bool
     */
    public function delete(int $id): bool
    {
        try {
            $this->db->delete(self::TABLE, ['id' => $id]);
            return true;
        } catch (\Exception $e) {
            error_log('删除文件记录失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 根据关联信息删除文件记录
     *
     * @param string $relatedType 关联类型
     * @param int $relatedId 关联ID
     * @return bool
     */
    public function deleteByRelated(string $relatedType, int $relatedId): bool
    {
        try {
            $this->db->delete(self::TABLE, [
                'related_type' => $relatedType,
                'related_id' => $relatedId
            ]);
            return true;
        } catch (\Exception $e) {
            error_log('删除关联文件失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取文件统计信息
     *
     * @return array<string, mixed>
     */
    public function getStatistics(): array
    {
        try {
            // 总文件数
            $totalFiles = $this->db->count(self::TABLE);
            
            // 总大小
            $totalSize = $this->db->sum(self::TABLE, 'size') ?: 0;
            
            // 按类型统计
            $typeStats = $this->db->select(
                self::TABLE,
                [
                    'mime_type',
                    'COUNT(*)[as count]',
                    'SUM(size) as total_size'
                ],
                [
                    'GROUP' => 'mime_type',
                    'ORDER' => ['count' => 'DESC']
                ]
            );
            
            return [
                'total_files' => $totalFiles,
                'total_size' => (int)$totalSize,
                'total_size_mb' => round((int)$totalSize / 1024 / 1024, 2),
                'type_stats' => $typeStats ?: []
            ];
        } catch (\Exception $e) {
            error_log('获取文件统计失败: ' . $e->getMessage());
            return [
                'total_files' => 0,
                'total_size' => 0,
                'total_size_mb' => 0,
                'type_stats' => []
            ];
        }
    }
}
