#!/bin/sh
set -e

echo "================================"
echo "Exchange System - 容器启动脚本"
echo "================================"

# 数据库文件路径
DB_FILE="/var/www/html/database/database.sqlite"

# 检查数据库文件是否存在
if [ ! -f "$DB_FILE" ]; then
    echo "⚠ 数据库文件不存在,正在创建..."
    
    # 创建空的SQLite数据库文件
    touch "$DB_FILE"
    chmod 664 "$DB_FILE"
    
    echo "✓ 数据库文件已创建: $DB_FILE"
    
    # 运行数据库迁移(仅在首次创建时)
    echo "⚠ 正在执行数据库迁移..."
    php artisan migrate --force
    
    echo "✓ 数据库迁移完成"
else
    echo "✓ 数据库文件已存在: $DB_FILE"
    
    # 检查是否有待执行的迁移
    if php artisan migrate:status | grep -q "Pending"; then
        echo "⚠ 发现待执行的迁移,正在执行..."
        php artisan migrate --force
        echo "✓ 数据库迁移完成"
    else
        echo "✓ 数据库已是最新状态"
    fi
fi

# 优化自动加载
php artisan optimize:clear 2>/dev/null || true
php artisan config:cache 2>/dev/null || true
php artisan route:cache 2>/dev/null || true

echo "✓ 应用优化完成"
echo "================================"
echo "✓ 容器启动完成,开始运行服务..."
echo "================================"

# 执行传入的命令(通常是 php-fpm 或 queue:work)
exec "$@"

