<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\Content;
use App\Services\CategoryService;
use App\Services\SiteService;
use App\Services\SeoService;
use Medoo\Medoo;

/**
 * 首页控制器
 * 处理首页相关逻辑
 */
class HomeController extends BaseController
{
    /**
     * 构造函数（使用 PHP 8.0 构造函数属性提升）
     *
     * @param Content $contentModel 内容模型实例
     * @param Banner $bannerModel 轮播图模型实例
     * @param CategoryService $categoryService 栏目服务实例
     * @param SiteService $siteService 站点服务实例
     * @param Medoo $db 数据库实例
     */
    public function __construct(
        private readonly Content $contentModel,
        private readonly Banner $bannerModel,
        private readonly CategoryService $categoryService,
        private readonly SiteService $siteService,
        private readonly SeoService $seoService,
        private readonly Medoo $db
    ) {
        parent::__construct();
    }

    /**
     * 首页
     *
     * @return void
     */
    public function index(): void
    {
        // 使用 SiteService 构建导航数据
        $navItems = $this->siteService->buildNav();

        // 获取院务公开文章（栏目ID 6）
        $ywggItems = $this->contentModel->getList(1, 9, ['cid' => 6]);
        $ywggList = $ywggItems['list'] ?? [];

        // 获取招标信息文章（栏目ID 2）
        $zbggItems = $this->contentModel->getList(1, 9, ['cid' => 2]);
        $zbggList = $zbggItems['list'] ?? [];

        // 获取专家介绍（栏目ID=3 及其所有子栏目）
        $doctorItems = $this->getDoctorItems();
        
        // 获取图片新闻（is_image_news=1）用于首页轮播展示
        $imageNewsItems = $this->getImageNewsItems();
        
        // 获取科室数据用于首页展示（parent_id=9的栏目及其子栏目文章）
        $departmentData = $this->getDepartmentDataWithArticles();

        // 从 sys_setting 表获取站点配置
        $siteSettings = [
            'site_title' => $this->db->get('sys_setting', 'value', ['key' => 'site_title']),
            'site_keywords' => $this->db->get('sys_setting', 'value', ['key' => 'site_keywords']),
            'site_description' => $this->db->get('sys_setting', 'value', ['key' => 'site_description'])
        ];
        
        // 生成SEO标签
        $ogTags = $this->seoService->generateOpenGraphTags([
            'title' => $siteSettings['site_title'] ?? '首页',
            'description' => $siteSettings['site_description'] ?? '',
            'url' => '/'
        ]);
        $organizationSchema = $this->seoService->generateOrganizationSchema();
        $jsonLdSchema = $organizationSchema;
        
        // 计算基础URL
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $appBaseUrl = $protocol . '://' . $host;
        
        $data = [
            'title' => $siteSettings['site_title'] ?? '默认标题',
            'keywords' => $siteSettings['site_keywords'] ?? '',
            'description' => $siteSettings['site_description'] ?? '',
            'appBaseUrl' => $appBaseUrl,
            'ogTags' => $ogTags,
            'jsonLdSchema' => $jsonLdSchema,
            'navItems' => $navItems,
            'bannerItems' => $this->bannerModel->getBanners(), // 轮播图
            'indexFastItems' => $this->bannerModel->getIndexFastItems(10), // 首页快捷方式
            'picLinks' => $this->bannerModel->getIndexFastItems(10), // 首页快捷方式（别名）
            'indexZtItems' => $this->bannerModel->getIndexZtItems(10), // 首页专题
            'newsSliderItems' => $imageNewsItems, // 图片新闻轮播
            'hospitalNewsItems' => $ywggList,
            'noticeItems' => $zbggList,
            'hospitalNewsMoreUrl' => '/c/yuanwu', // 院务公开栏目别名
            'noticeMoreUrl' => '/c/zhaobiao', // 招标信息栏目别名
            'doctorItems' => $doctorItems,
            'sidebarItems' => [], // 首页不需要特定的侧边栏项目
            'departmentTabs' => $departmentData['tabs'],
            'departmentGroups' => $departmentData['groups'],
        ];

        $this->render('front/index', $data);
    }

