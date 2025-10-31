# 🐳 Docker 部署指南 - 最简单的部署方式

使用 Docker 部署系统，**一条命令搞定所有环境配置**！

---

## 🎯 为什么选择 Docker？

### ✅ 优势

| 特性 | 传统部署 | Docker部署 |
|-----|---------|-----------|
| 环境配置 | ❌ 复杂，需要手动安装 | ✅ 自动化，一键完成 |
| 依赖管理 | ❌ 容易冲突 | ✅ 完全隔离 |
| 部署时间 | ⏱️ 2-4小时 | ⏱️ 10-20分钟 |
| 环境一致性 | ⚠️ 可能不一致 | ✅ 完全一致 |
| 回滚 | ❌ 困难 | ✅ 一键回滚 |
| 扩展 | ⚠️ 手动扩展 | ✅ 一键扩展 |

### 🎁 特别适合

- ✅ 不想手动配置环境
- ✅ 多环境部署（测试、生产）
- ✅ 快速上线
- ✅ 团队协作
- ✅ 云服务器部署

---

## 📋 准备工作

### 1. 服务器要求

**最低配置**：
- CPU: 2核
- 内存: 4GB
- 存储: 20GB
- 操作系统: Linux（Ubuntu 20.04+ / CentOS 7+）

**推荐配置**：
- CPU: 4核
- 内存: 8GB
- 存储: 40GB

### 2. 安装 Docker

#### Ubuntu / Debian

```bash
# 更新系统
sudo apt update

# 安装 Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh

# 启动 Docker
sudo systemctl start docker
sudo systemctl enable docker

# 验证安装
docker --version
docker-compose --version
```

#### CentOS / RHEL

```bash
# 安装 Docker
sudo yum install -y yum-utils
sudo yum-config-manager --add-repo https://download.docker.com/linux/centos/docker-ce.repo
sudo yum install -y docker-ce docker-ce-cli containerd.io

# 启动 Docker
sudo systemctl start docker
sudo systemctl enable docker

# 安装 Docker Compose
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose
```

### 3. 配置 Docker（可选但推荐）

```bash
# 添加当前用户到 docker 组（避免每次用 sudo）
sudo usermod -aG docker $USER

# 重新登录或执行
newgrp docker

# 配置 Docker 镜像加速（国内推荐）
sudo mkdir -p /etc/docker
sudo tee /etc/docker/daemon.json <<-'EOF'
{
  "registry-mirrors": [
    "https://docker.mirrors.ustc.edu.cn",
    "https://hub-mirror.c.163.com"
  ]
}
EOF

# 重启 Docker
sudo systemctl daemon-reload
sudo systemctl restart docker
```

---

## 🚀 快速部署（三步走）

### 步骤 1：上传代码

```bash
# 方式一：使用 Git
cd /opt
git clone https://your-repo-url/currency-exchange.git
cd currency-exchange

# 方式二：使用 SFTP/SCP 上传代码包
# 上传到 /opt/currency-exchange
```

### 步骤 2：构建前端

```bash
cd /opt/currency-exchange/frontend

# 安装依赖（如果本地已构建，可跳过）
npm install

# 创建生产环境配置
cat > .env.production << EOF
VITE_API_BASE_URL=http://your-domain.com/api
VITE_APP_NAME=财务管理系统
EOF

# 构建前端
npm run build

# 验证构建产物
ls -l dist/
```

### 步骤 3：启动 Docker 容器

```bash
cd /opt/currency-exchange

# 创建并启动所有服务
docker-compose up -d

# 查看容器状态
docker-compose ps

# 查看日志
docker-compose logs -f
```

**就这么简单！** 🎉

---

## 🔧 初始化应用

首次部署需要初始化：

```bash
# 进入后端容器
docker-compose exec backend sh

# 生成应用密钥
php artisan key:generate

# 数据库迁移
php artisan migrate --force

# 填充初始数据
php artisan db:seed --force

# 优化（可选）
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 退出容器
exit
```

---

## 🌐 访问应用

### 本地测试

- **前端应用**: http://localhost
- **后端API**: http://localhost/api
- **健康检查**: http://localhost/api/health

### 生产环境

需要配置域名和 Nginx 反向代理（见下文）。

---

## 🔐 配置 HTTPS（生产环境）

### 方法一：使用 Let's Encrypt（推荐）

```bash
# 安装 Certbot
sudo apt install -y certbot

# 申请证书
sudo certbot certonly --standalone -d your-domain.com -d api.your-domain.com

# 证书保存在 /etc/letsencrypt/live/your-domain.com/

# 复制证书到项目
sudo cp /etc/letsencrypt/live/your-domain.com/fullchain.pem docker/nginx/ssl/
sudo cp /etc/letsencrypt/live/your-domain.com/privkey.pem docker/nginx/ssl/
```

