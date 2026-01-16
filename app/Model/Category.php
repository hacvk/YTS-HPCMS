<?php
declare(strict_types=1);

namespace App\Model;

use Medoo\Medoo;

/**
 * 栏目模型（数据访问对象）
 * 只负责数据库的 CRUD 操作，不包含业务逻辑
 */
class Category
{
    /**
     * 表名
     */
    private const TABLE = 'sys_category';

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
     * 获取所有栏目
     *
     * @param array<string, mixed> $conditions 查询条件
     * @param array<string, string>|string $order 排序
     * @return array<int, array<string, mixed>>
     */
    public function getAll(array $conditions = [], array|string $order = ['sort' => 'ASC', 'id' => 'ASC']): array
    {
        try {
            // 使用 Medoo 的 select 方法
            $result = $this->db->select(self::TABLE, '*', array_merge($conditions, [
                'ORDER' => $order
            ]));
            
            return $result ?: [];
        } catch (\Exception $e) {
            error_log('获取栏目列表失败: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 根据ID获取栏目
     *
     * @param int $id 栏目ID
     * @return array<string, mixed>|null
     */
    public function getById(int $id): ?array
    {
        try {
            // 使用Medoo的get方法
            $result = $this->db->get(self::TABLE, '*', [
                'id' => $id
            ]);
            
            return $result ? $result : null;
        } catch (\Exception $e) {
            error_log('获取栏目失败: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 插入新记录
     *
     * @param array<string, mixed> $data 数据
     * @return int 返回ID
     */
    public function insert(array $data): int
    {
        $this->db->insert(self::TABLE, $data);
        return (int)$this->db->id();
    }

    /**
     * 更新记录
     *
     * @param int $id 记录ID
     * @param array<string, mixed> $data 数据
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        try {
            $this->db->update(self::TABLE, $data, ['id' => $id]);
            return true;
        } catch (\Exception $e) {
            error_log('更新栏目失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 删除记录
     *
     * @param int $id 记录ID
     * @return bool
     */
    public function delete(int $id): bool
    {
        try {
            $this->db->delete(self::TABLE, ['id' => $id]);
            return true;
        } catch (\Exception $e) {
            error_log('删除栏目失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 检查别名是否存在
     *
     * @param string $slug 别名
     * @param int|null $excludeId 排除的ID（用于更新时检查）
     * @return bool
     */
    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        try {
            $where = ['slug' => $slug];
            
            if ($excludeId !== null) {
                // 排除指定ID
                $where['id[!]'] = $excludeId;
            }

            // 使用 Medoo 的 has 方法检查记录是否存在
            return $this->db->has(self::TABLE, $where);
        } catch (\Exception $e) {
            error_log('检查别名失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 统计记录数
     *
     * @param array<string, mixed> $where 查询条件
     * @return int
     */
    public function count(array $where = []): int
    {
        try {
            return $this->db->count(self::TABLE, $where);
        } catch (\Exception $e) {
            error_log('统计栏目失败: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * 批量更新排序
     *
     * @param array<int, array{id: int, sort: int}> $items 排序项
     * @return bool
     */
    public function updateSort(array $items): bool
    {
        try {
            foreach ($items as $item) {
                // 使用 Medoo 的 update 方法
                $this->db->update(self::TABLE, [
                    'sort' => (int)$item['sort'],
                    'update_time' => date('Y-m-d H:i:s')
                ], [
                    'id' => (int)$item['id']
                ]);
            }

            return true;
        } catch (\Exception $e) {
            error_log('更新排序失败: ' . $e->getMessage());
            return false;
        }
    }
}
