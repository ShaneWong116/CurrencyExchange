@echo off
echo ================================================================
echo                    后台服务诊断脚本
echo ================================================================
echo.

echo [1] 检查PHP是否安装...
php --version
if %errorlevel% neq 0 (
    echo ❌ PHP未安装或未配置到环境变量
    echo 请安装PHP或检查环境变量配置
    pause
    exit /b 1
) else (
    echo ✅ PHP已安装
)
echo.

echo [2] 检查文件是否存在...
if exist "simple_backend.php" (
    echo ✅ simple_backend.php 存在
) else (
    echo ❌ simple_backend.php 不存在
)

if exist "admin.html" (
    echo ✅ admin.html 存在
) else (
    echo ❌ admin.html 不存在
)

if exist "test_backend.html" (
    echo ✅ test_backend.html 存在
) else (
    echo ❌ test_backend.html 不存在
)
echo.

echo [3] 检查端口8000是否被占用...
netstat -an | findstr :8000
if %errorlevel% equ 0 (
    echo ✅ 端口8000已被使用（服务器可能正在运行）
) else (
    echo ⚠️ 端口8000未被使用（服务器可能未启动）
)
echo.

echo [4] 检查PHP进程...
tasklist | findstr php.exe
if %errorlevel% eq 0 (
    echo ✅ 找到PHP进程
) else (
    echo ⚠️ 未找到PHP进程
)
echo.

echo [5] 尝试启动服务器...
echo 正在启动PHP服务器... （按Ctrl+C可停止）
echo 启动后请在浏览器访问以下地址：
echo.
echo 🏛️ 后台管理：    http://localhost:8000/admin
echo 📡 API健康检查： http://localhost:8000/api/health
echo 🔧 服务测试：    test_backend.html
echo.
echo ================================================================
php -S localhost:8000 simple_backend.php
