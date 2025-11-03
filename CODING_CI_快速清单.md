# ⚡ Coding CI 快速操作清单

> 5 分钟快速配置 Coding Docker 镜像自动构建

## 📋 操作步骤

### ✅ 步骤 1: 注册并创建项目（5 分钟）

1. 访问 https://coding.net/ 注册账号
2. 创建新项目（选择 DevOps 项目模板）
3. 记录团队名称（URL 中可以看到）

---

### ✅ 步骤 2: 创建制品库（2 分钟）

```
项目 → 制品库 → 创建制品库
├─ 仓库类型: Docker
├─ 仓库名称: exchange-system-docker
└─ 权限: 私有或公开
```

**记录仓库地址**（示例）：
```
coding-public.coding.net/myteam/exchange-system/exchange-system-docker
```

---

### ✅ 步骤 3: 创建访问令牌（3 分钟）

```
右上角头像 → 个人设置 → 访问令牌 → 新建令牌
```

勾选权限：
- ✅ `project:artifacts`
- ✅ `project`

**复制令牌**（只显示一次！）

---

### ✅ 步骤 4: 配置环境变量（2 分钟）

```
项目 → 持续集成 → 构建计划 → 设置 → 环境变量
```

添加以下变量：

| 变量名 | 值 | 保密 |
|--------|-----|------|
| `DOCKER_USERNAME` | 你的 Coding 用户名 | ✅ |
| `DOCKER_PASSWORD` | 刚才复制的访问令牌 | ✅ |
| `DOCKER_REGISTRY` | `coding-public.coding.net` | ❌ |
| `DOCKER_NAMESPACE` | `你的团队名/exchange-system/exchange-system-docker` | ❌ |

---

### ✅ 步骤 5: 修改 CI 配置文件（1 分钟）

编辑项目根目录的 `.coding-ci.yml`：

```yaml
env:
  DOCKER_REGISTRY: "coding-public.coding.net"
  DOCKER_NAMESPACE: "你的团队名/exchange-system/exchange-system-docker"  # 👈 改这里
  IMAGE_NAME: "exchange-system"
```

**示例**（假设团队名是 `myteam`）：
```yaml
  DOCKER_NAMESPACE: "myteam/exchange-system/exchange-system-docker"
```

---

### ✅ 步骤 6: 推送代码到 Coding（1 分钟）

```bash
# 添加远程仓库
git remote add coding https://e.coding.net/你的团队名/exchange-system.git

# 提交并推送
git add .
git commit -m "配置 Coding CI"
git push coding master
```

---

### ✅ 步骤 7: 创建构建计划（2 分钟）

```
项目 → 持续集成 → 构建计划 → 创建构建计划
├─ 选择: 自定义构建过程
├─ 构建计划名称: Docker 镜像构建
├─ 代码源: 选择你的仓库
├─ 配置来源: 使用代码库中的 Jenkinsfile
├─ Jenkinsfile 路径: .coding-ci.yml
└─ 触发规则:
   ✅ 代码更新时自动执行（分支: master）
   ✅ 创建标签时自动执行（标签: *）
```

点击 **确定** 保存。

---

### ✅ 步骤 8: 手动触发第一次构建（1 分钟）

```
持续集成 → 构建计划 → Docker 镜像构建 → 立即构建
```

查看构建日志，等待完成（约 5-10 分钟）。

---

## 🎯 验证结果

### 检查镜像是否成功推送

```
项目 → 制品库 → exchange-system-docker
```

应该看到：
- ✅ `exchange-system-backend:latest`
- ✅ `exchange-system-nginx:latest`

---

## 🚀 使用镜像

### 拉取镜像

```bash
# 登录（私有仓库需要）
docker login coding-public.coding.net
# 用户名: 你的 Coding 用户名
# 密码: 访问令牌

# 拉取镜像
docker pull coding-public.coding.net/你的团队名/exchange-system/exchange-system-docker/exchange-system-backend:latest
docker pull coding-public.coding.net/你的团队名/exchange-system/exchange-system-docker/exchange-system-nginx:latest
```

### 运行容器

```bash
docker run -d -p 9000:9000 \
  coding-public.coding.net/你的团队名/exchange-system/exchange-system-docker/exchange-system-backend:latest

docker run -d -p 80:80 \
  coding-public.coding.net/你的团队名/exchange-system/exchange-system-docker/exchange-system-nginx:latest
```

---

## 📦 后续使用

### 自动构建（已配置好）

每次推送代码到 `master` 分支，自动触发构建：

```bash
git add .
git commit -m "更新功能"
git push coding master
```

### 发布版本

创建标签发布版本：

```bash
git tag -a v1.0.0 -m "发布版本 1.0.0"
git push coding v1.0.0
```

会生成镜像：
- `exchange-system-backend:v1.0.0`
- `exchange-system-nginx:v1.0.0`

---

## ⚠️ 常见问题速查

### ❌ 构建失败：Docker 认证错误

**检查**：
1. `DOCKER_USERNAME` 是否正确
2. `DOCKER_PASSWORD` 是否使用访问令牌（不是密码）
3. 令牌权限是否包含 `project:artifacts`

### ❌ 构建失败：找不到 Dockerfile

**检查**：
- `.coding-ci.yml` 是否在项目根目录
- `backend/Dockerfile` 是否存在

### ❌ 推送镜像失败：权限不足

**检查**：
- 制品库是否已创建
- `DOCKER_NAMESPACE` 配置是否正确
- 访问令牌权限是否足够

---

## 🔗 相关文件

- `.coding-ci.yml` - CI 配置文件
- `CODING_CI_部署指南.md` - 详细部署文档
- `backend/Dockerfile` - 后端镜像构建文件
- `docker-compose.yml` - 本地开发编排文件

---

## 📞 需要帮助？

详细文档请查看：`CODING_CI_部署指南.md`

---

**总耗时约：15-20 分钟**

**🎉 完成后，你的项目就具备了自动化 CI/CD 能力！**