### 方法二：上传自己的证书

将证书文件放到 `docker/nginx/ssl/` 目录：
- `fullchain.pem` - 证书文件
- `privkey.pem` - 私钥文件

### 更新 Nginx 配置

编辑 `docker/nginx/conf.d/default.conf`，添加 HTTPS 配置：

```nginx
# HTTPS 配置示例
server {
    listen 443 ssl http2;
    server_name your-domain.com;
    
    ssl_certificate /etc/nginx/ssl/fullchain.pem;
    ssl_certificate_key /etc/nginx/ssl/privkey.pem;
    
    # ... 其他配置同 HTTP ...
}

# HTTP 跳转 HTTPS
server {
    listen 80;
    server_name your-domain.com;
    return 301 https://$server_name$request_uri;
}
```

重启 Nginx：

```bash
docker-compose restart nginx
```

---

## 📊 管理容器

### 常用命令

```bash
# 查看所有容器状态
docker-compose ps

# 查看实时日志
docker-compose logs -f

# 查看特定服务日志
docker-compose logs -f backend
docker-compose logs -f nginx

# 重启所有服务
docker-compose restart

# 重启特定服务
docker-compose restart backend

# 停止所有服务
docker-compose stop

# 启动所有服务
docker-compose start

# 停止并删除所有容器
docker-compose down

# 停止并删除所有容器和数据卷（⚠️ 会删除数据）
docker-compose down -v
```

### 进入容器

```bash
# 进入后端容器
docker-compose exec backend sh

# 进入 Nginx 容器
docker-compose exec nginx sh

# 进入队列容器
docker-compose exec queue sh
```

### 更新代码

```bash
# 拉取最新代码
git pull

# 重新构建并重启
docker-compose up -d --build

# 运行迁移（如有数据库变更）
docker-compose exec backend php artisan migrate --force

# 清除缓存
docker-compose exec backend php artisan cache:clear
docker-compose exec backend php artisan config:cache
```

---

## 🔄 数据备份与恢复

### 备份 SQLite 数据库

```bash
# 创建备份目录
mkdir -p /opt/backups

# 备份数据库
docker-compose exec backend cp /var/www/html/database/database.sqlite /tmp/backup.sqlite
docker cp currency-backend:/tmp/backup.sqlite /opt/backups/db_$(date +%Y%m%d_%H%M%S).sqlite

# 或者直接从主机复制
cp backend/database/database.sqlite /opt/backups/db_$(date +%Y%m%d_%H%M%S).sqlite
```

### 恢复数据库

```bash
# 恢复备份
cp /opt/backups/db_20241030_120000.sqlite backend/database/database.sqlite

# 重启容器
docker-compose restart backend
```

### 自动备份脚本

创建 `/opt/backup.sh`：

```bash
#!/bin/bash
BACKUP_DIR="/opt/backups"
DATE=$(date +%Y%m%d_%H%M%S)

# 创建备份
docker-compose -f /opt/currency-exchange/docker-compose.yml exec -T backend \
    cat /var/www/html/database/database.sqlite > $BACKUP_DIR/db_$DATE.sqlite

# 压缩备份
gzip $BACKUP_DIR/db_$DATE.sqlite

# 删除30天前的备份
find $BACKUP_DIR -name "db_*.sqlite.gz" -mtime +30 -delete

echo "Backup completed: $BACKUP_DIR/db_$DATE.sqlite.gz"
```

设置定时任务：

```bash
chmod +x /opt/backup.sh

# 添加到 crontab（每天凌晨2点备份）
crontab -e
# 添加：
0 2 * * * /opt/backup.sh >> /var/log/backup.log 2>&1
```

---

## 📈 监控与日志

### 查看容器资源使用

```bash
# 实时监控
docker stats

# 查看特定容器
docker stats currency-backend currency-nginx
```

### 日志管理

```bash
# 查看所有日志
docker-compose logs

# 只看最近100行
docker-compose logs --tail=100

# 实时跟踪日志
docker-compose logs -f

# 查看特定服务日志
docker-compose logs backend
docker-compose logs nginx

# Laravel 应用日志
docker-compose exec backend tail -f storage/logs/laravel.log
```

### 日志轮转配置

创建 `/etc/logrotate.d/docker-currency`：

```
/var/lib/docker/containers/*/*.log {
    daily
    rotate 7
    compress
    delaycompress
    missingok
    notifempty
    copytruncate
}
```

---

