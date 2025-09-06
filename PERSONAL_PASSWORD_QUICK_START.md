# 🎯 Mumble 个人密码认证系统 - 快速开始指南

## 概述

您现在可以让用户使用个人密码登录 Mumble，无需服务器密码，且自动获得认证权限！

## ⚡ 快速设置 (5分钟)

### 1. 运行数据库迁移
```bash
php artisan migrate
```

### 2. 启用个人密码认证
1. 访问 **SeAT → Connector → Settings**
2. 找到 Mumble 驱动设置
3. 勾选 ✅ **"启用个人密码认证"**
4. 可选：设置 **"服务器密码"** 作为备用
5. 💾 **保存设置**

### 3. 为用户设置个人密码
```bash
# 交互式设置
php artisan seat-mumble-connector:manage-passwords set

# 或直接指定参数
php artisan seat-mumble-connector:manage-passwords set --username=JohnDoe --password=mypass123 --seat-user-id=42
```

## 🎮 用户体验

### 用户登录流程
1. **用户打开 Mumble 客户端**
2. **连接到服务器**
3. **输入个人密码** (不是服务器密码!)
4. **自动获得认证权限** ✅
5. **开始使用语音功能** 🎤

### 双重认证支持
- 🔑 **个人密码**: 用户独有，自动获得 auth 权限
- 🔐 **服务器密码**: 管理员备用，传统认证方式
- 两种密码都有效！

## 📋 管理命令

### 查看所有用户
```bash
php artisan seat-mumble-connector:manage-passwords list
```

### 测试用户认证
```bash
php artisan seat-mumble-connector:manage-passwords test --username=JohnDoe --password=mypass123
```

### 移除用户密码
```bash
php artisan seat-mumble-connector:manage-passwords remove --username=JohnDoe
```

## 🛡️ 安全特性

- ✅ **密码加密存储** (bcrypt)
- ✅ **双重认证支持**
- ✅ **认证日志记录**
- ✅ **权限自动分配**
- ✅ **Ice 接口安全通信**

## 🔧 故障排除

### 用户无法登录？
```bash
# 1. 检查功能是否启用
# 在 Connector → Settings 确认已勾选 "启用个人密码认证"

# 2. 检查用户密码是否设置
php artisan seat-mumble-connector:manage-passwords list

# 3. 测试密码
php artisan seat-mumble-connector:manage-passwords test --username=用户名 --password=密码
```

### Ice 连接问题？
```bash
php artisan seat-mumble-connector:test-ice-connection
```

## 💡 使用场景

### 🏢 公司/组织
- 员工使用工号作为用户名 + 个人密码
- 管理员保留服务器密码进行管理
- 新员工入职自动分配密码

### 🎮 游戏公会
- 成员使用游戏角色名 + 个人密码
- 公会领导使用服务器密码
- 权限通过 Access Management 精细控制

### 🎓 学校/培训机构
- 学生使用学号 + 个人密码
- 老师使用服务器密码
- 按班级/课程分配频道权限

## 🎉 完成！

现在您的用户可以：
- ✅ 使用个人密码登录
- ✅ 自动获得认证权限
- ✅ 不需要知道服务器密码
- ✅ 享受便捷的语音体验

服务器密码仍然有效，为管理员提供备用访问！

---

📖 **详细文档**: 查看 `PERSONAL_PASSWORD_GUIDE.md`
🔧 **Access Management**: 查看 `ACCESS_MANAGEMENT_GUIDE.md`