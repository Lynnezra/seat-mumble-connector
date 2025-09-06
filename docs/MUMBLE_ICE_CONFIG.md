# Mumble 服务器 Ice 接口配置

## murmur.ini 配置示例

```ini
# 基本服务器配置
welcometext="<br />Welcome to the EVE Alliance Mumble Server.<br />Please use your SeAT credentials to connect.<br />"
port=64738
host=0.0.0.0
bandwidth=130000
users=100

# Ice 接口配置
ice="tcp -h 127.0.0.1 -p 6502"
icesecretwrite=your_ice_secret_here

# 注册配置
registerName="EVE Alliance Mumble"
registerPassword=""
registerUrl="https://your-seat-domain.com"
registerHostname="mumble.your-domain.com"

# 数据库配置
database=/var/lib/mumble-server/mumble-server.sqlite

# SSL/TLS 配置
sslCert=""
sslKey=""
sslPassPhrase=""
sslCiphers=""

# 日志配置
logfile=mumble-server.log
pidfile=/var/run/mumble-server/mumble-server.pid

# 用户限制
opusthreshold=100
channelnestinglimit=10
channelcountlimit=1000
```

## Docker Compose 配置

```yaml
version: '3.8'

services:
  mumble-server:
    image: mumblevoip/mumble-server:latest
    container_name: mumble-server
    restart: unless-stopped
    ports:
      - "64738:64738"
      - "64738:64738/udp"
      - "6502:6502"  # Ice 接口端口
    environment:
      # 基本配置
      MUMBLE_CONFIG_WELCOMETEXT: "Welcome to EVE Alliance Mumble Server"
      MUMBLE_CONFIG_PORT: 64738
      MUMBLE_CONFIG_USERS: 100
      
      # Ice 接口配置
      MUMBLE_CONFIG_ICE: "tcp -h 0.0.0.0 -p 6502"
      MUMBLE_CONFIG_ICESECRETWRITE: "your_ice_secret_here"
      
      # 注册配置
      MUMBLE_CONFIG_REGISTERNAME: "EVE Alliance Mumble"
      MUMBLE_CONFIG_REGISTERURL: "https://your-seat-domain.com"
      MUMBLE_CONFIG_REGISTERHOSTNAME: "mumble.your-domain.com"
    
    volumes:
      - mumble_data:/data
      - ./mumble-config:/etc/mumble
    
    networks:
      - seat_network

volumes:
  mumble_data:

networks:
  seat_network:
    external: true
```

## 防火墙配置

```bash
# UFW 配置
sudo ufw allow 64738/tcp
sudo ufw allow 64738/udp
sudo ufw allow 6502/tcp  # Ice 接口

# iptables 配置
sudo iptables -A INPUT -p tcp --dport 64738 -j ACCEPT
sudo iptables -A INPUT -p udp --dport 64738 -j ACCEPT
sudo iptables -A INPUT -p tcp --dport 6502 -j ACCEPT
```

## 安全注意事项

1. **Ice Secret**: 确保设置强密码
2. **网络访问**: 限制 Ice 接口只能从 SeAT 服务器访问
3. **SSL/TLS**: 生产环境建议启用 SSL
4. **防火墙**: 只开放必要的端口

## 测试连接

### 使用 PHP 脚本测试

```php
<?php
// test_ice_connection.php

try {
    // 初始化 Ice
    $ic = Ice_initialize();
    
    // 连接到 Meta 服务
    $base = $ic->stringToProxy("Meta:tcp -h 127.0.0.1 -p 6502");
    $meta = $base->ice_checkedCast("::Murmur::Meta");
    
    if ($meta) {
        echo "✅ Successfully connected to Mumble Ice interface\n";
        
        // 获取服务器列表
        $servers = $meta->getAllServers();
        echo "📋 Found " . count($servers) . " server(s)\n";
        
        foreach ($servers as $server) {
            echo "🖥️  Server ID: " . $server->id() . "\n";
        }
    } else {
        echo "❌ Failed to connect to Mumble Ice interface\n";
    }
    
    $ic->destroy();
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
```

运行测试：
```bash
php test_ice_connection.php
```