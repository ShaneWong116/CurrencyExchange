@echo off
REM ============================================
REM 本地开发环境构建（挂载本地代码，实时生效）
REM ============================================

setlocal enabledelayedexpansion

echo ============================================
echo   本地开发环境构建
echo ============================================
echo.

REM 停止现有开发容器
echo [步骤 1] 停止现有开发容器...
docker-compose -f docker-compose.dev.yml down
echo.

REM 构建前端
echo [步骤 2] 构建前端...
cd frontend
call npm install
call npm run build
cd ..
echo [成功] 前端构建完成
echo.

REM 构建并启动容器
echo [步骤 3] 构建并启动开发容器...
docker-compose -f docker-compose.dev.yml build
docker-compose -f docker-compose.dev.yml up -d
echo.

REM 等待服务启动
echo [步骤 4] 等待服务启动（5秒）...
timeout /t 5 /nobreak >nul
echo.

REM 安装 composer 依赖（在容器内）
echo [步骤 5] 安装 PHP 依赖...
docker exec currency-backend-dev composer install
echo.

REM 检查容器状态
echo [步骤 6] 检查容器状态...
docker-compose -f docker-compose.dev.yml ps
echo.

echo ============================================
echo   开发环境启动完成！
echo ============================================
echo.
echo 访问地址:
echo   前端: http://localhost:8080
echo   管理后台: http://localhost:8080/admin
echo.
echo 提示: 后端代码修改会实时生效，无需重新构建
echo.
pause
