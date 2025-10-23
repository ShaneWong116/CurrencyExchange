@echo off
echo 正在启动财务管理系统后台...

cd backend

echo 1. 检查依赖...
if not exist vendor (
    echo 安装依赖包...
    composer install --no-interaction --prefer-dist
    if errorlevel 1 (
        echo 依赖安装失败，尝试使用已有依赖...
    )
)

echo 2. 检查环境配置...
if not exist .env (
    echo 创建环境配置...
    echo APP_NAME="财务管理系统" > .env
    echo APP_ENV=local >> .env
    echo APP_KEY= >> .env
    echo APP_DEBUG=true >> .env
    echo APP_URL=http://localhost:8000 >> .env
    echo. >> .env
    echo DB_CONNECTION=sqlite >> .env
    echo DB_DATABASE=database.sqlite >> .env
    echo. >> .env
    echo CACHE_DRIVER=file >> .env
    echo SESSION_DRIVER=file >> .env
    echo QUEUE_CONNECTION=sync >> .env
)

echo 3. 生成应用密钥...
php artisan key:generate 2>nul || echo 密钥生成跳过...

echo 4. 创建数据库...
if not exist database\database.sqlite (
    echo. > database\database.sqlite
)

echo 5. 运行数据库迁移...
php artisan migrate --force 2>nul || echo 迁移跳过...

echo 6. 填充初始数据...
php artisan db:seed --force 2>nul || echo 填充跳过...

echo.
echo ========================================
echo 🎉 后台启动完成！
echo ========================================
echo 📍 访问地址：
echo    API接口: http://localhost:8000/api/health
echo    后台管理: http://localhost:8000/admin
echo 🔑 登录账户：
echo    管理员: admin / admin123
echo    财务: finance / finance123
echo ========================================
echo.

echo 正在启动开发服务器...
php artisan serve

pause
