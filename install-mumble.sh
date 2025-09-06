#!/bin/bash

# Mumble 服务器安装和配置脚本 - 支持个人用户密码
# 使用方法: ./install-mumble.sh
# 配置文件位置: /opt/mumble/murmur.ini

echo "🎵 开始安装 Mumble 服务器（个人用户密码模式）..."
echo "ℹ️ 使用官方 mumblevoip/mumble-server:latest 镜像"
echo "🔐 配置为每个用户使用独立密码（不使用服务器全局密码）"

# 检查 Docker 是否安装
if ! command -v docker &> /dev/null; then
    echo "❌ Docker 未安装，请先安装 Docker"
    exit 1
fi

if ! command -v docker-compose &> /dev/null; then
    echo "❌ Docker Compose 未安装，请先安装 Docker Compose"
    exit 1
fi

# 创建必要的目录
echo "📁 创建配置目录..."
sudo mkdir -p /opt/mumble
sudo mkdir -p mumble-data
sudo mkdir -p ssl-certs

# 检查并复制配置文件
echo "📝 检查配置文件..."
if [ ! -f /opt/mumble/murmur.ini ]; then
    echo "⚠️ /opt/mumble/murmur.ini 不存在，从模板复制..."
    if [ -f mumble-config/murmur.ini ]; then
        sudo cp mumble-config/murmur.ini /opt/mumble/murmur.ini
        echo "✅ 配置文件已复制到 /opt/mumble/murmur.ini"
    else
        echo "❌ 找不到模板配置文件 mumble-config/murmur.ini"
        echo "请确保在项目目录中运行此脚本"
        exit 1
    fi
else
    echo "✅ 配置文件 /opt/mumble/murmur.ini 已存在"
fi

# 检查 SeAT 网络是否存在
echo "🔍 检查 SeAT 网络..."
if ! docker network ls | grep -q seat-network; then
    echo "⚠️ SeAT 网络不存在，将创建外部网络"
    docker network create seat-network 2>/dev/null || echo "ℹ️ seat-network 网络已存在"
else
    echo "✅ 发现 SeAT 网络，将使用外部网络连接"
fi

