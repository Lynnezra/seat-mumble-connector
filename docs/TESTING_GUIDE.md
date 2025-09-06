# Mumble 显示名称功能测试指南

## 测试步骤

### 1. 配置验证

在 SeAT 管理界面中：

1. **检查 Connector Settings**：
   - 访问 **Connector** → **Settings**
   - 确认 **Use Ticker** 设置为 `Yes`
   - 确认 **Prefix Mask** 设置为 `[%2$s] %1$s`

### 2. 现有用户同步测试

```bash
# 1. 查看当前用户（干跑模式）
php artisan mumble:sync-display-names --dry-run

# 2. 实际同步用户显示名称
php artisan mumble:sync-display-names
```

预期结果：
- 应该看到用户的 connector_name 从原始用户名更新为 `[CORP] Character Name` 格式

### 3. 新用户注册测试

1. **注册新的 Mumble 用户**：
   - 访问 **Connector** → **Identities** → **Mumble** 选项卡
   - 填写：
     - Mumble Username: `testuser123`
     - Mumble Password: `password123`
     - Display Nickname: (可选，留空以测试自动格式化)

2. **检查数据库记录**：
   ```sql
   SELECT connector_name, nickname, user_id 
   FROM seat_connector_users 
   WHERE connector_type = 'mumble'
   ORDER BY id DESC LIMIT 1;
   ```

   预期结果：
   - `connector_name` 应该是 `[CORP] Character Name` 格式
   - `nickname` 是用户设置的自定义昵称（如果有）

### 4. Mumble 客户端测试

1. **使用原始用户名登录**：
   - 服务器：你的 Mumble 服务器地址
   - 用户名：`testuser123`（原始用户名）
   - 密码：`password123`

2. **检查显示名称**：
   - 登录后，在 Mumble 用户列表中应该显示为 `[CORP] Character Name`
   - 而不是原始的 `testuser123`

### 5. 昵称优先级测试

1. **设置自定义昵称**：
   - 在注册页面设置 Display Nickname 为 `MyCustomName`
   - 更新用户信息

2. **验证显示**：
   - 在 Mumble 中应该显示为 `MyCustomName`
   - 而不是格式化的名称

### 6. 军团变化测试

1. **角色军团变化**：
   - 在 SeAT 中，当用户的主角色变更军团时
   - 运行：`php artisan mumble:sync-display-names`

2. **验证更新**：
   - connector_name 应该更新为新的 `[NEW_CORP] Character Name`

## 预期行为总结

| 场景 | 登录用户名 | 自定义昵称 | Mumble 显示名称 |
|------|------------|--------------|------------------|
| 新注册用户（无昵称） | `testuser123` | 无 | `[CORP] Character Name` |
| 新注册用户（有昵称） | `testuser123` | `MyCustomName` | `[CORP] MyCustomName` |
| 军团变更后（无昵称） | `testuser123` | 无 | `[NEW_CORP] Character Name` |
| 军团变更后（有昵称） | `testuser123` | `MyCustomName` | `[NEW_CORP] MyCustomName` |
| 清除昵称后 | `testuser123` | 无 | `[CORP] Character Name` |

## 故障排除

### 显示名称未更新

1. **检查 ESI Token**：
   ```bash
   # 确保用户有有效的 ESI token
   php artisan esi:update:characters
   ```

2. **检查角色关联**：
   - 确保用户有主角色设置
   - 确保角色有军团关联信息

3. **手动触发同步**：
   ```bash
   php artisan mumble:sync-display-names
   ```

### 登录问题

1. **确认用户名**：
   - 始终使用原始的 Mumble 用户名登录
   - 不要使用格式化的显示名称登录

2. **检查 Mumble 服务器日志**：
   ```bash
   sudo journalctl -u mumble-server -f
   ```

### 权限问题

1. **检查 Ice 连接**：
   ```bash
   php artisan mumble:test-ice-connection
   ```

2. **检查配置**：
   ```bash
   php artisan mumble:manage-ice --status
   ```

## 日志监控

监控以下日志以获取调试信息：

1. **Laravel 日志**：
   ```bash
   tail -f storage/logs/laravel.log
   ```

2. **查找相关日志**：
   ```bash
   grep -i "mumble\|connector_name\|buildConnectorNickname" storage/logs/laravel.log
   ```

## 成功标准

✅ **成功实现时应该满足**：

1. 用户使用原始 Mumble 用户名登录
2. 在 Mumble 中显示为 `[Corporation Ticker]Character Name` 格式
3. 自定义昵称优先于格式化名称
4. 军团变化时显示名称自动更新
5. 同步命令可以批量更新现有用户