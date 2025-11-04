FROM php:8.3-fpm-alpine

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
    bash \
    icu-dev

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

# 复制应用代码（排除 vendor）
COPY composer.json composer.lock /var/www/html/
COPY . /var/www/html

# 创建数据库目录和文件（必须在 composer 之前）
RUN mkdir -p /var/www/html/database \
    && touch /var/www/html/database/database.sqlite

# 清理并安装 PHP 依赖
RUN rm -rf vendor \
    && composer install --optimize-autoloader --no-dev --no-interaction \
    && composer dump-autoload --optimize

# 设置权限
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/database \
    && chmod 664 /var/www/html/database/database.sqlite

# 切换到 www-data 用户
USER www-data

# 暴露端口
EXPOSE 9000

# 启动 PHP-FPM
CMD ["php-fpm"]

