<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Core\Database;
use App\Core\Security;
use App\Model\FriendLink;
use App\Service\LogService;

class FriendLinkController extends AdminBaseController
{
    public function __construct(
        private readonly FriendLink $friendLink
    ) {
        parent::__construct();
    }

    public function index(): void
    {
        $this->requirePerm('admin:friendlink:view');

        $page = (int)$this->get('page', 1);
        $limit = 20;
        $data = $this->friendLink->getPage($page, $limit);

        $this->render('admin/friendlink/index', [
            'title' => '友情链接管理',
            'links' => $data['list'],
            'total' => $data['total'],
            'page' => $page,
            'limit' => $limit,
            'can_save' => $this->hasPerm('admin:friendlink:save'),
            'can_delete' => $this->hasPerm('admin:friendlink:delete'),
        ]);
    }

    public function edit(): void
    {
        $this->requirePerm('admin:friendlink:save');

        $id = (int)$this->get('id', 0);
        $link = $id > 0 ? $this->friendLink->getById($id) : null;

        $this->render('admin/friendlink/edit', [
            'title' => $id > 0 ? '编辑友情链接' : '新增友情链接',
            'link' => $link,
        ]);
    }

    public function save(): void
    {
        $this->requirePerm('admin:friendlink:save');

        if (!$this->isPost()) {
            $this->errorJson('请求方法错误');
        }

        if (!Security::checkCsrfToken((string)$this->post('csrf_token', ''))) {
            $this->errorJson('CSRF 校验失败');
        }

        $id = (int)$this->post('id', 0);
        $title = trim((string)$this->post('title', ''));
        $url = trim((string)$this->post('url', ''));
        $logo = trim((string)$this->post('logo', ''));
        $sort = (int)$this->post('sort', 0);
        $status = (int)$this->post('status', 1);

        if ($title === '') {
            $this->errorJson('名称不能为空');
        }
        if ($url === '') {
            $this->errorJson('链接不能为空');
        }

        $data = [
            'id' => $id,
            'title' => $title,
            'url' => $url,
            'logo' => $logo,
            'sort' => $sort,
            'status' => $status,
        ];

        $ok = $this->friendLink->save($data);
        if (!$ok) {
            $this->errorJson('保存失败');
        }

        LogService::record(
            $this->getAdminId(),
            $this->getAdminUsername(),
            $id > 0 ? 'update' : 'create',
            '保存友情链接',
            'id:' . $id
        );

        $this->successJson('保存成功', null, '/admin/friendlink');
    }

    public function delete(): void
    {
        $this->requirePerm('admin:friendlink:delete');

        if (!$this->isPost()) {
            $this->errorJson('请求方法错误');
        }

        if (!Security::checkCsrfToken((string)$this->post('csrf_token', ''))) {
            $this->errorJson('CSRF 校验失败');
        }

        $id = (int)$this->post('id', 0);
        if ($id <= 0) {
            $this->errorJson('参数错误');
        }

        $this->friendLink->delete($id);

        LogService::record(
            $this->getAdminId(),
            $this->getAdminUsername(),
            'delete',
            '删除友情链接',
            'id:' . $id
        );

        $this->successJson('删除成功');
    }
}
