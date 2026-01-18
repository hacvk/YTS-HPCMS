<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\Content;
use App\Services\CategoryService;
use App\Services\SiteService;
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

        $data = [
            'navItems' => $navItems,
            'bannerItems' => $this->bannerModel->getEnabled(),
            'hospitalNewsItems' => $ywggList,
            'noticeItems' => $zbggList,
            'hospitalNewsMoreUrl' => '/c/yuanwu', // 院务公开栏目别名
            'noticeMoreUrl' => '/c/zhaobiao', // 招标信息栏目别名
            'doctorItems' => $doctorItems,
            'channelItems' => [], // 首页不需要特定的频道项目
        ];

        $this->render('front/index', $data);
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
}
