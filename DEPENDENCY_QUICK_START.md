# 依赖快速安装指南

> 快速配置项目所需的所有依赖

---

## ⚡ 快速检查

在开始之前，先运行检查工具：

```bash
# 检查PHP扩展
php check_php_extensions.php

# 检查PHP版本
php -v  # 需要 8.3.5+

# 检查Node.js版本
node -v  # 需要 16+

# 检查Composer
composer -V
```

---

## 🔧 第一步: 启用PHP扩展

### Windows (ServBay)

1. **打开php.ini文件**
   ```bash
   notepad D:\ServBay\etc\php\current\php.ini
   ```

2. **查找并启用以下扩展** (删除行首分号 `;`)
   ```ini
   extension=curl
   extension=fileinfo
   extension=pdo_mysql
   extension=gd
   extension=zip
   ```

3. **保存文件并重启服务**
   - 重启ServBay或Web服务器

4. **验证扩展已启用**
   ```bash
   php check_php_extensions.php
   ```

### 其他环境

详见 [PHP_EXTENSIONS_SETUP.md](PHP_EXTENSIONS_SETUP.md)

---

## 📦 第二步: 安装后端依赖

```bash
# 进入后端目录
cd backend

# 方式1: 正常安装 (推荐 - PHP扩展已启用后)
composer install

# 方式2: 忽略平台要求 (临时方案)
composer install --ignore-platform-reqs

# 生成应用密钥 (首次安装)
php artisan key:generate

# 运行数据库迁移
php artisan migrate

# 发现包
php artisan package:discover

# 清除缓存
php artisan config:clear
php artisan cache:clear
```

### 常见问题

**问题**: 提示缺少curl扩展
```
解决: 先完成"第一步"启用PHP扩展
```

**问题**: Composer很慢
```bash
# 使用国内镜像
composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/
```

---

## 🎨 第三步: 安装前端依赖

```bash
# 进入前端目录
cd frontend

# 安装依赖
npm install

# 或使用国内镜像
npm install --registry=https://registry.npmmirror.com
```

### 如果npm很慢

```bash
# 使用淘宝镜像
npm config set registry https://registry.npmmirror.com

# 或使用cnpm
npm install -g cnpm --registry=https://registry.npmmirror.com
cnpm install
```

### 更新依赖 (可选)

```bash
# 更新到最新兼容版本
npm update

# 检查过期包
npm outdated
```

---

## ✅ 第四步: 验证安装

### 验证后端

```bash
cd backend

# 检查已安装的包
composer show --installed | wc -l  # 应该有 120+ 个

# 测试Laravel
php artisan --version  # 应显示 Laravel Framework 10.48.x

# 测试数据库连接
php artisan migrate:status

# 启动开发服务器
php artisan serve
# 访问: http://localhost:8000
```

### 验证前端

```bash
cd frontend

# 检查已安装的包
npm list --depth=0  # 应该有 20+ 个

# 测试Vite
npm run dev
# 访问: http://localhost:5173
```

---

## 🚀 第五步: 启动项目

### 使用自动化脚本 (Windows)

```powershell
# 同时启动前后端
.\run-all.ps1
```

### 手动启动

**终端1 - 后端**:
```bash
cd backend
php artisan serve
```

**终端2 - 前端**:
```bash
cd frontend
npm run dev
```

---

## 🛡️ 安全检查 (可选)

### 后端安全审计

```bash
cd backend

# Composer安全审计
composer audit

# 查看详细信息
composer audit --format=plain
```

### 前端安全审计

```bash
cd frontend

# NPM安全审计
npm audit

# 自动修复低风险漏洞
npm audit fix

# 强制修复所有漏洞 (可能有破坏性)
# npm audit fix --force
```

---

## 📋 依赖检查清单

完成以下检查确保所有依赖正确安装：

### PHP环境
- [ ] PHP版本 >= 8.3.5
- [ ] curl扩展已启用
- [ ] fileinfo扩展已启用
- [ ] pdo_mysql扩展已启用
- [ ] gd扩展已启用 (推荐)
- [ ] zip扩展已启用 (推荐)

### Composer依赖
- [ ] Composer已安装
- [ ] 后端依赖已安装 (120+个包)
- [ ] Laravel可以正常启动
- [ ] Filament可以访问

### Node.js环境
- [ ] Node.js >= 16
- [ ] NPM已安装
- [ ] 前端依赖已安装 (20+个包)
- [ ] Vite可以正常启动
- [ ] 前端页面可以访问

### 数据库
- [ ] MySQL/SQLite已安装
- [ ] 数据库已创建
- [ ] 迁移已运行
- [ ] 种子数据已导入 (可选)

---

## 🔄 更新依赖

### 定期更新 (每月)

**后端**:
```bash
cd backend
composer update  # 更新所有依赖
composer show --outdated  # 查看过期包
```

**前端**:
```bash
cd frontend
npm update  # 更新所有依赖
npm outdated  # 查看过期包
```

### 更新单个包

**后端**:
```bash
composer update vendor/package
# 例如: composer update laravel/framework
```

**前端**:
```bash
npm update package-name
# 例如: npm update axios
```

---

## 🐛 故障排除

### 1. Composer安装失败

**错误**: `Your requirements could not be resolved`

**解决**:
```bash
# 删除锁文件
rm composer.lock

# 清除缓存
composer clear-cache

# 重新安装
composer install
```

### 2. NPM安装失败

**错误**: `EACCES: permission denied`

**解决**:
```bash
# 清除缓存
npm cache clean --force

# 删除node_modules
rm -rf node_modules package-lock.json

# 重新安装
npm install
```

### 3. PHP扩展无法加载

**错误**: `extension 'xxx' not found`

**解决**:
1. 确认扩展DLL/SO文件存在
2. 检查extension_dir路径是否正确
3. 重启Web服务器
4. 运行 `php -m` 确认

### 4. 端口被占用

**错误**: `Address already in use`

**解决**:
```bash
# 查找占用端口的进程
# Windows
netstat -ano | findstr :8000

# 停止进程或使用其他端口
php artisan serve --port=8001
npm run dev -- --port=5174
```

---

## 📞 获取帮助

如果遇到问题，请：

1. 查看详细文档
   - [DEPENDENCY_CHECK_REPORT.md](DEPENDENCY_CHECK_REPORT.md)
   - [PHP_EXTENSIONS_SETUP.md](PHP_EXTENSIONS_SETUP.md)
   - [SETUP_GUIDE.md](SETUP_GUIDE.md)

2. 运行诊断工具
   ```bash
   php check_php_extensions.php
   ```

3. 查看日志
   ```bash
   # 后端日志
   tail -f backend/storage/logs/laravel.log
   
   # 前端控制台
   浏览器开发者工具 -> Console
   ```

---

## 🎯 下一步

依赖安装完成后：

1. **配置环境变量**
   - 复制 `backend/.env.example` 到 `.env`
   - 配置数据库连接
   - 设置应用密钥

2. **初始化数据**
   ```bash
   cd backend
   php artisan migrate:fresh --seed
   ```

3. **访问应用**
   - 前端: http://localhost:5173
   - 后端API: http://localhost:8000/api
   - 管理后台: http://localhost:8000/admin

4. **阅读完整文档**
   - [README.md](README.md)
   - [SETUP_GUIDE.md](SETUP_GUIDE.md)

---

**最后更新**: 2025-10-24  
**预计完成时间**: 15-30分钟

