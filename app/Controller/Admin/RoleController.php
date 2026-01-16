<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Core\Security;
use App\Service\PermissionService;

class RoleController extends AdminBaseController
{
    public function __construct(
        private readonly \Medoo\Medoo $db,
        private readonly PermissionService $permissionService
    ) {
        parent::__construct();
    }

    public function index(): void
    {
        $this->requirePerm('admin:role:view');

        $db = $this->db;
        $roles = $db->select('sys_role', [
            'id', 'name', 'title', 'status', 'sort', 'remark', 'created_at', 'updated_at'
        ], [
            'ORDER' => ['sort' => 'ASC', 'id' => 'ASC']
        ]);

        $this->render('admin/role/index', [
            'title' => '角色管理',
            'roles' => $roles,
            'can_save' => $this->hasPerm('admin:role:save'),
            'can_delete' => $this->hasPerm('admin:role:delete'),
        ]);
    }

    public function edit(): void
    {
        $this->requirePerm('admin:role:save');

        $id = (int)$this->get('id', 0);
        $db = $this->db;

        $role = null;
        $selectedPermKeys = [];
        if ($id > 0) {
            $role = $db->get('sys_role', ['id', 'name', 'title', 'status', 'sort', 'remark'], ['id' => $id]);
            if (!$role) {
                $this->error('角色不存在');
            }

            $rows = $db->select('sys_role_permission', [
                '[>]sys_permission' => ['permission_id' => 'id']
            ], [
                'sys_permission.perm_key'
            ], [
                'sys_role_permission.role_id' => $id
            ]);

            foreach ($rows as $r) {
                $k = (string)($r['perm_key'] ?? '');
                if ($k !== '') {
                    $selectedPermKeys[] = $k;
                }
            }
        }

        // 使用 PermissionService 获取所有权限
        $permissionService = $this->permissionService;
        [$permGroups, $allPermKeys] = $permissionService->getAllPermissions();

        $this->render('admin/role/edit', [
            'title' => $id > 0 ? '编辑角色' : '新增角色',
            'role' => $role,
            'perm_groups' => $permGroups,
            'selected_perm_keys' => $selectedPermKeys,
            'all_perm_keys' => $allPermKeys,
        ]);
    }

    public function save(): void
    {
        $this->requirePerm('admin:role:save');

        if (!$this->isPost()) {
            $this->errorJson('请求方法错误');
            return;
        }

        $csrfToken = (string)$this->post('csrf_token', '');
        if (!Security::checkCsrfToken($csrfToken)) {
            $this->errorJson('CSRF Token验证失败');
            return;
        }

        $id = (int)$this->post('id', 0);
        $name = trim((string)$this->post('name', ''));
        $title = trim((string)$this->post('title', ''));
        $status = (int)$this->post('status', 1);
        $sort = (int)$this->post('sort', 0);
        $remark = trim((string)$this->post('remark', ''));
        $permKeys = $this->post('perm_keys', []);
        if (!is_array($permKeys)) {
            $permKeys = [];
        }

        if ($id === 1) {
            $this->errorJson('系统内置角色不可修改');
            return;
        }

        if ($name === '') {
            $this->errorJson('请输入角色标识');
            return;
        }

        $db = $this->db;

        $data = [
            'name' => $name,
            'title' => $title,
            'status' => $status,
            'sort' => $sort,
            'remark' => $remark,
        ];

        if ($id > 0) {
            $exists = $db->has('sys_role', ['id' => $id]);
            if (!$exists) {
                $this->errorJson('角色不存在');
                return;
            }

            $conflict = $db->get('sys_role', ['id'], [
                'name' => $name,
                'id[!]' => $id
            ]);
            if ($conflict) {
                $this->errorJson('角色标识已存在');
                return;
            }

            $db->update('sys_role', $data, ['id' => $id]);
        } else {
            $conflict = $db->has('sys_role', ['name' => $name]);
            if ($conflict) {
                $this->errorJson('角色标识已存在');
                return;
            }
            $db->insert('sys_role', $data);
            $id = (int)$db->id();
        }

        // 使用 PermissionService 同步角色权限
        $permissionService = $this->permissionService;
        $permissionService->syncRolePermissions($id, $permKeys);

        $this->successJson('保存成功', null, '/admin/role');
    }

    public function delete(): void
    {
        $this->requirePerm('admin:role:delete');

        if (!$this->isPost()) {
            $this->errorJson('请求方法错误');
            return;
        }

        $csrfToken = (string)$this->post('csrf_token', '');
        if (!Security::checkCsrfToken($csrfToken)) {
            $this->errorJson('CSRF Token验证失败');
            return;
        }

        $id = (int)$this->post('id', 0);
        if ($id <= 0) {
            $this->errorJson('参数错误');
            return;
        }

        if ($id === 1) {
            $this->errorJson('系统内置角色不可删除');
            return;
        }

        $db = $this->db;
        $db->delete('sys_role', ['id' => $id]);

        $this->successJson('删除成功', null, '/admin/role');
    }

}
