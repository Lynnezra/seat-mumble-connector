# 支持 Ice 扩展的 Dockerfile
FROM php:8.1-fpm

# 安装系统依赖
RUN apt-get update && apt-get install -y \
    libzeroc-ice-dev \
    ice-slice \
    build-essential \
    php-dev \
    wget \
    && rm -rf /var/lib/apt/lists/*

# 方法1: 使用包管理器安装（推荐）
RUN apt-get update && apt-get install -y php-zeroc-ice \
    && rm -rf /var/lib/apt/lists/*

# 方法2: 手动编译安装（如果包管理器方法失败）
# RUN cd /tmp \
#     && wget https://download.zeroc.com/Ice/3.7/php-ice-3.7.5.tar.gz \
#     && tar -xzf php-ice-3.7.5.tar.gz \
#     && cd php-ice-3.7.5 \
#     && phpize \
#     && ./configure \
#     && make \
#     && make install \
#     && docker-php-ext-enable ice \
#     && rm -rf /tmp/php-ice-3.7.5*

# 启用 Ice 扩展
RUN echo "extension=ice.so" >> /usr/local/etc/php/conf.d/ice.ini

# 验证 Ice 扩展
RUN php -m | grep ice

# 复制应用代码
COPY . /var/www/html

# 设置工作目录
WORKDIR /var/www/html

# 安装 Composer 依赖
RUN composer install --no-dev --optimize-autoloader

# 设置权限
RUN chown -R www-data:www-data /var/www/html