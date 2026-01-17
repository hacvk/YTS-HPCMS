<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Category;
use Medoo\Medoo;

/**
 * 站点服务类
 * 处理前台页面公共组件相关的业务逻辑
 */
class SiteService
{
    /**
     * 构造函数（使用 PHP 8.0 构造函数属性提升）
     *
     * @param Category $categoryModel 栏目模型实例
     * @param CategoryService $categoryService 栏目服务实例
     * @param Medoo $db 数据库实例
     */
    public function __construct(
        private readonly Category $categoryModel,
        private readonly CategoryService $categoryService,
        private readonly Medoo $db
    ) {
    }

    /**
     * 构建导航数据
     * 将栏目树结构转换为导航菜单格式
     *
     * @return array<int, array<string, mixed>> 导航菜单数组
     */
    public function buildNav(): array
    {
        $cats = $this->categoryModel->getAll(['is_hidden' => 0], ['sort' => 'ASC']);
        $tree = $this->categoryService->getTree(0, $cats);
        
        $convert = function(array $nodes) use (&$convert): array {
            $arr = [];
            foreach ($nodes as $n) {
                if (!is_array($n) || !isset($n['name']) || !isset($n['slug'])) {
                    continue;
                }
                
                $item = [
                    'name' => (string)$n['name'],
                    'url' => '/c/' . (string)$n['slug'],
                    'children' => $convert($n['children'] ?? [])
                ];
                $arr[] = $item;
            }
            return $arr;
        };
        
        return $convert($tree);
    }

    /**
     * 构建当前栏目的子栏目数据
     * 获取指定栏目及其直接子栏目，用于侧边栏显示
     *
     * @param int $categoryId 栏目ID
     * @return array<int, array<string, mixed>> 栏目列表，包含 id、name、url
     */
    public function buildChannelItems(int $categoryId): array
    {
        try {
            // 获取当前栏目及其子栏目
            $categories = $this->db->select('sys_category', [
                'id',
                'name',
                'slug',
                'parent_id'
            ], [
                'OR' => [
                    'id' => $categoryId,
                    'parent_id' => $categoryId
                ],
                'is_hidden' => 0,
                'ORDER' => ['sort' => 'ASC', 'id' => 'ASC']
            ]);

            if (!is_array($categories)) {
                return [];
            }

            $items = [];
            foreach ($categories as $cat) {
                if (!is_array($cat) || !isset($cat['id']) || !isset($cat['name']) || !isset($cat['slug'])) {
                    continue;
                }

                $items[] = [
                    'id' => (int)$cat['id'],
                    'name' => (string)$cat['name'],
                    'url' => '/c/' . (string)$cat['slug'],
                ];
            }

            return $items;
        } catch (\Throwable $e) {
            error_log('构建栏目子项失败: ' . $e->getMessage());
            return [];
        }
    }
}
