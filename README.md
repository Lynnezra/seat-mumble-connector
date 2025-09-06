# Seat Mumble Connector
# TEST
一个为 [SeAT](https://github.com/eveseat/seat) 设计的 Mumble 语音通信连接器插件。

## 🎯 功能特性

- **🎤 语音通信集成**：将 Mumble 语音服务器与 SeAT 用户管理系统整合
- **🏢 自动频道管理**：基于军团、联盟自动创建和管理频道
- **🔐 权限控制**：根据 SeAT 角色自动分配 Mumble 权限
- **👥 用户同步**：自动同步用户状态和权限
- **🌍 多语言支持**：支持中文和英文界面
- **⚙️ 灵活配置**：丰富的管理员配置选项

## 📋 系统要求

- SeAT 5.0+
- seat-connector 3.0+
- Mumble Server (支持 Ice 接口或 REST API)
- PHP 8.1+

## 🚀 安装

### 使用 Composer 安装

```bash
composer require lynnezra/seat-mumble-connector --no-dev
```

### 手动安装

1. 将插件文件放置到 SeAT 插件目录
2. 运行数据库迁移：
```bash
php artisan migrate --force
```

3. 清除缓存：
```bash
php artisan config:clear
php artisan cache:clear
```

## ⚙️ 配置

### 1. Mumble 服务器设置

在 SeAT 管理面板中配置 Mumble 连接器：

- **服务器主机**：Mumble 服务器的 IP 地址或域名
- **服务器端口**：Mumble 服务器端口（默认 64738）
- **管理员账户**：具有管理权限的 Mumble 账户
- **自动频道创建**：是否自动为军团/联盟创建频道
- **用户注册**：是否允许用户自行注册

### 2. Mumble 服务器配置

确保您的 Mumble 服务器启用了以下功能：

```ini
# murmur.ini 配置示例
ice="tcp -h 127.0.0.1 -p 6502"
icesecretwrite=your_ice_secret
allowhtml=true
registerhostname=your.mumble.server
registerpassword=registration_password
```

## 🎮 使用方法

### 用户注册

1. 用户登录 SeAT
2. 访问 "Connector" → "Identities" 页面
3. 点击 "Mumble" 选项卡
4. 填写 Mumble 用户名和密码
5. 系统自动在 Mumble 服务器创建账户

### 频道管理

系统将根据以下规则自动创建频道：

- **军团频道**：为每个军团创建专属频道
- **联盟频道**：为联盟创建公共频道
- **角色频道**：为特定 SeAT 角色创建频道
- **临时频道**：用户可创建临时频道

### 权限管理

权限基于 SeAT 角色自动分配：

- **超级管理员**：完全服务器控制权限
- **军团管理**：军团频道管理权限
- **联盟管理**：联盟频道管理权限
- **普通用户**：基本语音通信权限

## 🔧 高级配置

### 自定义频道模板

```php
// 在配置文件中定义频道结构
'channel_template' => [
    'alliance' => [
        'name' => '{alliance_name}',
        'description' => 'Alliance: {alliance_ticker}',
        'channels' => [
            'General',
            'Fleet Ops',
            'Leadership'
        ]
    ],
    'corporation' => [
        'name' => '{corp_name}',
        'description' => 'Corporation: {corp_ticker}',
        'channels' => [
            'General',
            'Industry',
            'PvP'
        ]
    ]
]
```

### 权限映射

```php
// 权限映射配置
'permission_mapping' => [
    'superuser' => ['admin', 'kick', 'ban', 'mute'],
    'corporation_ceo' => ['kick', 'mute'],
    'corporation_director' => ['mute'],
    'member' => ['speak', 'whisper']
]
```

## 🐛 故障排除

### 常见问题

**连接失败**
- 检查 Mumble 服务器是否运行
- 验证 Ice 接口配置
- 确认防火墙设置

**用户注册失败**
- 检查管理员账户权限
- 验证用户名格式
- 查看 SeAT 日志文件

**权限不同步**
- 重新运行权限同步任务
- 检查角色映射配置
- 验证 Mumble ACL 设置

### 日志位置

```bash
# SeAT 日志
storage/logs/laravel.log

# Mumble 服务器日志
/var/log/mumble-server/mumble-server.log
```

## 🤝 贡献

欢迎提交 Issue 和 Pull Request！

### 开发环境搭建

1. 克隆仓库
2. 安装依赖：`composer install`
3. 配置测试环境
4. 运行测试：`vendor/bin/phpunit`

## 📄 许可证

本项目基于 [GPL-3.0](LICENSE) 许可证开源。

## 🙏 致谢

- [SeAT 项目](https://github.com/eveseat/seat)
- [seat-connector](https://github.com/warlof/seat-connector)
- [Mumble 项目](https://www.mumble.info/)

## 📞 支持

- 创建 [Issue](https://github.com/Lynnezra/seat-mumble-connector/issues)
- 加入 [SeAT Slack](https://seat-slack.herokuapp.com/)
- 查看 [文档](https://github.com/Lynnezra/seat-mumble-connector/wiki)