#!/bin/bash

# ============================================
# Exchange System - 数据库备份脚本
# 支持开发和生产环境的数据库备份
# ============================================

set -e

# 颜色输出
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 项目根目录
PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$PROJECT_ROOT"

# 备份目录
BACKUP_DIR="$PROJECT_ROOT/data/backups"

# 时间戳
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")

# 使用说明
usage() {
    echo "用法: $0 <环境> [选项]"
    echo ""
    echo "环境:"
    echo "  dev   - 备份开发环境数据库"
    echo "  prod  - 备份生产环境数据库"
    echo ""
    echo "选项:"
    echo "  --docker  - 从Docker容器中备份(推荐用于生产环境)"
    echo ""
    echo "示例:"
    echo "  $0 dev              # 备份开发环境(本地)"
    echo "  $0 prod --docker    # 备份生产环境(Docker容器)"
    exit 1
}

# 检查参数
if [ $# -lt 1 ]; then
    usage
fi

ENV=$1
USE_DOCKER=false

if [ "$2" == "--docker" ]; then
    USE_DOCKER=true
fi

# 验证环境参数
if [ "$ENV" != "dev" ] && [ "$ENV" != "prod" ]; then
    echo -e "${RED}✗ 错误: 环境参数必须是 'dev' 或 'prod'${NC}"
    usage
fi

echo "================================"
echo "Exchange System - 数据库备份"
echo "================================"
echo "环境: $ENV"
echo "时间: $(date '+%Y-%m-%d %H:%M:%S')"
echo "================================"
echo ""

# 确保备份目录存在
mkdir -p "$BACKUP_DIR/$ENV"

# 设置数据库文件路径
if [ "$USE_DOCKER" == true ]; then
    # 从Docker容器备份
    CONTAINER_NAME="exchange-backend-prod"
    
    if [ "$ENV" == "dev" ]; then
        CONTAINER_NAME="currency-backend-dev"
    fi
    
    echo "正在从Docker容器备份: $CONTAINER_NAME"
    
    # 检查容器是否运行
    if ! docker ps --format '{{.Names}}' | grep -q "^${CONTAINER_NAME}$"; then
        echo -e "${RED}✗ 错误: 容器 $CONTAINER_NAME 未运行${NC}"
        exit 1
    fi
    
    # 从容器复制数据库文件
    BACKUP_FILE="$BACKUP_DIR/$ENV/database_${TIMESTAMP}.sqlite"
    docker cp "$CONTAINER_NAME:/var/www/html/database/database.sqlite" "$BACKUP_FILE"
    
else
    # 本地备份
    SOURCE_DB="$PROJECT_ROOT/data/$ENV/database.sqlite"
    
    if [ ! -f "$SOURCE_DB" ]; then
        echo -e "${RED}✗ 错误: 数据库文件不存在: $SOURCE_DB${NC}"
        exit 1
    fi
    
    echo "正在备份本地数据库: $SOURCE_DB"
    
    BACKUP_FILE="$BACKUP_DIR/$ENV/database_${TIMESTAMP}.sqlite"
    cp "$SOURCE_DB" "$BACKUP_FILE"
fi

# 验证备份文件
if [ -f "$BACKUP_FILE" ]; then
    FILE_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
    echo -e "${GREEN}✓ 备份成功!${NC}"
    echo ""
    echo "备份信息:"
    echo "  文件: $BACKUP_FILE"
    echo "  大小: $FILE_SIZE"
    echo ""
    
    # 创建最新备份的符号链接
    LATEST_LINK="$BACKUP_DIR/$ENV/database_latest.sqlite"
    ln -sf "$BACKUP_FILE" "$LATEST_LINK" 2>/dev/null || cp "$BACKUP_FILE" "$LATEST_LINK"
    echo -e "${GREEN}✓ 最新备份链接已更新: $LATEST_LINK${NC}"
    
else
    echo -e "${RED}✗ 备份失败!${NC}"
    exit 1
fi

# 清理旧备份(保留最近30天)
echo ""
echo "正在清理旧备份(保留30天内的备份)..."
find "$BACKUP_DIR/$ENV" -name "database_*.sqlite" -type f -mtime +30 -delete 2>/dev/null || true
echo -e "${GREEN}✓ 旧备份清理完成${NC}"

# 列出所有备份
echo ""
echo "================================"
echo "当前所有备份:"
echo "================================"
ls -lh "$BACKUP_DIR/$ENV/" | grep "database_" || echo "无备份文件"

echo ""
echo "================================"
echo -e "${GREEN}✓ 备份完成!${NC}"
echo "================================"

