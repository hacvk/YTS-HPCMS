<?php
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\AdminBaseController;
use App\Models\Banner;
use App\Core\Security;
use App\Services\LogService;
use Medoo\Medoo;

class BannerController extends AdminBaseController
{
    private Banner $bannerModel;
    
    public function __construct(
        private readonly Medoo $db
    ) {
        parent::__construct();
        $this->bannerModel = new Banner($this->db);
    }
    
    /**
     * 获取类型标签
     * 
     * @param string $type 类型
     * @return string
     */
    private function getTypeLabel(string $type): string
    {
        return match($type) {
            'indexFast' => '首页快捷方式',
            'banner' => '轮播图',
            'indexZt' => '首页专题',
            default => '未知类型'
        };
    }
    
    /**
     * 列表
     */
    public function index(): void
    {
        $page  = (int)$this->get('page', 1);
        $limit = 20;
        $type = $this->get('type', 'banner'); // 获取类型过滤参数，默认为 'banner'
        
        $data  = $this->bannerModel->getPageWithType($page, $limit, $type);

        $this->render('admin/banner/index', [
            'title' => 'Banner 管理',
            'banners' => $data['list'],
            'total' => $data['total'],
            'page'  => $page,
            'limit' => $limit,
            'current_type' => $type,
        ]);
    }

    /**
     * 编辑/新增
     */
    public function edit(): void
    {
        $id = (int)$this->get('id', 0);
        $banner = $id ? $this->bannerModel->getById($id) : null;
        
        // 如果是新增且指定了类型
        $type = $this->get('type', '');
        if (!$banner && !empty($type)) {
            $banner = ['type' => $type];
        }
        
        $this->render('admin/banner/edit', [
            'title' => $id ? '编辑 Banner' : '新增 Banner',
            'banner' => $banner,
            'type' => $type,
        ]);
    }

    /**
     * 保存
     */
    public function save(): void
    {
        // 验证POST请求和CSRF
        if (!$this->validatePostAndCsrf()) {
            return;
        }
        $data = [
            'id'   => (int)$this->post('id',0),
            'title'=> trim($this->post('title','')),
            'image_path'  => trim($this->post('pic','')),  // 前端用pic，后端存 image_path
            'url'  => trim($this->post('url','')),
            'sort' => (int)$this->post('sort',0),
            'status' => (int)$this->post('is_enabled',0),  // 前端用is_enabled，后端存 status
            'type' => trim($this->post('type', 'banner')),  // 新增类型字段，默认为 'banner'
        ];

        if($data['title']===''||$data['image_path']===''){
            $this->errorJson('标题和图片不能为空');
        }
        $this->bannerModel->save($data);
        LogService::record($this->getAdminId(),$this->getAdminUsername(),'update','保存Banner','id:'.$data['id']);
        $this->successJson('保存成功',null,'/admin/banner');
    }

    /**
     * 删除
     */
    public function delete(): void
    {
        // 验证POST请求和CSRF
        if (!$this->validatePostAndCsrf()) {
            return;
        }
        $id=(int)$this->post('id',0);
        if($id<=0){$this->errorJson('参数错误');}
        $this->bannerModel->delete($id);
        LogService::record($this->getAdminId(),$this->getAdminUsername(),'delete','删除Banner','id:'.$id);
        $this->successJson('删除成功');
    }
}