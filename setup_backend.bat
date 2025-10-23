@echo off
echo 正在设置财务管理系统后台...
cd backend

echo.
echo 1. 安装依赖包...
composer install --no-interaction --prefer-dist

echo.
echo 2. 生成应用密钥...
php artisan key:generate

echo.
echo 3. 创建数据库...
echo. > database/database.sqlite

echo.
echo 4. 运行数据库迁移...
php artisan migrate --force

echo.
echo 5. 填充初始数据...
php artisan db:seed --force

echo.
echo 6. 初始化系统...
php artisan system:initialize

echo.
echo 7. 启动开发服务器...
echo 系统设置完成！
echo 访问地址: http://localhost:8000/admin
echo 管理员账户: admin/admin123
echo 财务账户: finance/finance123
echo.
php artisan serve

pause
