#!/bin/bash

# ============================================
# 本地构建并推送到 Coding 制品库
# Exchange System
# ============================================

set -e  # 遇到错误立即退出

# 颜色输出
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 日志函数
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# 打印横幅
print_banner() {
    echo -e "${GREEN}"
    echo "============================================"
    echo "  本地构建并推送 Docker 镜像"
    echo "============================================"
    echo -e "${NC}"
}

# 配置信息 - 阿里云容器镜像服务
DOCKER_REGISTRY="crpi-nsc415g542h2toto.cn-shenzhen.personal.cr.aliyuncs.com"
DOCKER_NAMESPACE="currencyexchange"
PROJECT_NAME="currency_exchange"
DOCKER_USER="张同学t134"
# 密码需要在阿里云容器镜像服务的访问凭证页面设置
DOCKER_PWD="9T.!DJe9aVxi5-u"

# 版本号（使用时间戳）
VERSION=$(date +%Y%m%d-%H%M%S)

print_banner

log_info "配置信息"
echo "  仓库地址: ${DOCKER_REGISTRY}"
echo "  命名空间: ${DOCKER_NAMESPACE}"
echo "  项目名称: ${PROJECT_NAME}"
echo "  版本号: ${VERSION}"
echo

# 检查 Docker 服务
log_info "检查 Docker 服务..."
if ! docker ps >/dev/null 2>&1; then
    log_error "Docker 服务未运行，请先启动 Docker"
    exit 1
fi
log_success "Docker 服务正常运行"
echo

# 登录 Docker 仓库
log_info "登录 Coding Docker 仓库..."
echo "${DOCKER_PWD}" | docker login "${DOCKER_REGISTRY}" -u "${DOCKER_USER}" --password-stdin
if [ $? -ne 0 ]; then
    log_error "Docker 登录失败"
    exit 1
fi
log_success "Docker 登录成功"
echo

# 构建后端镜像
log_info "构建后端镜像..."
echo "  镜像名称: ${DOCKER_REGISTRY}/${DOCKER_NAMESPACE}/${PROJECT_NAME}/backend:${VERSION}"
echo "  镜像名称: ${DOCKER_REGISTRY}/${DOCKER_NAMESPACE}/${PROJECT_NAME}/backend:latest"
echo

cd backend
docker build --no-cache \
    -t "${DOCKER_REGISTRY}/${DOCKER_NAMESPACE}/${PROJECT_NAME}/backend:${VERSION}" \
    -t "${DOCKER_REGISTRY}/${DOCKER_NAMESPACE}/${PROJECT_NAME}/backend:latest" \
    .

if [ $? -ne 0 ]; then
    log_error "后端镜像构建失败"
    cd ..
    exit 1
fi
log_success "后端镜像构建完成"
cd ..
echo

# 构建前端
log_info "构建前端应用..."
cd frontend

if ! npm install; then
    log_error "npm install 失败"
    cd ..
    exit 1
fi

if ! npm run build; then
    log_error "前端构建失败"
    cd ..
    exit 1
fi

log_success "前端构建完成"
cd ..
echo

# 创建 Nginx Dockerfile
log_info "创建 Nginx Dockerfile..."
cat > Dockerfile.nginx <<EOF
FROM nginx:alpine

# 安装 wget 用于健康检查
RUN apk add --no-cache wget

# 复制前端构建产物
COPY frontend/dist /var/www/html/frontend

# 复制 Nginx 配置
COPY docker/nginx/conf.d /etc/nginx/conf.d

# 设置权限
RUN chmod -R 755 /var/www/html

# 健康检查
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
  CMD wget --no-verbose --tries=1 --spider http://localhost || exit 1

EXPOSE 80 443

CMD ["nginx", "-g", "daemon off;"]
EOF

log_success "Dockerfile.nginx 创建完成"
echo

# 构建 Nginx 镜像
log_info "构建 Nginx 镜像..."
echo "  镜像名称: ${DOCKER_REGISTRY}/${DOCKER_NAMESPACE}/${PROJECT_NAME}/nginx:${VERSION}"
echo "  镜像名称: ${DOCKER_REGISTRY}/${DOCKER_NAMESPACE}/${PROJECT_NAME}/nginx:latest"
echo

docker build --no-cache \
    -f Dockerfile.nginx \
    -t "${DOCKER_REGISTRY}/${DOCKER_NAMESPACE}/${PROJECT_NAME}/nginx:${VERSION}" \
    -t "${DOCKER_REGISTRY}/${DOCKER_NAMESPACE}/${PROJECT_NAME}/nginx:latest" \
    .

if [ $? -ne 0 ]; then
    log_error "Nginx 镜像构建失败"
    exit 1
fi
log_success "Nginx 镜像构建完成"
echo

# 推送后端镜像
log_info "推送后端镜像..."
docker push "${DOCKER_REGISTRY}/${DOCKER_NAMESPACE}/${PROJECT_NAME}/backend:${VERSION}"
docker push "${DOCKER_REGISTRY}/${DOCKER_NAMESPACE}/${PROJECT_NAME}/backend:latest"

if [ $? -ne 0 ]; then
    log_error "推送后端镜像失败"
    exit 1
fi
log_success "后端镜像推送完成"
echo

# 推送 Nginx 镜像
log_info "推送 Nginx 镜像..."
docker push "${DOCKER_REGISTRY}/${DOCKER_NAMESPACE}/${PROJECT_NAME}/nginx:${VERSION}"
docker push "${DOCKER_REGISTRY}/${DOCKER_NAMESPACE}/${PROJECT_NAME}/nginx:latest"

if [ $? -ne 0 ]; then
    log_error "推送 Nginx 镜像失败"
    exit 1
fi
log_success "Nginx 镜像推送完成"
echo

# 清理临时文件
log_info "清理临时文件..."
rm -f Dockerfile.nginx
log_success "清理完成"
echo

# 显示镜像信息
echo -e "${GREEN}============================================${NC}"
echo -e "${GREEN}  构建和推送完成！${NC}"
echo -e "${GREEN}============================================${NC}"
echo
echo "镜像地址:"
echo "  后端镜像:"
echo "    - ${DOCKER_REGISTRY}/${DOCKER_NAMESPACE}/${PROJECT_NAME}/backend:${VERSION}"
echo "    - ${DOCKER_REGISTRY}/${DOCKER_NAMESPACE}/${PROJECT_NAME}/backend:latest"
echo
echo "  Nginx 镜像:"
echo "    - ${DOCKER_REGISTRY}/${DOCKER_NAMESPACE}/${PROJECT_NAME}/nginx:${VERSION}"
echo "    - ${DOCKER_REGISTRY}/${DOCKER_NAMESPACE}/${PROJECT_NAME}/nginx:latest"
echo

# 显示本地镜像
log_info "本地镜像列表:"
docker images | grep -E "currencyexchange|exchange-system" || true
echo

log_success "🎉 完成！"

