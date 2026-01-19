# 侧边栏展开/闭合逻辑修复报告

## 问题描述

侧边栏链接组的展开与闭合逻辑与 git 2efc898b95 有出入，导致菜单展开逻辑不正确。

## 原始实现（git 2efc898b95）

### 1. 字段名
```php
// 使用 should_expand 字段
$item['should_expand'] = true;  // 展开
$item['should_expand'] = false; // 闭合
```

### 2. 匹配逻辑（完整版）

```php
// 完全匹配优先级最高
if ($currentUri === $child['route']) {
    $exactMatch = true;
    break;
}

// 参数精确匹配
if ($currentPath === $childPath && !empty($childParams)) {
    $paramsMatch = true;
    foreach ($childParams as $key => $value) {
        if (!isset($currentParams[$key]) || $currentParams[$key] != $value) {
            $paramsMatch = false;
            break;
        }
    }
    
    if ($paramsMatch) {
        $routeParamsMatch = true;
        break;
    }
}

// 基础路径匹配（用于编辑/添加页面）
if (!$routeParamsMatch && $currentPath !== $childPath) {
    $baseCurrentPath = preg_replace('/\/(add|edit|save|delete)$/', '', $currentPath);
    if ($baseCurrentPath === $childPath && !empty($childParams)) {
        $paramsMatch = true;
        foreach ($childParams as $key => $value) {
            if (!isset($currentParams[$key]) || $currentParams[$key] != $value) {
                $paramsMatch = false;
                break;
            }
        }
        
        if ($paramsMatch) {
            $routeParamsMatch = true;
            break;
        }
    }
}

// 前缀匹配
if (!$routeParamsMatch && str_starts_with($currentUri, $child['route'])) {
    $partialMatch = true;
}
```

### 3. 快捷菜单优先级

```php
// 如果当前是快捷菜单而之前的不是，则更新（快捷菜单优先级更高）
if ($isCurrentShortcut && !$isPreviousShortcut) {
    $targetIndex = $index;
}
```

### 4. 匹配优先级比较

```php
// 确定匹配优先级：完全匹配 > 路由参数匹配 > 前缀匹配
$currentPriority = $exactMatch ? 3 : ($routeParamsMatch ? 2 : 1);

// 获取之前目标的匹配类型
$prevPriority = $prevExactMatch ? 3 : ($prevRouteMatch ? 2 : 1);

// 如果当前匹配优先级更高，则更新
if ($currentPriority > $prevPriority) {
    $targetIndex = $index;
}
```

### 5. 应用状态

```php
// 第二步：应用状态
foreach ($menu as $index => &$item) {
    if ($index === $targetIndex) {
        $item['should_expand'] = true;
    } else {
        $item['should_expand'] = false;
    }
}
```

## 当前实现的问题

### 问题1：字段名不一致
```php
// 当前使用 is_open
$menu[$targetIndex]['is_open'] = true;

// 模板期望 should_expand
{if isset($item['should_expand']) && $item['should_expand']}
```

### 问题2：匹配逻辑简化
```php
// 当前实现（简化版）
if ($childPath === $currentPath && $childParams === $currentParams) {
    $routeParamsMatch = true;
    continue;
}
```

**问题**：使用 `===` 比较数组，可能导致意外结果。

### 问题3：缺少快捷菜单优先级
```php
// 当前实现
if ($exactMatch || $routeParamsMatch || $partialMatch) {
    $targetIndex = $index;
    break;  // ❌ 直接跳出，没有比较优先级
}
```

### 问题4：缺少基础路径匹配
```php
// 当前实现缺少
$baseCurrentPath = preg_replace('/\/(add|edit|save|delete)$/', '', $currentPath);
```

## 修复方案

### 修复1：使用正确的字段名

```php
// 修改前
$menu[$targetIndex]['is_open'] = true;

// 修改后
$menu[$targetIndex]['should_expand'] = true;
```

### 修复2：完善匹配逻辑

```php
// 参数精确匹配（逐个检查）
if ($currentPath === $childPath && !empty($childParams)) {
    $paramsMatch = true;
    foreach ($childParams as $key => $value) {
        if (!isset($currentParams[$key]) || $currentParams[$key] != $value) {
            $paramsMatch = false;
            break;
        }
    }
    
    if ($paramsMatch) {
        $routeParamsMatch = true;
        break;
    }
}
```

