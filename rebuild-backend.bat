@echo off
chcp 65001 > nul
setlocal enabledelayedexpansion

echo ============================================
echo   重新构建后端镜像
echo ============================================

set REGISTRY=crpi-nsc415g542h2toto.cn-shenzhen.personal.cr.aliyuncs.com
set NAMESPACE=currencyexchange
set PROJECT=currency_exchange

echo.
echo [步骤 1] 构建后端镜像...
docker.exe build -t %REGISTRY%/%NAMESPACE%/%PROJECT%:backend-latest -f backend/Dockerfile backend/
if errorlevel 1 (
    echo [错误] 后端镜像构建失败
    pause
    exit /b 1
)
echo [成功] 后端镜像构建完成

echo.
echo [步骤 2] 推送到阿里云...
docker.exe push %REGISTRY%/%NAMESPACE%/%PROJECT%:backend-latest
if errorlevel 1 (
    echo [错误] 后端镜像推送失败
    pause
    exit /b 1
)
echo [成功] 后端镜像推送完成

echo.
echo [步骤 3] 推送 Nginx 镜像...
docker.exe push %REGISTRY%/%NAMESPACE%/%PROJECT%:nginx-latest
if errorlevel 1 (
    echo [错误] Nginx 镜像推送失败
    pause
    exit /b 1
)
echo [成功] Nginx 镜像推送完成

echo.
echo ============================================
echo   所有镜像推送完成！
echo ============================================
echo.
pause

