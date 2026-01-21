<?php
declare(strict_types=1);

namespace App\Models;

use Medoo\Medoo;

/**
 * Banner模型（数据访问对象）
 * 只负责数据库 CRUD 操作，不包含业务逻辑
 */
class Banner
{
    /**
     * 表名
     */
    private const TABLE = 'sys_banner';

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
     * 获取启用的banner列表
     *
     * @param int $limit 限制数量
     * @return array<int, array<string, mixed>>
     */
    public function getEnabled(int $limit = 10): array
    {
        try {
            return $this->db->select(self::TABLE, '*', [
                'status' => 1,
                'ORDER' => ['sort' => 'ASC', 'id' => 'ASC'],
                'LIMIT' => $limit
            ]) ?: [];
        } catch (\Throwable $e) {
            error_log('获取banner失败: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 根据类型获取启用的banner列表
     *
     * @param string $type 类型 ('banner', 'indexFast')
     * @param int $limit 限制数量
     * @return array<int, array<string, mixed>>
     */
    public function getByType(string $type, int $limit = 10): array
    {
        try {
            return $this->db->select(self::TABLE, '*', [
                'status' => 1,
                'type' => $type,
                'ORDER' => ['sort' => 'ASC', 'id' => 'ASC'],
                'LIMIT' => $limit
            ]) ?: [];
        } catch (\Throwable $e) {
            error_log('获取' . $type . ' banner失败: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 获取启用的轮播图（banner类型）
     *
     * @param int $limit 限制数量
     * @return array<int, array<string, mixed>>
     */
    public function getBanners(int $limit = 10): array
    {
        return $this->getByType('banner', $limit);
    }
    
    /**
     * 获取启用的首页快捷方式（indexFast类型）
     *
     * @param int $limit 限制数量
     * @return array<int, array<string, mixed>>
     */
    public function getIndexFastItems(int $limit = 10): array
    {
        return $this->getByType('indexFast', $limit);
    }
    
    /**
     * 获取启用的首页专题（indexZt类型）
     *
     * @param int $limit 限制数量
     * @return array<int, array<string, mixed>>
     */
    public function getIndexZtItems(int $limit = 10): array
    {
        return $this->getByType('indexZt', $limit);
    }
    
    /**
     * 分页列表
     *
     * @param int $page 页码
     * @param int $limit 每页条数
     * @return array{list: array<int, array<string, mixed>>, total: int}
     */
    public function getPage(int $page, int $limit): array
    {
        try {
            $offset = ($page - 1) * $limit;
            $list = $this->db->select(self::TABLE, '*', [
                'ORDER' => ['sort' => 'ASC', 'id' => 'ASC'],
                'LIMIT' => [$offset, $limit]
            ]);
            $total = $this->db->count(self::TABLE);
            return [
                'list' => $list ?: [],
                'total' => $total
            ];
        } catch (\Exception $e) {
            error_log('获取banner列表失败: ' . $e->getMessage());
            return [
                'list' => [],
                'total' => 0
            ];
        }
    }
    
    /**
     * 带类型筛选的分页列表
     *
     * @param int $page 页码
     * @param int $limit 每页条数
     * @param string $type 类型筛选 ('banner', 'indexFast', 或空值表示所有类型)
     * @return array{list: array<int, array<string, mixed>>, total: int}
     */
    public function getPageWithType(int $page, int $limit, string $type = ''): array
    {
        try {
            $offset = ($page - 1) * $limit;
            
            $where = [];
            if (!empty($type)) {
                $where['type'] = $type;
            }
            
            $list = $this->db->select(self::TABLE, '*', [
                'ORDER' => ['sort' => 'ASC', 'id' => 'ASC'],
                'LIMIT' => [$offset, $limit]
            ] + $where);
            $total = $this->db->count(self::TABLE, $where);
            return [
                'list' => $list ?: [],
                'total' => $total
            ];
        } catch (\Exception $e) {
            error_log('获取banner列表失败: ' . $e->getMessage());
            return [
                'list' => [],
                'total' => 0
            ];
        }
    }

    /**
     * 插入新记录
     *
     * @param array<string, mixed> $data 数据
     * @return int|false 返回ID，失败返回false
     */
    public function insert(array $data): int|false
    {
        try {
            // 确保包含 type 字段，默认为 'banner'
            if (!isset($data['type']) || empty($data['type'])) {
                $data['type'] = 'banner';
            }
            
            $this->db->insert(self::TABLE, $data);
            $id = (int)$this->db->id();
            return $id > 0 ? $id : false;
        } catch (\Exception $e) {
            error_log('插入banner失败: ' . $e->getMessage());
            return false;
        }
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
            // 如果提供了 type 字段但为空，使用默认值
            if (isset($data['type']) && empty($data['type'])) {
                $data['type'] = 'banner';
            }
            
            $this->db->update(self::TABLE, $data, ['id' => $id]);
            return true;
        } catch (\Exception $e) {
            error_log('更新banner失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 保存记录（插入或更新）
     *
     * @param array<string, mixed> $data 数据（包含id则为更新，否则为插入）
     * @return bool
     */
    public function save(array $data): bool
    {
        $id = (int)($data['id'] ?? 0);
        unset($data['id']);
        
        if ($id > 0) {
            return $this->update($id, $data);
        } else {
            return $this->insert($data) !== false;
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
            error_log('删除banner失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 根据ID获取记录
     *
     * @param int $id 记录ID
     * @return array<string, mixed>|null
     */
    public function getById(int $id): ?array
    {
        try {
            return $this->db->get(self::TABLE, '*', ['id' => $id]) ?: null;
        } catch (\Exception $e) {
            error_log('获取banner失败: ' . $e->getMessage());
            return null;
        }
    }
}
