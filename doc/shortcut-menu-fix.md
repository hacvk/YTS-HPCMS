# 快捷内容管理功能修复报告

## 问题描述

修改后的快捷内容管理功能与原始实现不一致，导致功能异常。

## 原始实现（git 4d8b067313）

### 1. 查询条件
```php
$shortcutCategories = $db->select('sys_category', [
    'id', 'name', 'slug'
], [
    'is_shortcut' => 1,  // ✅ 查询快捷栏目
    'is_hidden' => 0,
    'ORDER' => ['sort' => 'ASC', 'id' => 'ASC']
]);
```

### 2. 菜单结构
```php
$shortcutChildren = [];

// 添加单页管理
$shortcutChildren[] = [
    'title' => '单页管理',
    'route' => '/admin/page',
    'perm' => 'admin:page:view'
];

// 添加快捷栏目
foreach ($shortcutCategories as $category) {
    $shortcutChildren[] = [
        'title' => $category['name'],
        'route' => '/admin/article?cid=' . (int)$category['id'],  // ✅ 使用 cid
        'perm' => 'admin:article:view'
    ];
}
```

### 3. 菜单配置
```php
$shortcutMenu = [
    'title' => '快捷内容管理',
    'icon' => 'lightning',  // ✅ 使用 lightning 图标
    'children' => $shortcutChildren,
    'is_shortcut' => true
];
```

### 4. 插入位置
```php
// 查找"快捷导航"菜单项的位置，在其后插入
$insertIndex = -1;
foreach ($menu as $index => $item) {
    if (isset($item['title']) && $item['title'] === '快捷导航') {
        $insertIndex = $index + 1;
        break;
    }
}

if ($insertIndex > 0) {
    array_splice($menu, $insertIndex, 0, [$shortcutMenu]);
} else {
    array_unshift($menu, $shortcutMenu);
}
```

## 修改后的实现（当前）

### 1. 查询条件
```php
$categories = $db->select('sys_category', [
    'id', 'name', 'slug', 'type'
], [
    'is_hidden' => 0,  // ❌ 没有 is_shortcut 条件
    'ORDER' => ['sort' => 'ASC']
]);
```

### 2. 菜单结构
```php
foreach ($categories as $category) {
    $icon = 'fa fa-file-text';
    if ($category['type'] === 'page') {
        $icon = 'fa fa-file';
    } elseif ($category['type'] === 'product') {
        $icon = 'fa fa-shopping-cart';
    }

    $shortcutMenu['children'][] = [
        'name' => $category['name'],
        'icon' => $icon,  // ❌ 子菜单有图标（原版没有）
        'route' => '/admin/article?category_id=' . $category['id']  // ❌ 使用 category_id
    ];
}
```

### 3. 菜单配置
```php
$shortcutMenu = [
    'title' => '快捷内容管理',
    'name' => '快捷内容管理',
    'icon' => 'fa fa-bolt',  // ❌ 使用 fa 图标（模板需要 bi 图标）
    'route' => '',  // ❌ 多余的 route 字段
    'children' => []
];
```

### 4. 插入位置
```php
// ❌ 直接插入到开头
array_unshift($menu, $shortcutMenu);
```

## 问题总结

| 问题 | 原始实现 | 当前实现 | 影响 |
|------|----------|----------|------|
| 查询条件 | `is_shortcut = 1` | 无此条件 | 会查询所有栏目，包括非快捷栏目 |
| 路由参数 | `cid` | `category_id` | ArticleController 使用 `cid`，不兼容 |
| 图标 | `lightning` | `fa fa-bolt` | 模板使用 `bi bi-` 前缀，`fa` 图标不显示 |
| 插入位置 | 在"快捷导航"后 | 开头 | 菜单顺序错误 |
| 子菜单图标 | 无 | 有 | 原版子菜单没有图标 |
| 单页管理 | 有 | 无 | 缺少单页管理入口 |
| 菜单 route | 无 | 有 | 父菜单不应该有 route |

## 修复方案

### 修复后的实现

```php
private function loadShortcutCategories(array &$menu): void
{
    try {
        $db = Database::get();
        
        // 查询所有 is_shortcut = 1 且 is_hidden = 0 的栏目
        $shortcutCategories = $db->select('sys_category', [
            'id',
            'name',
            'slug'
        ], [
            'is_shortcut' => 1,  // ✅ 只查询快捷栏目
            'is_hidden' => 0,
            'ORDER' => ['sort' => 'ASC', 'id' => 'ASC']
        ]) ?: [];
        
        // 构建快捷栏目的子菜单项
        $shortcutChildren = [];
        
        // 添加单页管理到快捷内容管理组
        $shortcutChildren[] = [
            'title' => '单页管理',
            'route' => '/admin/page',
            'perm' => 'admin:page:view'
        ];
        
        // 添加快捷导航栏目
        foreach ($shortcutCategories as $category) {
            $shortcutChildren[] = [
                'title' => $category['name'],
                'route' => '/admin/article?cid=' . (int)$category['id'],  // ✅ 使用 cid
                'perm' => 'admin:article:view'
            ];
        }
        
        // 创建"快捷内容管理"菜单组
        $shortcutMenu = [
            'title' => '快捷内容管理',
            'icon' => 'lightning',  // ✅ 使用 lightning 图标
            'children' => $shortcutChildren,
            'is_shortcut' => true  // ✅ 标识为快捷导航组
        ];
        
        // 查找"快捷导航"菜单项的位置，在其后插入
        $insertIndex = -1;
        foreach ($menu as $index => $item) {
            if (isset($item['title']) && $item['title'] === '快捷导航') {
                $insertIndex = $index + 1;
                break;
            }
        }
        
        // 如果找到"快捷导航"，在其后插入；否则插入到开头
        if ($insertIndex > 0) {
            array_splice($menu, $insertIndex, 0, [$shortcutMenu]);
        } else {
            array_unshift($menu, $shortcutMenu);
        }
        
    } catch (\Exception $e) {
        // 加载快捷菜单失败不影响主流程
        error_log('加载快捷菜单失败: ' . $e->getMessage());
    }
}
```

