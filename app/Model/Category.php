<?php
declare(strict_types=1);

namespace App\Model;

use App\Core\Database;
use App\Service\LogService;

/**
 * 栏目模型
 */
class Category
{
    /**
     * 表名
     */
    private const TABLE = 'sys_category';

    /**
     * 获取所有栏目
     *
     * @param array<string, mixed> $conditions 查询条件
     * @param array<string, string>|string $order 排序
     * @return array<int, array<string, mixed>>
     */
    public static function getAll(array $conditions = [], array|string $order = ['sort' => 'ASC', 'id' => 'ASC']): array
    {
        try {
            $db = Database::get();
            
            // 使用 Medoo 的 select 方法
            $result = $db->select(self::TABLE, '*', array_merge($conditions, [
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
    public static function getById(int $id): ?array
    {
        try {
            $db = Database::get();

            // 使用Medoo的get方法
            $result = $db->get(self::TABLE, '*', [
                'id' => $id
            ]);
            
            return $result ? $result : null;
        } catch (\Exception $e) {
            error_log('获取栏目失败: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 获取无限级分类树结构
     *
     * @param int $parentId 父级ID（默认0表示顶级）
     * @param array<int, array<string, mixed>> $allCategories 所有栏目数据
     * @return array<int, array<string, mixed>>
     */
    public static function getTree(int $parentId = 0, ?array $allCategories = null): array
    {
        if ($allCategories === null) {
            $allCategories = self::getAll();
        }

        $tree = [];

        foreach ($allCategories as $category) {
            if ((int)$category['parent_id'] === $parentId) {
                $category['children'] = self::getTree((int)$category['id'], $allCategories);
                $category['level'] = self::getLevel($category['id'], $allCategories);
                $tree[] = $category;
            }
        }

        return $tree;
    }

    /**
     * 获取栏目层级
     *
     * @param int $id 栏目ID
     * @param array<int, array<string, mixed>> $allCategories 所有栏目
     * @param int $level 当前层级
     * @return int
     */
    private static function getLevel(int $id, array $allCategories, int $level = 0): int
    {
        foreach ($allCategories as $category) {
            if ((int)$category['id'] === $id) {
                if ((int)$category['parent_id'] === 0) {
                    return $level;
                }
                return self::getLevel((int)$category['parent_id'], $allCategories, $level + 1);
            }
        }
        return $level;
    }

    /**
     * 获取栏目路径（面包屑）
     *
     * @param int $id 栏目ID
     * @return array<int, array<string, mixed>>
     */
    public static function getPath(int $id): array
    {
        $path = [];
        $category = self::getById($id);

        while ($category && (int)$category['parent_id'] > 0) {
            array_unshift($path, $category);
            $category = self::getById((int)$category['parent_id']);
        }

        if ($category) {
            array_unshift($path, $category);
        }

        return $path;
    }

    /**
     * 保存栏目（添加或更新）
     *
     * @param array<string, mixed> $data 栏目数据
     * @return int 返回ID
     */
    public static function save(array $data): int
    {
        try {
            $db = Database::get();

            // 验证必填字段
            if (empty($data['name'])) {
                throw new \InvalidArgumentException('栏目名称不能为空');
            }

            // 准备数据
            $saveData = [
                'parent_id' => (int)($data['parent_id'] ?? 0),
                'name' => trim($data['name']),
                'slug' => isset($data['slug']) ? trim($data['slug']) : null,
                'type' => isset($data['type']) ? trim($data['type']) : 'article',
                'template' => isset($data['template']) ? trim($data['template']) : 'list.latte',
                'detail_template' => isset($data['detail_template']) ? trim($data['detail_template']) : 'article.latte',
                'sort' => (int)($data['sort'] ?? 0),
                'is_hidden' => isset($data['is_hidden']) ? (int)$data['is_hidden'] : 0,
                'update_time' => date('Y-m-d H:i:s')
            ];

            // 如果有ID则更新，否则插入
            if (isset($data['id']) && $data['id'] > 0) {
                $id = (int)$data['id'];
                
                // 使用 Medoo 的 update 方法
                $db->update(self::TABLE, $saveData, ['id' => $id]);
                
                // 记录更新日志
                LogService::updated("栏目: {$saveData['name']}", "ID: {$id}");
                
                return $id;
            } else {
                // 添加创建时间
                $saveData['create_time'] = date('Y-m-d H:i:s');
                
                // 使用 Medoo 的 insert 方法
                $db->insert(self::TABLE, $saveData);
                
                // 使用 Medoo 的 id() 方法获取最后插入ID
                $insertId = (int)$db->id();
                
                // 记录创建日志
                LogService::created("栏目: {$saveData['name']}", "ID: {$insertId}");
                
                return $insertId;
            }
        } catch (\Exception $e) {
            // 记录错误日志
            LogService::error($e, 'category_save_error');
            error_log('保存栏目失败: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 删除栏目
     *
     * @param int $id 栏目ID
     * @return bool
     * @throws \Exception
     */
    public static function delete(int $id): bool
    {
        try {
            $db = Database::get();

            // 检查栏目是否存在
            $category = self::getById($id);
            if (!$category) {
                throw new \Exception('栏目不存在');
            }

            // 使用 Medoo 的 count 方法检查是否有子栏目
            $childCount = $db->count(self::TABLE, ['parent_id' => $id]);

            if ($childCount > 0) {
                throw new \Exception('该栏目下还有子栏目，无法删除');
            }

            // TODO: 检查是否有文章（文章表创建后需要检查）
            // $articleCount = $db->count('sys_article', ['category_id' => $id]);
            // if ($articleCount > 0) {
            //     throw new \Exception('该栏目下还有文章，无法删除');
            // }

            // 获取栏目名称用于日志
            $categoryName = $category['name'] ?? '未知栏目';
            
            // 使用 Medoo 的 delete 方法删除栏目
            $db->delete(self::TABLE, ['id' => $id]);

            // 记录删除日志
            LogService::deleted("栏目: {$categoryName}", "ID: {$id}");

            return true;
        } catch (\Exception $e) {
            // 记录错误日志
            LogService::error($e, 'category_delete_error');
            error_log('删除栏目失败: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 检查别名是否存在
     *
     * @param string $slug 别名
     * @param int|null $excludeId 排除的ID（用于更新时检查）
     * @return bool
     */
    public static function slugExists(string $slug, ?int $excludeId = null): bool
    {
        try {
            $db = Database::get();

            $where = ['slug' => $slug];
            
            if ($excludeId !== null) {
                // 排除指定ID
                $where['id[!]'] = $excludeId;
            }

            // 使用 Medoo 的 has 方法检查记录是否存在
            return $db->has(self::TABLE, $where);
        } catch (\Exception $e) {
            error_log('检查别名失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取栏目的所有子栏目ID（包括所有层级）
     *
     * @param int $parentId 父级ID
     * @return array<int, int>
     */
    public static function getAllChildrenIds(int $parentId): array
    {
        $ids = [];
        $allCategories = self::getAll();

        foreach ($allCategories as $category) {
            if ((int)$category['parent_id'] === $parentId) {
                $ids[] = (int)$category['id'];
                $ids = array_merge($ids, self::getAllChildrenIds((int)$category['id']));
            }
        }

        return $ids;
    }

    /**
     * 更新排序
     *
     * @param array<int, array{id: int, sort: int}> $items 排序项
     * @return bool
     */
    public static function updateSort(array $items): bool
    {
        try {
            $db = Database::get();

            foreach ($items as $item) {
                // 使用 Medoo 的 update 方法
                $db->update(self::TABLE, [
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
