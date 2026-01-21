<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Category;

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
     * 优化版本：使用引用传递算法，时间复杂度从 O(n^2) 降至 O(n)
     * 
     * 算法原理：
     * 1. 一次循环遍历所有分类，建立 ID -> 分类数据的映射
     * 2. 使用引用传递，将子分类直接添加到父分类的 children 数组中
     * 3. 同时计算层级，避免递归查找
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

        if (empty($allCategories)) {
            return [];
        }

        // 第一步：建立 ID -> 分类数据的映射，并初始化 children 和 level
        foreach ($allCategories as &$category) {
            $id = (int)$category['id'];
            $category['children'] = [];
            $category['level'] = 0; // 初始层级，后续计算
            $categoryMap[$id] = &$category;
        }
        unset($category); // 释放引用

        // 第二步：建立 parent_id -> 子分类ID列表的映射
        $childrenMap = [];
        foreach ($allCategories as $category) {
            $pid = (int)$category['parent_id'];
            if (!isset($childrenMap[$pid])) {
                $childrenMap[$pid] = [];
            }
            $childrenMap[$pid][] = (int)$category['id'];
        }

        // 第三步：使用引用传递构建树结构，并计算层级
        // 从根节点开始，使用 BFS（广度优先搜索）计算层级
        $rootNodes = [];
        foreach ($allCategories as &$category) {
            $id = (int)$category['id'];
            $pid = (int)$category['parent_id'];
            
            if ($pid === 0) {
                // 根节点
                $rootNodes[] = $id;
            } else {
                // 子节点：通过引用直接添加到父节点的 children 数组
                if (isset($categoryMap[$pid])) {
                    $categoryMap[$pid]['children'][] = &$category;
                }
            }
        }
        unset($category); // 释放引用

        // 第四步：计算每个节点的层级（使用 BFS）
        $this->calculateLevels($categoryMap, $childrenMap, $rootNodes);

        // 第五步：根据 parentId 返回对应的树结构
        if ($parentId === 0) {
            // 返回所有根节点
            $tree = [];
            foreach ($rootNodes as $rootId) {
                if (isset($categoryMap[$rootId])) {
                    $tree[] = $categoryMap[$rootId];
                }
            }
            return $tree;
        } else {
            // 返回指定父节点的子节点树
            if (isset($categoryMap[$parentId])) {
                return $categoryMap[$parentId]['children'];
            }
            return [];
        }
    }

    /**
     * 计算所有节点的层级（使用 BFS 广度优先搜索）
     *
     * @param array<int, array<string, mixed>> $categoryMap 分类映射表（引用传递）
     * @param array<int, array<int>> $childrenMap 子节点映射表
     * @param array<int> $rootNodes 根节点ID列表
     * @return void
     */
    private function calculateLevels(array &$categoryMap, array $childrenMap, array $rootNodes): void
    {
        // 使用队列进行 BFS
        $queue = [];
        
        // 初始化：所有根节点的层级为 0
        foreach ($rootNodes as $rootId) {
            if (isset($categoryMap[$rootId])) {
                $categoryMap[$rootId]['level'] = 0;
                $queue[] = $rootId;
            }
        }

        // BFS 遍历：计算每个节点的层级
        while (!empty($queue)) {
            $currentId = array_shift($queue);
            $currentLevel = $categoryMap[$currentId]['level'] ?? 0;

            // 处理当前节点的所有子节点
            if (isset($childrenMap[$currentId])) {
                foreach ($childrenMap[$currentId] as $childId) {
                    if (isset($categoryMap[$childId])) {
                        $categoryMap[$childId]['level'] = $currentLevel + 1;
                        $queue[] = $childId;
                    }
                }
            }
        }
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
     * 优化版本：使用 BFS 算法，时间复杂度 O(n)
     *
     * @param int $parentId 父级ID
     * @return array<int, int>
     */
    public function getAllChildrenIds(int $parentId): array
    {
        $allCategories = $this->categoryModel->getAll();
        
        if (empty($allCategories)) {
            return [];
        }

        // 建立 parent_id -> 子分类ID列表的映射
        $childrenMap = [];
        foreach ($allCategories as $category) {
            $pid = (int)$category['parent_id'];
            if (!isset($childrenMap[$pid])) {
                $childrenMap[$pid] = [];
            }
            $childrenMap[$pid][] = (int)$category['id'];
        }

        // 使用 BFS 收集所有子节点ID
        $ids = [];
        $queue = [$parentId];
        
        while (!empty($queue)) {
            $currentId = array_shift($queue);
            
            if (isset($childrenMap[$currentId])) {
                foreach ($childrenMap[$currentId] as $childId) {
                    $ids[] = $childId;
                    $queue[] = $childId;
                }
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
        $banner = isset($data['banner']) ? trim((string)$data['banner']) : null;
        // 验证并处理banner图片路径，确保文件实际存在
        if (!empty($banner)) {
            // 使用与上传逻辑一致的路径计算方式
            // 修复路径计算逻辑
            $root = dirname(dirname(__DIR__)); // 从 app/Services 开始
            
            // 确保我们找到的是正确的项目根目录
            if (basename($root) === 'app') {
                $root = dirname($root); // 上一级才是真正的项目根目录
            }
            $fullPath = $root . '/public' . $banner;
            
            // 记录调试信息
            error_log("CategoryService Banner图片路径验证: \$banner = $banner");
            error_log("CategoryService 项目根目录: " . $root);
            error_log("CategoryService 文档根目录: " . ($_SERVER['DOCUMENT_ROOT'] ?? '未设置'));
            error_log("CategoryService Banner完整路径: \$fullPath = $fullPath");
            error_log("CategoryService 文件是否存在: " . (file_exists($fullPath) ? '是' : '否')); 
            error_log("CategoryService public目录是否存在: " . (is_dir($root . '/public') ? '是' : '否'));
            error_log("CategoryService uploads目录是否存在: " . (is_dir($root . '/public/uploads') ? '是' : '否'));
            error_log("CategoryService images目录是否存在: " . (is_dir($root . '/public/uploads/images') ? '是' : '否'));
            
            if (!file_exists($fullPath)) {
                // 如果文件不存在，清空banner字段
                $banner = null;
                
                // 记录日志以便调试
                $adminId = $_SESSION['admin_id'] ?? 0;
                $adminName = $_SESSION['admin_username'] ?? 'unknown';
                \App\Services\LogService::record(
                    $adminId, 
                    $adminName, 
                    'error', 
                    'CategoryService: 栏目保存时发现无效的Banner图片路径: ' . $banner . '，文件不存在于: ' . $fullPath
                );
            }
        }
        
        $saveData = [
            'parent_id' => (int)($data['parent_id'] ?? 0),
            'name' => trim($data['name']),
            'slug' => isset($data['slug']) ? trim($data['slug']) : null,
            'type' => isset($data['type']) ? trim($data['type']) : 'article',
            'template' => isset($data['template']) ? trim($data['template']) : 'list.latte',
            'detail_template' => isset($data['detail_template']) ? trim($data['detail_template']) : 'article.latte',
            'sort' => (int)($data['sort'] ?? 0),
            'is_hidden' => isset($data['is_hidden']) ? (int)$data['is_hidden'] : 0,
            'is_shortcut' => isset($data['is_shortcut']) ? (int)$data['is_shortcut'] : 0,
            'is_channel' => isset($data['is_channel']) ? (int)$data['is_channel'] : 0,
            'allow_crop' => isset($data['allow_crop']) ? (int)$data['allow_crop'] : 0,
            'banner' => $banner,
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