    /**
     * 获取科室数据用于首页展示
     * parent_id=9的栏目作为标签页，其子栏目下的文章作为内容
     *
     * @return array{tabs: array<string>, groups: array<array<array<string, mixed>>>}
     */
    private function getDepartmentDataWithArticles(): array
    {
        try {
            // 获取 parent_id=9 的栏目（作为标签页）
            $parentDepartments = $this->db->select('sys_category', [
                'id',
                'name',
                'parent_id'
            ], [
                'parent_id' => 9,
                'is_hidden' => 0,
                'ORDER' => ['sort' => 'ASC', 'id' => 'ASC']
            ]) ?: [];
            
            $tabs = [];
            $groups = [];
            
            foreach ($parentDepartments as $dept) {
                $tabs[] = $dept['name'];
                
                // 直接获取该父栏目下的文章（因为parent_id=9的子栏目就是我们要获取文章的栏目）
                $articles = $this->db->select('sys_content', [
                    'id',
                    'title',
                    'cover_img',
                    'summary',
                    'published_at'
                ], [
                    'category_id' => $dept['id'],
                    'status' => 1,
                    'ORDER' => ['published_at' => 'DESC'],
                    'LIMIT' => 9 // 限制每组最多显示6篇文章
                ]) ?: [];
                
                $groupItems = [];
                foreach ($articles as $article) {
                    $groupItems[] = [
                        'title' => $article['title'],
                        'pic' => $article['cover_img'] ? $article['cover_img'] : '', // 有图片则使用原图，否则留空
                        'url' => '/article/' . $article['id']
                    ];
                }
                
                $groups[] = $groupItems;
            }
            
            return [
                'tabs' => $tabs,
                'groups' => $groups
            ];
        } catch (\Throwable $e) {
            error_log('获取科室数据失败: ' . $e->getMessage());
            return [
                'tabs' => [],
                'groups' => []
            ];
        }
    }
    
    /**
     * 获取专家介绍列表
     * 从栏目ID=3及其所有子栏目中获取专家数据
     *
     * @return array<int, array<string, mixed>> 专家列表
     */
    private function getDoctorItems(): array
    {
        $doctorCategoryId = 3;
        $doctorCategoryIds = array_values(array_unique(array_merge(
            [$doctorCategoryId],
            $this->categoryService->getAllChildrenIds($doctorCategoryId)
        )));

        $doctorItems = [];

        try {
            $rows = $this->db->select('sys_content', [
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
                if (!is_array($r)) {
                    continue;
                }

                // 解析 attributes 字段
                $attrs = [];
                if (!empty($r['attributes'])) {
                    $decoded = json_decode((string)$r['attributes'], true);
                    if (is_array($decoded)) {
                        $attrs = $decoded;
                    }
                }

                $doctorItems[] = [
                    'url' => '/article/' . (int)($r['id'] ?? 0),
                    'pic' => (string)($r['cover_img'] ?? ''),
                    'name' => (string)($attrs['name'] ?? $r['title'] ?? ''),
                    'dept' => (string)($attrs['dept'] ?? $attrs['department'] ?? ''),
                    'title' => (string)($attrs['title'] ?? $attrs['position'] ?? ''),
                    'desc' => (string)($attrs['desc'] ?? $attrs['summary'] ?? $r['summary'] ?? ''),
                ];
            }
        } catch (\Throwable $e) {
            error_log('获取专家列表失败: ' . $e->getMessage());
            $doctorItems = [];
        }

        return $doctorItems;
    }
    
    /**
     * 获取图片新闻用于首页轮播展示
     *
     * @return array<int, array<string, mixed>> 图片新闻列表
     */
    private function getImageNewsItems(): array
    {
        $imageNewsItems = [];

        try {
            $rows = $this->db->select('sys_content', [
                'id',
                'title',
                'cover_img',
                'summary',
                'published_at'
            ], [
                'is_image_news' => 1,
                'status' => 1,
                'ORDER' => ['published_at' => 'DESC'],
                'LIMIT' => 5
            ]) ?: [];

            foreach ($rows as $r) {
                if (!is_array($r)) {
                    continue;
                }

                $imageNewsItems[] = [
                    'url' => '/article/' . (int)($r['id'] ?? 0),
                    'pic' => (string)($r['cover_img'] ?? ''),
                    'title' => (string)($r['title'] ?? ''),
                ];
            }
        } catch (\Throwable $e) {
            error_log('获取图片新闻失败: ' . $e->getMessage());
            $imageNewsItems = [];
        }

        return $imageNewsItems;
    }
}