## 🔧 性能优化

### 1. 配置资源限制

编辑 `docker-compose.yml`，添加资源限制：

```yaml
services:
  backend:
    # ... 其他配置 ...
    deploy:
      resources:
        limits:
          cpus: '2'
          memory: 2G
        reservations:
          cpus: '1'
          memory: 1G
```

### 2. 启用 OPcache

在 `backend/Dockerfile` 中添加：

```dockerfile
# 启用 OPcache
RUN docker-php-ext-install opcache

# 配置 OPcache
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=256'; \
    echo 'opcache.interned_strings_buffer=16'; \
    echo 'opcache.max_accelerated_files=10000'; \
    echo 'opcache.revalidate_freq=60'; \
} > /usr/local/etc/php/conf.d/opcache.ini
```

### 3. 使用多阶段构建（优化镜像大小）

```dockerfile
# 构建阶段
FROM composer:latest AS composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader

# 运行阶段
FROM php:8.1-fpm-alpine
COPY --from=composer /app/vendor /var/www/html/vendor
# ... 其他配置 ...
```

---

## 🆘 故障排查

### 容器无法启动

```bash
# 查看详细错误
docker-compose logs backend

# 检查配置文件
docker-compose config

# 强制重建
docker-compose up -d --force-recreate --build
```

### 权限问题

```bash
# 进入容器检查权限
docker-compose exec backend ls -la storage/

# 修复权限
docker-compose exec backend chown -R www-data:www-data storage bootstrap/cache
docker-compose exec backend chmod -R 775 storage bootstrap/cache
```

### 端口被占用

```bash
# 查看端口占用
sudo netstat -tulpn | grep :80
sudo netstat -tulpn | grep :443

# 修改 docker-compose.yml 中的端口映射
ports:
  - "8080:80"  # 改用 8080 端口
  - "8443:443"
```

### SQLite 数据库锁定

```bash
# 检查数据库文件
docker-compose exec backend ls -la database/database.sqlite

# 重启所有服务
docker-compose restart
```

### 内存不足

```bash
# 查看系统内存
free -h

# 增加交换空间
sudo fallocate -l 2G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

---

## 🔒 安全建议

### 1. 更新 .env 配置

```bash
# 进入容器
docker-compose exec backend sh

# 修改 .env
vi .env
```

重要配置：

```env
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:生成的密钥

DB_CONNECTION=sqlite

# 修改默认密码
# ...
```

### 2. 限制网络访问

```bash
# 配置防火墙
sudo ufw allow 22/tcp   # SSH
sudo ufw allow 80/tcp   # HTTP
sudo ufw allow 443/tcp  # HTTPS
sudo ufw enable
```

### 3. 定期更新

```bash
# 更新 Docker 镜像
docker-compose pull

# 重新构建
docker-compose up -d --build

# 清理旧镜像
docker image prune -a
```

---

## 📦 完整部署流程总结

```bash
# 1. 安装 Docker
curl -fsSL https://get.docker.com | sh

# 2. 克隆代码
git clone https://your-repo.git /opt/currency-exchange
cd /opt/currency-exchange

# 3. 构建前端
cd frontend
npm install
npm run build
cd ..

# 4. 启动服务
docker-compose up -d

# 5. 初始化
docker-compose exec backend php artisan key:generate
docker-compose exec backend php artisan migrate --force
docker-compose exec backend php artisan db:seed --force

# 6. 验证
curl http://localhost/api/health

# ✅ 完成！
```

**总耗时：10-20分钟** ⏱️

---

## 🎉 优势总结

### 对比传统部署

| 项目 | 传统部署 | Docker部署 |
|-----|---------|-----------|
| 安装软件 | 手动安装PHP、Nginx、MySQL等 | ✅ 自动完成 |
| 配置文件 | 手动编辑多个配置文件 | ✅ 配置文件已准备好 |
| 依赖管理 | 可能版本冲突 | ✅ 完全隔离 |
| 环境一致 | 开发和生产可能不一致 | ✅ 完全一致 |
| 部署时间 | 2-4小时 | ✅ 10-20分钟 |
| 回滚 | 困难 | ✅ 一条命令 |
| 扩展 | 手动配置 | ✅ 修改配置文件 |

---

## 📞 获取帮助

遇到问题时：

1. 查看容器日志：`docker-compose logs -f`
2. 检查容器状态：`docker-compose ps`
3. 查看本文档的故障排查章节
4. 检查 Docker 官方文档

---

**恭喜！** 🎉

您已经掌握了使用 Docker 部署财务管理系统的完整流程！

Docker 部署确实是最简单、最快速、最可靠的方式！

