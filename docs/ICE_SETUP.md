# Mumble Ice 接口完整配置指南

本指南将帮助您完整配置 Mumble 服务器的 Ice 接口，以便与 SeAT Mumble Connector 集成。

## 目录
1. [系统要求](#系统要求)
2. [安装 PHP Ice 扩展](#安装-php-ice-扩展)
3. [配置 Mumble 服务器](#配置-mumble-服务器)
4. [配置 SeAT 连接器](#配置-seat-连接器)
5. [测试连接](#测试连接)
6. [故障排除](#故障排除)

## 系统要求

- PHP 7.4+ 或 8.0+
- Mumble 服务器 (Murmur) 1.3+
- ZeroC Ice 3.6+ 或 3.7+
- Linux/Unix 环境（推荐）

## 安装 PHP Ice 扩展

### Ubuntu/Debian 系统

```bash
# 方法 1: 使用包管理器（推荐）
sudo apt-get update
sudo apt-get install php-zeroc-ice

# 方法 2: 手动编译安装
sudo apt-get install libzeroc-ice-dev ice-slice build-essential
wget https://download.zeroc.com/Ice/3.7/php-ice-3.7.5.tar.gz
tar -xzf php-ice-3.7.5.tar.gz
cd php-ice-3.7.5
phpize
./configure
make
sudo make install
```

### CentOS/RHEL 系统

```bash
# 安装 EPEL 仓库
sudo yum install epel-release

# 安装 Ice
sudo yum install ice ice-php ice-devel

# 或者在较新版本的系统上
sudo dnf install ice ice-php ice-devel
```

### Docker 环境

创建 Dockerfile:

```dockerfile
FROM php:8.1-fpm

# 安装系统依赖
RUN apt-get update && apt-get install -y \
    libzeroc-ice-dev \
    ice-slice \
    build-essential \
    && rm -rf /var/lib/apt/lists/*

# 安装 Ice 扩展
RUN cd /tmp \
    && wget https://download.zeroc.com/Ice/3.7/php-ice-3.7.5.tar.gz \
    && tar -xzf php-ice-3.7.5.tar.gz \
    && cd php-ice-3.7.5 \
    && phpize \
    && ./configure \
    && make \
    && make install \
    && docker-php-ext-enable ice

# 或者使用包管理器（如果可用）
# RUN apt-get update && apt-get install -y php-zeroc-ice
```

### 验证安装

```bash
# 检查扩展是否加载
php -m | grep -i ice

# 检查扩展详情
php -r "phpinfo();" | grep -i ice

# 检查 Ice 版本
php -r "echo phpversion('ice');"
```

### 配置 PHP

在 `php.ini` 中添加（如果未自动添加）:

```ini
extension=ice.so

# 可选的 Ice 配置
log_errors = On
log_errors_max_len = 0
```

重启 PHP-FPM 和 Web 服务器:

```bash
sudo systemctl restart php8.1-fpm
sudo systemctl restart nginx  # 或 apache2
```

## 配置 Mumble 服务器

### 1. 编辑 Murmur 配置文件

通常位于 `/etc/murmur/murmur.ini` 或 `/etc/mumble-server.ini`:

```ini
# Ice 接口配置
ice="tcp -h 127.0.0.1 -p 6502"

# Ice 密钥（强烈推荐设置）
icesecretread=your_read_secret_here
icesecretwrite=your_write_secret_here

# 如果只需要一个密钥
icesecret=your_secret_here

# 其他相关配置
port=64738
users=100

# 启用注册功能
allowhtml=false
rememberchannelduration=31

# 数据库配置（SQLite 示例）
database=/var/lib/mumble-server/mumble-server.sqlite

# 日志配置
logfile=/var/log/mumble-server/mumble-server.log
```

### 2. 安全配置建议

```ini
# 限制 Ice 接口访问
ice="tcp -h 127.0.0.1 -p 6502"  # 仅本地访问

# 如果需要远程访问，指定具体 IP
# ice="tcp -h 192.168.1.100 -p 6502"

# 设置强密钥（至少 16 字符）
icesecret=your_very_strong_secret_key_here_123456

# 启用 SSL（如果需要）
# certrequired=false
# sslCert=/path/to/cert.pem
# sslKey=/path/to/key.pem
```

### 3. 防火墙配置

```bash
# UFW (Ubuntu)
sudo ufw allow 64738/tcp  # Mumble 服务端口
sudo ufw allow 64738/udp
sudo ufw allow from 127.0.0.1 to any port 6502  # Ice 端口仅本地

# iptables
sudo iptables -A INPUT -p tcp --dport 64738 -j ACCEPT
sudo iptables -A INPUT -p udp --dport 64738 -j ACCEPT
sudo iptables -A INPUT -s 127.0.0.1 -p tcp --dport 6502 -j ACCEPT
```

### 4. 重启 Mumble 服务器

```bash
sudo systemctl restart mumble-server
# 或
sudo service mumble-server restart

# 检查状态
sudo systemctl status mumble-server

# 查看日志
sudo journalctl -u mumble-server -f
```

## 配置 SeAT 连接器

### 1. 在 SeAT 管理界面配置

1. 登录 SeAT 管理界面
2. 导航到 "设置" > "连接器" > "Mumble"
3. 填写以下配置：

```
Mumble Server Host: 你的Mumble服务器IP
Mumble Server Port: 64738
Mumble Ice Host: 127.0.0.1
Mumble Ice Port: 6502
Mumble Ice Secret: your_secret_here
Mumble Ice Timeout: 10
Allow User Registration: Yes
```

### 2. 高级配置选项

```
# 连接超时设置
Connection Timeout: 10 seconds
Ice Timeout: 10 seconds

# 用户管理
Auto Create Users: Yes
Auto Assign Groups: Yes
Default Channel: Root

# 同步设置
Sync Interval: 300 seconds
Sync Online Users: Yes
Sync Channels: Yes
```

## 测试连接

### 1. 使用命令行工具测试

```bash
# 基本连接测试
php artisan mumble:test-ice

# 详细测试
php artisan mumble:test-ice --detailed

# 仅配置检查
php artisan mumble:test-ice --config-only

# 生成完整报告
php artisan mumble:test-ice --report
```

### 2. 测试 TCP 连接

```bash
# 测试 Ice 端口连接
telnet 127.0.0.1 6502

# 或使用 nc
nc -zv 127.0.0.1 6502

# 测试 Mumble 服务器端口
telnet your-mumble-server 64738
```

### 3. 检查 Mumble 服务器日志

```bash
# 查看实时日志
sudo tail -f /var/log/mumble-server/mumble-server.log

# 搜索 Ice 相关日志
sudo grep -i ice /var/log/mumble-server/mumble-server.log

# 检查错误
sudo grep -i error /var/log/mumble-server/mumble-server.log
```

## 故障排除

### 常见问题及解决方案

#### 1. PHP Ice 扩展未加载

**错误**: `PHP Ice extension is not loaded`

**解决方案**:
```bash
# 检查扩展是否安装
php -m | grep ice

# 如果未安装，重新安装
sudo apt-get install php-zeroc-ice

# 检查 php.ini 配置
php --ini

# 确保包含 extension=ice.so
echo "extension=ice.so" | sudo tee -a /etc/php/8.1/cli/php.ini
echo "extension=ice.so" | sudo tee -a /etc/php/8.1/fpm/php.ini

# 重启服务
sudo systemctl restart php8.1-fpm
```

#### 2. 连接被拒绝

**错误**: `Connection refused to 127.0.0.1:6502`

**解决方案**:
```bash
# 检查 Mumble 服务器是否运行
sudo systemctl status mumble-server

# 检查端口是否监听
sudo netstat -tulpn | grep 6502
# 或
sudo ss -tulpn | grep 6502

# 检查 Ice 配置
sudo grep -i ice /etc/murmur/murmur.ini

# 重启 Mumble 服务器
sudo systemctl restart mumble-server
```

#### 3. 认证失败

**错误**: `Ice authentication failed`

**解决方案**:
```bash
# 检查 Ice 密钥配置
sudo grep -i icesecret /etc/murmur/murmur.ini

# 确保 SeAT 配置中的密钥与 Mumble 配置匹配
# 如果使用读写分离密钥，使用写密钥
```

#### 4. 权限问题

**错误**: `Permission denied`

**解决方案**:
```bash
# 检查 Mumble 服务器运行用户
ps aux | grep mumble

# 检查配置文件权限
sudo ls -la /etc/murmur/murmur.ini

# 检查日志文件权限
sudo ls -la /var/log/mumble-server/

# 修复权限（如果需要）
sudo chown mumble-server:mumble-server /etc/murmur/murmur.ini
sudo chmod 640 /etc/murmur/murmur.ini
```

#### 5. SELinux 问题（CentOS/RHEL）

```bash
# 检查 SELinux 状态
getenforce

# 临时禁用 SELinux 测试
sudo setenforce 0

# 如果问题解决，配置 SELinux 策略
sudo setsebool -P httpd_can_network_connect 1

# 重新启用 SELinux
sudo setenforce 1
```

### 调试技巧

#### 1. 启用详细日志

在 `murmur.ini` 中添加:
```ini
# 启用调试日志
logfile=/var/log/mumble-server/mumble-server.log
logtargets=1

# Ice 调试（谨慎使用，会产生大量日志）
# icewarn=1
```

#### 2. 使用 Ice 工具测试

```bash
# 如果安装了 Ice 工具包
iceping tcp -h 127.0.0.1 -p 6502
```

#### 3. PHP 调试

创建测试脚本 `test_ice.php`:
```php
<?php
try {
    if (!extension_loaded('ice')) {
        die("Ice extension not loaded\n");
    }
    
    $properties = \Ice\createProperties();
    $properties->setProperty('Ice.Default.Timeout', '10000');
    
    $initData = new \Ice\InitializationData();
    $initData->properties = $properties;
    $communicator = \Ice\initialize($initData);
    
    $proxy = $communicator->stringToProxy('Meta:tcp -h 127.0.0.1 -p 6502');
    $meta = $proxy->ice_checkedCast('::Murmur::Meta');
    
    if ($meta) {
        echo "Successfully connected to Mumble Ice interface\n";
        $servers = $meta->getAllServers();
        echo "Found " . count($servers) . " servers\n";
    } else {
        echo "Failed to connect to Meta interface\n";
    }
    
    $communicator->destroy();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
```

运行测试:
```bash
php test_ice.php
```

## 生产环境建议

1. **安全性**:
   - 使用强密钥（至少 32 字符）
   - 限制 Ice 接口仅本地访问
   - 定期更换密钥
   - 启用防火墙规则

2. **性能**:
   - 设置合适的超时值
   - 监控连接数和内存使用
   - 定期重启服务（如需要）

3. **监控**:
   - 设置日志轮转
   - 监控服务可用性
   - 设置告警机制

4. **备份**:
   - 定期备份 Mumble 数据库
   - 备份配置文件
   - 制定恢复计划

## 相关资源

- [ZeroC Ice 官方文档](https://doc.zeroc.com/ice/3.7)
- [Mumble 官方文档](https://wiki.mumble.info/wiki/Main_Page)
- [Murmur Ice 接口文档](https://wiki.mumble.info/wiki/Ice)
- [PHP Ice 扩展文档](https://doc.zeroc.com/ice/3.7/language-mappings/php-mapping)