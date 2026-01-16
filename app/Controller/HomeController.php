<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\Banner;
use App\Model\Category;
use App\Model\Content;
use App\Service\CategoryService;

class HomeController extends BaseController
{
    public function __construct(
        private readonly Category $categoryModel,
        private readonly CategoryService $categoryService,
        private readonly Content $contentModel,
        private readonly Banner $bannerModel
    ) {
        parent::__construct();
    }

    public function index(): void
    {
        // 构建导航数据
        $navItems = $this->buildNav();

        // 获取院务公开文章（栏目ID 6）
        $ywggItems = $this->contentModel->getList(1, 9, ['cid' => 6]);
        $ywggList = $ywggItems['list'] ?? [];
        
        // 获取招标信息文章（栏目ID 2）
        $zbggItems = $this->contentModel->getList(1, 9, ['cid' => 2]);
        $zbggList = $zbggItems['list'] ?? [];
        
        // 获取专家介绍（栏目ID=3 及其所有子栏目）
        $doctorCategoryId = 3;
        $doctorCategoryIds = array_values(array_unique(array_merge(
            [$doctorCategoryId],
            $this->categoryService->getAllChildrenIds($doctorCategoryId)
        )));

        $doctorItems = [];
        try {
            $db = \App\Core\Database::get();
            $rows = $db->select('sys_content', [
                'id',
                'category_id',
                'title',
                'cover_img',
                'summary',
                'attributes',
                'published_at',
                'status'
            ], [
                'category_id' => $doctorCategoryIds,
                'status' => 1,
                'ORDER' => ['id' => 'DESC'],
                'LIMIT' => 12,
            ]) ?: [];

            foreach ($rows as $r) {
                $attrs = [];
                if (!empty($r['attributes'])) {
                    $decoded = json_decode((string)$r['attributes'], true);
                    if (is_array($decoded)) {
                        $attrs = $decoded;
                    }
                }

                $doctorItems[] = [
                    'url' => '/article/' . (int)$r['id'],
                    'pic' => (string)($r['cover_img'] ?? ''),
                    'name' => (string)($attrs['name'] ?? $r['title'] ?? ''),
                    'dept' => (string)($attrs['dept'] ?? $attrs['department'] ?? ''),
                    'title' => (string)($attrs['title'] ?? $attrs['position'] ?? ''),
                    'desc' => (string)($attrs['desc'] ?? $attrs['summary'] ?? $r['summary'] ?? ''),
                ];
            }
        } catch (\Throwable $e) {
            $doctorItems = [];
        }

        $data = [
            'navItems' => $navItems,
            'bannerItems'=> $this->bannerModel->getEnabled(),
            'hospitalNewsItems' => $ywggList,
            'noticeItems' => $zbggList,
            'hospitalNewsMoreUrl' => '/c/yuanwu', // 院务公开栏目别名
            'noticeMoreUrl' => '/c/zhaobiao', // 招标信息栏目别名
            'doctorItems' => $doctorItems,
            'channelItems' => [], // 首页不需要特定的频道项目
        ];

        $this->render('front/index', $data);
    }

    private function buildNav(): array
    {
        $cats = $this->categoryModel->getAll(['is_hidden' => 0], ['sort' => 'ASC']);
        $tree = $this->categoryService->getTree(0, $cats);
        $convert = function(array $nodes) use (&$convert): array {
            $arr = [];
            foreach ($nodes as $n) {
                $item = [
                    'name'=>$n['name'],
                    'url'=>'/c/'.$n['slug'],
                    'children'=>$convert($n['children'] ?? [])
                ];
                $arr[]=$item;
            }
            return $arr;
        };
        return $convert($tree);
    }
    
    public function article(): void
    {
        $id = (int)$this->get('id', 0);
        
        if ($id <= 0) {
            $this->redirect('/');
            return;
        }
        
        $article = $this->contentModel->getById($id);
        
        if (!$article) {
            $this->redirect('/');
            return;
        }
        
        // 获取上一篇文章
        $prevArticle = $this->getPrevArticle($id, $article['category_id']);
        
        // 获取下一篇文章
        $nextArticle = $this->getNextArticle($id, $article['category_id']);
        
        // 构建导航数据
        $navItems = $this->buildNav();
        
        // 构建当前文章所属栏目的子栏目数据
        $channelItems = $this->buildChannelItems($article['category_id']);
        
        // 读取栏目详情模板（detail_template）并渲染
        $detailTemplate = 'front/article';
        try {
            $db = \App\Core\Database::get();
            $cat = $db->get('sys_category', ['detail_template'], ['id' => (int)$article['category_id']]);
            $tpl = trim((string)($cat['detail_template'] ?? ''));
            if ($tpl !== '') {
                if (!str_ends_with($tpl, '.latte')) {
                    $tpl .= '.latte';
                }
                $candidate = 'front/' . ltrim($tpl, '/\\');
                $candidatePath = dirname(__DIR__, 1) . '/View/' . $candidate;
                if (is_file($candidatePath)) {
                    $detailTemplate = $candidate;
                }
            }
        } catch (\Throwable $e) {
            // ignore and fallback
        }

        $this->render($detailTemplate, [
            'title' => $article['title'],
            'article' => $article,
            'prevArticle' => $prevArticle,
            'nextArticle' => $nextArticle,
            'channelItems' => $channelItems,
            'navItems' => $navItems,
        ]);
    }
    
