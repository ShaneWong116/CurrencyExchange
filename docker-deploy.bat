@echo off
chcp 65001 >nul
setlocal enabledelayedexpansion

REM ============================================
REM Docker 一键部署脚本 (Windows版本)
REM ============================================

echo.
echo ╔═══════════════════════════════════════════╗
echo ║   财务管理系统 Docker 一键部署脚本          ║
echo ║   Currency Exchange System Docker Deploy  ║
echo ╚═══════════════════════════════════════════╝
echo.

REM 检查 Docker 是否安装
echo [1/7] 检查 Docker 环境...
docker --version >nul 2>&1
if errorlevel 1 (
    echo ❌ Docker 未安装，请先安装 Docker Desktop
    echo    下载地址: https://www.docker.com/products/docker-desktop
    pause
    exit /b 1
)

docker-compose --version >nul 2>&1
if errorlevel 1 (
    echo ❌ Docker Compose 未安装
    pause
    exit /b 1
)

echo ✅ Docker 已安装
echo.

REM 创建必要的目录
echo [2/7] 创建必要的目录...
if not exist "docker\nginx\conf.d" mkdir docker\nginx\conf.d
if not exist "docker\nginx\ssl" mkdir docker\nginx\ssl
if not exist "backend\storage\logs" mkdir backend\storage\logs
if not exist "backend\storage\framework\cache" mkdir backend\storage\framework\cache
if not exist "backend\storage\framework\sessions" mkdir backend\storage\framework\sessions
if not exist "backend\storage\framework\views" mkdir backend\storage\framework\views
echo ✅ 目录创建完成
echo.

REM 构建前端
echo [3/7] 构建前端应用...
cd frontend

if not exist ".env.production" (
    echo VITE_API_BASE_URL=http://localhost:8080/api > .env.production
    echo VITE_APP_NAME=财务管理系统 >> .env.production
    echo ⚠️  已创建 .env.production，请检查 API 地址是否正确
)

if exist "dist" (
    echo ⚠️  检测到已存在的构建产物
    set /p REBUILD="是否重新构建？(Y/N): "
    if /i "!REBUILD!"=="Y" (
        rmdir /s /q dist
        echo 正在安装依赖...
        call npm install
        echo 正在构建前端...
        call npm run build
    )
) else (
    echo 正在安装依赖...
    call npm install
    echo 正在构建前端...
    call npm run build
)

if not exist "dist" (
    echo ❌ 前端构建失败
    cd ..
    pause
    exit /b 1
)

echo ✅ 前端构建完成
cd ..
echo.

REM 准备后端
echo [4/7] 准备后端环境...
cd backend

if not exist "database\database.sqlite" (
    echo ⚠️  SQLite 数据库文件不存在，将创建新文件
    type nul > database\database.sqlite
)

if not exist ".env" (
    if exist ".env.example" (
        echo 从 .env.example 创建 .env 文件...
        copy .env.example .env >nul
    ) else (
        echo ⚠️  .env 文件不存在，将使用默认配置
    )
)

echo ✅ 后端环境准备完成
cd ..
echo.

REM 启动 Docker 容器
echo [5/7] 启动 Docker 容器...
echo 停止现有容器...
docker-compose down 2>nul

echo 拉取基础镜像...
docker-compose pull

echo 构建并启动容器...
docker-compose up -d --build

echo 等待容器启动...
timeout /t 10 /nobreak >nul

echo ✅ 容器已启动
echo.

REM 初始化应用
echo [6/7] 初始化应用...
echo 生成应用密钥...
docker-compose exec -T backend php artisan key:generate --force

echo 运行数据库迁移...
docker-compose exec -T backend php artisan migrate --force

echo 填充初始数据...
docker-compose exec -T backend php artisan db:seed --force

echo 优化应用...
docker-compose exec -T backend php artisan config:cache
docker-compose exec -T backend php artisan route:cache
docker-compose exec -T backend php artisan view:cache

echo ✅ 应用初始化完成
echo.

REM 健康检查
echo [7/7] 健康检查...
timeout /t 5 /nobreak >nul

echo 检查容器状态:
docker-compose ps

echo.
echo ╔═══════════════════════════════════════════╗
echo ║              🎉 部署完成！                 ║
echo ╚═══════════════════════════════════════════╝
echo.
echo 📝 访问地址：
echo    前端应用: http://localhost
echo    后端API:  http://localhost/api
echo    健康检查: http://localhost/api/health
echo.
echo 👥 默认账户：
echo    外勤人员: field001/field002/field003 (密码: 123456)
echo    后台管理: admin (密码: admin123)
echo    财务人员: finance (密码: finance123)
echo.
echo ⚠️  重要提示：
echo    1. 请立即修改所有默认密码
echo    2. 生产环境请配置域名和 HTTPS
echo    3. 定期备份 SQLite 数据库文件
echo.
echo 🔧 常用命令：
echo    查看日志: docker-compose logs -f
echo    重启服务: docker-compose restart
echo    停止服务: docker-compose stop
echo    进入容器: docker-compose exec backend sh
echo.
echo 📖 详细文档: 查看 DOCKER_DEPLOYMENT.md
echo.

pause