# 生成 SSL 证书（自签名）
echo "🔐 生成 SSL 证书..."
if [ ! -f ssl-certs/cert.pem ]; then
    openssl req -x509 -newkey rsa:4096 -keyout ssl-certs/key.pem -out ssl-certs/cert.pem -days 365 -nodes \
        -subj "/C=CN/ST=State/L=City/O=Organization/CN=mumble.local"
    
    # 生成 DH 参数
    openssl dhparam -out ssl-certs/dh.pem 2048
    
    # 复制证书到 mumble-data 目录
    mkdir -p mumble-data
    cp ssl-certs/* mumble-data/
    
    echo "✅ SSL 证书已生成"
else
    echo "ℹ️ SSL 证书已存在，跳过生成"
    # 复制证书到 mumble-data 目录
    mkdir -p mumble-data
    cp ssl-certs/* mumble-data/
fi

# 设置文件权限
echo "🔒 设置文件权限..."
chmod 600 ssl-certs/key.pem
chmod 644 ssl-certs/cert.pem ssl-certs/dh.pem
sudo chmod 644 /opt/mumble/murmur.ini

# 生成Ice密钥（如果需要更新）
echo "🔑 生成 Ice 密钥..."
ICE_READ_SECRET=$(openssl rand -base64 32)
ICE_WRITE_SECRET=$(openssl rand -base64 32)

# 更新配置文件中的 Ice 密钥（如果包含占位符）
if grep -q "your_ice_read_secret" /opt/mumble/murmur.ini; then
    echo "🔄 更新配置文件中的 Ice 密钥..."
    sudo sed -i "s/your_ice_read_secret/$ICE_READ_SECRET/g" /opt/mumble/murmur.ini
    sudo sed -i "s/your_ice_write_secret/$ICE_WRITE_SECRET/g" /opt/mumble/murmur.ini
    echo "✅ Ice 密钥已更新"
else
    echo "ℹ️ 配置文件中的 Ice 密钥已配置，跳过更新"
    # 从配置文件中读取现有密钥
    ICE_READ_SECRET=$(grep "icesecretread=" /opt/mumble/murmur.ini | cut -d'=' -f2)
    ICE_WRITE_SECRET=$(grep "icesecretwrite=" /opt/mumble/murmur.ini | cut -d'=' -f2)
fi

# 启动 Mumble 服务器（独立运行）
echo "🚀 启动 Mumble 服务器..."
echo "ℹ️ 将连接到外部 seat-network 网络以与 SeAT 集成"
echo "🛡️ 使用独立项目名称以避免影响 SeAT 容器"
echo "🔐 配置为个人用户密码模式（无全局服务器密码）"
docker-compose -f docker-compose.mumble.yml -p mumble-server up -d

# 等待服务启动
echo "⏳ 等待服务启动..."
sleep 15

# 获取超级用户密码
echo "🔍 获取超级用户密码..."
echo "ℹ️ 官方镜像会自动生成 SuperUser 密码"
SUPER_USER_PASSWORD=$(docker logs mumble-server 2>&1 | grep "Password for 'SuperUser' set to" | awk -F"'" '{print $4}' | tail -1)
if [ -n "$SUPER_USER_PASSWORD" ]; then
    echo "✅ 自动获取的超级用户密码: $SUPER_USER_PASSWORD"
else
    echo "⚠️ 未能自动获取超级用户密码，请查看容器日志："
    echo "docker logs mumble-server | grep SuperUser"
    SUPER_USER_PASSWORD="请查看容器日志"
fi

# 检查服务状态
echo "🔍 检查服务状态..."
docker-compose -f docker-compose.mumble.yml -p mumble-server ps

echo ""
echo "🎉 Mumble 服务器安装完成！"
echo ""
echo "📋 Mumble 连接信息："
echo "   服务器地址: 192.168.1.33"
echo "   端口: 64738"
echo "   认证模式: 个人用户密码（每个用户都有独立密码）"
echo "   超级用户账户: SuperUser"
echo "   超级用户密码: $SUPER_USER_PASSWORD"
echo ""
echo "🔧 SeAT 插件配置信息："
echo "   服务器主机: 192.168.1.33"
echo "   服务器端口: 64738"
echo "   Ice 服务器: 192.168.1.33:6502 或 mumble-server:6502"
echo "   Ice 读取密钥: $ICE_READ_SECRET"
echo "   Ice 写入密钥: $ICE_WRITE_SECRET"
echo "   管理员用户名: SuperUser"
echo "   管理员密码: $SUPER_USER_PASSWORD"
echo ""
echo "📝 用户注册说明："
echo "   - 每个用户在 SeAT 中注册时会创建独立的 Mumble 账户"
echo "   - 用户使用自己设置的用户名和密码连接"
echo "   - 不需要输入服务器密码"
echo "   - Mumble 已连接到 seat-network，可与 SeAT 集成"
echo "   - 配置文件位置: /opt/mumble/murmur.ini"
echo ""
echo "⚠️ 重要说明："
echo "   1. 在 SeAT 管理界面配置 Mumble 连接器设置"
echo "   2. 用户需要先在 SeAT 中注册 Mumble 账户"
echo "   3. 然后使用注册的用户名和密码连接到 Mumble 服务器"

# 保存配置到文件
cat > mumble-credentials.txt << EOF
# Mumble 服务器凭据 - 个人用户密码模式
# 生成时间: $(date)

服务器连接信息:
- 服务器地址: 192.168.1.33
- 端口: 64738
- 认证模式: 个人用户密码（无全局服务器密码）
- 超级用户账户: SuperUser
- 超级用户密码: $SUPER_USER_PASSWORD

SeAT 插件配置:
- 服务器主机: 192.168.1.33
- 服务器端口: 64738
- Ice 服务器: 192.168.1.33:6502
- Ice 读取密钥: $ICE_READ_SECRET
- Ice 写入密钥: $ICE_WRITE_SECRET
- 管理员用户名: SuperUser
- 管理员密码: $SUPER_USER_PASSWORD

使用说明:
- 用户需要先在 SeAT 中注册 Mumble 账户
- 每个用户使用自己的用户名和密码连接
- 不需要输入服务器密码
- 在 SeAT 管理界面中配置上述 Ice 连接信息
- Mumble 已连接到 seat-network，可与 SeAT 集成
- 配置文件位置: /opt/mumble/murmur.ini
EOF

echo "💾 凭据已保存到 mumble-credentials.txt 文件中"