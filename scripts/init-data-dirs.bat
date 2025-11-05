@echo off
REM ============================================
REM Exchange System - 数据目录初始化脚本 (Windows)
REM 创建必要的数据目录结构
REM ============================================

echo ================================
echo Exchange System - 数据目录初始化
echo ================================
echo.

cd /d "%~dp0.."
echo 项目根目录: %CD%
echo.

echo 正在创建数据目录结构...
echo.

REM 开发环境数据目录
if not exist "data\dev" (
    mkdir "data\dev"
    echo [√] 创建开发环境数据目录: data\dev
) else (
    echo [√] 开发环境数据目录已存在: data\dev
)

REM 生产环境数据目录
if not exist "data\prod" (
    mkdir "data\prod"
    echo [√] 创建生产环境数据目录: data\prod
) else (
    echo [√] 生产环境数据目录已存在: data\prod
)

REM 备份目录
if not exist "data\backups" (
    mkdir "data\backups"
    echo [√] 创建备份目录: data\backups
) else (
    echo [√] 备份目录已存在: data\backups
)

REM 存储目录
if not exist "data\storage" (
    mkdir "data\storage"
    echo [√] 创建存储目录: data\storage
) else (
    echo [√] 存储目录已存在: data\storage
)

echo.
echo 正在创建日志目录...

if not exist "logs" mkdir "logs"
if not exist "logs\backend" mkdir "logs\backend"
if not exist "logs\queue" mkdir "logs\queue"
if not exist "logs\scheduler" mkdir "logs\scheduler"

echo [√] 日志目录创建完成
echo.

REM 创建 .gitkeep 文件
echo 正在创建 .gitkeep 文件...

type nul > "data\dev\.gitkeep"
type nul > "data\prod\.gitkeep"
type nul > "data\backups\.gitkeep"
type nul > "data\storage\.gitkeep"

echo [√] .gitkeep 文件创建完成
echo.

echo ================================
echo [√] 数据目录初始化完成!
echo ================================
echo.
echo 目录说明:
echo   data\dev\      - 开发环境数据(不会影响生产)
echo   data\prod\     - 生产环境数据(与开发环境隔离)
echo   data\backups\  - 数据库备份文件
echo   data\storage\  - 应用存储文件
echo.
echo 下一步:
echo   1. 启动开发环境: docker-compose up -d
echo   2. 启动生产环境: docker-compose -f docker-compose.prod.yml up -d
echo.

pause

