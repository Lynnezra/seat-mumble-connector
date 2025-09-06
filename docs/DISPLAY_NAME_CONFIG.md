# Mumble 用户显示名称配置指南

## 概述

现在 Mumble 连接器支持显示格式化的用户名，包含军团标识：

- **未填写 Display Nickname**：显示为 `[Corporation Ticker]Character Name`
- **填写了 Display Nickname**：显示为 `[Corporation Ticker]Custom Nickname`

## 配置步骤

### 1. 启用 Ticker 功能

1. 登录 SeAT 管理界面
2. 导航到 **Connector** → **Settings**
3. 在 **Common** 部分找到以下设置：

   **Use Ticker**: 选择 `Yes`
   
   **Prefix Mask**: 设置为 `[%2$s] %1$s`
   
   其中：
   - `%1$s` = 角色名称
   - `%2$s` = 军团标识
   - `%3$s` = 联盟标识（如果需要）

4. 点击 **Save** 保存设置

### 2. 格式化选项

你可以自定义显示格式：

- `[%2$s] %1$s` → `[CORP] Character Name`
- `%1$s [%2$s]` → `Character Name [CORP]`
- `[%3$s][%2$s] %1$s` → `[ALLIANCE][CORP] Character Name`
- `%1$s (%2$s)` → `Character Name (CORP)`

### 3. 同步现有用户

如果你已经有注册的 Mumble 用户，需要同步他们的显示名称：

```bash
# 查看会被更改的用户（不实际更改）
php artisan mumble:sync-display-names --dry-run

# 实际同步所有用户的显示名称
php artisan mumble:sync-display-names
```

### 4. 用户体验

**注册新用户：**
- 用户在 SeAT 中注册 Mumble 账户时使用他们的 Mumble 用户名
- 可以选择填写自定义 Display Nickname
- 在 Mumble 中显示的是格式化的名称（包含军团标识）

**登录：**
- 用户仍然使用他们的原始 Mumble 用户名登录
- 登录后在 Mumble 中显示格式化的名称

**显示名称优先级：**
- 如果设置了 Display Nickname：显示 `[CORP] Custom Nickname`
- 如果没有设置：显示 `[CORP] Character Name`

**更新显示名称：**
- 当用户的军团或联盟变化时，显示名称会自动更新
- 用户也可以在注册页面修改自定义 Display Nickname

## 注意事项

1. **登录用户名 vs 显示名称**：
   - 登录用仍然使用原始的 Mumble 用户名
   - 显示名称是格式化的名称（包含军团标识）

2. **自动更新**：
   - 当用户的军团或角色信息变化时，系统会自动更新显示名称
   - 建议定期运行同步命令以确保所有用户的显示名称都是最新的

3. **自定义昵称优先级**：
   - 如果用户设置了自定义 Display Nickname，将显示 `[CORP] Custom Nickname`
   - 如果没有设置，则显示 `[CORP] Character Name`

## 故障排除

### 显示名称没有更新

1. 检查 SeAT Connector 设置是否正确启用了 ticker
2. 运行同步命令：`php artisan mumble:sync-display-names`
3. 检查用户是否有有效的角色信息和军团关联

### 格式显示不正确

1. 检查 **Prefix Mask** 设置是否正确
2. 确保用户的 ESI token 有效
3. 确认角色的军团信息已同步到 SeAT

### 命令执行错误

1. 确保有足够的权限执行 artisan 命令
2. 检查数据库连接是否正常
3. 查看 Laravel 日志文件以获取详细错误信息

## 示例配置

### 标准军团格式
```
Use Ticker: Yes
Prefix Mask: [%2$s] %1$s
结果（无自定义昵称）: [CORP] John Doe
结果（有自定义昵称）: [CORP] MyNickname
```

### 联盟+军团格式
```
Use Ticker: Yes  
Prefix Mask: [%3$s][%2$s] %1$s
结果（无自定义昵称）: [ALLIANCE][CORP] John Doe
结果（有自定义昵称）: [ALLIANCE][CORP] MyNickname
```

### 简单括号格式
```
Use Ticker: Yes
Prefix Mask: %1$s (%2$s)
结果（无自定义昵称）: John Doe (CORP)
结果（有自定义昵称）: MyNickname (CORP)
```