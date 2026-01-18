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
        try {
            // 获取所有专家数据（从当前栏目）
            $allDoctors = $this->db->select('sys_content', ['attributes'], [
                'category_id' => $categoryId,
                'status' => 1
            ]);

            // 收集所有科室ID和职称
            $allKeshiIds = [];
            $allZhichengSet = [];

            foreach ($allDoctors ?: [] as $doctor) {
                if (isset($doctor['attributes']) && !empty($doctor['attributes'])) {
                    $attrs = is_string($doctor['attributes']) 
                        ? json_decode($doctor['attributes'], true) 
                        : $doctor['attributes'];

                    if (!is_array($attrs)) {
                        continue;
                    }

                    // 提取职称
                    if (isset($attrs['zhicheng']) && !empty($attrs['zhicheng'])) {
                        $zhicheng = trim((string)$attrs['zhicheng']);
                        if ($zhicheng !== '') {
                            $allZhichengSet[$zhicheng] = true;
                        }
                    }

                    // 提取科室ID（keshi 字段）
                    if (isset($attrs['keshi'])) {
                        $rawKeshi = $attrs['keshi'];
                        $keshiIds = $this->parseKeshiIds($rawKeshi);

                        foreach ($keshiIds as $kid) {
                            if ($kid > 0) {
                                $allKeshiIds[$kid] = true;
                            }
                        }
                    }
                }
            }

            // 获取临床科室和医技科室分类
            $clinicalDepts = [];
            $medicalTechDepts = [];

            if (!empty($allKeshiIds)) {
                $clinicalCategoryIds = $this->getCategoryIdsWithChildren([10, 12]);
                $medicalTechCategoryIds = $this->getCategoryIdsWithChildren([13, 14]);

                $keshiIdsArray = array_keys($allKeshiIds);
                $deptContents = $this->db->select('sys_content', ['id', 'title', 'category_id'], [
                    'id' => $keshiIdsArray,
                    'status' => 1,
                    'ORDER' => ['title' => 'ASC']
                ]);

                foreach ($deptContents ?: [] as $dept) {
                    $deptItem = [
                        'id' => (int)($dept['id'] ?? 0),
                        'title' => (string)($dept['title'] ?? ''),
                        'category_id' => (int)($dept['category_id'] ?? 0)
                    ];

                    // 根据 category_id 分类
                    $catId = (int)($dept['category_id'] ?? 0);
                    if (in_array($catId, $clinicalCategoryIds, true)) {
                        $clinicalDepts[] = $deptItem;
                    } elseif (in_array($catId, $medicalTechCategoryIds, true)) {
                        $medicalTechDepts[] = $deptItem;
                    }
                    // 如果不在预定义分类中，则不显示（避免显示无关科室）
                }
            }

            // 获取职称列表
            $zhichengOptions = array_keys($allZhichengSet);
            if (empty($zhichengOptions)) {
                // 如果数据库中没有，使用预定义列表
                $zhichengOptions = [
                    '主任医师', '副主任医师', '主治医师', '住院医师',
                    '主任中医师', '副主任中医师',
                    '主任技师', '副主任技师', '主管技师', '技师'
                ];
            } else {
                sort($zhichengOptions);
            }

            return [
                'departments' => [
                    'clinical' => $clinicalDepts,
                    'medical_tech' => $medicalTechDepts
                ],
                'titles' => $zhichengOptions
            ];
        } catch (\Throwable $e) {
            error_log('获取筛选数据失败 ' . $e->getMessage());
            error_log('堆栈跟踪: ' . $e->getTraceAsString());
            return [
                'departments' => ['clinical' => [], 'medical_tech' => []],
                'titles' => []
            ];
        }
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
