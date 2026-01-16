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
        
        $data = [
            'navItems' => $navItems,
            'bannerItems'=> $this->bannerModel->getEnabled(),
            'hospitalNewsItems' => $ywggList,
            'noticeItems' => $zbggList,
            'hospitalNewsMoreUrl' => '/c/yuanwu', // 院务公开栏目别名
            'noticeMoreUrl' => '/c/zhaobiao', // 招标信息栏目别名
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
        
        $this->render('front/article', [
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
        
        // 计算分页信息
        $total = $articles['total'] ?? 0;
        $totalPages = (int)max(1, ceil($total / $limit));
        
        // 构建分页HTML
        $pagination = $this->generatePagination($page, $totalPages, '/c/' . $category['slug']);
        
        // 构建导航数据
        $navItems = $this->buildNav();
        
        // 构建当前栏目的子栏目数据
        $channelItems = $this->buildChannelItems($category['id']);
        
        $this->render('front/list', [
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