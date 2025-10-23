# 财务管理系统完整安装指南

## 🎯 项目概述

本项目是一个完整的财务管理系统，包含：
- **后端管理系统** (Laravel + Filament)
- **前端H5应用** (Vue3 + Quasar PWA)
- **移动端优化** (响应式设计 + 离线支持)

## 📋 环境要求

### 必需软件
- **PHP** 8.0+ (推荐 8.2+)
- **Node.js** 16+ (推荐 18+)
- **MySQL** 8.0+
- **Redis** 7.0+ (可选，用于缓存)
- **Composer** 2.0+
- **NPM** 8.0+

### 推荐工具
- **Git** (版本控制)
- **VS Code** (开发工具)
- **Postman** (API测试)

## 🚀 快速安装

### 方法一：使用自动化脚本 (推荐)

```bash
# 1. 克隆项目 (如果从Git仓库)
git clone <repository-url>
cd CurrencyExSystem

# 2. 运行安装脚本
chmod +x deploy.sh
./deploy.sh development

# 3. 按照脚本提示完成配置
```

### 方法二：手动安装

#### 步骤1：后端安装

```bash
# 进入后端目录
cd backend

# 安装PHP依赖
composer install

# 创建环境配置
cp .env.example .env

# 生成应用密钥
php artisan key:generate

# 配置数据库 (编辑 .env 文件)
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=currency_exchange
# DB_USERNAME=root
# DB_PASSWORD=your_password

# 运行数据库迁移和种子
php artisan migrate --seed

# 启动开发服务器
php artisan serve
```

#### 步骤2：前端安装

```bash
# 进入前端目录
cd frontend

# 安装依赖
npm install

# 创建环境配置
echo "VITE_API_BASE_URL=http://localhost:8000/api" > .env.local

# 启动开发服务器
npm run dev
```

## 🔧 详细配置

### 数据库配置

1. **创建数据库**
```sql
CREATE DATABASE currency_exchange CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. **配置连接** (backend/.env)
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=currency_exchange
DB_USERNAME=root
DB_PASSWORD=your_password
```

3. **运行迁移**
```bash
cd backend
php artisan migrate --seed
```

### Redis配置 (可选)

```env
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### 前端环境配置

创建 `frontend/.env.local`:
```env
# API地址
VITE_API_BASE_URL=http://localhost:8000/api

# 应用信息
VITE_APP_NAME=财务管理系统
VITE_APP_VERSION=1.0.0

# 调试模式
VITE_ENABLE_DEBUG=true
```

## 👥 默认账户

### 后台管理员
- **用户名**: admin
- **密码**: admin123
- **权限**: 超级管理员

### 财务人员
- **用户名**: finance  
- **密码**: finance123
- **权限**: 财务管理

### 外勤人员 (前端登录)
- **用户名**: field001 / field002 / field003
- **密码**: 123456
- **用途**: H5应用登录

## 🌐 访问地址

### 开发环境
- **后端API**: http://localhost:8000
- **后台管理**: http://localhost:8000/admin
- **前端应用**: http://localhost:3000
- **API文档**: http://localhost:8000/api/health

### 生产环境
根据实际部署域名调整

## ✅ 功能测试

### 1. 后端API测试
```bash
# 健康检查
curl http://localhost:8000/api/health

# 登录测试
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"field001","password":"123456"}'
```

### 2. 前端功能测试
1. 访问 http://localhost:3000
2. 使用 field001/123456 登录
3. 测试交易录入功能
4. 测试草稿保存功能
5. 测试离线模式

### 3. 后台管理测试
1. 访问 http://localhost:8000/admin
2. 使用 admin/admin123 登录
3. 查看仪表盘统计
4. 管理交易记录
5. 配置系统设置

## 🔍 故障排除

### 常见问题

#### 1. Composer安装失败
```bash
# 清理缓存
composer clear-cache

# 使用国内镜像
composer config repo.packagist composer https://mirrors.aliyun.com/composer/

# 重新安装
composer install --no-dev --optimize-autoloader
```

#### 2. NPM安装失败
```bash
# 清理缓存
npm cache clean --force

# 使用国内镜像
npm config set registry https://registry.npm.taobao.org/

# 重新安装
npm install
```

#### 3. 数据库连接失败
- 检查MySQL服务是否启动
- 确认数据库用户权限
- 验证连接参数正确性

#### 4. 端口冲突
```bash
# 查看端口占用
netstat -ano | findstr :8000
netstat -ano | findstr :3000

# 修改端口
php artisan serve --port=8001
npm run dev -- --port 3001
```

#### 5. 权限问题 (Linux/Mac)
```bash
# 设置存储目录权限
chmod -R 755 backend/storage
chmod -R 755 backend/bootstrap/cache

# 设置所有者
chown -R www-data:www-data backend/storage
```

### 日志文件位置
- **Laravel日志**: `backend/storage/logs/laravel.log`
- **Nginx日志**: `/var/log/nginx/error.log`
- **PHP日志**: 检查 `php.ini` 中的 `error_log` 设置

## 📱 PWA安装

### 移动端安装
1. 用手机浏览器访问前端应用
2. 点击浏览器菜单中的"添加到主屏幕"
3. 应用将以类原生方式运行

### 桌面端安装 (Chrome)
1. 访问前端应用
2. 地址栏会显示安装图标
3. 点击安装即可添加到桌面

## 🔄 更新和维护

### 代码更新
```bash
# 拉取最新代码
git pull origin main

# 更新后端依赖
cd backend && composer install

# 更新前端依赖  
cd frontend && npm install

# 运行数据库迁移
cd backend && php artisan migrate
```

### 数据备份
```bash
# 备份数据库
mysqldump -u root -p currency_exchange > backup_$(date +%Y%m%d).sql

# 备份上传文件
tar -czf uploads_backup_$(date +%Y%m%d).tar.gz backend/storage/app/public
```

### 性能优化
```bash
# Laravel优化
cd backend
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 前端构建优化
cd frontend
npm run build
```

## 📞 技术支持

### 开发团队
- **项目负责人**: Currency Exchange Team
- **技术栈**: Laravel + Vue3 + Quasar + MySQL
- **版本**: v1.0.0

### 文档资源
- **Laravel文档**: https://laravel.com/docs
- **Vue3文档**: https://vuejs.org/
- **Quasar文档**: https://quasar.dev/
- **Filament文档**: https://filamentphp.com/

---

🎉 **恭喜！您已成功安装财务管理系统！**

如有任何问题，请查看故障排除部分或联系技术支持团队。
