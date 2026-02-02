@echo off
REM ============================================
REM 本地测试构建脚本（使用国内镜像源）
REM ============================================

setlocal enabledelayedexpansion

echo ============================================
echo   本地测试构建 Docker 镜像（国内源）
echo ============================================
echo.

echo [步骤 1] 停止现有容器...
docker-compose -f docker-compose.local.yml down
echo.

echo [步骤 2] 修改后端Dockerfile使用国内镜像源...
cd backend

REM 创建使用国内镜像源的Dockerfile
(
echo FROM registry.cn-hangzhou.aliyuncs.com/library/php:8.3-fpm-alpine
echo.
echo # 设置工作目录
echo WORKDIR /var/www/html
echo.
echo # 使用阿里云镜像源
echo RUN sed -i 's/dl-cdn.alpinelinux.org/mirrors.aliyun.com/g' /etc/apk/repositories
echo.
echo # 安装系统依赖
echo RUN apk add --no-cache \
echo     git \
echo     curl \
echo     libpng-dev \
echo     libjpeg-turbo-dev \
echo     freetype-dev \
echo     libzip-dev \
echo     oniguruma-dev \
echo     sqlite \
echo     sqlite-dev \
echo     zip \
echo     unzip \
echo     bash \
echo     icu-dev
echo.
echo # 安装 PHP 扩展
echo RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
echo     ^&^& docker-php-ext-install -j$^(nproc^) \
echo         pdo \
echo         pdo_sqlite \
echo         pdo_mysql \
echo         mbstring \
echo         zip \
echo         exif \
echo         pcntl \
echo         bcmath \
echo         gd \
echo         intl
echo.
echo # 手动安装 Composer
echo RUN curl -sS https://getcomposer.org/installer ^| php -- --install-dir=/usr/local/bin --filename=composer
echo.
echo # 复制应用代码
echo COPY composer.json composer.lock /var/www/html/
echo COPY . /var/www/html
echo.
echo # 创建数据库目录和文件
echo RUN mkdir -p /var/www/html/database \
echo     ^&^& touch /var/www/html/database/database.sqlite
echo.
echo # 创建 storage 目录结构
echo RUN mkdir -p /var/www/html/storage/framework/cache/data \
echo     ^&^& mkdir -p /var/www/html/storage/framework/sessions \
echo     ^&^& mkdir -p /var/www/html/storage/framework/views \
echo     ^&^& mkdir -p /var/www/html/storage/logs \
echo     ^&^& mkdir -p /var/www/html/bootstrap/cache
echo.
echo # 设置Composer国内镜像源并安装依赖
echo RUN composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/ \
echo     ^&^& rm -rf vendor \
echo     ^&^& composer install --optimize-autoloader --no-dev --no-interaction \
echo     ^&^& composer dump-autoload --optimize
echo.
echo # 设置权限
echo RUN chown -R www-data:www-data /var/www/html \
echo     ^&^& chmod -R 775 /var/www/html/storage \
echo     ^&^& chmod -R 775 /var/www/html/bootstrap/cache \
echo     ^&^& chmod -R 775 /var/www/html/database \
echo     ^&^& chmod 664 /var/www/html/database/database.sqlite
echo.
echo # 切换到 www-data 用户
echo USER www-data
echo.
echo # 暴露端口
echo EXPOSE 9000
echo.
echo # 启动 PHP-FPM
echo CMD ["php-fpm"]
) > Dockerfile.china

echo [步骤 3] 构建后端镜像...
docker build -f Dockerfile.china -t currency-backend:local .
if %errorlevel% neq 0 (
    echo [错误] 后端镜像构建失败
    cd ..
    pause
    exit /b 1
)
cd ..
echo [成功] 后端镜像构建完成
echo.

echo [步骤 4] 构建前端...
cd frontend
call npm install --registry=https://registry.npmmirror.com
if %errorlevel% neq 0 (
    echo [错误] 前端依赖安装失败
    pause
    exit /b 1
)
call npm run build
if %errorlevel% neq 0 (
    echo [错误] 前端构建失败
    pause
    exit /b 1
)
cd ..
echo [成功] 前端构建完成
echo.

echo [步骤 5] 创建 Nginx Dockerfile...
(
echo FROM registry.cn-hangzhou.aliyuncs.com/library/nginx:alpine
echo.
echo RUN sed -i 's/dl-cdn.alpinelinux.org/mirrors.aliyun.com/g' /etc/apk/repositories \
echo     ^&^& apk add --no-cache wget
echo.
echo COPY frontend/dist /usr/share/nginx/html
echo COPY docker/nginx/conf.d /etc/nginx/conf.d
echo.
echo RUN chmod -R 755 /usr/share/nginx/html
echo.
echo HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
echo   CMD wget --no-verbose --tries=1 --spider http://localhost ^|^| exit 1
echo.
echo EXPOSE 80 443
echo CMD ["nginx", "-g", "daemon off;"]
) > Dockerfile.nginx.china

echo [步骤 6] 构建 Nginx 镜像...
docker build -f Dockerfile.nginx.china -t currency-nginx:local .
if %errorlevel% neq 0 (
    echo [错误] Nginx 镜像构建失败
    pause
    exit /b 1
)
echo [成功] Nginx 镜像构建完成
echo.

echo [步骤 7] 清理临时文件...
del backend\Dockerfile.china 2>nul
del Dockerfile.nginx.china 2>nul

echo [步骤 8] 启动容器...
docker-compose -f docker-compose.local.yml up -d
if %errorlevel% neq 0 (
    echo [错误] 容器启动失败
    pause
    exit /b 1
)
echo.

echo [步骤 9] 等待服务启动（15秒）...
timeout /t 15 /nobreak >nul
echo.

echo [步骤 10] 检查容器状态...
docker-compose -f docker-compose.local.yml ps
echo.

echo [步骤 11] 检查服务健康状态...
echo 检查后端服务...
docker exec currency-backend php artisan --version 2>nul
if %errorlevel% equ 0 (
    echo [成功] 后端服务正常
) else (
    echo [警告] 后端服务可能未完全启动，查看日志：
    docker-compose -f docker-compose.local.yml logs backend
)

echo.
echo 检查容器日志...
docker-compose -f docker-compose.local.yml logs --tail=10
echo.

echo ============================================
echo   构建完成！
echo ============================================
echo.
echo 访问地址:
echo   前端: http://localhost:8080
echo   管理后台: http://localhost:8080/admin
echo.
echo 常用命令:
echo   查看日志: docker-compose -f docker-compose.local.yml logs -f
echo   停止服务: docker-compose -f docker-compose.local.yml down
echo   重启服务: docker-compose -f docker-compose.local.yml restart
echo   进入后端容器: docker exec -it currency-backend bash
echo.
pause