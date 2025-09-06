# Ice 扩展自动安装指南

本插件提供了多种方式来简化 Ice 扩展的安装过程。

## 🚀 快速安装

### 方法1: 使用安装助手命令

```bash
# 检查当前状态
php artisan mumble:install-ice --check

# 显示安装指南
php artisan mumble:install-ice --guide

# 尝试自动安装（需要 sudo 权限）
php artisan mumble:install-ice --auto
```

### 方法2: 使用安装脚本

```bash
# 下载并运行安装脚本
wget https://raw.githubusercontent.com/Lynnezra/seat-mumble-connector/main/scripts/install-ice.sh
chmod +x install-ice.sh
./install-ice.sh
```

### 方法3: 使用 Docker

```bash
# 使用预配置的 Docker 环境
cd seat-mumble-connector/docker
docker-compose up -d

# 或者构建自定义镜像
docker build -f docker/Dockerfile.ice -t seat-mumble-ice .
```

## 📋 支持的系统

| 操作系统 | 自动安装 | 手动安装 | Docker |
|----------|----------|----------|--------|
| Ubuntu 18.04+ | ✅ | ✅ | ✅ |
| Debian 10+ | ✅ | ✅ | ✅ |
| CentOS 7+ | ✅ | ✅ | ✅ |
| RHEL 7+ | ✅ | ✅ | ✅ |
| Fedora 30+ | ✅ | ✅ | ✅ |
| Windows | ❌ | ⚠️ | ✅ |
| macOS | ❌ | ⚠️ | ✅ |

## 🔧 安装选项详解

### 智能安装助手

安装助手会：
1. 自动检测操作系统和 PHP 版本
2. 检查必要的编译工具
3. 提供针对性的安装指导
4. 尝试自动安装（如果支持）
5. 验证安装结果

**使用示例：**

```bash
# 完整检查和指导
php artisan mumble:install-ice --guide

# 输出示例：
=== Mumble Ice Extension Installation Helper ===

1. Checking current status...
   PHP Version: 8.1.12
   ❌ Ice Extension: Not loaded
   Operating System: Ubuntu
   Package Manager: APT (Debian/Ubuntu)
   Build Tools Available: gcc, make, phpize

2. Quick Installation Guide:
   Ubuntu/Debian Installation:
   
   # Method 1: Using package manager (recommended)
   sudo apt-get update
   sudo apt-get install php-zeroc-ice
   
   # Method 2: Manual compilation
   sudo apt-get install libzeroc-ice-dev ice-slice build-essential
   sudo apt-get install php-dev
   ...

3. After installation, run: php artisan mumble:install-ice --check
```

### 自动安装脚本

安装脚本特性：
- 支持多种 Linux 发行版
- 智能检测包管理器
- 自动处理依赖
- 回退到手动编译
- 自动重启服务
- 完整的错误处理

**使用示例：**

```bash
./install-ice.sh

# 输出示例：
================================================
  SeAT Mumble Connector - Ice Extension Installer
================================================

[INFO] Detected OS: Ubuntu 22.04
[INFO] Detected PHP version: 8.1
[INFO] Ice extension is not installed
[INFO] Installing Ice extension on Ubuntu/Debian...
[SUCCESS] Ice extension installed via package manager
[INFO] Restarting PHP services...
[SUCCESS] PHP-FPM restarted
[SUCCESS] Nginx restarted
[INFO] Verifying installation...
[SUCCESS] ✅ Ice extension is working correctly!
🎉 Installation completed!
```

### Docker 解决方案

如果系统安装困难，可以使用 Docker：

```bash
# 使用完整的开发环境
cd seat-mumble-connector/docker
docker-compose up -d

# 或者只构建 Ice 支持的 PHP 容器
docker build -f docker/Dockerfile.ice -t my-seat-ice .

# 运行容器
docker run -it --rm \
  -v $(pwd):/var/www/html \
  my-seat-ice \
  php artisan mumble:test-ice
```

## 🎯 无 Ice 扩展时的功能

即使没有安装 Ice 扩展，插件仍然可以工作：

### ✅ 可用功能：
- 用户绑定管理
- 权限组分配
- 基本的 SeAT 集成
- 用户界面
- 配置管理

### ❌ 不可用功能：
- 真实的 Mumble 服务器用户创建
- 在线用户状态同步
- 频道管理
- 实时权限更新
- 用户踢出等管理操作

### 🔄 智能回退策略

插件会按以下顺序尝试连接：

1. **Ice 接口**（最完整的功能）
   - 需要 Ice 扩展
   - 提供所有功能

2. **REST API**（部分功能）
   - 如果 Mumble 服务器提供了自定义 API
   - 功能有限但无需 Ice 扩展

3. **简单记录模式**（基本功能）
   - 仅在 SeAT 数据库中记录
   - 不需要任何额外扩展

## 🔍 故障排除

### 常见问题

1. **安装权限问题**
   ```bash
   # 确保有 sudo 权限
   sudo -v
   
   # 或者使用 Docker
   docker run --rm -it php:8.1-cli bash
   ```

2. **编译失败**
   ```bash
   # 安装所有必要的编译工具
   sudo apt-get install build-essential php-dev libzeroc-ice-dev
   ```

3. **扩展未加载**
   ```bash
   # 检查 php.ini 配置
   php --ini
   
   # 手动添加扩展
   echo "extension=ice.so" | sudo tee -a /etc/php/8.1/cli/php.ini
   ```

### 验证安装

```bash
# 方法1: 检查扩展
php -m | grep ice

# 方法2: 使用插件命令
php artisan mumble:install-ice --check

# 方法3: 测试连接
php artisan mumble:test-ice
```

## 📚 更多资源

- [ZeroC Ice 官方文档](https://doc.zeroc.com/ice/3.7)
- [Mumble Ice 接口文档](https://wiki.mumble.info/wiki/Ice)
- [插件完整文档](docs/ICE_SETUP.md)

## 💡 建议

1. **生产环境**: 使用包管理器安装
2. **开发环境**: 可以使用 Docker
3. **测试环境**: 可以不安装 Ice 扩展，使用简单模式

记住：即使没有 Ice 扩展，插件的核心功能仍然可用！