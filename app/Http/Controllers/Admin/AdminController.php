<?php
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Database;
use App\Core\Security;
use App\Services\LogService;

class AdminController extends AdminBaseController
{
    public function __construct(
        private readonly \Medoo\Medoo $db,
        private readonly \App\Services\SecurityService $securityService
    ) {
        parent::__construct();
    }

    public function index(): void
    {
        $this->requirePerm('admin:admin:view');

        $db = Database::get();
        $admins = $db->select('sys_admin', [
            'id', 'username', 'name', 'email', 'phone', 'status', 'last_login_time', 'last_login_ip', 'login_count', 'created_at'
        ], [
            'ORDER' => ['id' => 'ASC']
        ]);

        // 获取每个管理员的角色
        $adminIds = array_column($admins, 'id');
        $adminRoles = [];
        if (count($adminIds) > 0) {
            $rows = $db->select('sys_admin_role', [
                '[>]sys_role' => ['role_id' => 'id']
            ], [
                'sys_admin_role.admin_id',
                'sys_role.id',
                'sys_role.name',
                'sys_role.title'
            ], [
                'sys_admin_role.admin_id' => $adminIds
            ]);

            foreach ($rows as $r) {
                $aid = (int)($r['admin_id'] ?? 0);
                if ($aid > 0) {
                    if (!isset($adminRoles[$aid])) {
                        $adminRoles[$aid] = [];
                    }
                    $adminRoles[$aid][] = [
                        'id' => (int)($r['id'] ?? 0),
                        'name' => (string)($r['name'] ?? ''),
                        'title' => (string)($r['title'] ?? ''),
                    ];
                }
            }
        }

        foreach ($admins as &$a) {
            $a['roles'] = $adminRoles[(int)($a['id'] ?? 0)] ?? [];
        }
        unset($a);

        $this->render('admin/admin/index', [
            'title' => '管理员管理',
            'admins' => $admins,
            'can_save' => $this->hasPerm('admin:admin:save'),
            'can_delete' => $this->hasPerm('admin:admin:delete'),
        ]);
    }

    public function edit(): void
    {
        $this->requirePerm('admin:admin:save');

        $id = (int)$this->get('id', 0);
        $db = Database::get();

        $admin = null;
        $selectedRoleIds = [];
        if ($id > 0) {
            $admin = $db->get('sys_admin', [
                'id', 'username', 'name', 'avatar', 'email', 'phone', 'status'
            ], ['id' => $id]);

            if (!$admin) {
                $this->error('管理员不存在');
            }

            $rows = $db->select('sys_admin_role', ['role_id'], ['admin_id' => $id]);
            foreach ($rows as $r) {
                $rid = (int)($r['role_id'] ?? 0);
                if ($rid > 0) {
                    $selectedRoleIds[] = $rid;
                }
            }
        }

        $roles = $db->select('sys_role', [
            'id', 'name', 'title', 'status'
        ], [
            'status' => 1,
            'ORDER' => ['sort' => 'ASC', 'id' => 'ASC']
        ]);

        $this->render('admin/admin/edit', [
            'title' => $id > 0 ? '编辑管理员' : '新增管理员',
            'admin' => $admin,
            'roles' => $roles,
            'selected_role_ids' => $selectedRoleIds,
        ]);
    }

    public function save(): void
    {
        $this->requirePerm('admin:admin:save');

        // 验证POST请求和CSRF
        if (!$this->validatePostAndCsrf()) {
            return;
        }

        $id = (int)$this->post('id', 0);
        $username = trim((string)$this->post('username', ''));
        $name = trim((string)$this->post('name', ''));
        $email = trim((string)$this->post('email', ''));
        $phone = trim((string)$this->post('phone', ''));
        $status = (int)$this->post('status', 1);
        $password = (string)$this->post('password', '');
        $roleIds = $this->post('role_ids', []);
        if (!is_array($roleIds)) {
            $roleIds = [];
        }

        if ($username === '') {
            $this->errorJson('请输入用户名');
            return;
        }

        $db = Database::get();

        // 密码复杂度校验（当提供新密码时）
        if ($password !== '') {
            $check = $this->securityService->validatePasswordComplexity($password, $username);
            if (!$check['ok']) {
                $this->errorJson($check['message'] ?? '密码复杂度不足');
                return;
            }
        }

        if ($id > 0) {
            $exists = $db->get('sys_admin', ['id'], ['id' => $id]);
            if (!$exists) {
                $this->errorJson('管理员不存在');
                return;
            }

            $conflict = $db->get('sys_admin', ['id'], [
                'username' => $username,
                'id[!]' => $id
            ]);
            if ($conflict) {
                $this->errorJson('用户名已存在');
                return;
            }

            $data = [
                'username' => $username,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'status' => $status,
            ];

            if ($password !== '') {
                $data['password'] = $this->securityService->passwordHash($password);
            }

            $db->update('sys_admin', $data, ['id' => $id]);
        } else {
            if ($password === '') {
                $this->errorJson('新增管理员必须设置密码');
                return;
            }

            $conflict = $db->has('sys_admin', ['username' => $username]);
            if ($conflict) {
                $this->errorJson('用户名已存在');
                return;
            }

            $db->insert('sys_admin', [
                'username' => $username,
                'password' => $this->securityService->passwordHash($password),
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'status' => $status,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $id = (int)$db->id();
        }

        $this->syncAdminRoles($id, $roleIds);

        LogService::record(
            $this->getAdminId(),
            $this->getAdminUsername(),
            $id > 0 ? 'update' : 'create',
            $id > 0 ? '编辑管理员' : '新增管理员',
            "管理员 {$username}"
        );

        $adminPath = $this->getAdminPath();
        $this->successJson('保存成功', null, $adminPath . '/admin');
    }

    public function delete(): void
    {
        $this->requirePerm('admin:admin:delete');

        // 验证POST请求和CSRF
        if (!$this->validatePostAndCsrf()) {
            return;
        }

        $id = (int)$this->post('id', 0);
        if ($id <= 0) {
            $this->errorJson('参数错误');
            return;
        }

        if ($id === $this->getAdminId()) {
            $this->errorJson('不能删除自己');
            return;
        }

        $db = Database::get();
        $admin = $db->get('sys_admin', ['username'], ['id' => $id]);
        if (!$admin) {
            $this->errorJson('管理员不存在');
            return;
        }

        $db->delete('sys_admin', ['id' => $id]);

        LogService::record(
            $this->getAdminId(),
            $this->getAdminUsername(),
            'delete',
            '删除管理员',
            "管理员 " . ($admin['username'] ?? '')
        );

        $adminPath = $this->getAdminPath();
        $this->successJson('删除成功', null, $adminPath . '/admin');
    }

    /**
     * @param int $adminId
     * @param array<int, mixed> $roleIds
     */
    private function syncAdminRoles(int $adminId, array $roleIds): void
    {
        if ($adminId <= 0) {
            return;
        }

        $roleIds = array_values(array_unique(array_filter(array_map(fn($v) => (int)$v, $roleIds), fn($v) => $v > 0)));

        $db = Database::get();

        $db->delete('sys_admin_role', ['admin_id' => $adminId]);

        if (count($roleIds) === 0) {
            return;
        }

        $rows = [];
        foreach ($roleIds as $rid) {
            $rows[] = ['admin_id' => $adminId, 'role_id' => $rid];
        }

        if (count($rows) > 0) {
            $db->insert('sys_admin_role', $rows);
        }
    }
}
