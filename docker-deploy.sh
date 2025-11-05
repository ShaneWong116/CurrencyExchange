#!/bin/bash

# ============================================
# Docker 一键部署脚本
# ============================================

set -e

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 打印函数
print_status() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

print_header() {
    echo -e "${BLUE}"
    echo "============================================"
    echo "$1"
    echo "============================================"
    echo -e "${NC}"
}

# 检查 Docker 是否安装
check_docker() {
    print_header "检查 Docker 环境"
    
    if ! command -v docker &> /dev/null; then
        print_error "Docker 未安装"
        print_info "请先安装 Docker: curl -fsSL https://get.docker.com | sh"
        exit 1
    fi
    
    if ! command -v docker-compose &> /dev/null; then
        print_error "Docker Compose 未安装"
        exit 1
    fi
    
    print_status "Docker 已安装: $(docker --version)"
    print_status "Docker Compose 已安装: $(docker-compose --version)"
}

# 初始化数据目录
init_data_dirs() {
    print_header "初始化数据目录"
    
    # 创建必要的目录
    mkdir -p data/dev data/prod data/backups data/storage
    mkdir -p logs/backend logs/queue logs/scheduler
    
    # 创建 .gitkeep 文件
    touch data/dev/.gitkeep data/prod/.gitkeep data/backups/.gitkeep data/storage/.gitkeep
    
    print_status "数据目录已创建"
    print_info "  data/dev/      - 开发环境数据"
    print_info "  data/prod/     - 生产环境数据"
    print_info "  data/backups/  - 备份文件"
    print_info "  data/storage/  - 存储文件"
}

# 备份现有数据库(如果存在)
backup_existing_db() {
    local ENV=$1
    local DB_FILE="data/$ENV/database.sqlite"
    
    if [ -f "$DB_FILE" ]; then
        print_warning "发现现有数据库,正在备份..."
        local BACKUP_FILE="data/backups/$ENV/database_before_deploy_$(date +%Y%m%d_%H%M%S).sqlite"
        cp "$DB_FILE" "$BACKUP_FILE"
        print_status "数据库已备份到: $BACKUP_FILE"
    else
        print_info "未发现现有数据库,将创建新数据库"
    fi
}

# 构建前端
build_frontend() {
    print_header "构建前端应用"
    
    if [ ! -d "frontend" ]; then
        print_error "前端目录不存在"
        exit 1
    fi
    
    cd frontend
    
    # 检查是否已构建
    if [ -d "dist" ]; then
        print_warning "检测到已存在的构建产物"
        read -p "是否重新构建？(y/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            cd ..
            return
        fi
        rm -rf dist
    fi
    
    # 创建环境配置
    if [ ! -f ".env.production" ]; then
        print_info "创建生产环境配置..."
        cat > .env.production << 'EOF'
VITE_API_BASE_URL=http://localhost/api
VITE_APP_NAME=财务管理系统
EOF
        print_warning "请检查 .env.production 中的 API 地址是否正确"
    fi
    
    # 检查 Node.js
    if ! command -v npm &> /dev/null; then
        print_error "Node.js/npm 未安装，请先安装"
        exit 1
    fi
    
    # 安装依赖
    print_info "安装前端依赖..."
    npm install
    
    # 构建
    print_info "构建前端应用..."
    npm run build
    
    if [ ! -d "dist" ]; then
        print_error "前端构建失败"
        exit 1
    fi
    
    print_status "前端构建完成"
    cd ..
}

# 准备后端
prepare_backend() {
    print_header "准备后端环境"
    
    if [ ! -d "backend" ]; then
        print_error "后端目录不存在"
        exit 1
    fi
    
    cd backend
    
    # 检查 SQLite 数据库
    if [ ! -f "database/database.sqlite" ]; then
        print_warning "SQLite 数据库文件不存在，将创建新文件"
        touch database/database.sqlite
        chmod 664 database/database.sqlite
    fi
    
    # 创建 .env 文件（如果不存在）
    if [ ! -f ".env" ]; then
        if [ -f ".env.example" ]; then
            print_info "从 .env.example 创建 .env 文件..."
            cp .env.example .env
        else
            print_warning ".env 文件不存在，将使用默认配置"
        fi
    fi
    
    print_status "后端环境准备完成"
    cd ..
}

