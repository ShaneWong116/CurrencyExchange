@echo off
REM ============================================
REM 本地构建并推送到 Coding 制品库
REM Exchange System
REM ============================================

setlocal enabledelayedexpansion

echo ============================================
echo   本地构建并推送 Docker 镜像
echo ============================================
echo.

REM 配置信息 - 阿里云容器镜像服务
set DOCKER_REGISTRY=crpi-nsc415g542h2toto.cn-shenzhen.personal.cr.aliyuncs.com
set DOCKER_NAMESPACE=currencyexchange
set PROJECT_NAME=currency_exchange
set DOCKER_USER=张同学t134
REM 密码需要在阿里云容器镜像服务的访问凭证页面设置
set DOCKER_PWD=9T.!DJe9aVxi5-u

REM 版本号（使用时间戳）
for /f "tokens=2 delims==" %%I in ('wmic os get localdatetime /value') do set datetime=%%I
set VERSION=%datetime:~0,8%-%datetime:~8,6%
set VERSION=%VERSION: =0%

echo [配置信息]
echo   仓库地址: %DOCKER_REGISTRY%
echo   命名空间: %DOCKER_NAMESPACE%
echo   项目名称: %PROJECT_NAME%
echo   版本号: %VERSION%
echo.

REM 检查 Docker 服务
echo [步骤 1] 检查 Docker 服务...
docker ps >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo [错误] Docker 服务未运行，请先启动 Docker Desktop
    pause
    exit /b 1
)
echo [成功] Docker 服务正常运行
echo.

REM 登录 Docker 仓库
echo [步骤 2] 登录阿里云 Docker 仓库...
docker login --username=%DOCKER_USER% --password=%DOCKER_PWD% %DOCKER_REGISTRY%
if %ERRORLEVEL% NEQ 0 (
    echo [错误] Docker 登录失败
    pause
    exit /b 1
)
echo [成功] Docker 登录成功
echo.

REM 构建后端镜像
echo [步骤 3] 构建后端镜像...
echo   镜像名称: %DOCKER_REGISTRY%/%DOCKER_NAMESPACE%/%PROJECT_NAME%/backend:%VERSION%
echo   镜像名称: %DOCKER_REGISTRY%/%DOCKER_NAMESPACE%/%PROJECT_NAME%/backend:latest
echo.

cd backend
docker build --no-cache -t %DOCKER_REGISTRY%/%DOCKER_NAMESPACE%/%PROJECT_NAME%/backend:%VERSION% -t %DOCKER_REGISTRY%/%DOCKER_NAMESPACE%/%PROJECT_NAME%/backend:latest .
if %ERRORLEVEL% NEQ 0 (
    echo [错误] 后端镜像构建失败
    cd ..
    pause
    exit /b 1
)
echo [成功] 后端镜像构建完成
cd ..
echo.

REM 构建前端
echo [步骤 4] 构建前端应用...
cd frontend
call npm install
if %ERRORLEVEL% NEQ 0 (
    echo [错误] npm install 失败
    cd ..
    pause
    exit /b 1
)
call npm run build
if %ERRORLEVEL% NEQ 0 (
    echo [错误] 前端构建失败
    cd ..
    pause
    exit /b 1
)
echo [成功] 前端构建完成
cd ..
echo.

REM 创建 Nginx Dockerfile
echo [步骤 5] 创建 Nginx Dockerfile...
(
echo FROM nginx:alpine
echo.
echo # 安装 wget 用于健康检查
echo RUN apk add --no-cache wget
echo.
echo # 复制前端构建产物
echo COPY frontend/dist /var/www/html/frontend
echo.
echo # 复制 Nginx 配置
echo COPY docker/nginx/conf.d /etc/nginx/conf.d
echo.
echo # 设置权限
echo RUN chmod -R 755 /var/www/html
echo.
echo # 健康检查
echo HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
echo   CMD wget --no-verbose --tries=1 --spider http://localhost ^|^| exit 1
echo.
echo EXPOSE 80 443
echo.
echo CMD ["nginx", "-g", "daemon off;"]
) > Dockerfile.nginx

echo [成功] Dockerfile.nginx 创建完成
echo.

REM 构建 Nginx 镜像
echo [步骤 6] 构建 Nginx 镜像...
echo   镜像名称: %DOCKER_REGISTRY%/%DOCKER_NAMESPACE%/%PROJECT_NAME%/nginx:%VERSION%
echo   镜像名称: %DOCKER_REGISTRY%/%DOCKER_NAMESPACE%/%PROJECT_NAME%/nginx:latest
echo.

docker build --no-cache -f Dockerfile.nginx -t %DOCKER_REGISTRY%/%DOCKER_NAMESPACE%/%PROJECT_NAME%/nginx:%VERSION% -t %DOCKER_REGISTRY%/%DOCKER_NAMESPACE%/%PROJECT_NAME%/nginx:latest .
if %ERRORLEVEL% NEQ 0 (
    echo [错误] Nginx 镜像构建失败
    pause
    exit /b 1
)
echo [成功] Nginx 镜像构建完成
echo.

REM 推送后端镜像
echo [步骤 7] 推送后端镜像...
docker push %DOCKER_REGISTRY%/%DOCKER_NAMESPACE%/%PROJECT_NAME%/backend:%VERSION%
if %ERRORLEVEL% NEQ 0 (
    echo [错误] 推送后端镜像失败
    pause
    exit /b 1
)
docker push %DOCKER_REGISTRY%/%DOCKER_NAMESPACE%/%PROJECT_NAME%/backend:latest
if %ERRORLEVEL% NEQ 0 (
    echo [错误] 推送后端镜像 latest 标签失败
    pause
    exit /b 1
)
echo [成功] 后端镜像推送完成
echo.

REM 推送 Nginx 镜像
echo [步骤 8] 推送 Nginx 镜像...
docker push %DOCKER_REGISTRY%/%DOCKER_NAMESPACE%/%PROJECT_NAME%/nginx:%VERSION%
if %ERRORLEVEL% NEQ 0 (
    echo [错误] 推送 Nginx 镜像失败
    pause
    exit /b 1
)
docker push %DOCKER_REGISTRY%/%DOCKER_NAMESPACE%/%PROJECT_NAME%/nginx:latest
if %ERRORLEVEL% NEQ 0 (
    echo [错误] 推送 Nginx 镜像 latest 标签失败
    pause
    exit /b 1
)
echo [成功] Nginx 镜像推送完成
echo.

REM 清理临时文件
echo [步骤 9] 清理临时文件...
del Dockerfile.nginx 2>nul
echo [成功] 清理完成
echo.

REM 显示镜像信息
echo ============================================
echo   构建和推送完成！
echo ============================================
echo.
echo [镜像地址]
echo   后端镜像:
echo     - %DOCKER_REGISTRY%/%DOCKER_NAMESPACE%/%PROJECT_NAME%/backend:%VERSION%
echo     - %DOCKER_REGISTRY%/%DOCKER_NAMESPACE%/%PROJECT_NAME%/backend:latest
echo.
echo   Nginx 镜像:
echo     - %DOCKER_REGISTRY%/%DOCKER_NAMESPACE%/%PROJECT_NAME%/nginx:%VERSION%
echo     - %DOCKER_REGISTRY%/%DOCKER_NAMESPACE%/%PROJECT_NAME%/nginx:latest
echo.

REM 显示本地镜像
echo [本地镜像列表]
docker images | findstr /i "currencyexchange exchange-system"
echo.

echo 🎉 完成！
echo.
pause

