#!/bin/bash

# 财务管理系统部署脚本
# 使用方法: ./deploy.sh [环境]
# 环境: development (默认) | production

set -e

ENVIRONMENT=${1:-development}
PROJECT_DIR=$(pwd)
BACKEND_DIR="$PROJECT_DIR/backend"
FRONTEND_DIR="$PROJECT_DIR/frontend"

echo "🚀 开始部署财务管理系统 - 环境: $ENVIRONMENT"

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
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

# 检查依赖
check_dependencies() {
    echo "🔍 检查系统依赖..."
    
    # 检查PHP
    if ! command -v php &> /dev/null; then
        print_error "PHP 未安装"
        exit 1
    fi
    
    # 检查Composer
    if ! command -v composer &> /dev/null; then
        print_error "Composer 未安装"
        exit 1
    fi
    
    # 检查Node.js
    if ! command -v node &> /dev/null; then
        print_error "Node.js 未安装"
        exit 1
    fi
    
    # 检查NPM
    if ! command -v npm &> /dev/null; then
        print_error "NPM 未安装"
        exit 1
    fi
    
    print_status "系统依赖检查通过"
}

# 部署后端
deploy_backend() {
    echo "📦 部署后端服务..."
    
    cd "$BACKEND_DIR"
    
    # 安装依赖
    if [ "$ENVIRONMENT" = "production" ]; then
        print_status "安装生产环境依赖..."
        composer install --optimize-autoloader --no-dev --no-interaction
    else
        print_status "安装开发环境依赖..."
        composer install
    fi
    
    # 环境配置
    if [ ! -f .env ]; then
        if [ "$ENVIRONMENT" = "production" ]; then
            cp .env.production .env
            print_warning "请手动配置 .env 文件中的数据库和其他设置"
        else
            cp .env.example .env
        fi
    fi
    
    # 生成密钥
    if ! grep -q "APP_KEY=" .env || [ -z "$(grep '^APP_KEY=' .env | cut -d'=' -f2)" ]; then
        php artisan key:generate --no-interaction
        print_status "应用密钥已生成"
    fi
    
    # 数据库迁移
    read -p "是否执行数据库迁移？(y/N): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        if [ "$ENVIRONMENT" = "production" ]; then
            php artisan migrate --force
        else
            php artisan migrate --seed
        fi
        print_status "数据库迁移完成"
    fi
    
    # 优化（生产环境）
    if [ "$ENVIRONMENT" = "production" ]; then
        php artisan config:cache
        php artisan route:cache
        php artisan view:cache
        print_status "Laravel 优化完成"
    fi
    
    # 设置权限
    chmod -R 755 storage bootstrap/cache
    print_status "文件权限设置完成"
    
    print_status "后端部署完成"
}

# 部署前端
deploy_frontend() {
    echo "🎨 部署前端应用..."
    
    cd "$FRONTEND_DIR"
    
    # 安装依赖
    print_status "安装前端依赖..."
    npm install
    
    # 环境配置
    if [ ! -f .env.local ]; then
        if [ "$ENVIRONMENT" = "production" ]; then
            cat > .env.local << EOF
VITE_API_BASE_URL=https://api.your-domain.com/api
VITE_APP_NAME=财务管理系统
VITE_APP_VERSION=1.0.0
EOF
            print_warning "请手动配置 .env.local 文件中的API地址"
        else
            cat > .env.local << EOF
VITE_API_BASE_URL=http://localhost:8000/api
VITE_APP_NAME=财务管理系统
VITE_APP_VERSION=1.0.0
VITE_ENABLE_DEBUG=true
EOF
        fi
    fi
    
    # 构建
    if [ "$ENVIRONMENT" = "production" ]; then
        print_status "构建生产版本..."
        npm run build
        
        # 生成部署包
        if [ -d "dist" ]; then
            tar -czf "../frontend-dist-$(date +%Y%m%d-%H%M%S).tar.gz" -C dist .
            print_status "生产包已创建: frontend-dist-*.tar.gz"
        fi
    else
        print_status "开发环境构建完成，运行 'npm run dev' 启动开发服务器"
    fi
    
    print_status "前端部署完成"
}

