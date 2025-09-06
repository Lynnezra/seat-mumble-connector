# Mumble 权限管理完整指南

## 🎯 概述

Mumble 连接器现在提供了完整的权限管理系统，允许您：

- 指定特定用户为管理员
- 基于 SeAT 角色自动分配权限
- 基于军团职位分配权限
- 通过 Web 界面和命令行管理权限
- 自动同步权限到 Mumble 服务器

## 🔧 权限级别

### 管理员权限 (Admin)
拥有 Mumble 服务器的完全控制权：
- ✅ 踢出用户
- ✅ 封禁用户  
- ✅ 静音/取消静音用户
- ✅ 移动用户到任意频道
- ✅ 创建/删除频道
- ✅ 管理服务器设置
- ✅ 查看所有用户信息

### 版主权限 (Moderator)
适用于军团 CEO 和董事：
- ✅ 踢出用户
- ✅ 静音/取消静音用户
- ✅ 移动用户
- ✅ 创建临时频道
- ❌ 封禁用户
- ❌ 删除永久频道

### 普通用户权限 (User)
基本语音通信权限：
- ✅ 进入允许的频道
- ✅ 语音通话
- ✅ 文字消息
- ✅ 私聊
- ❌ 管理功能

## 📋 管理员分配方式

### 1. SeAT 超级管理员
拥有 `global.superuser` 权限的用户自动获得 Mumble 管理员权限。

### 2. 配置的管理员用户
通过以下方式手动指定管理员：

#### Web 界面方式
1. 访问 **Connector** → **Permissions** → **Mumble**
2. 在"添加管理员"框中输入：
   - 用户ID（如：123）
   - SeAT 用户名（如：JohnDoe）
   - EVE 角色名（如：John Doe）
3. 点击"添加"

#### 命令行方式
```bash
# 添加管理员
php artisan mumble:permissions add-admin --user="John Doe"

# 移除管理员
php artisan mumble:permissions remove-admin --user="John Doe"

# 查看当前配置
php artisan mumble:permissions list
```

### 3. SeAT 角色权限
创建以下 SeAT 角色可自动获得对应权限：

| SeAT 角色名 | Mumble 权限级别 |
|-------------|-----------------|
| `mumble_admin` | 管理员 |
| `voice_admin` | 管理员 |
| `mumble_moderator` | 版主 |
| `voice_moderator` | 版主 |
| `fleet_commander` | 版主（舰队频道） |

### 4. 军团职位权限
基于 EVE 军团职位自动分配权限：

| 军团职位 | Mumble 权限级别 |
|----------|-----------------|
| CEO | 版主 |
| Director（董事） | 版主 |
| Member | 普通用户 |

## 🚀 使用说明

### 步骤1：配置管理员

#### 方法A：使用 Web 界面
1. 登录 SeAT 管理界面
2. 访问 **Connector** → **Permissions** → **Mumble**
3. 添加管理员用户

#### 方法B：使用命令行
```bash
# 添加多个管理员
php artisan mumble:permissions add-admin --user="12345"     # 用户ID
php artisan mumble:permissions add-admin --user="AdminUser" # 用户名
php artisan mumble:permissions add-admin --user="John Doe"  # 角色名
```

### 步骤2：同步权限到 Mumble

#### Web 界面同步
1. 在权限管理页面点击"同步权限到 Mumble"
2. 等待同步完成

#### 命令行同步
```bash
# 预览同步操作
php artisan mumble:permissions sync --dry-run

# 实际执行同步
php artisan mumble:permissions sync
```

### 步骤3：验证权限

#### 检查特定用户权限
```bash
php artisan mumble:permissions show --user="John Doe"
```

#### 测试权限系统
```bash
php artisan mumble:permissions test
```

## 📊 权限管理界面

### 访问路径
- **URL**: `/seat-connector/permissions/mumble`
- **权限要求**: `global.superuser`

### 界面功能

#### 管理员管理区域
- **添加管理员**: 输入用户标识添加新管理员
- **管理员列表**: 显示当前所有管理员及其详情
- **移除管理员**: 一键移除管理员权限

#### 用户统计区域
- **总用户数**: 显示已注册的 Mumble 用户总数
- **管理员数量**: 当前配置的管理员数量
- **新增用户**: 最近7天新注册的用户数量

#### 权限配置区域
- **角色权限表**: 显示每个角色拥有的具体权限
- **权限说明**: 详细解释各权限级别

## 🔄 自动化机制

### 1. 用户注册时自动分配权限
新用户注册 Mumble 时，系统会自动：
- 检查用户的 SeAT 角色
- 检查军团职位
- 分配相应的权限级别

### 2. 角色变化时自动更新
当用户的以下信息发生变化时，系统会自动更新权限：
- SeAT 角色变化
- 军团职位变化
- 主角色变化

### 3. 定期权限同步
建议设置定期任务来同步权限：

```bash
# 添加到 crontab
0 2 * * * php artisan mumble:permissions sync
```

## 🛠️ 高级配置

### 自定义权限配置
可以通过配置文件自定义权限映射：

```php
// config/mumble-connector.php
'permission_mapping' => [
    'custom_role' => [
        'kick' => true,
        'mute' => true,
        'move' => false,
        'create_channel' => true,
    ],
    // ... 其他角色
],
```

### 批量管理员设置
可以通过环境变量预设管理员：

```env
MUMBLE_ADMIN_USERS="user1,user2,Character Name,12345"
```

## 📝 命令行参考

### 权限管理命令
```bash
# 查看所有可用操作
php artisan mumble:permissions

# 列出当前配置
php artisan mumble:permissions list

# 添加管理员
php artisan mumble:permissions add-admin --user="标识"

# 移除管理员  
php artisan mumble:permissions remove-admin --user="标识"

# 显示用户权限详情
php artisan mumble:permissions show --user="标识"

# 同步权限（预览模式）
php artisan mumble:permissions sync --dry-run

# 同步权限（实际执行）
php artisan mumble:permissions sync

# 测试权限系统
php artisan mumble:permissions test
```

## 🔍 故障排除

### 权限未生效
1. **检查 Ice 连接**：
   ```bash
   php artisan mumble:test-ice
   ```

2. **手动同步权限**：
   ```bash
   php artisan mumble:permissions sync
   ```

3. **检查用户权限**：
   ```bash
   php artisan mumble:permissions show --user="用户名"
   ```

### 管理员权限无法添加
1. **验证用户存在**：确保用户已在 SeAT 中注册
2. **检查用户标识**：确保输入的用户ID/用户名/角色名正确
3. **查看日志**：检查 Laravel 日志文件获取错误详情

### 同步失败
1. **检查 Mumble 服务器状态**：
   ```bash
   sudo systemctl status mumble-server
   ```

2. **验证 Ice 配置**：
   ```bash
   php artisan mumble:ice validate
   ```

3. **查看同步日志**：
   ```bash
   tail -f storage/logs/laravel.log | grep -i mumble
   ```

## 🎯 最佳实践

### 1. 权限分层管理
- **全局管理员**: 1-2个超级管理员
- **军团管理员**: CEO 和主要董事
- **临时管理员**: 活动期间的临时权限

### 2. 定期审核
- 每月检查管理员列表
- 移除离开军团的用户权限
- 更新权限配置

### 3. 安全考虑
- 仅将管理员权限授予可信用户
- 定期轮换管理员
- 监控权限使用日志

### 4. 文档记录
- 记录所有权限变更
- 维护管理员联系方式
- 建立权限申请流程

---

通过这套完整的权限管理系统，您可以轻松地为 Mumble 服务器配置和管理用户权限，确保语音通信的秩序和安全！