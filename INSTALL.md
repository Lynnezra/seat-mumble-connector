# SeAT Mumble Connector 安装指南

## 🚀 快速安装

### 1. 安装插件

```bash
# 在 SeAT 根目录执行
composer require lynnezra/seat-mumble-connector --no-dev
```

### 2. 运行数据库迁移

```bash
php artisan migrate --force
```

### 3. 清除缓存

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

### 4. 重启 SeAT 服务

如果使用 Docker：
```bash
docker-compose restart
```

如果使用 systemd：
```bash
sudo systemctl restart seat-worker
sudo systemctl restart seat-web
```

## ⚙️ 配置插件

1. 登录 SeAT 管理面板
2. 导航到 **Configuration** → **Settings** → **Connectors**
3. 找到 **Mumble** 配置部分
4. 填写以下信息：

   **基本设置：**
   - **Mumble Server Host**: 你的 Mumble 服务器地址 (例如: `mumble.yoursite.com`)
   - **Mumble Server Port**: Mumble 服务器端口 (默认: `64738`)
   
   **Ice 接口设置（推荐，用于完整功能）：**
   - **Ice Interface Host**: Ice 接口地址 (例如: `127.0.0.1`)
   - **Ice Interface Port**: Ice 接口端口 (默认: `6502`)
   - **Ice Secret Key**: Ice 接口密钥（与 murmur.ini 中的 icesecretwrite 一致）
   
   **其他设置：**
   - **Allow User Registration**: 是否允许用户自行注册
   - **Auto Create Channels**: 是否自动为军团/联盟创建频道

5. 点击 **Save** 保存配置

### Ice 接口优势

启用 Ice 接口后，插件可以：
- ✅ 直接在 Mumble 服务器上创建用户
- ✅ 实时管理用户权限和频道
- ✅ 自动同步用户状态
- ✅ 支持踢人、禁言等管理功能

如果不配置 Ice 接口，插件将：
- ⚠️ 只在 SeAT 数据库中记录用户
- ⚠️ 需要手动在 Mumble 服务器上创建用户
- ⚠️ 功能受限

## 🎯 用户使用

### 注册 Mumble 账户

1. 用户登录 SeAT
2. 访问 **Connector** → **Identities** 页面
3. 点击 **Mumble** 标签页
4. 填写：
   - **Mumble Username**: Mumble 用户名
   - **Mumble Password**: 密码（仅首次注册时需要）
   - **Display Nickname**: 可选的显示昵称
5. 点击 **Register** 或 **Update**

### 连接到 Mumble 服务器

用户可以使用以下信息连接到 Mumble 服务器：

- **服务器地址**: 在设置中配置的 Mumble 服务器地址
- **端口**: 在设置中配置的端口（默认 64738）
- **用户名**: 在 SeAT 中注册的用户名
- **密码**: 在 SeAT 中设置的密码

## 🔧 Mumble 服务器配置

### 基本配置 (murmur.ini)

```ini
# 服务器端口
port=64738

# 服务器名称
welcometext="Welcome to EVE Alliance Mumble Server<br/>Please use your SeAT credentials to connect."

# 注册功能
registerName="EVE Alliance Mumble"
registerUrl=https://your-seat-url.com
registerHostname=mumble.yoursite.com

# Ice 接口（用于高级集成）
ice="tcp -h 127.0.0.1 -p 6502"
icesecretwrite=your_ice_secret

# 证书配置
sslCert=/path/to/cert.pem
sslKey=/path/to/key.pem
```

### 安装 PHP Ice 扩展

**Ubuntu/Debian:**
```bash
sudo apt-get install php-zeroc-ice
```

**CentOS/RHEL:**
```bash
sudo yum install ice-php
```

**Docker 环境:**
```dockerfile
RUN apt-get install -y php-zeroc-ice
```

详细配置请参考：[Ice 接口配置指南](docs/MUMBLE_ICE_CONFIG.md)

### Docker Compose 示例

```yaml
version: '3.8'
services:
  mumble:
    image: mumblevoip/mumble-server:latest
    ports:
      - "64738:64738"
      - "64738:64738/udp"
    environment:
      - MUMBLE_CONFIG_WELCOMETEXT=Welcome to EVE Alliance Mumble
      - MUMBLE_CONFIG_REGISTERNAME=EVE Alliance Mumble
    volumes:
      - ./mumble-data:/data
    restart: unless-stopped
```

## 🐛 故障排除

### 常见问题

**1. 用户无法注册**
- 检查数据库迁移是否正确执行
- 查看 SeAT 日志文件：`storage/logs/laravel.log`
- 确认插件已正确安装

**2. 连接设置失败**
- 验证 Mumble 服务器是否运行
- 检查网络连接和防火墙设置
- 确认服务器地址和端口正确

**3. 权限问题**
- 检查 SeAT 用户角色配置
- 验证 seat-connector 基础插件是否安装

### 查看日志

```bash
# SeAT 应用日志
tail -f storage/logs/laravel.log

# 如果使用 Docker
docker-compose logs -f seat-web
docker-compose logs -f seat-worker
```

### 手动测试连接

在 SeAT 管理面板中：
1. 导航到 **Connectors** → **Settings**
2. 找到 Mumble 部分
3. 点击 **Test Connection** 按钮

## 🚀 高级配置

### 自定义频道结构

编辑 `mumble-connector.config.php` 文件自定义频道模板：

```php
'channel_templates' => [
    'alliance' => [
        'name_format' => '[{alliance_ticker}] {alliance_name}',
        'sub_channels' => ['General', 'Fleet Ops', 'Leadership']
    ],
    'corporation' => [
        'name_format' => '[{corp_ticker}] {corp_name}',
        'sub_channels' => ['General', 'Industry', 'PvP']
    ]
]
```

### 权限映射

```php
'permission_mapping' => [
    'superuser' => ['admin', 'kick', 'ban', 'mute'],
    'corporation_ceo' => ['kick', 'mute'],
    'member' => ['speak', 'whisper']
]
```

## 📞 获得帮助

- [GitHub Issues](https://github.com/Lynnezra/seat-mumble-connector/issues)
- [SeAT Slack](https://seat-slack.herokuapp.com/)
- [SeAT 官方文档](https://eveseat.github.io/docs/)