### 修复3：添加快捷菜单优先级

```php
// 如果当前是快捷菜单而之前的不是，则更新
if ($isCurrentShortcut && !$isPreviousShortcut) {
    $targetIndex = $index;
}
// 如果都不是快捷菜单或都是快捷菜单，比较匹配优先级
elseif (!$isCurrentShortcut && !$isPreviousShortcut) {
    // 比较优先级
    if ($currentPriority > $prevPriority) {
        $targetIndex = $index;
    }
}
```

### 修复4：添加基础路径匹配

```php
// 特殊处理：如果当前路径是编辑/添加路径，尝试匹配基础路径
if (!$routeParamsMatch && $currentPath !== $childPath) {
    $baseCurrentPath = preg_replace('/\/(add|edit|save|delete)$/', '', $currentPath);
    if ($baseCurrentPath === $childPath && !empty($childParams)) {
        $paramsMatch = true;
        foreach ($childParams as $key => $value) {
            if (!isset($currentParams[$key]) || $currentParams[$key] != $value) {
                $paramsMatch = false;
                break;
            }
        }
        
        if ($paramsMatch) {
            $routeParamsMatch = true;
            break;
        }
    }
}
```

## 匹配优先级说明

### 优先级顺序（从高到低）

1. **完全匹配** (优先级 3)
   ```
   当前 URI: /admin/article?cid=5
   菜单路由: /admin/article?cid=5
   ```

2. **路由参数匹配** (优先级 2)
   ```
   当前 URI: /admin/article/add?cid=5
   菜单路由: /admin/article?cid=5
   路径相同，参数相同
   ```

3. **前缀匹配** (优先级 1)
   ```
   当前 URI: /admin/article/edit/123
   菜单路由: /admin/article
   路径前缀匹配
   ```

### 快捷菜单优先级

快捷菜单的优先级高于普通菜单：
```
如果当前匹配是快捷菜单，而之前匹配的是普通菜单 → 更新为快捷菜单
如果当前匹配是普通菜单，而之前匹配的是快捷菜单 → 保持快捷菜单
```

## 测试场景

### 场景1：完全匹配
```
当前页面: /admin/dashboard
预期: "控制台"菜单展开
```

### 场景2：路由参数匹配
```
当前页面: /admin/article?cid=5
预期: "快捷内容管理"菜单展开，显示 "栏目名称" 高亮
```

### 场景3：基础路径匹配
```
当前页面: /admin/article/add?cid=5
预期: "快捷内容管理"菜单展开，"栏目名称" 高亮
```

### 场景4：前缀匹配
```
当前页面: /admin/category/edit/3
预期: "内容管理"菜单展开，"栏目管理" 高亮
```

### 场景5：快捷菜单优先级
```
当前页面: /admin/article?cid=5
菜单结构:
- 快捷导航（子菜单：缓存管理、联系方式）
- 快捷内容管理（子菜单：栏目名称）
- 内容管理（子菜单：栏目管理、文章管理）

预期: "快捷内容管理"菜单展开（而不是"内容管理"）
```

## 修复效果对比

### 修复前

| 功能 | 状态 |
|------|------|
| 字段名 | ❌ 使用 `is_open` |
| 参数匹配 | ❌ 使用 `===` 比较数组 |
| 快捷菜单优先级 | ❌ 无 |
| 基础路径匹配 | ❌ 无 |
| 优先级比较 | ❌ 直接跳出 |

### 修复后

| 功能 | 状态 |
|------|------|
| 字段名 | ✅ 使用 `should_expand` |
| 参数匹配 | ✅ 逐个检查参数 |
| 快捷菜单优先级 | ✅ 有 |
| 基础路径匹配 | ✅ 有 |
| 优先级比较 | ✅ 完整比较 |

## 代码示例

### 完整的 processMenu 方法

