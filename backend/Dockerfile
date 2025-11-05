FROM php:8.1-fpm-alpine

# 设置工作目录
WORKDIR /var/www/html

# 安装系统依赖
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    oniguruma-dev \
    sqlite \
    sqlite-dev \
    zip \
    unzip \
    bash

# 安装 PHP 扩展
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_sqlite \
        pdo_mysql \
        mbstring \
        zip \
        exif \
        pcntl \
        bcmath \
        gd \
        intl

# 安装 Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 复制应用代码
COPY . /var/www/html

# 安装 PHP 依赖
RUN composer install --optimize-autoloader --no-dev --no-interaction

# 设置权限
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# 创建数据库目录(但不创建数据库文件,数据库文件由volume提供)
RUN mkdir -p /var/www/html/database \
    && chown www-data:www-data /var/www/html/database \
    && chmod 775 /var/www/html/database

# 复制启动脚本
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# 切换到 www-data 用户
USER www-data

# 暴露端口
EXPOSE 9000

# 使用启动脚本(会在容器启动时检查并初始化数据库)
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]