## 数据库要求

### sys_category 表字段

需要确保 `sys_category` 表有以下字段：

```sql
CREATE TABLE `sys_category` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL DEFAULT '',
  `slug` VARCHAR(100) NOT NULL DEFAULT '',
  `type` VARCHAR(20) NOT NULL DEFAULT 'article',
  `is_shortcut` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否快捷栏目：1-是，0-否',
  `is_hidden` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否隐藏：1-隐藏，0-显示',
  `sort` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 设置快捷栏目

在后台栏目管理中，将需要在快捷菜单显示的栏目设置为：
- `is_shortcut = 1`
- `is_hidden = 0`

## 测试步骤

1. **设置快捷栏目**
   ```sql
   -- 将某些栏目设置为快捷栏目
   UPDATE `sys_category` SET `is_shortcut` = 1 WHERE `id` IN (1, 2, 3);
   ```

2. **清除浏览器缓存**

3. **访问后台首页**
   ```
   URL: http://yycms.com/admin/dashboard
   ```

4. **检查侧边栏**
   - ✅ 应该看到"快捷内容管理"菜单组
   - ✅ 位置应该在"快捷导航"之后
   - ✅ 包含"单页管理"入口
   - ✅ 包含设置的快捷栏目

5. **测试链接**
   - ✅ 点击"单页管理"应该跳转到 `/admin/page`
   - ✅ 点击快捷栏目应该跳转到 `/admin/article?cid=栏目ID`

## 图标说明

### Bootstrap Icons 图标

模板使用 Bootstrap Icons，图标名称不带 `bi bi-` 前缀：

| 图标名称 | 显示效果 |
|----------|----------|
| `speedometer2` | 📊 控制台 |
| `star` | ⭐ 快捷导航 |
| `lightning` | ⚡ 快捷内容管理 |
| `file-text` | 📄 内容管理 |
| `folder` | 📁 资源管理 |
| `gear` | ⚙️ 网站设置 |
| `house-door` | 🏠 前台首页 |

### 图标映射

```php
// 模板渲染
<i class="bi bi-{$item['icon']}"></i>

// 示例
'icon' => 'speedometer2'  // 渲染为 <i class="bi bi-speedometer2"></i>
'icon' => 'lightning'     // 渲染为 <i class="bi bi-lightning"></i>
```

## 修复效果

### 修复前

| 问题 | 状态 |
|------|------|
| 查询所有栏目 | ❌ 包括非快捷栏目 |
| 路由参数错误 | ❌ 使用 category_id |
| 图标不显示 | ❌ 使用 fa 图标 |
| 位置错误 | ❌ 在开头 |
| 缺少单页管理 | ❌ 没有单页入口 |

### 修复后

| 功能 | 状态 |
|------|------|
| 只查询快捷栏目 | ✅ 正确 |
| 路由参数 cid | ✅ 正确 |
| 图标 lightning | ✅ 正确 |
| 位置在快捷导航后 | ✅ 正确 |
| 包含单页管理 | ✅ 正确 |

## 后续建议

### 1. 添加数据库字段说明

在栏目管理界面添加"快捷栏目"选项：
- 复选框：设为快捷栏目
- 说明：勾选后会在"快捷内容管理"菜单组中显示

### 2. 优化快捷栏目排序

```php
$shortcutCategories = $db->select('sys_category', [
    'id', 'name', 'slug'
], [
    'is_shortcut' => 1,
    'is_hidden' => 0,
    'ORDER' => ['sort' => 'ASC', 'name' => 'ASC']  // 添加名称排序
]);
```

### 3. 添加快捷栏目数量限制

```php
$shortcutCategories = $db->select('sys_category', [
    'id', 'name', 'slug'
], [
    'is_shortcut' => 1,
    'is_hidden' => 0,
    'ORDER' => ['sort' => 'ASC', 'id' => 'ASC'],
    'LIMIT' => 10  // 限制最多显示10个
]);
```

## 总结

通过恢复原始实现，修复了以下问题：

1. ✅ **查询条件** - 只查询 `is_shortcut = 1` 的栏目
2. ✅ **路由参数** - 使用 `cid` 而不是 `category_id`
3. ✅ **图标** - 使用 `lightning` 图标（Bootstrap Icons）
4. ✅ **插入位置** - 在"快捷导航"之后插入
5. ✅ **单页管理** - 添加单页管理入口
6. ✅ **菜单结构** - 移除子菜单图标，移除父菜单 route

修复后，快捷内容管理功能应该与原始实现一致，正常工作。

---

**修复时间**: 2026年1月19日  
**修复工具**: OpenCode AI  
**参考**: git commit 4d8b067313  
**相关文件**:
- app\Http\Controllers\Admin\AdminBaseController.php
- config/menu.php
- views/admin/layout.latte
