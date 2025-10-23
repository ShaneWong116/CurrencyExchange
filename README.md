# 财务管理系统

一个专为外勤人员设计的财务交易录入系统，支持离线操作和数据同步。

## 项目概述

本系统包含前端H5应用和后端API服务，主要功能包括：

- **交易录入**: 支持入账、出账、兑换三种交易类型
- **草稿管理**: 可保存未完成的交易为草稿
- **离线操作**: 支持离线录入，网络恢复后自动同步
- **图片上传**: 支持交易凭证图片上传
- **PWA支持**: 可安装到手机桌面，提供原生应用体验

## 技术架构

### 后端 (Laravel 9)
- **框架**: Laravel 9 + PHP 8.0+
- **数据库**: MySQL 8.0
- **缓存**: Redis 7.0
- **认证**: Laravel Sanctum (Token认证)
- **图片处理**: Intervention Image

### 前端 (Vue 3 + Quasar)
- **框架**: Vue 3 + Composition API
- **UI库**: Quasar Framework
- **状态管理**: Pinia
- **PWA**: Vite PWA Plugin
- **离线存储**: IndexedDB + LocalStorage

## 快速开始

### 环境要求

- PHP 8.0+
- Node.js 16+
- MySQL 8.0+
- Redis 7.0+
- Composer
- NPM/Yarn

### 后端安装

1. **克隆项目**
```bash
cd backend
```

2. **安装依赖**
```bash
composer install
```

3. **环境配置**
```bash
cp .env.example .env
# 配置数据库和Redis连接信息
```

4. **生成密钥**
```bash
php artisan key:generate
```

5. **数据库迁移**
```bash
php artisan migrate --seed
```

6. **启动服务**
```bash
php artisan serve --host=0.0.0.0 --port=8000
```

### 前端安装

1. **进入前端目录**
```bash
cd frontend
```

2. **安装依赖**
```bash
npm install
```

3. **环境配置**
```bash
# 创建 .env.local 文件
VITE_API_BASE_URL=http://localhost:8000/api
```

4. **启动开发服务器**
```bash
npm run dev
```

5. **构建生产版本**
```bash
npm run build
```

## 默认账户

### 外勤人员账户 (前端登录)
- **用户名**: field001 / field002 / field003
- **密码**: 123456

### 后台管理账户
- **管理员**: admin / admin123
- **财务**: finance / finance123

## 主要功能

### 1. 用户认证
- Token认证机制
- 自动登出 (15分钟无操作)
- Refresh Token刷新

### 2. 交易录入
- **入账**: 人民币增加，港币减少
- **出账**: 人民币减少，港币增加  
- **兑换**: 货币兑换交易
- 支持图片上传作为凭证

### 3. 草稿管理
- 本地保存未完成交易
- 云端同步草稿数据
- 支持草稿编辑和提交

### 4. 离线功能
- IndexedDB本地存储
- 离线录入数据
- 网络恢复自动同步
- 冲突解决机制

### 5. PWA特性
- 可安装到桌面
- 离线缓存策略
- 后台同步
- 推送通知支持

## API文档

### 认证接口
- `POST /api/auth/login` - 用户登录
- `POST /api/auth/refresh` - 刷新Token
- `POST /api/auth/logout` - 退出登录
- `GET /api/auth/me` - 获取用户信息

### 交易接口
- `GET /api/transactions` - 获取交易列表
- `POST /api/transactions` - 创建交易
- `POST /api/transactions/batch` - 批量创建交易
- `GET /api/transactions/{id}` - 获取交易详情

### 草稿接口
- `GET /api/drafts` - 获取草稿列表
- `POST /api/drafts` - 创建草稿
- `PUT /api/drafts/{id}` - 更新草稿
- `DELETE /api/drafts/{id}` - 删除草稿
- `POST /api/drafts/{id}/submit` - 提交草稿
- `POST /api/drafts/batch-sync` - 批量同步草稿

### 其他接口
- `GET /api/channels` - 获取支付渠道
- `POST /api/images` - 上传图片
- `GET /api/images/{uuid}` - 获取图片

## 数据库设计

### 主要数据表
- `users` - 后台用户表
- `field_users` - 外勤人员表
- `channels` - 支付渠道表
- `transactions` - 交易记录表
- `transaction_drafts` - 交易草稿表
- `images` - 图片存储表
- `settings` - 系统配置表

## 部署指南

### 后端部署

1. **服务器环境配置**
```bash
# 安装PHP、MySQL、Redis、Nginx
sudo apt update
sudo apt install php8.0-fpm mysql-server redis-server nginx
```

2. **项目部署**
```bash
# 上传代码
git clone <repository>
cd backend

# 安装依赖
composer install --optimize-autoloader --no-dev

# 配置环境
cp .env.example .env
# 编辑 .env 配置生产环境参数

# 数据库迁移
php artisan migrate --force
php artisan db:seed --force

# 优化
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

3. **Nginx配置**
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/backend/public;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
    }
}
```

### 前端部署

1. **构建生产版本**
```bash
cd frontend
npm run build
```

2. **上传到Web服务器**
```bash
# 将 dist/ 目录内容上传到 Web 服务器
rsync -av dist/ user@server:/var/www/html/
```

3. **Nginx配置** (SPA路由支持)
```nginx
server {
    listen 80;
    server_name your-app.com;
    root /var/www/html;
    
    location / {
        try_files $uri $uri/ /index.html;
    }
}
```

## 故障排除

### 常见问题

1. **CORS错误**
   - 检查后端 `config/cors.php` 配置
   - 确保前端域名在允许列表中

2. **Token过期**
   - 检查系统时间同步
   - 调整Token过期时间配置

3. **离线数据丢失**
   - 检查浏览器IndexedDB存储限制
   - 清理过期数据

4. **图片上传失败**
   - 检查PHP文件上传大小限制
   - 验证存储权限

### 日志位置
- 后端日志: `backend/storage/logs/laravel.log`
- 前端错误: 浏览器开发者工具Console
- Nginx日志: `/var/log/nginx/error.log`

## 开发团队

- **项目负责人**: Currency Exchange Team
- **技术栈**: Laravel + Vue 3 + Quasar
- **版本**: v1.0.0

## 许可证

MIT License

## 更新日志

### v1.0.0 (2024-09-08)
- 初始版本发布
- 完整的交易录入功能
- 离线操作支持
- PWA特性实现
