# 🐳 Docker 快速开始 - 3步部署

**最简单的部署方式！** 无需手动配置环境，一键完成所有配置。

---

## ⏱️ 时间估算

- **首次部署**: 10-20分钟
- **更新部署**: 2-5分钟

---

## 🎯 部署步骤（只需3步）

### 步骤 1: 安装 Docker

#### Windows 用户

1. 下载 Docker Desktop: https://www.docker.com/products/docker-desktop
2. 安装并启动 Docker Desktop
3. 打开 PowerShell 验证：
```powershell
docker --version
docker-compose --version
```

#### Mac 用户

1. 下载 Docker Desktop for Mac
2. 安装并启动
3. 打开终端验证：
```bash
docker --version
docker-compose --version
```

#### Linux 用户

```bash
# Ubuntu/Debian
curl -fsSL https://get.docker.com | sh
sudo systemctl start docker
sudo systemctl enable docker

# 验证
docker --version
docker-compose --version
```

---

### 步骤 2: 运行部署脚本

#### Windows 用户

双击运行 `docker-deploy.bat`

或在项目目录打开 PowerShell：
```powershell
.\docker-deploy.bat
```

#### Linux/Mac 用户

在项目目录打开终端：
```bash
chmod +x docker-deploy.sh
./docker-deploy.sh
```

**脚本会自动完成**：
- ✅ 检查 Docker 环境
- ✅ 创建必要目录
- ✅ 构建前端应用
- ✅ 准备后端环境
- ✅ 启动所有容器
- ✅ 初始化数据库
- ✅ 健康检查

---

### 步骤 3: 访问应用

等待脚本完成后：

- **前端应用**: http://localhost
- **后台管理**: http://localhost/api/admin
- **API接口**: http://localhost/api

**默认账户**：
- 外勤: field001 / 123456
- 管理: admin / admin123

---

## 🎉 就这么简单！

整个过程：
```
安装Docker (5分钟)
    ↓
运行脚本 (10-15分钟)
    ↓
访问应用 ✅
```

---

## 🔧 常用命令

```bash
# 查看容器状态
docker-compose ps

# 查看日志
docker-compose logs -f

# 重启服务
docker-compose restart

# 停止服务
docker-compose stop

# 启动服务
docker-compose start

# 完全清理（包括数据）
docker-compose down -v
```

---

## 🆘 遇到问题？

### 端口被占用

如果 80 端口被占用，编辑 `docker-compose.yml`：

```yaml
nginx:
  ports:
    - "8080:80"  # 改用 8080 端口
```

然后访问 http://localhost:8080

### 权限问题（Linux）

```bash
# 添加当前用户到 docker 组
sudo usermod -aG docker $USER
newgrp docker
```

### 容器启动失败

```bash
# 查看详细日志
docker-compose logs

# 重新构建
docker-compose up -d --force-recreate --build
```

---

## 📦 对比传统部署

| 项目 | 传统部署 | Docker部署 |
|-----|---------|-----------|
| 安装软件 | PHP、Nginx、MySQL... | ✅ 一键完成 |
| 配置环境 | 手动编辑多个文件 | ✅ 自动配置 |
| 依赖冲突 | 可能有问题 | ✅ 完全隔离 |
| 部署时间 | 2-4小时 | ✅ 10-20分钟 |
| 环境一致 | 可能不一致 | ✅ 完全一致 |
| 回滚 | 困难 | ✅ 一条命令 |

---

## 🚀 生产环境部署

如需部署到云服务器：

1. **上传代码到服务器**
```bash
# 本地
git push origin main

# 服务器
git clone https://your-repo.git /opt/currency
cd /opt/currency
```

2. **运行部署脚本**
```bash
chmod +x docker-deploy.sh
./docker-deploy.sh
```

3. **配置域名和HTTPS**（可选）

参考 [DOCKER_DEPLOYMENT.md](DOCKER_DEPLOYMENT.md) 的 HTTPS 配置章节。

---

## 📚 更多信息

- 📖 完整文档: [DOCKER_DEPLOYMENT.md](DOCKER_DEPLOYMENT.md)
- 🔧 Docker配置: [docker-compose.yml](docker-compose.yml)
- 🐳 后端镜像: [backend/Dockerfile](backend/Dockerfile)
- ⚙️ Nginx配置: [docker/nginx/conf.d/default.conf](docker/nginx/conf.d/default.conf)

---

## 🎯 下一步

部署完成后：

1. ✅ 登录系统测试功能
2. ✅ 修改默认密码
3. ✅ 配置定期备份
4. ✅ 配置HTTPS（生产环境）
5. ✅ 设置监控（可选）

---

**享受 Docker 带来的便利！** 🐳✨

有任何问题，查看详细文档：[DOCKER_DEPLOYMENT.md](DOCKER_DEPLOYMENT.md)

