<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Category;

/**
 * 栏目服务类
 * 处理栏目相关的业务逻辑
 */
class CategoryService
{
    /**
     * 构造函数（使用 PHP 8.0 构造函数属性提升）
     *
     * @param Category $categoryModel 栏目模型实例
     */
    public function __construct(
        private readonly Category $categoryModel
    ) {
    }

    /**
     * 获取无限级分类树结构
     *
     * @param int $parentId 父级ID（默认0表示顶级）
     * @param array<int, array<string, mixed>>|null $allCategories 所有栏目数据
     * @return array<int, array<string, mixed>>
     */
    public function getTree(int $parentId = 0, ?array $allCategories = null): array
    {
        if ($allCategories === null) {
            $allCategories = $this->categoryModel->getAll();
        }

        $tree = [];

        foreach ($allCategories as $category) {
            if ((int)$category['parent_id'] === $parentId) {
                $category['children'] = $this->getTree((int)$category['id'], $allCategories);
                $category['level'] = $this->getLevel($category['id'], $allCategories);
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
    private function getLevel(int $id, array $allCategories, int $level = 0): int
    {
        foreach ($allCategories as $category) {
            if ((int)$category['id'] === $id) {
                if ((int)$category['parent_id'] === 0) {
                    return $level;
                }
                return $this->getLevel((int)$category['parent_id'], $allCategories, $level + 1);
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
    public function getPath(int $id): array
    {
        $path = [];
        $category = $this->categoryModel->getById($id);

        while ($category && (int)$category['parent_id'] > 0) {
            array_unshift($path, $category);
            $category = $this->categoryModel->getById((int)$category['parent_id']);
        }

        if ($category) {
            array_unshift($path, $category);
        }

        return $path;
    }

    /**
     * 获取栏目的所有子栏目ID（包括所有层级）
     *
     * @param int $parentId 父级ID
     * @return array<int, int>
     */
    public function getAllChildrenIds(int $parentId): array
    {
        $ids = [];
        $allCategories = $this->categoryModel->getAll();

        foreach ($allCategories as $category) {
            if ((int)$category['parent_id'] === $parentId) {
                $ids[] = (int)$category['id'];
                $ids = array_merge($ids, $this->getAllChildrenIds((int)$category['id']));
            }
        }

        return $ids;
    }

    /**
     * 保存栏目（添加或更新）
     *
     * @param array<string, mixed> $data 栏目数据
     * @return int 返回ID
     */
    public function save(array $data): int
    {
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
            $this->categoryModel->update($id, $saveData);
            return $id;
        } else {
            // 添加创建时间
            $saveData['create_time'] = date('Y-m-d H:i:s');
            return $this->categoryModel->insert($saveData);
        }
    }

    /**
     * 删除栏目
     *
     * @param int $id 栏目ID
     * @return bool
     * @throws \Exception
     */
    public function delete(int $id): bool
    {
        // 检查栏目是否存在
        $category = $this->categoryModel->getById($id);
        if (!$category) {
            throw new \Exception('栏目不存在');
        }

        // 检查是否有子栏目
        $childCount = $this->categoryModel->count(['parent_id' => $id]);

        if ($childCount > 0) {
            throw new \Exception('该栏目下还有子栏目，无法删除');
        }

        // TODO: 检查是否有文章（文章表创建后需要检查）
        // $articleCount = $db->count('sys_article', ['category_id' => $id]);
        // if ($articleCount > 0) {
        //     throw new \Exception('该栏目下还有文章，无法删除');
        // }

        // 删除栏目
        return $this->categoryModel->delete($id);
    }
}
