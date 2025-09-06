#!/bin/bash

# seat-mumble-connector Ice 扩展安装脚本
# 支持多种 Linux 发行版的自动安装

set -e

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 日志函数
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# 检查是否为 root 用户
check_root() {
    if [[ $EUID -eq 0 ]]; then
        log_warning "Running as root. This is not recommended for security reasons."
        read -p "Continue anyway? (y/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            exit 1
        fi
    fi
}

# 检测操作系统
detect_os() {
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        OS=$NAME
        VER=$VERSION_ID
    elif type lsb_release >/dev/null 2>&1; then
        OS=$(lsb_release -si)
        VER=$(lsb_release -sr)
    elif [ -f /etc/redhat-release ]; then
        OS="CentOS"
        VER=$(cat /etc/redhat-release | grep -oE '[0-9]+\.[0-9]+')
    else
        OS=$(uname -s)
        VER=$(uname -r)
    fi
    
    log_info "Detected OS: $OS $VER"
}

# 检测 PHP 版本
detect_php() {
    if ! command -v php &> /dev/null; then
        log_error "PHP is not installed. Please install PHP first."
        exit 1
    fi
    
    PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
    log_info "Detected PHP version: $PHP_VERSION"
}

# 检查 Ice 扩展
check_ice_extension() {
    if php -m | grep -q ice; then
        ICE_VERSION=$(php -r "echo phpversion('ice');")
        log_success "Ice extension is already installed (Version: $ICE_VERSION)"
        return 0
    else
        log_info "Ice extension is not installed"
        return 1
    fi
}

# Ubuntu/Debian 安装
install_ubuntu() {
    log_info "Installing Ice extension on Ubuntu/Debian..."
    
    # 更新包列表
    sudo apt-get update
    
    # 尝试使用包管理器安装
    if sudo apt-get install -y php-zeroc-ice; then
        log_success "Ice extension installed via package manager"
        return 0
    fi
    
    log_warning "Package manager installation failed, trying manual compilation..."
    
    # 安装编译依赖
    sudo apt-get install -y \
        libzeroc-ice-dev \
        ice-slice \
        build-essential \
        php-dev \
        wget
    
    # 手动编译安装
    cd /tmp
    wget https://download.zeroc.com/Ice/3.7/php-ice-3.7.5.tar.gz
    tar -xzf php-ice-3.7.5.tar.gz
    cd php-ice-3.7.5
    phpize
    ./configure
    make
    sudo make install
    
    # 启用扩展
    echo "extension=ice.so" | sudo tee -a /etc/php/$PHP_VERSION/cli/php.ini
    echo "extension=ice.so" | sudo tee -a /etc/php/$PHP_VERSION/fpm/php.ini
    
    # 清理
    rm -rf /tmp/php-ice-3.7.5*
    
    log_success "Ice extension compiled and installed"
}

# CentOS/RHEL 安装
install_centos() {
    log_info "Installing Ice extension on CentOS/RHEL..."
    
    # 安装 EPEL 仓库
    sudo yum install -y epel-release
    
    # 安装 Ice
    if sudo yum install -y ice ice-php ice-devel; then
        log_success "Ice extension installed via yum"
        return 0
    fi
    
    # 如果失败，尝试 dnf（较新系统）
    if command -v dnf &> /dev/null; then
        if sudo dnf install -y ice ice-php ice-devel; then
            log_success "Ice extension installed via dnf"
            return 0
        fi
    fi
    
    log_error "Failed to install Ice extension on CentOS/RHEL"
    return 1
}

# Fedora 安装
install_fedora() {
    log_info "Installing Ice extension on Fedora..."
    
    sudo dnf install -y ice ice-php ice-devel
    log_success "Ice extension installed on Fedora"
}

# 重启 PHP 服务
restart_php_services() {
    log_info "Restarting PHP services..."
    
    # 重启 PHP-FPM
    if sudo systemctl restart php$PHP_VERSION-fpm 2>/dev/null; then
        log_success "PHP-FPM restarted"
    elif sudo systemctl restart php-fpm 2>/dev/null; then
        log_success "PHP-FPM restarted"
    else
        log_warning "Could not restart PHP-FPM automatically"
    fi
    
    # 重启 Web 服务器
    if sudo systemctl restart nginx 2>/dev/null; then
        log_success "Nginx restarted"
    elif sudo systemctl restart apache2 2>/dev/null; then
        log_success "Apache restarted"
    elif sudo systemctl restart httpd 2>/dev/null; then
        log_success "Apache restarted"
    else
        log_warning "Could not restart web server automatically"
    fi
}

# 验证安装
verify_installation() {
    log_info "Verifying installation..."
    
    if check_ice_extension; then
        log_success "✅ Ice extension is working correctly!"
        
        # 运行 SeAT 测试命令（如果可用）
        if command -v php artisan &> /dev/null; then
            if php artisan mumble:install-ice --check 2>/dev/null; then
                log_success "✅ SeAT Mumble connector can detect Ice extension"
            fi
        fi
        
        return 0
    else
        log_error "❌ Ice extension verification failed"
        log_info "You may need to:"
        log_info "1. Restart your web server manually"
        log_info "2. Check PHP configuration files"
        log_info "3. Verify extension=ice.so is in php.ini"
        return 1
    fi
}

# 主函数
main() {
    echo "================================================"
    echo "  SeAT Mumble Connector - Ice Extension Installer"
    echo "================================================"
    echo
    
    check_root
    detect_os
    detect_php
    
    # 检查是否已安装
    if check_ice_extension; then
        log_info "Ice extension is already available. Nothing to do."
        exit 0
    fi
    
    # 根据操作系统选择安装方法
    case "$OS" in
        "Ubuntu"*|"Debian"*)
            install_ubuntu
            ;;
        "CentOS"*|"Red Hat"*)
            install_centos
            ;;
        "Fedora"*)
            install_fedora
            ;;
        *)
            log_error "Unsupported operating system: $OS"
            log_info "Please install Ice extension manually:"
            log_info "1. Install ZeroC Ice development package"
            log_info "2. Install PHP development headers"
            log_info "3. Compile Ice PHP extension"
            log_info "4. Add extension=ice.so to php.ini"
            exit 1
            ;;
    esac
    
    restart_php_services
    verify_installation
    
    echo
    log_success "🎉 Installation completed!"
    log_info "You can now use the full Mumble Ice integration features."
    log_info "Run 'php artisan mumble:test-ice' to test the connection."
}

# 运行主函数
main "$@"