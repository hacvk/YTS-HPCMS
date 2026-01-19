# 数据库修复和功能优化说明

## 当前问题

1. **登录时提示验证码错误** - 需要自动刷新验证码
2. **访问后台报错** - 数据库缺少 `role_id` 字段

## 解决方案

### 第一步：修复数据库

有两种方法修复数据库：

#### 方法1：使用 PHP 脚本（推荐）

```bash
php fix_database.php
```

**优点**：
- 自动检测已存在的字段
- 避免重复执行报错
- 有详细的执行日志

#### 方法2：执行 SQL 脚本

在数据库管理工具（如 phpMyAdmin、Navicat）中执行 `fix_db.sql` 文件。

**优点**：
- 可以查看和修改 SQL 语句
- 适合熟悉 SQL 的用户

### 第二步：测试登录

1. **清除浏览器缓存和 Cookie**
   - 删除 yycms.com 的所有 Cookie
   - 清除浏览器缓存

2. **访问登录页面**
   ```
   http://yycms.com/admin
   ```

3. **测试验证码刷新**
   - 点击验证码图片应立即刷新
   - 登录失败后应自动刷新验证码
   - 输入框会被清空

### 第三步：验证修复

访问后台页面：
```
http://yycms.com/admin/dashboard
```

应该正常显示后台首页，不再报错。

## 验证码自动刷新功能

### 功能说明

1. **点击刷新**：点击验证码图片立即刷新
2. **失败刷新**：登录失败后自动刷新验证码
3. **输入清空**：登录失败后清空验证码输入框

### 实现方式

使用 AJAX 提交登录表单，避免页面刷新：

```javascript
// 提交表单
fetch('/admin/login/login', {
    method: 'POST',
    body: formData
})
.then(response => response.json())
.then(data => {
    if (data.code !== 0) {
        // 登录失败：刷新验证码 + 清空输入框
        captchaImg.src = '/admin/login/captcha?rand=' + Date.now();
        document.getElementById('captcha').value = '';
        alert(data.message);
    } else {
        // 登录成功：跳转
        window.location.href = data.url;
    }
});
```

## 数据库结构说明

### 新增字段

**sys_admin 表**：
- `role_id` INT(11) - 角色ID，默认值 1
- `last_login_ip` VARCHAR(64) - 最后登录IP

**sys_log 表**：
- `admin_name` VARCHAR(50) - 管理员姓名

### 新增表

**sys_role 表**：
- `id` - 角色ID
- `name` - 角色名称
- `description` - 角色描述
- `permissions` - 权限列表（JSON格式）
- `status` - 状态
- `create_time` - 创建时间
- `update_time` - 更新时间

### 默认数据

- 角色1：超级管理员（拥有所有权限）
- 角色2：普通管理员（普通权限）

## 常见问题

### Q1: 执行 SQL 脚本时提示字段已存在

**原因**：字段可能已经添加过了。

**解决**：脚本会自动检查，如果字段已存在会跳过。使用 PHP 脚本更安全。

### Q2: 登录后仍然报错

**原因**：可能还有其他缺失的字段。

**解决**：检查错误信息，添加缺失的字段。或者查看 `update_database.sql` 文件，执行完整的数据库更新。

### Q3: 验证码不刷新

**原因**：JavaScript 可能未正确加载。

**解决**：
1. 检查浏览器控制台是否有错误
2. 清除浏览器缓存
3. 检查网络连接

### Q4: AJAX 请求失败

**原因**：可能是 CSRF Token 验证失败。

**解决**：
1. 确保表单中包含 CSRF Token
2. 检查 Session 是否正常工作
3. 查看浏览器网络面板的响应

## 后续优化建议

### 1. 完善权限系统

当前 `hasPerm()` 方法返回 `true`，需要添加 `role_id` 字段后完善：

```php
protected function hasPerm(string $perm): bool
{
    if (empty($perm)) {
        return true;
    }

    $admin = $this->admin;
    if (!$admin || !isset($admin['role_id'])) {
        return false;
    }

    $roleId = (int)$admin['role_id'];
    if ($roleId === 1) {
        // 超级管理员拥有所有权限
        return true;
    }

    $db = Database::get();
    $role = $db->get('sys_role', ['permissions'], ['id' => $roleId]);

    if (!$role || empty($role['permissions'])) {
        return false;
    }

    $permissions = json_decode($role['permissions'], true) ?? [];
    return in_array($perm, $permissions, true);
}
```

### 2. 添加权限管理界面

创建权限管理模块：
- 角色管理（增删改查）
- 权限分配
- 权限验证

### 3. 优化错误处理

添加更友好的错误提示：
- 网络错误提示
- 服务器错误提示
- 超时处理

## 执行步骤总结

1. ✅ 执行数据库修复脚本
2. ✅ 清除浏览器缓存和 Cookie
3. ✅ 访问登录页面测试
4. ✅ 测试验证码刷新功能
5. ✅ 测试登录功能
6. ✅ 访问后台页面验证

---

**修复时间**: 2026年1月19日  
**修复工具**: OpenCode AI  
**相关文件**:
- fix_database.php - 数据库修复脚本
- fix_db.sql - SQL 修复脚本
- views/admin/login.latte - 登录页面（添加验证码自动刷新）
- app\Http\Controllers\Admin\LoginController.php - 登录控制器
- app\Http\Controllers\Admin\AdminBaseController.php - 后台基类控制器
