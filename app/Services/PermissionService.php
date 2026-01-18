<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Core\Database;
use Medoo\Medoo;

/**
 * 权限服务类
 * 处理权限相关的业务逻辑：扫描菜单、构建权限目录、同步权限到数据库
 */
class PermissionService
{
    /**
     * 构造函数
     *
     * @param Medoo $db 数据库实例
     */
    public function __construct(
        private readonly Medoo $db
    ) {
    }

    /**
     * 获取所有权限（分组）
     * 从菜单配置扫描权限，并同步到数据库
     *
     * @return array{0: array<string, array<int, array{key:string,title:string}>>, 1: array<int, string>}
     *         返回 [权限分组, 所有权限键列表]
     */
    public function getAllPermissions(): array
    {
        $menu = $this->loadMenuConfig();
        [$groups, $all] = $this->buildPermissionCatalog($menu);
        $this->seedPermissions($groups);

        return [$groups, $all];
    }

    /**
     * 加载菜单配置文件
     *
     * @return array<int, array<string, mixed>>
     */
    private function loadMenuConfig(): array
    {
        $menuFile = App::getConfigPath('menu.php');
        if (!is_file($menuFile)) {
            return [];
        }

        return require $menuFile;
    }

    /**
     * 构建权限目录：从菜单扫描 view 权限 + 手工补充 save/delete
     *
     * @param array<int, array<string, mixed>> $menu 菜单配置
     * @return array{0: array<string, array<int, array{key:string,title:string}>>, 1: array<int, string>}
     */
    private function buildPermissionCatalog(array $menu): array
    {
        $groups = [];
        $all = [];

        $add = function(string $groupName, string $key, string $title) use (&$groups, &$all): void {
            if ($key === '') {
                return;
            }
            if (!isset($groups[$groupName])) {
                $groups[$groupName] = [];
            }
            $groups[$groupName][] = ['key' => $key, 'title' => $title];
            $all[] = $key;
        };

        $walk = function(array $items, string $groupName) use (&$walk, $add): void {
            foreach ($items as $it) {
                $title = (string)($it['title'] ?? '');
                $route = (string)($it['route'] ?? '');
                $perm = (string)($it['perm'] ?? '');

                if ($title !== '' && $perm !== '') {
                    $add($groupName, $perm, $title . '（查看）');
                } elseif ($title !== '' && $route !== '') {
                    // 如果未配置 perm，则从 route 推导一个 view key
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

        return [$groups, $all];
    }

    /**
     * 将路由转换为查看权限键
     *
     * @param string $route 路由
     * @return string
     */
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
     * 如果权限已存在，则更新标题和分组；如果不存在，则插入
     *
     * @param array<string, array<int, array{key:string,title:string}>> $groups 权限分组
     * @return void
     */
    private function seedPermissions(array $groups): void
    {
        foreach ($groups as $groupName => $items) {
            foreach ($items as $it) {
                $key = (string)($it['key'] ?? '');
                $title = (string)($it['title'] ?? '');
                if ($key === '') {
                    continue;
                }

                $exists = $this->db->get('sys_permission', ['id', 'title', 'group_name'], ['perm_key' => $key]);
                if ($exists) {
                    $update = [];
                    if ((string)($exists['title'] ?? '') !== $title && $title !== '') {
                        $update['title'] = $title;
                    }
                    if ((string)($exists['group_name'] ?? '') !== $groupName) {
                        $update['group_name'] = $groupName;
                    }
                    if (count($update) > 0) {
                        $this->db->update('sys_permission', $update, ['perm_key' => $key]);
                    }
                    continue;
                }

                $this->db->insert('sys_permission', [
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
     * 同步角色权限
     *
     * @param int $roleId 角色ID
     * @param array<int, mixed> $permKeys 权限键列表
     * @return void
     */
    public function syncRolePermissions(int $roleId, array $permKeys): void
    {
        if ($roleId <= 0) {
            return;
        }

        $permKeys = array_values(array_unique(array_filter(
            array_map(fn($v) => trim((string)$v), $permKeys),
            fn($v) => $v !== ''
        )));

        // 先删除旧关联
        $this->db->delete('sys_role_permission', ['role_id' => $roleId]);

        if (count($permKeys) === 0) {
            return;
        }

        // 查询 permission_id
        $perms = $this->db->select('sys_permission', ['id', 'perm_key'], [
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
            $this->db->insert('sys_role_permission', $rows);
        }
    }
}