```php
private function processMenu(array $menu, string $currentUri): array
{
    // 如果是首页，确保控制台菜单项被标记为active
    $isDashboard = ($currentUri === '/admin/dashboard' || $currentUri === '/admin' || $currentUri === '/admin/');
    
    foreach ($menu as &$item) {
        if (isset($item['route']) && $item['route'] === '/admin/dashboard' && $isDashboard) {
            $item['is_active'] = true;
        }
    }
    unset($item);
    
    // 第一步：寻找最佳匹配项（Best Match）
    $targetIndex = null;
    foreach ($menu as $index => $item) {
        if (isset($item['children']) && is_array($item['children'])) {
            $exactMatch = false;
            $routeParamsMatch = false;
            $partialMatch = false;
            
            $currentPath = parse_url($currentUri, PHP_URL_PATH) ?: '';
            $currentQuery = parse_url($currentUri, PHP_URL_QUERY) ?: '';
            parse_str($currentQuery, $currentParams);
            
            foreach ($item['children'] as $child) {
                if (isset($child['route'])) {
                    // 完全匹配
                    if ($currentUri === $child['route']) {
                        $exactMatch = true;
                        break;
                    }
                    
                    $childPath = parse_url($child['route'], PHP_URL_PATH) ?: '';
                    $childQuery = parse_url($child['route'], PHP_URL_QUERY) ?: '';
                    parse_str($childQuery, $childParams);
                    
                    // 参数精确匹配
                    if ($currentPath === $childPath && !empty($childParams)) {
                        $paramsMatch = true;
                        foreach ($childParams as $key => $value) {
                            if (!isset($currentParams[$key]) || $currentParams[$key] != $value) {
                                $paramsMatch = false;
                                break;
                            }
                        }
                        
                        if ($paramsMatch) {
                            $routeParamsMatch = true;
                            break;
                        }
                    }
                    
                    // 基础路径匹配（用于编辑/添加页面）
                    if (!$routeParamsMatch && $currentPath !== $childPath) {
                        $baseCurrentPath = preg_replace('/\/(add|edit|save|delete)$/', '', $currentPath);
                        if ($baseCurrentPath === $childPath && !empty($childParams)) {
                            $paramsMatch = true;
                            foreach ($childParams as $key => $value) {
                                if (!isset($currentParams[$key]) || $currentParams[$key] != $value) {
                                    $paramsMatch = false;
                                    break;
                                }
                            }
                            
                            if ($paramsMatch) {
                                $routeParamsMatch = true;
                                break;
                            }
                        }
                    }
                    
                    // 前缀匹配
                    if (!$routeParamsMatch && str_starts_with($currentUri, $child['route'])) {
                        $partialMatch = true;
                    }
                }
            }
            
            if ($exactMatch || $routeParamsMatch || $partialMatch) {
                $currentPriority = $exactMatch ? 3 : ($routeParamsMatch ? 2 : 1);
                
                if ($targetIndex === null) {
                    $targetIndex = $index;
                } else {
                    $isCurrentShortcut = isset($item['is_shortcut']) && $item['is_shortcut'];
                    $isPreviousShortcut = isset($menu[$targetIndex]['is_shortcut']) && $menu[$targetIndex]['is_shortcut'];
                    
                    if ($isCurrentShortcut && !$isPreviousShortcut) {
                        $targetIndex = $index;
                    } elseif (!$isCurrentShortcut && !$isPreviousShortcut) {
                        // 比较匹配优先级
                        $prevExactMatch = false;
                        $prevRouteMatch = false;
                        
                        foreach ($menu[$targetIndex]['children'] as $prevChild) {
                            if (isset($prevChild['route'])) {
                                if ($currentUri === $prevChild['route']) {
                                    $prevExactMatch = true;
                                    break;
                                }
                                
                                $prevPath = parse_url($prevChild['route'], PHP_URL_PATH) ?: '';
                                $prevQuery = parse_url($prevChild['route'], PHP_URL_QUERY) ?: '';
                                parse_str($prevQuery, $prevParams);
                                
                                if ($currentPath === $prevPath && !empty($prevParams)) {
                                    $paramsMatch = true;
                                    foreach ($prevParams as $key => $value) {
                                        if (!isset($currentParams[$key]) || $currentParams[$key] != $value) {
                                            $paramsMatch = false;
                                            break;
                                        }
                                    }
                                    
                                    if ($paramsMatch) {
                                        $prevRouteMatch = true;
                                        break;
                                    }
                                }
                                
                                if (!$prevRouteMatch && $currentPath !== $prevPath) {
                                    $baseCurrentPath = preg_replace('/\/(add|edit|save|delete)$/', '', $currentPath);
                                    if ($baseCurrentPath === $prevPath && !empty($prevParams)) {
                                        $paramsMatch = true;
                                        foreach ($prevParams as $key => $value) {
                                            if (!isset($currentParams[$key]) || $currentParams[$key] != $value) {
                                                $paramsMatch = false;
                                                break;
                                            }
                                        }
                                        
                                        if ($paramsMatch) {
                                            $prevRouteMatch = true;
                                            break;
                                        }
                                    }
                                }
                                
                                if (!$prevRouteMatch && str_starts_with($currentUri, $prevChild['route'])) {
                                    $prevPartialMatch = true;
                                }
                            }
                        }
                        
                        $prevPriority = $prevExactMatch ? 3 : ($prevRouteMatch ? 2 : 1);
                        
                        if ($currentPriority > $prevPriority) {
                            $targetIndex = $index;
                        }
                    }
                }
            }
        }
    }
    
    // 第二步：应用状态
    foreach ($menu as $index => &$item) {
        if ($index === $targetIndex) {
            $item['should_expand'] = true;
        } else {
            $item['should_expand'] = false;
        }
    }
    unset($item);
    
    return $menu;
}
```

