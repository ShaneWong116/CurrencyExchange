@echo off
REM ============================================
REM 本地构建镜像（仅构建，不推送）
REM ============================================

setlocal enabledelayedexpansion

echo ============================================
echo   本地构建 Docker 镜像（测试）
echo ============================================
echo.

REM 配置信息
set DOCKER_REGISTRY=crpi-nsc415g542h2toto.cn-shenzhen.personal.cr.aliyuncs.com
set DOCKER_NAMESPACE=currencyexchange
set PROJECT_NAME=currency_exchange

REM 版本号
for /f "tokens=2 delims==" %%I in ('wmic os get localdatetime /value') do set datetime=%%I
set VERSION=%datetime:~0,8%-%datetime:~8,6%

echo [配置信息]
echo   仓库地址: %DOCKER_REGISTRY%
echo   命名空间: %DOCKER_NAMESPACE%
echo   项目名称: %PROJECT_NAME%
echo   版本号: %VERSION%
echo.

REM 停止现有容器
echo [步骤 1] 停止现有容器...
docker-compose down
echo.

REM 构建后端镜像
echo [步骤 2] 构建后端镜像...
cd backend
docker build -t %DOCKER_REGISTRY%/%DOCKER_NAMESPACE%/%PROJECT_NAME%:backend-latest .
cd ..
echo [成功] 后端镜像构建完成
echo.

REM 构建前端
echo [步骤 3] 构建前端...
cd frontend
call npm run build
cd ..
echo [成功] 前端构建完成
echo.

REM 创建 Nginx Dockerfile
echo [步骤 4] 创建 Nginx Dockerfile...
(
echo FROM nginx:alpine
echo.
echo RUN apk add --no-cache wget
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
) > Dockerfile.nginx
echo.

REM 构建 Nginx 镜像
echo [步骤 5] 构建 Nginx 镜像...
docker build -f Dockerfile.nginx -t %DOCKER_REGISTRY%/%DOCKER_NAMESPACE%/%PROJECT_NAME%:nginx-latest .
echo [成功] Nginx 镜像构建完成
echo.

REM 清理临时文件
del Dockerfile.nginx 2>nul

REM 启动容器
echo [步骤 6] 启动容器...
docker-compose up -d
echo.

REM 等待服务启动
echo [步骤 7] 等待服务启动（5秒）...
timeout /t 5 /nobreak >nul
echo.

REM 检查容器状态
echo [步骤 8] 检查容器状态...
docker-compose ps
echo.

echo ============================================
echo   构建完成！
echo ============================================
echo.
echo 访问地址:
echo   前端: http://localhost:8080
echo   管理后台: http://localhost:8080/admin
echo.
pause

