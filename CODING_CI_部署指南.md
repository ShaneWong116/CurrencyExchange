# 📦 Coding CI/CD Docker 镜像发布指南

> 本指南教你如何使用 Coding DevOps 平台构建和发布 Exchange System 的 Docker 镜像

## 📋 目录

1. [前置准备](#前置准备)
2. [Coding 平台配置](#coding-平台配置)
3. [创建制品库](#创建制品库)
4. [配置 CI/CD](#配置-cicd)
5. [触发构建](#触发构建)
6. [镜像使用](#镜像使用)
7. [常见问题](#常见问题)

---

## 🎯 前置准备

### 1. 注册 Coding 账号

访问 [Coding.net](https://coding.net/) 注册账号（推荐使用企业账号）

### 2. 创建项目

1. 登录 Coding 控制台
2. 点击 **新建项目**
3. 填写项目信息：
   - 项目名称：`exchange-system`
   - 项目标识：`exchange-system`
   - 选择项目模板：**DevOps 项目**

### 3. 推送代码到 Coding

```bash
# 添加 Coding 远程仓库
git remote add coding https://e.coding.net/你的团队名/exchange-system.git

# 推送代码
git push coding master
```

---

## 🏭 Coding 平台配置

### 步骤 1: 创建制品库

制品库用于存储 Docker 镜像。

#### 1.1 进入制品库

1. 进入你的项目
2. 点击左侧菜单 **制品库**
3. 点击 **创建制品库**

#### 1.2 配置制品库

- **仓库类型**：选择 `Docker`
- **仓库名称**：`exchange-system-docker`
- **权限**：
  - 公开（任何人可拉取）或
  - 私有（需要认证）
- 点击 **确定创建**

#### 1.3 获取仓库地址

创建成功后，会显示仓库地址，格式如下：

```
coding-public.coding.net/你的团队名/exchange-system/exchange-system-docker
```

**记录这个地址**，后面配置 CI 时需要用到。

---

### 步骤 2: 配置环境变量

CI/CD 需要使用敏感信息（如账号密码），应配置为环境变量。

#### 2.1 进入环境变量配置

1. 点击左侧菜单 **持续集成** → **构建计划**
2. 点击 **设置** → **环境变量**

#### 2.2 添加以下环境变量

| 变量名 | 值 | 说明 | 是否保密 |
|--------|-----|------|---------|
| `DOCKER_USERNAME` | 你的 Coding 用户名 | Docker 登录用户名 | ✅ |
| `DOCKER_PASSWORD` | 你的 Coding 密码或访问令牌 | Docker 登录密码 | ✅ |
| `DOCKER_REGISTRY` | `coding-public.coding.net` | Docker 仓库地址 | ❌ |
| `DOCKER_NAMESPACE` | `你的团队名/exchange-system/exchange-system-docker` | 镜像命名空间 | ❌ |
| `DEPLOY_SERVER` | 你的服务器 IP | 部署服务器地址（可选） | ❌ |
| `DEPLOY_USER` | root | 部署服务器用户（可选） | ❌ |
| `SSH_PRIVATE_KEY` | SSH 私钥内容 | 用于部署（可选） | ✅ |

**注意**：
- "保密"选项勾选后，变量值不会在日志中显示
- `DOCKER_PASSWORD` 建议使用**访问令牌**而非密码

#### 2.3 创建访问令牌（推荐）

1. 点击右上角头像 → **个人设置**
2. 点击 **访问令牌**
3. 点击 **新建令牌**
4. 勾选权限：
   - ✅ `project:artifacts` (制品库读写)
   - ✅ `project` (项目读写)
5. 点击 **新建**
6. **复制令牌**（只显示一次！）
7. 将令牌设置为 `DOCKER_PASSWORD` 的值

---

### 步骤 3: 更新 CI 配置文件

打开项目根目录的 `.coding-ci.yml` 文件，修改以下内容：

```yaml
env:
  DOCKER_REGISTRY: "coding-public.coding.net"
  DOCKER_NAMESPACE: "你的团队名/exchange-system/exchange-system-docker"  # 👈 修改这里
  IMAGE_NAME: "exchange-system"
```

**示例**（假设团队名是 `myteam`）：
```yaml
env:
  DOCKER_REGISTRY: "coding-public.coding.net"
  DOCKER_NAMESPACE: "myteam/exchange-system/exchange-system-docker"
  IMAGE_NAME: "exchange-system"
```

提交并推送更改：
```bash
git add .coding-ci.yml
git commit -m "配置 Coding CI"
git push coding master
```

---

## 🚀 配置 CI/CD

### 步骤 4: 创建构建计划

#### 4.1 创建新构建计划

1. 进入项目，点击 **持续集成** → **构建计划**
2. 点击 **创建构建计划**
3. 选择 **自定义构建过程**

#### 4.2 配置构建计划

- **构建计划名称**：`Docker 镜像构建`
- **代码源**：选择你的 Coding 代码仓库
- **配置来源**：选择 `使用代码库中的 Jenkinsfile`
- **Jenkinsfile 路径**：`.coding-ci.yml`
- **节点池**：选择默认节点池

#### 4.3 触发规则

配置自动触发条件：

- ✅ **代码更新时自动执行**
  - 监听分支：`master`、`main`、`develop`
- ✅ **创建标签时自动执行**
  - 标签规则：`*`（所有标签）

点击 **确定** 保存。

---

## 🎬 触发构建

### 方法 1: 手动触发

1. 进入 **持续集成** → **构建计划**
2. 找到 `Docker 镜像构建`
3. 点击 **立即构建**
4. 选择分支（默认 `master`）
5. 点击 **执行**

### 方法 2: 推送代码触发

```bash
# 修改代码后提交
git add .
git commit -m "更新代码"
git push coding master
```

推送后会自动触发构建。

### 方法 3: 创建标签触发

```bash
# 创建版本标签
git tag -a v1.0.0 -m "发布版本 1.0.0"
git push coding v1.0.0
```

---

## 📊 查看构建进度

### 实时查看日志

1. 进入 **持续集成** → **构建计划**
2. 点击正在执行的构建任务
3. 查看实时日志输出

### 构建阶段

CI 配置包含以下阶段：

```
┌─────────────┐
│ 环境准备     │  ← 检查环境、显示版本信息
└─────────────┘
       ↓
┌─────────────┐
│ 构建前端     │  ← npm install & npm run build
└─────────────┘
       ↓
┌─────────────┐
│ 构建后端镜像  │  ← docker build (backend + nginx)
└─────────────┘
       ↓
┌─────────────┐
│ 推送镜像     │  ← docker push 到制品库
└─────────────┘
       ↓
┌─────────────┐
│ 部署 (可选)  │  ← 自动部署到服务器
└─────────────┘
```

---

## 🎁 镜像使用

### 查看已发布的镜像

1. 进入 **制品库** → **exchange-system-docker**
2. 可以看到已推送的镜像列表：
   - `exchange-system-backend:latest`
   - `exchange-system-backend:v1.0.0`
   - `exchange-system-nginx:latest`
   - `exchange-system-nginx:v1.0.0`

### 拉取镜像

#### 方法 1: 公开仓库（无需登录）

```bash
docker pull coding-public.coding.net/你的团队名/exchange-system/exchange-system-docker/exchange-system-backend:latest
docker pull coding-public.coding.net/你的团队名/exchange-system/exchange-system-docker/exchange-system-nginx:latest
```

#### 方法 2: 私有仓库（需要登录）

```bash
# 登录
docker login coding-public.coding.net
# 用户名：你的 Coding 用户名
# 密码：你的 Coding 密码或访问令牌

# 拉取镜像
docker pull coding-public.coding.net/你的团队名/exchange-system/exchange-system-docker/exchange-system-backend:latest
docker pull coding-public.coding.net/你的团队名/exchange-system/exchange-system-docker/exchange-system-nginx:latest
```

### 使用 Docker Compose 部署

创建 `docker-compose.prod.yml`：

```yaml
version: '3.8'

services:
  backend:
    image: coding-public.coding.net/你的团队名/exchange-system/exchange-system-docker/exchange-system-backend:latest
    container_name: exchange-backend
    restart: unless-stopped
    environment:
      - APP_ENV=production
      - APP_DEBUG=false
      - DB_CONNECTION=sqlite
    volumes:
      - ./data:/var/www/html/database
      - backend-storage:/var/www/html/storage
    networks:
      - exchange-network

  nginx:
    image: coding-public.coding.net/你的团队名/exchange-system/exchange-system-docker/exchange-system-nginx:latest
    container_name: exchange-nginx
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    depends_on:
      - backend
    networks:
      - exchange-network

  queue:
    image: coding-public.coding.net/你的团队名/exchange-system/exchange-system-docker/exchange-system-backend:latest
    container_name: exchange-queue
    restart: unless-stopped
    command: php artisan queue:work --sleep=3 --tries=3
    volumes:
      - ./data:/var/www/html/database
      - backend-storage:/var/www/html/storage
    depends_on:
      - backend
    networks:
      - exchange-network

networks:
  exchange-network:
    driver: bridge

volumes:
  backend-storage:
```

启动服务：

```bash
docker-compose -f docker-compose.prod.yml up -d
```

---

## ❓ 常见问题

### Q1: 构建失败：无法连接到 Docker 守护进程

**原因**：节点池未启用 Docker in Docker (DinD)

**解决方案**：
1. 进入 **持续集成** → **构建计划** → **设置**
2. 在 **节点池** 选项中勾选 **启用 Docker 支持**
3. 保存并重新构建

---

### Q2: 推送镜像失败：认证错误

**原因**：`DOCKER_USERNAME` 或 `DOCKER_PASSWORD` 配置错误

**解决方案**：
1. 检查环境变量是否正确配置
2. 确认密码使用的是**访问令牌**（推荐）
3. 检查令牌权限是否包含 `project:artifacts`

---

### Q3: 前端构建失败：内存不足

**原因**：Node.js 构建需要较大内存

**解决方案**：

修改 `.coding-ci.yml` 中的前端构建部分：

```yaml
build-frontend:
  stage: 构建前端
  image: node:18-alpine
  script:
    - export NODE_OPTIONS="--max-old-space-size=4096"  # 👈 增加内存限制
    - cd frontend
    - npm install
    - npm run build
```

---

### Q4: 如何使用不同的镜像标签？

**方法 1：基于分支**

推送到不同分支会生成不同标签：
- `master` → `latest`
- `develop` → `develop`

**方法 2：基于 Git Tag**

创建语义化版本标签：
```bash
git tag -a v1.0.0 -m "版本 1.0.0"
git push coding v1.0.0
```

会生成标签：`v1.0.0`

---

### Q5: 如何配置自动部署到服务器？

#### 步骤 1: 生成 SSH 密钥对

```bash
ssh-keygen -t rsa -b 4096 -C "coding-ci@deploy"
# 保存到: ~/.ssh/coding_deploy
```

#### 步骤 2: 添加公钥到服务器

```bash
ssh-copy-id -i ~/.ssh/coding_deploy.pub user@your-server.com
```

#### 步骤 3: 配置环境变量

在 Coding 中添加以下环境变量：

- `DEPLOY_SERVER`: `your-server.com`
- `DEPLOY_USER`: `root`
- `SSH_PRIVATE_KEY`: 私钥内容（`cat ~/.ssh/coding_deploy`）

#### 步骤 4: 启用部署阶段

在 `.coding-ci.yml` 中修改：

```yaml
deploy:
  stage: 部署
  when: manual  # 改为 auto 自动部署
```

---

### Q6: 如何清理旧镜像？

#### 在 Coding 制品库中清理

1. 进入 **制品库** → **exchange-system-docker**
2. 选择要删除的镜像版本
3. 点击 **删除**

#### 在服务器上清理

```bash
# 清理未使用的镜像
docker image prune -a

# 清理特定镜像
docker rmi coding-public.coding.net/xxx/exchange-system-backend:old-tag
```

---

## 📝 最佳实践

### 1. 版本管理

使用语义化版本号：

```bash
# 主版本更新（不兼容的 API 修改）
git tag -a v2.0.0 -m "重大更新"

# 次版本更新（向下兼容的功能性新增）
git tag -a v1.1.0 -m "新增功能"

# 修订版本（向下兼容的问题修正）
git tag -a v1.0.1 -m "修复 bug"

git push coding --tags
```

### 2. 多环境部署

创建不同分支对应不同环境：

- `master` → 生产环境
- `staging` → 预发布环境
- `develop` → 开发环境

### 3. 安全建议

✅ **推荐做法**：
- 使用访问令牌而非密码
- 敏感变量勾选"保密"
- 定期轮换令牌
- 使用私有制品库

❌ **不推荐**：
- 在代码中硬编码密码
- 公开包含敏感信息的镜像
- 使用 `root` 用户运行容器

### 4. 镜像优化

在 `backend/Dockerfile` 中：

```dockerfile
# 使用多阶段构建减小镜像大小
FROM php:8.1-fpm-alpine AS builder
# ... 构建步骤

FROM php:8.1-fpm-alpine
COPY --from=builder /var/www/html /var/www/html
# ... 其他配置
```

---

## 🎓 扩展阅读

- [Coding 官方文档](https://help.coding.net/)
- [Docker 官方文档](https://docs.docker.com/)
- [Laravel 部署指南](https://laravel.com/docs/deployment)

---

## 💬 技术支持

如有问题，请：
1. 查看 Coding 构建日志
2. 检查环境变量配置
3. 参考本文档的常见问题部分

---

**🎉 恭喜！你已经成功配置了 Coding CI/CD 自动构建和发布 Docker 镜像！**

每次推送代码或创建标签，系统会自动构建并发布新的镜像版本。

