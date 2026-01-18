<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\Admin\AdminBaseController;
use App\Model\Banner;
use App\Core\Security;
use App\Service\LogService;
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
     * 列表
     */
    public function index(): void
    {
        $page  = (int)$this->get('page', 1);
        $limit = 20;
        $data  = $this->bannerModel->getPage($page, $limit);

        $this->render('admin/banner/index', [
            'title' => 'Banner 管理',
            'banners' => $data['list'],
            'total' => $data['total'],
            'page'  => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * 编辑/新增
     */
    public function edit(): void
    {
        $id = (int)$this->get('id', 0);
        $banner = $id ? $this->bannerModel->getById($id) : null;
        $this->render('admin/banner/edit', [
            'title' => $id ? '编辑 Banner' : '新增 Banner',
            'banner' => $banner,
        ]);
    }

    /**
     * 保存
     */
    public function save(): void
    {
        if(!$this->isPost()) { $this->errorJson('请求方法错误'); }
        if(!Security::checkCsrfToken($this->post('csrf_token',''))) {
            $this->errorJson('CSRF 校验失败');
        }
        $data = [
            'id'   => (int)$this->post('id',0),
            'title'=> trim($this->post('title','')),
            'image_path'  => trim($this->post('pic','')),  // 前端传 pic，后端存 image_path
            'url'  => trim($this->post('url','')),
            'sort' => (int)$this->post('sort',0),
            'status' => (int)$this->post('is_enabled',0),  // 前端传 is_enabled，后端存 status
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
        if(!$this->isPost()){$this->errorJson('请求方法错误');}
        if(!Security::checkCsrfToken($this->post('csrf_token',''))){$this->errorJson('CSRF校验失败');}
        $id=(int)$this->post('id',0);
        if($id<=0){$this->errorJson('参数错误');}
        $this->bannerModel->delete($id);
        LogService::record($this->getAdminId(),$this->getAdminUsername(),'delete','删除Banner','id:'.$id);
        $this->successJson('删除成功');
    }
}
