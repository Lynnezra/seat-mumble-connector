# Mumble 个人密码认证系统指南

## 🎯 功能概述

这个系统允许用户使用个人密码登录 Mumble 服务器，而不需要知道服务器密码。用户使用个人密码登录后会自动获得认证权限。

### ✨ 主要特性

- **个人密码登录** - 每个用户都有自己的独立密码
- **自动认证权限** - 使用个人密码登录后自动获得 auth 状态
- **服务器密码仍然有效** - 管理员仍可使用服务器密码
- **双重认证支持** - 支持个人密码和服务器密码两种方式
- **灵活配置** - 可以启用/禁用个人密码认证功能

## 🔧 配置设置

### 1. 启用个人密码认证

在 SeAT 管理界面中：

1. 访问 **Connector → Settings**
2. 找到 Mumble 驱动设置
3. 勾选 **"启用个人密码认证"** (Enable personal password authentication)
4. 可选：设置 **"服务器密码"** 作为备用认证方式
5. 保存设置

### 2. 运行数据库迁移

```bash
php artisan migrate
```

## 👥 用户密码管理

### 设置用户密码

```bash
# 交互式设置密码
php artisan seat-mumble-connector:manage-passwords set

# 直接指定参数
php artisan seat-mumble-connector:manage-passwords set --username=JohnDoe --password=mypassword123 --seat-user-id=42
```

### 查看所有用户

```bash
php artisan seat-mumble-connector:manage-passwords list
```

### 移除用户密码

```bash
# 交互式移除
php artisan seat-mumble-connector:manage-passwords remove

# 直接指定用户名
php artisan seat-mumble-connector:manage-passwords remove --username=JohnDoe
```

### 测试用户认证

```bash
# 测试特定用户的密码
php artisan seat-mumble-connector:manage-passwords test --username=JohnDoe --password=mypassword123
```

## 🔐 认证流程

### 用户登录过程

1. **用户输入密码**
   - 在 Mumble 客户端中输入个人密码
   
2. **系统验证密码**
   - 首先检查是否为服务器密码
   - 然后检查是否为个人密码
   
3. **自动权限分配**
   - 如果是个人密码：自动设置为已认证状态
   - 如果是服务器密码：按标准流程处理

### 认证类型

- **服务器密码** (`server_password`) - 传统的服务器密码认证
- **个人密码** (`personal_password`) - 新的个人密码认证，自动获得 auth 权限
- **错误** (`error`) - 认证过程中发生错误

## 🎮 使用场景

### 场景1: 普通用户登录

1. 用户在 Mumble 客户端中连接服务器
2. 输入自己的个人密码
3. 系统自动验证并授予认证权限
4. 用户可以正常使用语音功能

### 场景2: 管理员登录

1. 管理员可以使用服务器密码登录
2. 也可以设置个人密码使用个人密码登录
3. 两种方式都有效

### 场景3: 批量用户管理

```bash
# 为所有已注册的 Mumble 用户设置默认密码
# 注意：实际使用中应该让用户自己设置密码

# 查看当前用户
php artisan seat-mumble-connector:manage-passwords list

# 为特定用户设置密码
php artisan seat-mumble-connector:manage-passwords set --username=User1 --password=temppass123 --seat-user-id=1
```

## 🔧 管理员操作

### 查看认证日志

系统会记录所有认证尝试，可以在 Laravel 日志中查看：

```bash
tail -f storage/logs/laravel.log | grep "Authentication"
```

### 重置用户密码

```bash
# 移除旧密码
php artisan seat-mumble-connector:manage-passwords remove --username=JohnDoe

# 设置新密码
php artisan seat-mumble-connector:manage-passwords set --username=JohnDoe --password=newpassword123 --seat-user-id=42
```

### 禁用个人密码认证

如果需要暂时禁用个人密码认证：

1. 访问 Connector → Settings
2. 取消勾选 **"启用个人密码认证"**
3. 保存设置

系统会自动回退到标准的服务器密码认证模式。

## 🛡️ 安全考虑

### 密码安全

- 用户密码使用 bcrypt 加密存储
- 建议用户使用强密码
- 支持密码定期更换

### 服务器安全

- 服务器密码仍然有效，作为备用认证方式
- Ice 接口通信加密
- 认证日志记录所有尝试

### 权限控制

- 个人密码登录自动获得基本认证权限
- 频道权限仍通过 Access Management 控制
- 管理员权限独立管理

## 🔧 故障排除

### 问题1: 用户无法使用个人密码登录

**解决方案**:
```bash
# 检查功能是否启用
# 在 Connector → Settings 中确认 "启用个人密码认证" 已勾选

# 检查用户是否有设置密码
php artisan seat-mumble-connector:manage-passwords list

# 测试密码
php artisan seat-mumble-connector:manage-passwords test --username=用户名 --password=密码
```

### 问题2: 认证后没有获得权限

**解决方案**:
```bash
# 检查 Ice 连接
php artisan seat-mumble-connector:test-ice-connection

# 检查用户权限
php artisan seat-mumble-connector:test-access-management --user-id=用户ID
```

### 问题3: 数据库错误

**解决方案**:
```bash
# 运行数据库迁移
php artisan migrate

# 检查表是否存在
# 应该有 mumble_user_auth 表
```

## 📊 数据库结构

### mumble_user_auth 表

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| seat_user_id | bigint | SeAT 用户ID |
| mumble_username | varchar(64) | Mumble 用户名 |
| password_hash | varchar(255) | 密码哈希 |
| enabled | boolean | 是否启用 |
| auth_status | enum | 认证状态 |
| last_login | timestamp | 最后登录时间 |
| notes | text | 备注 |

## 🎉 完成！

通过这个系统，您的用户现在可以：

1. ✅ 使用个人密码登录 Mumble
2. ✅ 自动获得认证权限
3. ✅ 不需要知道服务器密码
4. ✅ 享受安全且便捷的语音通信体验

服务器密码仍然存在并有效，为管理员提供了备用访问方式。这样既保证了安全性，又提升了用户体验！