@echo off
REM ============================================
REM Exchange System - 数据库备份脚本 (Windows)
REM 支持开发和生产环境的数据库备份
REM ============================================

setlocal enabledelayedexpansion

REM 项目根目录
cd /d "%~dp0.."
set PROJECT_ROOT=%CD%

REM 时间戳
for /f "tokens=2 delims==" %%a in ('wmic OS Get localdatetime /value') do set "dt=%%a"
set "TIMESTAMP=%dt:~0,4%%dt:~4,2%%dt:~6,2%_%dt:~8,2%%dt:~10,2%%dt:~12,2%"

REM 备份目录
set BACKUP_DIR=%PROJECT_ROOT%\data\backups

REM 显示用法
if "%1"=="" goto usage
if "%1"=="-h" goto usage
if "%1"=="--help" goto usage

set ENV=%1
set USE_DOCKER=false

if "%2"=="--docker" set USE_DOCKER=true

REM 验证环境参数
if not "%ENV%"=="dev" if not "%ENV%"=="prod" (
    echo [X] 错误: 环境参数必须是 'dev' 或 'prod'
    goto usage
)

echo ================================
echo Exchange System - 数据库备份
echo ================================
echo 环境: %ENV%
echo 时间: %date% %time%
echo ================================
echo.

REM 确保备份目录存在
if not exist "%BACKUP_DIR%\%ENV%" mkdir "%BACKUP_DIR%\%ENV%"

if "%USE_DOCKER%"=="true" (
    REM 从Docker容器备份
    if "%ENV%"=="prod" (
        set CONTAINER_NAME=exchange-backend-prod
    ) else (
        set CONTAINER_NAME=currency-backend-dev
    )
    
    echo 正在从Docker容器备份: !CONTAINER_NAME!
    
    REM 检查容器是否运行
    docker ps --format "{{.Names}}" | findstr /X "!CONTAINER_NAME!" >nul
    if errorlevel 1 (
        echo [X] 错误: 容器 !CONTAINER_NAME! 未运行
        exit /b 1
    )
    
    REM 从容器复制数据库文件
    set BACKUP_FILE=%BACKUP_DIR%\%ENV%\database_%TIMESTAMP%.sqlite
    docker cp "!CONTAINER_NAME!:/var/www/html/database/database.sqlite" "!BACKUP_FILE!"
    
) else (
    REM 本地备份
    set SOURCE_DB=%PROJECT_ROOT%\data\%ENV%\database.sqlite
    
    if not exist "!SOURCE_DB!" (
        echo [X] 错误: 数据库文件不存在: !SOURCE_DB!
        exit /b 1
    )
    
    echo 正在备份本地数据库: !SOURCE_DB!
    
    set BACKUP_FILE=%BACKUP_DIR%\%ENV%\database_%TIMESTAMP%.sqlite
    copy "!SOURCE_DB!" "!BACKUP_FILE!" >nul
)

REM 验证备份文件
if exist "%BACKUP_FILE%" (
    echo [√] 备份成功!
    echo.
    echo 备份信息:
    echo   文件: %BACKUP_FILE%
    for %%A in ("%BACKUP_FILE%") do echo   大小: %%~zA 字节
    echo.
    
    REM 复制为最新备份
    set LATEST_FILE=%BACKUP_DIR%\%ENV%\database_latest.sqlite
    copy "%BACKUP_FILE%" "!LATEST_FILE!" >nul
    echo [√] 最新备份已更新: !LATEST_FILE!
    
) else (
    echo [X] 备份失败!
    exit /b 1
)

REM 清理旧备份(保留最近30个)
echo.
echo 正在清理旧备份(保留最近30个)...
set /a count=0
for /f "delims=" %%f in ('dir /b /o-d "%BACKUP_DIR%\%ENV%\database_*.sqlite" 2^>nul') do (
    set /a count+=1
    if !count! gtr 30 (
        del "%BACKUP_DIR%\%ENV%\%%f" >nul 2>&1
    )
)
echo [√] 旧备份清理完成

REM 列出所有备份
echo.
echo ================================
echo 当前所有备份:
echo ================================
dir /b /o-d "%BACKUP_DIR%\%ENV%\database_*.sqlite" 2>nul || echo 无备份文件

echo.
echo ================================
echo [√] 备份完成!
echo ================================
echo.

goto end

:usage
echo 用法: %~nx0 ^<环境^> [选项]
echo.
echo 环境:
echo   dev   - 备份开发环境数据库
echo   prod  - 备份生产环境数据库
echo.
echo 选项:
echo   --docker  - 从Docker容器中备份(推荐用于生产环境)
echo.
echo 示例:
echo   %~nx0 dev              # 备份开发环境(本地)
echo   %~nx0 prod --docker    # 备份生产环境(Docker容器)
echo.
exit /b 1

:end
endlocal