    private function buildChannelItems(int $categoryId): array
    {
        try {
            $db = \App\Core\Database::get();
            
            // 获取当前栏目及其子栏目
            $categories = $db->select('sys_category', [
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
            
            $items = [];
            foreach ($categories as $cat) {
                $items[] = [
                    'name' => $cat['name'],
                    'url' => '/c/' . $cat['slug'],
                    'id' => $cat['id'],
                ];
            }
            
            return $items;
        } catch (\Exception $e) {
            return [];
        }
    }
    
    private function getPrevArticle(int $currentId, int $categoryId): ?array
    {
        try {
            $db = \App\Core\Database::get();
            $article = $db->get(
                'sys_content',
                '*',
                [
                    'AND' => [
                        'id[<]' => $currentId,
                        'category_id' => $categoryId,
                        'status' => 1
                    ],
                    'ORDER' => ['id' => 'DESC']
                ]
            );
            
            if ($article && isset($article['attributes']) && !empty($article['attributes'])) {
                $attributes = json_decode($article['attributes'], true);
                if (is_array($attributes)) {
                    unset($article['attributes']);
                    $article = array_merge($article, $attributes);
                }
            }
            
            return $article ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    private function getNextArticle(int $currentId, int $categoryId): ?array
    {
        try {
            $db = \App\Core\Database::get();
            $article = $db->get(
                'sys_content',
                '*',
                [
                    'AND' => [
                        'id[>]' => $currentId,
                        'category_id' => $categoryId,
                        'status' => 1
                    ],
                    'ORDER' => ['id' => 'ASC']
                ]
            );
            
            if ($article && isset($article['attributes']) && !empty($article['attributes'])) {
                $attributes = json_decode($article['attributes'], true);
                if (is_array($attributes)) {
                    unset($article['attributes']);
                    $article = array_merge($article, $attributes);
                }
            }
            
            return $article ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    private function generatePagination(int $currentPage, int $totalPages, string $baseUrl): string
    {
        if ($totalPages <= 1) {
            return '';
        }
        
        $pagination = '<div class="pagination">';
        
        // 上一页
        if ($currentPage > 1) {
            $prevPage = $currentPage - 1;
            $pagination .= '<a href="' . $baseUrl . '/page/' . $prevPage . '" class="prev">上一页</a>';
        }
        
        // 页码链接
        $startPage = max(1, $currentPage - 4);
        $endPage = min($totalPages, $currentPage + 4);
        
        if ($startPage > 1) {
            $pagination .= '<a href="' . $baseUrl . '/page/1">1</a>';
            if ($startPage > 2) {
                $pagination .= '<span class="ellipsis">...</span>';
            }
        }
        
        for ($i = $startPage; $i <= $endPage; $i++) {
            if ($i == $currentPage) {
                $pagination .= '<span class="current">' . $i . '</span>';
            } else {
                $pagination .= '<a href="' . $baseUrl . '/page/' . $i . '">' . $i . '</a>';
            }
        }
        
        if ($endPage < $totalPages) {
            if ($endPage < $totalPages - 1) {
                $pagination .= '<span class="ellipsis">...</span>';
            }
            $pagination .= '<a href="' . $baseUrl . '/page/' . $totalPages . '">' . $totalPages . '</a>';
        }
        
        // 下一页
        if ($currentPage < $totalPages) {
            $nextPage = $currentPage + 1;
            $pagination .= '<a href="' . $baseUrl . '/page/' . $nextPage . '" class="next">下一页</a>';
        }
        
        $pagination .= '</div>';
        
        return $pagination;
    }
    
    public function category(): void
    {
        $slug = $this->get('slug', '');
        
        if (empty($slug)) {
            $this->redirect('/');
            return;
        }
        
        // 获取栏目信息
        $db = \App\Core\Database::get();
        $category = $db->get('sys_category', '*', [
            'slug' => $slug,
            'is_hidden' => 0
        ]);
        
        if (!$category) {
            $this->redirect('/');
            return;
        }
        
        // 获取栏目下的文章列表
        $page = (int)$this->get('page', 1);
        $limit = 10; // 每页显示10篇文章
        
        $articles = $this->contentModel->getList($page, $limit, ['cid' => $category['id']]);

        // 如果是专家列表模板（doctorlist.latte），将内容列表映射为模板需要的字段结构
        $tplName = trim((string)($category['template'] ?? ''));
        if ($tplName !== '') {
            $tplBase = basename($tplName);
            if ($tplBase === 'doctorlist.latte' || $tplBase === 'doctorlist') {
                $list = $articles['list'] ?? [];
                if (is_array($list)) {
                    foreach ($list as &$row) {
                        if (is_array($row)) {
                            $row['link'] = $row['link'] ?? ('/article/' . (int)($row['id'] ?? 0));
                            $row['pic'] = $row['pic'] ?? ($row['cover_img'] ?? '');
                            $row['description'] = $row['description'] ?? ($row['summary'] ?? '');

                            // 扩展字段映射（attributes 已在 Content::getList 中反序列化）
                            $row['zhicheng'] = $row['zhicheng'] ?? ($row['title2'] ?? $row['position'] ?? '');

                            // keshi 字段处理：来自 doctor.attributes.keshi（lookup_category=9），存的是科室文章ID
                            // 兼容格式："39" / "39,45" / "[39,45]" / "[[9,39],[9,45]]"
                            $rawKeshi = $row['keshi'] ?? null;
                            $keshiIds = [];

                            if (is_string($rawKeshi)) {
                                $raw = trim($rawKeshi);
                                if ($raw !== '') {
                                    if (str_contains($raw, ',')) {
                                        foreach (explode(',', $raw) as $part) {
                                            $part = trim($part);
                                            if ($part !== '' && ctype_digit($part)) {
                                                $keshiIds[] = (int)$part;
                                            }
                                        }
                                    } elseif ($raw[0] === '[') {
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
                                        $keshiIds[] = (int)$raw;
                                    }
                                }
                            } elseif (is_int($rawKeshi)) {
                                $keshiIds[] = $rawKeshi;
                            } elseif (is_array($rawKeshi)) {
                                foreach ($rawKeshi as $it) {
                                    if (is_int($it) || ctype_digit((string)$it)) {
                                        $keshiIds[] = (int)$it;
                                    } elseif (is_array($it) && isset($it[1]) && (is_int($it[1]) || ctype_digit((string)$it[1]))) {
                                        $keshiIds[] = (int)$it[1];
                                    }
                                }
                            }

                            $keshiIds = array_values(array_unique(array_filter($keshiIds, fn($v) => $v > 0)));

                            // 批量查科室标题
                            $deptTitleById = [];
                            if (count($keshiIds) > 0) {
                                try {
                                    $db2 = \App\Core\Database::get();
                                    $deptRows = $db2->select('sys_content', 
                                        ['id', 'title'], 
                                        ['id' => $keshiIds]
                                    ) ?: [];

                                    foreach ($deptRows as $dr) {
                                        $deptTitleById[(int)($dr['id'] ?? 0)] = (string)($dr['title'] ?? '');
                                    }
                                } catch (\Throwable $e) {
                                    error_log('Keshi title lookup failed: ' . $e->getMessage());
                                    $deptTitleById = [];
                                }
                            }

                            // 输出为 [[9, 科室文章ID, 科室标题], ...]，供模板直接渲染
                            $keshiOut = [];
                            foreach ($keshiIds as $kid) {
                                $keshiOut[] = [9, $kid, $deptTitleById[$kid] ?? ''];
                            }
                            $row['keshi'] = $keshiOut;
                        }
                    }
                    unset($row);
                    $articles['list'] = $list;
                }
            }
        }
        
        // 计算分页信息
        $total = $articles['total'] ?? 0;
        $totalPages = (int)max(1, ceil($total / $limit));
        
        // 构建分页HTML
        $pagination = $this->generatePagination($page, $totalPages, '/c/' . $category['slug']);
        
        // 构建导航数据
        $navItems = $this->buildNav();
        
        // 构建当前栏目的子栏目数据
        $channelItems = $this->buildChannelItems($category['id']);
        
        // 读取栏目模板（template）并渲染
        $listTemplate = 'front/list';
        $tpl = trim((string)($category['template'] ?? ''));
        if ($tpl !== '') {
            if (!str_ends_with($tpl, '.latte')) {
                $tpl .= '.latte';
            }
            $candidate = 'front/' . ltrim($tpl, '/\\');
            $candidatePath = dirname(__DIR__, 1) . '/View/' . $candidate;
            if (is_file($candidatePath)) {
                $listTemplate = $candidate;
            }
        }

        $this->render($listTemplate, [
            'title' => $category['name'],
            'category' => $category,
            'articles' => $articles,
            'pagination' => $pagination,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'navItems' => $navItems,
            'channelItems' => $channelItems,
        ]);
    }
}