# 创建必要的目录
create_directories() {
    print_header "创建必要的目录"
    
    mkdir -p docker/nginx/conf.d
    mkdir -p docker/nginx/ssl
    mkdir -p backend/storage/logs
    mkdir -p backend/storage/framework/cache
    mkdir -p backend/storage/framework/sessions
    mkdir -p backend/storage/framework/views
    
    print_status "目录创建完成"
}

# 启动 Docker 容器
start_containers() {
    print_header "启动 Docker 容器"
    
    print_info "停止现有容器..."
    docker-compose down 2>/dev/null || true
    
    print_info "拉取基础镜像..."
    docker-compose pull
    
    print_info "构建并启动容器..."
    docker-compose up -d --build
    
    print_status "等待容器启动..."
    sleep 10
    
    # 检查容器状态
    print_info "容器状态："
    docker-compose ps
}

# 初始化应用
init_application() {
    print_header "初始化应用"
    
    print_info "生成应用密钥..."
    docker-compose exec -T backend php artisan key:generate --force
    
    print_info "运行数据库迁移..."
    docker-compose exec -T backend php artisan migrate --force
    
    print_info "填充初始数据..."
    docker-compose exec -T backend php artisan db:seed --force
    
    print_info "优化应用..."
    docker-compose exec -T backend php artisan config:cache
    docker-compose exec -T backend php artisan route:cache
    docker-compose exec -T backend php artisan view:cache
    
    print_status "应用初始化完成"
}

# 健康检查
health_check() {
    print_header "健康检查"
    
    # 等待服务启动
    sleep 5
    
    # 检查后端 API
    print_info "检查后端 API..."
    if curl -f http://localhost/api/health &>/dev/null; then
        print_status "后端 API 正常"
    else
        print_warning "后端 API 可能未就绪，请稍后检查"
    fi
    
    # 检查前端
    print_info "检查前端应用..."
    if curl -f http://localhost/ &>/dev/null; then
        print_status "前端应用正常"
    else
        print_warning "前端应用可能未就绪，请稍后检查"
    fi
    
    # 显示容器状态
    print_info "容器运行状态："
    docker-compose ps
}

# 显示部署信息
show_info() {
    print_header "🎉 部署完成！"
    
    echo ""
    echo "📝 访问地址："
    echo "   前端应用: http://localhost"
    echo "   后端API:  http://localhost/api"
    echo "   健康检查: http://localhost/api/health"
    echo ""
    echo "👥 默认账户："
    echo "   外勤人员: field001/field002/field003 (密码: 123456)"
    echo "   后台管理: admin (密码: admin123)"
    echo "   财务人员: finance (密码: finance123)"
    echo ""
    echo "⚠️  重要提示："
    echo "   1. 请立即修改所有默认密码"
    echo "   2. 生产环境请配置域名和 HTTPS"
    echo "   3. 定期备份 SQLite 数据库文件"
    echo ""
    echo "🔧 常用命令："
    echo "   查看日志: docker-compose logs -f"
    echo "   重启服务: docker-compose restart"
    echo "   停止服务: docker-compose stop"
    echo "   进入容器: docker-compose exec backend sh"
    echo ""
    echo "📖 详细文档: 查看 DOCKER_DEPLOYMENT.md"
    echo ""
}

# 主函数
main() {
    clear
    echo -e "${BLUE}"
    cat << "EOF"
╔═══════════════════════════════════════════╗
║   财务管理系统 Docker 一键部署脚本          ║
║   Currency Exchange System Docker Deploy  ║
╚═══════════════════════════════════════════╝
EOF
    echo -e "${NC}"
    
    # 检查是否在项目根目录
    if [ ! -f "docker-compose.yml" ]; then
        print_error "请在项目根目录运行此脚本"
        exit 1
    fi
    
    # 执行部署步骤
    check_docker
    init_data_dirs              # 新增: 初始化数据目录
    backup_existing_db "dev"    # 新增: 备份开发环境数据
    create_directories
    build_frontend
    prepare_backend
    start_containers
    init_application
    health_check
    show_info
    
    # 新增: 数据隔离提示
    print_header "数据隔离提示"
    print_status "开发环境数据: data/dev/database.sqlite"
    print_status "生产环境数据: data/prod/database.sqlite"
    print_info "详细说明请查看: DOCKER_DATA_ISOLATION_GUIDE.md"
}

# 运行主函数
main

