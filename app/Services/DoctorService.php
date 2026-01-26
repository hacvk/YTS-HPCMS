<?php
declare(strict_types=1);

namespace App\Services;

use Medoo\Medoo;

/**
 * 专家服务类
 * 处理专家列表相关的业务逻辑
 */
class DoctorService
{
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
     * 处理专家列表数据清洗
     * 将内容列表映射为模板需要的字段结构，并处理 keshi 字段
     *
     * @param array<string, mixed> $articles 文章列表数据（包含 'list' 和 'total' 键）
     * @return array<string, mixed> 处理后的文章列表数据
     */
    public function processDoctorList(array $articles): array
    {
        $list = $articles['list'] ?? [];
        
        if (!is_array($list)) {
            return $articles;
        }

        foreach ($list as &$row) {
            if (!is_array($row)) {
                continue;
            }

            // 映射基础字段
            $row['link'] = $row['link'] ?? ('/article/' . (int)($row['id'] ?? 0));
            $row['pic'] = $row['pic'] ?? ($row['cover_img'] ?? '');
            $row['description'] = $row['description'] ?? ($row['summary'] ?? '');

            // 扩展字段映射（attributes 已在 Content::getList 中反序列化）
            $row['zhicheng'] = $row['zhicheng'] ?? ($row['title2'] ?? $row['position'] ?? '');

            // 处理 keshi 字段
            $rawKeshi = $row['keshi'] ?? null;
            $keshiIds = $this->parseKeshiIds($rawKeshi);
            $row['keshi_ids'] = $keshiIds;

            // 批量查询科室标题
            $deptTitleById = $this->fetchDepartmentTitles($keshiIds);

            // 输出格式：[[9, 科室文章ID, 科室标题], ...]，供模板直接渲染
            $keshiOut = [];
            foreach ($keshiIds as $kid) {
                $keshiOut[] = [9, $kid, $deptTitleById[$kid] ?? ''];
            }
            $row['keshi'] = $keshiOut;
        }
        unset($row);

        $articles['list'] = $list;
        return $articles;
    }

    /**
     * 获取筛选数据（科室和职称）
     *
     * @param int $categoryId 栏目ID
     * @return array<string, mixed> 返回筛选数据，包含 departments 和 titles
     */
    public function getFilterData(int $categoryId): array
    {
        return $this->getFilterDataByCategoryIds([$categoryId]);
    }

    /**
     * 根据专家栏目ID集合获取筛选数据
     *
     * @param array<int> $categoryIds
     * @return array<string, mixed>
     */
    public function getFilterDataByCategoryIds(array $categoryIds): array
    {
        try {
            $departmentCategories = $this->getDepartmentCategories(9);
            $deptCategoryIds = array_values(array_filter(
                array_map(fn($row) => (int)($row['id'] ?? 0), $departmentCategories),
                fn($v) => $v > 0
            ));
            $deptArticles = $this->getDepartmentArticlesByCategoryIds($deptCategoryIds);
            $deptGroups = [];
            foreach ($departmentCategories as $deptCategory) {
                $deptId = (int)($deptCategory['id'] ?? 0);
                $deptGroups[] = [
                    'id' => $deptId,
                    'name' => (string)($deptCategory['name'] ?? ''),
                    'items' => $deptArticles['groups'][$deptId] ?? []
                ];
            }
            $zhichengOptions = $this->collectTitlesByDoctorCategories($categoryIds);

            return [
                'departments' => $deptGroups,
                'dept_index' => $deptArticles['index'] ?? [],
                'titles' => $zhichengOptions
            ];
        } catch (\Throwable $e) {
            error_log('获取筛选数据失败 ' . $e->getMessage());
            error_log('堆栈跟踪: ' . $e->getTraceAsString());
            return [
                'departments' => [],
                'dept_index' => [],
                'titles' => []
            ];
        }
    }

