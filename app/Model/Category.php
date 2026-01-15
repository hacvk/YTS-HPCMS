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
     * @param string $order 排序
     * @return array<int, array<string, mixed>>
     */
    public static function getAll(array $conditions = [], string $order = 'sort ASC, id ASC'): array
    {
        try {
            $db = Database::get();
            
            // 使用PDO原生查询，直接执行SQL语句
            $stmt = $db->pdo->query("SELECT * FROM " . self::TABLE . " ORDER BY " . $order);
            return $stmt->fetchAll();
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

            $parentId = (int)($data['parent_id'] ?? 0);
            $name = trim($data['name']);
            $slug = isset($data['slug']) ? trim($data['slug']) : null;
            $type = isset($data['type']) ? trim($data['type']) : 'article';
            $template = isset($data['template']) ? trim($data['template']) : 'list.latte';
            $detailTemplate = isset($data['detail_template']) ? trim($data['detail_template']) : 'article.latte';
            $sort = (int)($data['sort'] ?? 0);
            $isHidden = isset($data['is_hidden']) ? (int)$data['is_hidden'] : 0;
            $updateTime = date('Y-m-d H:i:s');

            // 如果有ID则更新，否则插入
            if (isset($data['id']) && $data['id'] > 0) {
                $id = (int)$data['id'];
                // 使用PDO原生更新
                $stmt = $db->pdo->prepare(
                    "UPDATE " . self::TABLE . " SET parent_id = ?, name = ?, slug = ?, type = ?, template = ?, detail_template = ?, sort = ?, is_hidden = ?, update_time = ? WHERE id = ?"
                );
                $stmt->execute([$parentId, $name, $slug, $type, $template, $detailTemplate, $sort, $isHidden, $updateTime, $id]);
                
                // 记录更新日志
                LogService::updated("栏目: {$name}", "ID: {$id}");
                
                return $id;
            } else {
                $createTime = date('Y-m-d H:i:s');
                // 使用PDO原生插入
                $stmt = $db->pdo->prepare(
                    "INSERT INTO " . self::TABLE . " (parent_id, name, slug, type, template, detail_template, sort, is_hidden, create_time, update_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([$parentId, $name, $slug, $type, $template, $detailTemplate, $sort, $isHidden, $createTime, $updateTime]);
                $insertId = (int)$db->pdo->lastInsertId();
                
                // 记录创建日志
                LogService::created("栏目: {$name}", "ID: {$insertId}");
                
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

            // 检查是否有子栏目
            $stmt = $db->pdo->prepare("SELECT COUNT(*) FROM " . self::TABLE . " WHERE parent_id = ?");
            $stmt->execute([$id]);
            $childCount = (int)$stmt->fetchColumn();

            if ($childCount > 0) {
                throw new \Exception('该栏目下还有子栏目，无法删除');
            }

            // TODO: 检查是否有文章（文章表创建后需要检查）
            // $stmt = $db->pdo->prepare("SELECT COUNT(*) FROM sys_article WHERE category_id = ?");
            // $stmt->execute([$id]);
            // $articleCount = (int)$stmt->fetchColumn();
            // if ($articleCount > 0) {
            //     throw new \Exception('该栏目下还有文章，无法删除');
            // }

            // 获取栏目名称用于日志
            $categoryName = $category['name'] ?? '未知栏目';
            
            // 删除栏目
            $stmt = $db->pdo->prepare("DELETE FROM " . self::TABLE . " WHERE id = ?");
            $stmt->execute([$id]);

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

            if ($excludeId !== null) {
                // 排除指定ID
                $stmt = $db->pdo->prepare("SELECT COUNT(*) FROM " . self::TABLE . " WHERE slug = ? AND id != ?");
                $stmt->execute([$slug, $excludeId]);
            } else {
                // 不排除任何ID
                $stmt = $db->pdo->prepare("SELECT COUNT(*) FROM " . self::TABLE . " WHERE slug = ?");
                $stmt->execute([$slug]);
            }

            $count = (int)$stmt->fetchColumn();
            return $count > 0;
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
                $stmt = $db->query(
                    "UPDATE " . self::TABLE . " SET sort = ?, update_time = ? WHERE id = ?",
                    [(int)$item['sort'], date('Y-m-d H:i:s'), (int)$item['id']]
                );
            }

            return true;
        } catch (\Exception $e) {
            error_log('更新排序失败: ' . $e->getMessage());
            return false;
        }
    }
}
