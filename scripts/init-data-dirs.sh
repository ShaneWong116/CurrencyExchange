#!/bin/bash

# ============================================
# Exchange System - 数据目录初始化脚本
# 创建必要的数据目录结构
# ============================================

set -e

echo "================================"
echo "Exchange System - 数据目录初始化"
echo "================================"

# 项目根目录
PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$PROJECT_ROOT"

echo "✓ 项目根目录: $PROJECT_ROOT"

# 创建数据目录结构
echo ""
echo "正在创建数据目录结构..."

# 开发环境数据目录
if [ ! -d "data/dev" ]; then
    mkdir -p data/dev
    echo "✓ 创建开发环境数据目录: data/dev"
else
    echo "✓ 开发环境数据目录已存在: data/dev"
fi

# 生产环境数据目录
if [ ! -d "data/prod" ]; then
    mkdir -p data/prod
    echo "✓ 创建生产环境数据目录: data/prod"
else
    echo "✓ 生产环境数据目录已存在: data/prod"
fi

# 备份目录
if [ ! -d "data/backups" ]; then
    mkdir -p data/backups
    echo "✓ 创建备份目录: data/backups"
else
    echo "✓ 备份目录已存在: data/backups"
fi

# 存储目录
if [ ! -d "data/storage" ]; then
    mkdir -p data/storage
    echo "✓ 创建存储目录: data/storage"
else
    echo "✓ 存储目录已存在: data/storage"
fi

# 日志目录
echo ""
echo "正在创建日志目录..."

mkdir -p logs/backend logs/queue logs/scheduler
echo "✓ 日志目录创建完成"

# 设置权限(Linux/Mac)
if [[ "$OSTYPE" != "msys" && "$OSTYPE" != "win32" ]]; then
    echo ""
    echo "正在设置目录权限..."
    chmod -R 775 data/
    chmod -R 775 logs/
    echo "✓ 权限设置完成"
fi

# 创建 .gitkeep 文件(保留目录结构但不提交数据)
echo ""
echo "正在创建 .gitkeep 文件..."

touch data/dev/.gitkeep
touch data/prod/.gitkeep
touch data/backups/.gitkeep
touch data/storage/.gitkeep

echo "✓ .gitkeep 文件创建完成"

# 显示目录结构
echo ""
echo "================================"
echo "数据目录结构:"
echo "================================"
tree -L 2 data/ 2>/dev/null || find data/ -maxdepth 2 -type d

echo ""
echo "================================"
echo "✓ 数据目录初始化完成!"
echo "================================"
echo ""
echo "目录说明:"
echo "  data/dev/      - 开发环境数据(不会影响生产)"
echo "  data/prod/     - 生产环境数据(与开发环境隔离)"
echo "  data/backups/  - 数据库备份文件"
echo "  data/storage/  - 应用存储文件"
echo ""
echo "下一步:"
echo "  1. 启动开发环境: docker-compose up -d"
echo "  2. 启动生产环境: docker-compose -f docker-compose.prod.yml up -d"
echo ""