    /**
     * 获取科室栏目（父栏目ID固定为 9）
     *
     * @param int $parentId
     * @return array<int, array<string, mixed>>
     */
    public function getDepartmentCategories(int $parentId = 9): array
    {
        try {
            return $this->db->select('sys_category', ['id', 'name', 'slug', 'parent_id'], [
                'parent_id' => $parentId,
                'is_hidden' => 0,
                'ORDER' => ['sort' => 'ASC', 'id' => 'ASC']
            ]) ?: [];
        } catch (\Throwable $e) {
            error_log('获取科室栏目失败: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取科室下的专家列表，并支持筛选
     *
     * @param array<int> $departmentIds
     * @param array<string, mixed> $filters
     * @param int $page
     * @param int $limit
     * @return array{list: array<int, array<string, mixed>>, total: int}
     */
    public function getDoctorListByCategories(array $categoryIds, array $filters, int $page, int $limit): array
    {
        if (empty($categoryIds)) {
            return ['list' => [], 'total' => 0];
        }

        $rows = $this->db->select(
            'sys_content(content)',
            [
                '[>]sys_category(category)' => ['category_id' => 'id']
            ],
            [
                'content.id',
                'content.category_id',
                'content.title',
                'content.cover_img',
                'content.summary',
                'content.status',
                'content.views',
                'content.published_at',
                'content.is_top',
                'content.is_image_news',
                'content.attributes',
                'category.name(category_name)',
                'category.slug(category_slug)'
            ],
            [
                'content.category_id' => $categoryIds,
                'content.status' => 1,
                'ORDER' => ['content.id' => 'DESC']
            ]
        ) ?: [];

        $list = $this->decodeAttributesBatch($rows);
        $processed = $this->processDoctorList(['list' => $list, 'total' => count($list)]);
        $list = $processed['list'] ?? [];

        $selectedDeptIds = array_values(array_unique(array_filter(
            array_map(fn($v) => (int)$v, $filters['dept_ids'] ?? []),
            fn($v) => $v > 0
        )));
        $selectedTitles = array_values(array_unique(array_filter(
            array_map(fn($v) => trim((string)$v), $filters['titles'] ?? []),
            fn($v) => $v !== ''
        )));
        $keyword = trim((string)($filters['keyword'] ?? ''));

        $filtered = [];
        foreach ($list as $row) {
            if (!is_array($row)) {
                continue;
            }

            if (!empty($selectedDeptIds)) {
                $rowDeptIds = $row['keshi_ids'] ?? [];
                if (!is_array($rowDeptIds)) {
                    $rowDeptIds = [];
                }
                $matchedDept = false;
                foreach ($rowDeptIds as $did) {
                    if (in_array((int)$did, $selectedDeptIds, true)) {
                        $matchedDept = true;
                        break;
                    }
                }
                if (!$matchedDept) {
                    continue;
                }
            }

            if (!empty($selectedTitles)) {
                $titleText = trim((string)($row['zhicheng'] ?? ''));
                $matched = false;
                foreach ($selectedTitles as $t) {
                    if ($t !== '' && $titleText === $t) {
                        $matched = true;
                        break;
                    }
                }
                if (!$matched) {
                    continue;
                }
            }

            if ($keyword !== '') {
                $haystack = strtolower(
                    (string)($row['title'] ?? '') . ' ' .
                    (string)($row['summary'] ?? '') . ' ' .
                    (string)($row['description'] ?? '')
                );
                if (strpos($haystack, strtolower($keyword)) === false) {
                    continue;
                }
            }

            $filtered[] = $row;
        }

        $total = count($filtered);
        $offset = max(0, ($page - 1) * $limit);
        $paged = array_slice($filtered, $offset, $limit);

        return [
            'list' => $paged,
            'total' => $total
        ];
    }

    /**
     * 读取科室下的职称集合
     *
     * @param array<int> $departmentIds
     * @return array<int, string>
     */
    private function collectTitlesByDoctorCategories(array $categoryIds): array
    {
        if (empty($categoryIds)) {
            return [];
        }

        $rows = $this->db->select('sys_content', ['attributes'], [
            'category_id' => $categoryIds,
            'status' => 1
        ]) ?: [];

        $titles = [];
        foreach ($rows as $row) {
            $merged = $this->decodeAttributes($row);
            $zhicheng = trim((string)($merged['zhicheng'] ?? $merged['title2'] ?? $merged['position'] ?? ''));
            if ($zhicheng !== '') {
                $titles[$zhicheng] = true;
            }
        }

        $titleList = array_keys($titles);
        if (empty($titleList)) {
            return [
                '主任医师', '副主任医师', '主治医师', '住院医师',
                '主任中医师', '副主任中医师',
                '主任技师', '副主任技师', '主管技师', '技师'
            ];
        }
        sort($titleList);
        return $titleList;
    }

    /**
     * 反序列化 attributes 字段
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function decodeAttributes(array $row): array
    {
        if (isset($row['attributes']) && is_string($row['attributes']) && $row['attributes'] !== '') {
            $decoded = json_decode($row['attributes'], true);
            if (is_array($decoded)) {
                unset($row['attributes']);
                $row = array_merge($row, $decoded);
            }
        }
        return $row;
    }

    /**
     * 批量反序列化 attributes 字段
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function decodeAttributesBatch(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $out[] = $this->decodeAttributes($row);
            }
        }
        return $out;
    }

    /**
     * 解析 keshi 字段，提取科室ID列表
     * 兼容格式："39" / "39,45" / "[39,45]" / "[[9,39],[9,45]]"
     *
     * @param mixed $rawKeshi 原始 keshi 字段值
     * @return array<int> 科室ID列表
     */
    private function parseKeshiIds(mixed $rawKeshi): array
    {
        $keshiIds = [];

        if (is_string($rawKeshi)) {
            $raw = trim($rawKeshi);
            if ($raw === '') {
                return [];
            }

            if (str_contains($raw, ',')) {
                // 处理逗号分隔的字符串："39,45"
                foreach (explode(',', $raw) as $part) {
                    $part = trim($part);
                    if ($part !== '' && ctype_digit($part)) {
                        $keshiIds[] = (int)$part;
                    }
                }
            } elseif ($raw[0] === '[') {
                // 处理JSON数组格式："[39,45]" 或 "[[9,39],[9,45]]"
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $it) {
                        if (is_int($it) || ctype_digit((string)$it)) {
                            $keshiIds[] = (int)$it;
                        } elseif (is_array($it) && isset($it[1]) && (is_int($it[1]) || ctype_digit((string)$it[1]))) {
                            $keshiIds[] = (int)$it[1];
                        }
                    }
                }
            } elseif (ctype_digit($raw)) {
                // 处理单个数字字符串："39"
                $keshiIds[] = (int)$raw;
            }
        } elseif (is_int($rawKeshi)) {
            // 处理整数类型
            $keshiIds[] = $rawKeshi;
        } elseif (is_array($rawKeshi)) {
            // 处理数组类型：[39, 45] 或 [[9, 39], [9, 45]]
            foreach ($rawKeshi as $it) {
                if (is_int($it) || ctype_digit((string)$it)) {
                    $keshiIds[] = (int)$it;
                } elseif (is_array($it) && isset($it[1]) && (is_int($it[1]) || ctype_digit((string)$it[1]))) {
                    $keshiIds[] = (int)$it[1];
                }
            }
        }

        // 去重、过滤无效值并重新索引
        return array_values(array_unique(array_filter($keshiIds, fn($v) => $v > 0)));
    }

    /**
     * 批量查询科室标题
     *
     * @param array<int> $keshiIds 科室ID列表
     * @return array<int, string> 科室ID => 科室标题 的映射
     */
    private function fetchDepartmentTitles(array $keshiIds): array
    {
        if (empty($keshiIds)) {
            return [];
        }

        try {
            $deptRows = $this->db->select('sys_content', ['id', 'title'], [
                'id' => $keshiIds
            ]) ?: [];

            $deptTitleById = [];
            foreach ($deptRows as $dr) {
                $deptTitleById[(int)($dr['id'] ?? 0)] = (string)($dr['title'] ?? '');
            }

            return $deptTitleById;
        } catch (\Throwable $e) {
            error_log('科室标题查询失败: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取科室栏目下的科室文章列表
     *
     * @param array<int> $categoryIds 科室栏目ID列表
     * @return array{groups: array<int, array<int, array{id:int, title:string}>>, index: array<int, int>}
     */
    private function getDepartmentArticlesByCategoryIds(array $categoryIds): array
    {
        if (empty($categoryIds)) {
            return ['groups' => [], 'index' => []];
        }

        try {
            $rows = $this->db->select('sys_content', ['id', 'title', 'category_id'], [
                'category_id' => $categoryIds,
                'status' => 1,
                'ORDER' => ['id' => 'ASC']
            ]) ?: [];

            $groups = [];
            $index = [];
            foreach ($rows as $row) {
                $contentId = (int)($row['id'] ?? 0);
                $categoryId = (int)($row['category_id'] ?? 0);
                if ($contentId <= 0 || $categoryId <= 0) {
                    continue;
                }
                $groups[$categoryId][] = [
                    'id' => $contentId,
                    'title' => (string)($row['title'] ?? '')
                ];
                $index[$contentId] = $categoryId;
            }

            return ['groups' => $groups, 'index' => $index];
        } catch (\Throwable $e) {
            error_log('获取科室文章失败: ' . $e->getMessage());
            return ['groups' => [], 'index' => []];
        }
    }

    /**
     * 获取栏目ID及其所有子栏目ID
     *
     * @param array<int> $parentIds 父栏目ID列表
     * @return array<int> 包含父栏目和所有子栏目的ID列表
     */
    private function getCategoryIdsWithChildren(array $parentIds): array
    {
        $allIds = $parentIds;

        try {
            foreach ($parentIds as $catId) {
                $children = $this->db->select('sys_category', ['id'], [
                    'parent_id' => $catId,
                    'is_hidden' => 0
                ]);

                if (!empty($children)) {
                    $allIds = array_merge($allIds, array_column($children, 'id'));
                }
            }
        } catch (\Throwable $e) {
            error_log('获取子栏目失败 ' . $e->getMessage());
        }

        return $allIds;
    }
}
