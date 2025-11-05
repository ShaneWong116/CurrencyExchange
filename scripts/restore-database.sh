#!/bin/bash

# ============================================
# Exchange System - 数据库恢复脚本
# 从备份恢复数据库
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

# 使用说明
usage() {
    echo "用法: $0 <环境> <备份文件> [选项]"
    echo ""
    echo "环境:"
    echo "  dev   - 恢复到开发环境"
    echo "  prod  - 恢复到生产环境"
    echo ""
    echo "备份文件:"
    echo "  可以是完整路径或备份文件名"
    echo "  使用 'latest' 恢复最新备份"
    echo ""
    echo "选项:"
    echo "  --docker  - 恢复到Docker容器(生产环境推荐)"
    echo "  --force   - 强制恢复,不提示确认"
    echo ""
    echo "示例:"
    echo "  $0 dev latest                          # 恢复最新备份到开发环境"
    echo "  $0 prod database_20241105_120000.sqlite --docker  # 恢复指定备份到生产容器"
    echo "  $0 dev /path/to/backup.sqlite --force  # 强制恢复外部备份"
    exit 1
}

# 检查参数
if [ $# -lt 2 ]; then
    usage
fi

ENV=$1
BACKUP_INPUT=$2
USE_DOCKER=false
FORCE=false

# 解析选项
shift 2
while [ $# -gt 0 ]; do
    case "$1" in
        --docker)
            USE_DOCKER=true
            ;;
        --force)
            FORCE=true
            ;;
        *)
            echo -e "${RED}✗ 未知选项: $1${NC}"
            usage
            ;;
    esac
    shift
done

# 验证环境参数
if [ "$ENV" != "dev" ] && [ "$ENV" != "prod" ]; then
    echo -e "${RED}✗ 错误: 环境参数必须是 'dev' 或 'prod'${NC}"
    usage
fi

echo "================================"
echo "Exchange System - 数据库恢复"
echo "================================"
echo "环境: $ENV"
echo "时间: $(date '+%Y-%m-%d %H:%M:%S')"
echo "================================"
echo ""

# 确定备份文件路径
if [ "$BACKUP_INPUT" == "latest" ]; then
    BACKUP_FILE="$PROJECT_ROOT/data/backups/$ENV/database_latest.sqlite"
    echo "使用最新备份: $BACKUP_FILE"
elif [ -f "$BACKUP_INPUT" ]; then
    BACKUP_FILE="$BACKUP_INPUT"
    echo "使用指定备份: $BACKUP_FILE"
elif [ -f "$PROJECT_ROOT/data/backups/$ENV/$BACKUP_INPUT" ]; then
    BACKUP_FILE="$PROJECT_ROOT/data/backups/$ENV/$BACKUP_INPUT"
    echo "使用备份: $BACKUP_FILE"
else
    echo -e "${RED}✗ 错误: 备份文件不存在: $BACKUP_INPUT${NC}"
    exit 1
fi

# 验证备份文件
if [ ! -f "$BACKUP_FILE" ]; then
    echo -e "${RED}✗ 错误: 备份文件不存在: $BACKUP_FILE${NC}"
    exit 1
fi

FILE_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
echo "备份文件大小: $FILE_SIZE"
echo ""

# 确认操作
if [ "$FORCE" != true ]; then
    echo -e "${YELLOW}⚠ 警告: 此操作将覆盖当前 $ENV 环境的数据库!${NC}"
    echo -e "${YELLOW}⚠ 建议先备份当前数据库${NC}"
    echo ""
    read -p "确定要继续吗? (yes/no): " confirm
    
    if [ "$confirm" != "yes" ]; then
        echo "操作已取消"
        exit 0
    fi
fi

echo ""
echo "开始恢复数据库..."

if [ "$USE_DOCKER" == true ]; then
    # 恢复到Docker容器
    if [ "$ENV" == "prod" ]; then
        CONTAINER_NAME="exchange-backend-prod"
    else
        CONTAINER_NAME="currency-backend-dev"
    fi
    
    echo "正在恢复到Docker容器: $CONTAINER_NAME"
    
    # 检查容器是否运行
    if ! docker ps --format '{{.Names}}' | grep -q "^${CONTAINER_NAME}$"; then
        echo -e "${RED}✗ 错误: 容器 $CONTAINER_NAME 未运行${NC}"
        exit 1
    fi
    
    # 停止容器(避免数据不一致)
    echo "正在停止容器..."
    docker stop "$CONTAINER_NAME" >/dev/null
    
    # 复制备份文件到容器
    echo "正在复制备份文件..."
    docker cp "$BACKUP_FILE" "$CONTAINER_NAME:/var/www/html/database/database.sqlite"
    
    # 启动容器
    echo "正在启动容器..."
    docker start "$CONTAINER_NAME" >/dev/null
    
else
    # 恢复到本地
    TARGET_DB="$PROJECT_ROOT/data/$ENV/database.sqlite"
    
    echo "正在恢复到本地数据库: $TARGET_DB"
    
    # 备份当前数据库(如果存在)
    if [ -f "$TARGET_DB" ]; then
        CURRENT_BACKUP="$PROJECT_ROOT/data/backups/$ENV/database_before_restore_$(date +%Y%m%d_%H%M%S).sqlite"
        echo "正在备份当前数据库到: $CURRENT_BACKUP"
        cp "$TARGET_DB" "$CURRENT_BACKUP"
    fi
    
    # 恢复数据库
    cp "$BACKUP_FILE" "$TARGET_DB"
    chmod 664 "$TARGET_DB"
fi

# 验证恢复
echo ""
echo "正在验证恢复结果..."

if [ "$USE_DOCKER" == true ]; then
    # 等待容器启动
    sleep 3
    
    # 检查容器中的数据库
    if docker exec "$CONTAINER_NAME" test -f /var/www/html/database/database.sqlite; then
        DB_SIZE=$(docker exec "$CONTAINER_NAME" du -h /var/www/html/database/database.sqlite | cut -f1)
        echo -e "${GREEN}✓ 数据库文件存在 (大小: $DB_SIZE)${NC}"
    else
        echo -e "${RED}✗ 恢复失败: 数据库文件不存在${NC}"
        exit 1
    fi
else
    if [ -f "$TARGET_DB" ]; then
        DB_SIZE=$(du -h "$TARGET_DB" | cut -f1)
        echo -e "${GREEN}✓ 数据库文件存在 (大小: $DB_SIZE)${NC}"
    else
        echo -e "${RED}✗ 恢复失败: 数据库文件不存在${NC}"
        exit 1
    fi
fi

echo ""
echo "================================"
echo -e "${GREEN}✓ 数据库恢复完成!${NC}"
echo "================================"
echo ""
echo "后续操作:"
if [ "$USE_DOCKER" == true ]; then
    echo "  1. 检查应用日志: docker logs $CONTAINER_NAME"
    echo "  2. 测试应用功能"
else
    echo "  1. 重启应用: docker-compose restart"
    echo "  2. 测试应用功能"
fi
echo ""

