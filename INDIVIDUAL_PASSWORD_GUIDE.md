# Mumble 个人用户密码配置指南

## 🎯 概述

此配置将 Mumble 服务器设置为**个人用户密码模式**，每个用户都有独立的账户和密码，而不是使用共享的服务器密码。

## 🔐 工作原理

### 传统模式 vs 个人密码模式

| 对比项 | 传统模式 | 个人密码模式 |
|--------|----------|--------------|
| **服务器密码** | 所有用户共享一个密码 | 无全局服务器密码 |
| **用户账户** | 可选，通常匿名连接 | 每个用户必须有账户 |
| **安全性** | 较低（密码泄露影响所有人） | 较高（每个用户独立） |
| **管理** | 简单但不够灵活 | 复杂但精细控制 |

## 🚀 部署步骤

### 1. 运行安装脚本

```bash
./install-mumble.sh
```

### 2. 配置 SeAT 连接器

在 SeAT 管理界面的 Mumble 连接器设置中填写：

| 设置项 | 值 | 说明 |
|--------|-----|------|
| **Mumble Server Host** | `192.168.1.33` | 服务器地址 |
| **Mumble Server Port** | `64738` | 服务器端口 |
| **Ice Interface Host** | `192.168.1.33` | Ice接口地址 |
| **Ice Interface Port** | `6502` | Ice接口端口 |
| **Ice Secret Key** | `写入密钥` | 从安装脚本输出获取 |
| **Admin Username** | `SuperUser` | 超级管理员用户名 |
| **Admin Password** | `生成的密码` | 从容器日志获取 |
| **Allow User Registration** | ✅ 启用 | 允许用户注册 |

### 3. 用户注册流程

#### 用户在 SeAT 中的操作：

1. **访问注册页面**
   - 登录 SeAT
   - 进入连接器 → Mumble 注册

2. **填写注册信息**
   ```
   Mumble 用户名: [用户选择的用户名]
   Mumble 密码: [用户设置的密码]
   显示昵称: [可选的显示名称]
   ```

3. **确认注册**
   - SeAT 通过 Ice 接口在 Mumble 服务器上创建账户
   - 用户获得独立的 Mumble 账户

#### 用户在 Mumble 客户端的操作：

1. **连接服务器**
   ```
   服务器地址: 192.168.1.33
   端口: 64738
   用户名: [在SeAT中注册的用户名]
   密码: [在SeAT中设置的密码]
   ```

2. **不需要服务器密码**
   - 连接时不会提示输入服务器密码
   - 直接使用个人账户认证

## 🔧 配置文件解析

### /opt/mumble/murmur.ini 关键配置

```ini
# 禁用全局服务器密码
# serverpassword=9zxc9a9z99a  # 注释掉这行

# 欢迎消息
welcometext="欢迎来到 SeAT Mumble 服务器！<br/>请使用您的个人账户登录。"

# Ice 接口配置（用于 SeAT 管理用户）
ice="tcp -h 0.0.0.0 -p 6502"
icesecretread=读取密钥
icesecretwrite=写入密钥

# 用户认证设置
certrequired=false         # 不强制证书认证
forceExternalAuth=false    # 不强制外部认证

# 允许用户注册
allowhtml=true
messagelength=5000
```

## 🎯 用户体验

### 对于管理员：
- ✅ 每个用户都有独立账户，便于管理
- ✅ 可以精确控制每个用户的权限
- ✅ 可以单独禁用或删除用户
- ✅ 完整的审计日志

### 对于用户：
- ✅ 拥有个人专属账户
- ✅ 可以自定义用户名和昵称
- ✅ 密码安全（不会因为其他人泄露而受影响）
- ✅ 与 SeAT 角色系统集成

## 🔍 故障排查

### 常见问题

#### 1. "服务器拒绝连接：密码错误"
**原因**: 用户尝试输入服务器密码
**解决**: 
- 确认用户已在 SeAT 中注册 Mumble 账户
- 使用注册时设置的用户名和密码
- 不要输入服务器密码（现在没有服务器密码）

#### 2. "找不到用户账户"
**原因**: 用户未在 SeAT 中注册
**解决**:
- 先在 SeAT 连接器页面注册 Mumble 账户
- 确认注册成功后再连接

#### 3. Ice 连接失败
**原因**: SeAT 无法连接到 Mumble 的 Ice 接口
**解决**:
- 检查 Ice 接口配置
- 确认端口 6502 开放
- 验证 Ice 密钥正确

## 📊 管理命令

### 查看服务状态
```bash
docker-compose -f docker-compose.mumble.yml -p mumble-server ps
```

### 查看日志
```bash
docker logs mumble-server
```

### 重启服务
```bash
docker-compose -f docker-compose.mumble.yml -p mumble-server restart
```

### 获取超级用户密码
```bash
docker logs mumble-server | grep "Password for 'SuperUser'"
```

## ⚠️ 注意事项

1. **首次部署**: 确保清理旧的服务器密码配置
2. **用户教育**: 告知用户新的连接方式
3. **备份**: 定期备份 Mumble 数据库
4. **更新**: 保持配置文件和容器镜像最新

## 🔄 从共享密码模式迁移

如果你之前使用共享服务器密码，需要：

1. **备份数据**
   ```bash
   docker cp mumble-server:/data ./mumble-backup
   ```

2. **更新配置**
   - 注释掉 `serverpassword` 行
   - 重启 Mumble 服务器

3. **通知用户**
   - 告知用户需要在 SeAT 中注册账户
   - 提供新的连接说明

4. **逐步迁移**
   - 可以暂时保留服务器密码，让用户逐步迁移
   - 完成迁移后再移除服务器密码