# 创建systemd服务（生产环境）
create_systemd_service() {
    if [ "$ENVIRONMENT" != "production" ]; then
        return
    fi
    
    read -p "是否创建systemd服务？(y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        return
    fi
    
    cat > /tmp/currency-exchange-queue.service << EOF
[Unit]
Description=Currency Exchange Queue Worker
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
Restart=always
RestartSec=5s
ExecStart=/usr/bin/php $BACKEND_DIR/artisan queue:work --sleep=3 --tries=3 --max-time=3600
WorkingDirectory=$BACKEND_DIR

[Install]
WantedBy=multi-user.target
EOF
    
    sudo mv /tmp/currency-exchange-queue.service /etc/systemd/system/
    sudo systemctl daemon-reload
    sudo systemctl enable currency-exchange-queue
    
    print_status "Systemd 服务已创建"
}

# 创建Nginx配置
create_nginx_config() {
    if [ "$ENVIRONMENT" != "production" ]; then
        return
    fi
    
    read -p "是否创建Nginx配置？(y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        return
    fi
    
    read -p "请输入域名: " DOMAIN_NAME
    
    # 后端API配置
    cat > /tmp/currency-exchange-api.conf << EOF
server {
    listen 80;
    server_name api.$DOMAIN_NAME;
    root $BACKEND_DIR/public;
    index index.php;
    
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }
    
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF
    
    # 前端应用配置
    cat > /tmp/currency-exchange-app.conf << EOF
server {
    listen 80;
    server_name $DOMAIN_NAME app.$DOMAIN_NAME;
    root $FRONTEND_DIR/dist;
    index index.html;
    
    location / {
        try_files \$uri \$uri/ /index.html;
    }
    
    location /api/ {
        proxy_pass http://api.$DOMAIN_NAME;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }
}
EOF
    
    print_warning "Nginx配置文件已生成到 /tmp/ 目录"
    print_warning "请手动复制到 /etc/nginx/sites-available/ 并启用"
}

# 健康检查
health_check() {
    echo "🏥 执行健康检查..."
    
    # 检查后端
    cd "$BACKEND_DIR"
    if php artisan --version &> /dev/null; then
        print_status "后端服务正常"
    else
        print_error "后端服务异常"
    fi
    
    # 检查前端构建
    if [ "$ENVIRONMENT" = "production" ] && [ -d "$FRONTEND_DIR/dist" ]; then
        print_status "前端构建产物存在"
    elif [ "$ENVIRONMENT" = "development" ]; then
        print_status "开发环境前端检查通过"
    else
        print_error "前端构建产物不存在"
    fi
}

# 显示部署信息
show_deployment_info() {
    echo
    echo "📋 部署完成信息:"
    echo "===================="
    echo "环境: $ENVIRONMENT"
    echo "项目目录: $PROJECT_DIR"
    echo "后端目录: $BACKEND_DIR"
    echo "前端目录: $FRONTEND_DIR"
    echo
    
    if [ "$ENVIRONMENT" = "development" ]; then
        echo "🛠️  开发环境启动命令:"
        echo "后端: cd backend && php artisan serve"
        echo "前端: cd frontend && npm run dev"
        echo
        echo "默认访问地址:"
        echo "后端API: http://localhost:8000"
        echo "前端应用: http://localhost:3000"
    else
        echo "🚀 生产环境部署完成"
        echo "请配置Web服务器指向相应目录"
    fi
    
    echo
    echo "📝 默认测试账户:"
    echo "外勤人员: field001/field002/field003 (密码: 123456)"
    echo "后台管理: admin/admin123, finance/finance123"
}

# 主执行流程
main() {
    check_dependencies
    deploy_backend
    deploy_frontend
    
    if [ "$ENVIRONMENT" = "production" ]; then
        create_systemd_service
        create_nginx_config
    fi
    
    health_check
    show_deployment_info
    
    print_status "🎉 部署完成！"
}

# 执行主流程
main
