# Mumble Connector Access Management 集成指南

## 概述

Mumble 连接器现在已完全集成到 SeAT 的 Access Management 系统中。这意味着您可以通过 SeAT 的统一界面管理 Mumble 频道的访问权限，而不需要使用独立的权限管理系统。

## 主要变更

### ✅ 新功能
- **统一权限管理**: 通过 SeAT Connector → Access Management 界面管理所有 Mumble 频道权限
- **自动频道同步**: 自动将 Mumble 服务器的频道同步到 Access Management 系统
- **基于角色的访问控制**: 支持基于用户、角色、军团、联盟、头衔和小队的权限分配
- **策略自动应用**: 集成到 seat-connector 的自动策略应用系统

### ❌ 移除的功能
- 独立的权限管理界面 (之前的 `/permissions` 路由)
- 独立的权限管理命令 (`seat-mumble-connector:manage-permissions`)

## 设置步骤

### 1. 同步 Mumble 频道到 Access Management

首先，您需要将 Mumble 服务器上的频道同步到 SeAT 的 Access Management 系统：

```bash
# 同步所有频道
php artisan seat-mumble-connector:sync-sets

# 同步频道并清理孤立记录
php artisan seat-mumble-connector:sync-sets --cleanup

# 强制同步（不询问确认）
php artisan seat-mumble-connector:sync-sets --force
```

### 2. 配置访问权限

访问 **SeAT → Connector → Access Management** 页面：

1. **选择驱动程序**: 在页面顶部选择 "Mumble"
2. **选择频道**: 从列表中选择要配置的 Mumble 频道
3. **添加访问规则**: 选择实体类型和具体实体来授予访问权限

#### 支持的实体类型：
- **Public**: 所有用户都可以访问
- **Users**: 特定的 SeAT 用户
- **Roles**: SeAT 角色
- **Corporations**: EVE 军团
- **Alliances**: EVE 联盟
- **Titles**: EVE 军团头衔
- **Squads**: SeAT 小队

### 3. 应用权限策略

权限配置完成后，需要应用策略到 Mumble 服务器：

```bash
# 应用所有驱动程序的策略
php artisan seat-connector:apply:policies

# 仅应用 Mumble 的策略
php artisan seat-connector:apply:policies --driver=mumble

# 立即同步执行（调试用）
php artisan seat-connector:apply:policies --driver=mumble --sync
```

## 管理命令

### 频道同步
```bash
# 同步频道到 Access Management
php artisan seat-mumble-connector:sync-sets [--cleanup] [--force]
```

### 权限测试
```bash
# 测试 Access Management 集成
php artisan seat-mumble-connector:test-access-management

# 显示所有可用的 Sets
php artisan seat-mumble-connector:test-access-management --show-sets

# 测试特定用户的权限
php artisan seat-mumble-connector:test-access-management --user-id=123

# 仅显示结果，不执行操作
php artisan seat-mumble-connector:test-access-management --dry-run
```

### 策略应用
```bash
# 使用 seat-connector 的标准命令
php artisan seat-connector:apply:policies --driver=mumble
php artisan seat-connector:sync:sets --driver=mumble
```

## 自动化

### 定时任务

seat-connector 自动配置了以下定时任务：

- **每小时**: 应用权限策略 (`seat-connector:apply:policies`)
- **每天**: 同步 Sets (`seat-connector:sync:sets`)

如需更频繁的 Mumble 频道同步，可以在 `app/Console/Kernel.php` 中添加：

```php
// 每10分钟同步 Mumble 频道
$schedule->command('seat-mumble-connector:sync-sets --force')
         ->everyTenMinutes()
         ->withoutOverlapping();
```

### 事件监听

系统会自动监听以下事件并更新用户权限：
- 用户角色变更
- 军团变更
- ESI 令牌更新

## 示例场景

### 场景1: 军团频道访问

为军团 "Brave Newbies Inc." 创建专用频道访问：

1. 在 Mumble 服务器上创建频道 "Brave-Only"
2. 运行 `php artisan seat-mumble-connector:sync-sets` 同步频道
3. 在 Access Management 中：
   - 选择 "Brave-Only" 频道
   - 添加访问规则：实体类型 "Corporations"，选择 "Brave Newbies Inc."
4. 运行 `php artisan seat-connector:apply:policies --driver=mumble` 应用权限

### 场景2: 基于角色的访问

为 SeAT 中的 "FC" 角色用户开放指挥频道：

1. 确保 Mumble 中有 "Command" 频道
2. 在 Access Management 中：
   - 选择 "Command" 频道
   - 添加访问规则：实体类型 "Roles"，选择 "FC" 角色
3. 应用策略

### 场景3: 公开频道

设置大厅频道为所有人可访问：

1. 在 Access Management 中选择 "Lobby" 频道
2. 添加访问规则：实体类型 "Public"
3. 应用策略

## 故障排除

### 问题1: 频道未显示在 Access Management 中

**解决方案**:
```bash
php artisan seat-mumble-connector:sync-sets --cleanup
```

### 问题2: 用户权限未生效

**解决方案**:
1. 检查用户的角色和军团分配
2. 验证访问规则配置
3. 强制应用策略：
```bash
php artisan seat-connector:apply:policies --driver=mumble --sync
```

### 问题3: 测试权限配置

**解决方案**:
```bash
# 检查特定用户的权限
php artisan seat-mumble-connector:test-access-management --user-id=USER_ID

# 检查所有用户的权限状态
php artisan seat-mumble-connector:test-access-management
```

## 迁移说明

如果您之前使用了独立的权限管理系统，请注意：

1. **备份现有配置**: 在升级前备份任何自定义权限配置
2. **重新配置权限**: 使用新的 Access Management 界面重新配置权限
3. **测试验证**: 使用测试命令验证新配置是否正确工作
4. **移除旧文件**: 可以安全删除之前的权限管理相关文件

## 技术细节

### 数据模型

- **seat_connector_sets**: 存储 Mumble 频道信息
- **seat_connector_set_entity**: 存储访问规则关联
- **seat_connector_users**: 存储用户连接器信息

### API 集成

Mumble 连接器实现了以下 seat-connector 接口：
- `IClient`: 客户端管理
- `IUser`: 用户管理
- `ISet`: 频道/组管理

### 权限流程

1. 用户登录 → 检查 ESI 令牌
2. 获取用户属性 → 角色、军团、联盟等
3. 查询 Access Management 规则 → 确定允许的频道
4. 应用权限到 Mumble 服务器 → 通过 Ice 接口

---

通过这个集成，您现在可以享受统一、强大且灵活的 Mumble 权限管理体验！