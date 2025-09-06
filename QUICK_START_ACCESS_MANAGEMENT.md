# Mumble Connector Access Management 快速开始

## 🎯 目标

将 Mumble 连接器与 SeAT 的 Access Management 系统集成，让您可以通过统一界面管理 Mumble 频道权限。

## ⚡ 快速设置 (5分钟)

### 1. 同步 Mumble 频道
```bash
php artisan seat-mumble-connector:sync-sets --force
```

### 2. 访问 Access Management
1. 登录 SeAT 管理界面
2. 导航到 **Connector → Access Management**
3. 在页面顶部选择 **Driver: Mumble**

### 3. 配置权限
为频道添加访问规则：
- 选择频道名称
- 选择实体类型 (Users/Roles/Corporations/Alliances等)
- 选择具体的实体
- 点击 **Add** 保存规则

### 4. 应用策略
```bash
php artisan seat-connector:apply:policies --driver=mumble
```

## 🔥 常用场景

### 军团专用频道
```bash
# 1. 同步频道
php artisan seat-mumble-connector:sync-sets

# 2. 在 Access Management 中:
#    - 选择频道: "Corp-Only-Channel"
#    - 实体类型: "Corporations"
#    - 选择您的军团

# 3. 应用权限
php artisan seat-connector:apply:policies --driver=mumble
```

### 公开大厅
```bash
# 在 Access Management 中:
# - 选择频道: "Lobby"
# - 实体类型: "Public"
# - 应用策略
```

### 指挥官频道
```bash
# 在 Access Management 中:
# - 选择频道: "Command"
# - 实体类型: "Roles"
# - 选择 "FC" 或 "Commander" 角色
```

## 🔧 测试和验证

```bash
# 测试所有用户权限
php artisan seat-mumble-connector:test-access-management

# 测试特定用户
php artisan seat-mumble-connector:test-access-management --user-id=123

# 显示所有频道
php artisan seat-mumble-connector:test-access-management --show-sets
```

## 🔄 自动化

系统已自动配置：
- ⏰ **每小时**: 自动应用权限策略
- 📅 **每天**: 自动同步频道列表

## ❓ 故障排除

### 频道未显示？
```bash
php artisan seat-mumble-connector:sync-sets --cleanup
```

### 权限未生效？
```bash
php artisan seat-connector:apply:policies --driver=mumble --sync
```

### 检查配置状态？
```bash
php artisan seat-mumble-connector:test-access-management
```

## 📚 更多信息

查看详细文档：`ACCESS_MANAGEMENT_GUIDE.md`

---

🎉 **恭喜！您现在可以通过 SeAT 的 Access Management 界面统一管理 Mumble 频道权限了！**