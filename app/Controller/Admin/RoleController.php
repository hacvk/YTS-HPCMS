<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Core\Database;
use App\Core\Security;

class RoleController extends AdminBaseController
{
    public function index(): void
    {
        $this->requirePerm('admin:role:view');

        $db = Database::get();
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
        $db = Database::get();

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

        [$permGroups, $allPermKeys] = $this->buildPermissionCatalog();

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

        $db = Database::get();

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

        $this->syncRolePermissions($id, $permKeys);

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

        $db = Database::get();
        $db->delete('sys_role', ['id' => $id]);

        $this->successJson('删除成功', null, '/admin/role');
    }

    /**
     * 构建权限目录：从菜单扫描 view 权限 + 手工补 save/delete
     *
     * @return array{0: array<string, array<int, array{key:string,title:string}>>, 1: array<int, string>}
     */
    private function buildPermissionCatalog(): array
    {
        $menuFile = dirname(__DIR__, 2) . '/Config/menu.php';
        $menu = [];
        if (is_file($menuFile)) {
            $menu = require $menuFile;
        }

        $groups = [];
        $all = [];

        $add = (function(string $groupName, string $key, string $title) use (&$groups, &$all) {
            if ($key === '') {
                return;
            }
            if (!isset($groups[$groupName])) {
                $groups[$groupName] = [];
            }
            $groups[$groupName][] = ['key' => $key, 'title' => $title];
            $all[] = $key;
        });

        $walk = function(array $items, string $groupName) use (&$walk, $add) {
            foreach ($items as $it) {
                $title = (string)($it['title'] ?? '');
                $route = (string)($it['route'] ?? '');
                $perm = (string)($it['perm'] ?? '');

                if ($title !== '' && $perm !== '') {
                    $add($groupName, $perm, $title . '（查看）');
                } elseif ($title !== '' && $route !== '') {
                    // 如果未配置 perm，则按 route 推导一个 view key
                    $key = $this->routeToViewPerm($route);
                    $add($groupName, $key, $title . '（查看）');
                }

                if (isset($it['children']) && is_array($it['children'])) {
                    $walk($it['children'], $groupName);
                }
            }
        };

        foreach ($menu as $top) {
            $groupName = (string)($top['title'] ?? '默认');
            if (isset($top['children']) && is_array($top['children'])) {
                $walk($top['children'], $groupName);
            } else {
                $walk([$top], $groupName);
            }
        }

        // 关键操作权限（手工补充）
        $add('系统设置', 'admin:role:save', '角色管理（新增/编辑）');
        $add('系统设置', 'admin:role:delete', '角色管理（删除）');
        $add('系统设置', 'admin:admin:save', '管理员管理（新增/编辑）');
        $add('系统设置', 'admin:admin:delete', '管理员管理（删除）');
        $add('内容管理', 'admin:category:save', '栏目管理（新增/编辑）');
        $add('内容管理', 'admin:category:delete', '栏目管理（删除）');
        $add('内容管理', 'admin:article:save', '文章管理（新增/编辑）');
        $add('内容管理', 'admin:article:delete', '文章管理（删除）');
        $add('内容管理', 'admin:page:save', '单页管理（新增/编辑）');
        $add('内容管理', 'admin:page:delete', '单页管理（删除）');

        $all = array_values(array_unique($all));
        sort($all);

        ksort($groups);
        foreach ($groups as $g => $items) {
            usort($items, fn($a, $b) => strcmp($a['key'], $b['key']));
            $groups[$g] = $items;
        }

        $this->seedPermissions($groups);

        return [$groups, $all];
    }

    private function routeToViewPerm(string $route): string
    {
        $route = trim($route);
        if ($route === '') {
            return '';
        }

        $route = trim($route, '/');
        $parts = explode('/', $route);
        if (count($parts) >= 2 && $parts[0] === 'admin') {
            $controller = $parts[1] ?? 'home';
            return 'admin:' . $controller . ':view';
        }

        return 'admin:route:view';
    }

    /**
     * 写入 sys_permission（幂等）
     *
     * @param array<string, array<int, array{key:string,title:string}>> $groups
     */
    private function seedPermissions(array $groups): void
    {
        $db = Database::get();

        foreach ($groups as $groupName => $items) {
            foreach ($items as $it) {
                $key = (string)($it['key'] ?? '');
                $title = (string)($it['title'] ?? '');
                if ($key === '') {
                    continue;
                }

                $exists = $db->get('sys_permission', ['id', 'title', 'group_name'], ['perm_key' => $key]);
                if ($exists) {
                    $update = [];
                    if ((string)($exists['title'] ?? '') !== $title && $title !== '') {
                        $update['title'] = $title;
                    }
                    if ((string)($exists['group_name'] ?? '') !== $groupName) {
                        $update['group_name'] = $groupName;
                    }
                    if (count($update) > 0) {
                        $db->update('sys_permission', $update, ['perm_key' => $key]);
                    }
                    continue;
                }

                $db->insert('sys_permission', [
                    'perm_key' => $key,
                    'title' => $title !== '' ? $title : $key,
                    'group_name' => $groupName,
                    'status' => 1,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    /**
     * @param int $roleId
     * @param array<int, mixed> $permKeys
     */
    private function syncRolePermissions(int $roleId, array $permKeys): void
    {
        if ($roleId <= 0) {
            return;
        }

        $permKeys = array_values(array_unique(array_filter(array_map(fn($v) => trim((string)$v), $permKeys), fn($v) => $v !== '')));

        $db = Database::get();

        // 先删除旧关联
        $db->delete('sys_role_permission', ['role_id' => $roleId]);

        if (count($permKeys) === 0) {
            return;
        }

        // 查询 permission_id
        $perms = $db->select('sys_permission', ['id', 'perm_key'], [
            'perm_key' => $permKeys
        ]);

        $permIdByKey = [];
        foreach ($perms as $p) {
            $k = (string)($p['perm_key'] ?? '');
            $permIdByKey[$k] = (int)($p['id'] ?? 0);
        }

        $rows = [];
        foreach ($permKeys as $k) {
            $pid = (int)($permIdByKey[$k] ?? 0);
            if ($pid > 0) {
                $rows[] = ['role_id' => $roleId, 'permission_id' => $pid];
            }
        }

        if (count($rows) > 0) {
            $db->insert('sys_role_permission', $rows);
        }
    }
}