## 测试步骤

1. **访问不同页面测试展开逻辑**

| 测试页面 | 预期展开的菜单 | 预期高亮的子菜单 |
|----------|---------------|-----------------|
| `/admin/dashboard` | 控制台 | - |
| `/admin/article?cid=5` | 快捷内容管理 | 栏目名称（cid=5） |
| `/admin/article/add?cid=5` | 快捷内容管理 | 栏目名称（cid=5） |
| `/admin/category` | 内容管理 | 栏目管理 |
| `/admin/category/edit/3` | 内容管理 | 栏目管理 |
| `/admin/cache` | 快捷导航 | 缓存管理 |

2. **检查快捷菜单优先级**
   - 访问 `/admin/article?cid=5`
   - 应该展开"快捷内容管理"而不是"内容管理"

3. **检查箭头方向**
   - 展开的菜单应该显示向上箭头（bi-chevron-up）
   - 闭合的菜单应该显示向下箭头（bi-chevron-down）

## 后续建议

### 1. 添加调试模式

```php
private function processMenu(array $menu, string $currentUri): array
{
    $debug = App::isDebug();
    
    if ($debug) {
        error_log("Processing menu for URI: {$currentUri}");
    }
    
    // ... 处理逻辑
    
    if ($debug && $targetIndex !== null) {
        error_log("Expanded menu index: {$targetIndex}, title: " . $menu[$targetIndex]['title']);
    }
    
    return $menu;
}
```

### 2. 添加菜单缓存

```php
private function getMenu(): array
{
    $cacheKey = 'admin_menu_' . $this->getAdminId();
    $menu = Cache::get($cacheKey);
    
    if ($menu === null) {
        $menu = $this->loadMenu();
        Cache::set($cacheKey, $menu, 3600);
    }
    
    return $menu;
}
```

### 3. 优化匹配逻辑

可以考虑使用更灵活的路由匹配规则：

```php
// 配置路由匹配规则
$matchRules = [
    '/admin/article' => ['cid'],  // 匹配路径和参数
    '/admin/category' => [],      // 只匹配路径
];
```

## 总结

通过恢复 git 2efc898b95 的实现，修复了以下问题：

1. ✅ **字段名** - 使用 `should_expand` 而不是 `is_open`
2. ✅ **参数匹配** - 逐个检查参数，而不是使用 `===`
3. ✅ **快捷菜单优先级** - 快捷菜单优先级更高
4. ✅ **基础路径匹配** - 支持编辑/添加页面匹配
5. ✅ **优先级比较** - 完整的优先级比较逻辑

修复后，侧边栏菜单的展开/闭合逻辑应该与原始实现一致。

---

**修复时间**: 2026年1月19日  
**修复工具**: OpenCode AI  
**参考**: git commit 2efc898b95  
**相关文件**:
- app\Http\Controllers\Admin\AdminBaseController.php
- views/admin/layout.latte
