# Docker 数据隔离与保护完整指南

## 📋 目录

- [概述](#概述)
- [问题背景](#问题背景)
- [解决方案架构](#解决方案架构)
- [目录结构](#目录结构)
- [首次部署](#首次部署)
- [日常开发](#日常开发)
- [生产部署](#生产部署)
- [代码更新流程](#代码更新流程)
- [数据备份与恢复](#数据备份与恢复)
- [常见问题](#常见问题)
- [最佳实践](#最佳实践)

---

## 概述

本指南详细说明如何在使用Docker + SQLite的情况下,确保**开发环境**和**生产环境**的数据完全隔离,并在代码更新时保护数据不被覆盖。

### 核心原则

✅ **环境隔离** - 开发和生产环境数据存储在不同目录  
✅ **数据持久化** - 数据库文件永远在容器外部(Volume挂载)  
✅ **镜像无状态** - Docker镜像不包含任何数据  
✅ **自动备份** - 定时备份防止意外数据丢失  
✅ **版本控制** - 数据文件不提交到Git  

---

## 问题背景

### 原有配置的风险

**开发环境 (docker-compose.yml)**:
```yaml
volumes:
  - ./backend:/var/www/html  # 包含了database目录
```
- ⚠️ **风险**: 当更新代码时,如果覆盖backend目录,数据库也会被覆盖

**Dockerfile**:
```dockerfile
RUN touch /var/www/html/database/database.sqlite
```
- ⚠️ **风险**: 数据库文件被打包进镜像,更新镜像可能覆盖数据

### 可能导致数据丢失的场景

1. ❌ 使用`docker-compose down -v` 删除了volume
2. ❌ 重新构建镜像后数据被镜像中的空文件覆盖
3. ❌ 从Git拉取代码覆盖了backend目录
4. ❌ 错误地将开发数据复制到生产环境

---

## 解决方案架构

### 核心改进

#### 1. 独立挂载数据库目录

```yaml
# 开发环境
volumes:
  - ./backend:/var/www/html          # 代码
  - ./data/dev:/var/www/html/database:rw  # 开发数据(独立)

# 生产环境
volumes:
  - ./data/prod:/var/www/html/database:rw # 生产数据(独立)
```

#### 2. 启动脚本初始化

```dockerfile
# Dockerfile中不再创建数据库文件
ENTRYPOINT ["docker-entrypoint.sh"]
```

```bash
# docker-entrypoint.sh在容器启动时检查并初始化
if [ ! -f "$DB_FILE" ]; then
    touch "$DB_FILE"
    php artisan migrate --force
fi
```

#### 3. 目录隔离

```
ExchangeSystem/
├── backend/              # 代码(提交到Git)
│   ├── app/
│   ├── database/
│   │   └── migrations/  # 只有迁移文件
│   └── ...
├── data/                # 数据(不提交到Git)
│   ├── dev/            # 开发环境数据
│   │   └── database.sqlite
│   ├── prod/           # 生产环境数据
│   │   └── database.sqlite
│   └── backups/        # 备份文件
└── ...
```

---

## 目录结构

### 完整目录树

```
ExchangeSystem/
├── backend/                    # 后端代码
│   ├── app/
│   ├── database/
│   │   ├── migrations/        # 迁移文件(提交到Git)
│   │   └── .gitkeep
│   ├── docker-entrypoint.sh   # 容器启动脚本
│   ├── Dockerfile
│   └── ...
├── frontend/                   # 前端代码
├── data/                       # 数据目录(不提交到Git)
│   ├── dev/                   # 开发环境数据
│   │   ├── database.sqlite   # 开发数据库
│   │   └── .gitkeep
│   ├── prod/                  # 生产环境数据
│   │   ├── database.sqlite   # 生产数据库
│   │   └── .gitkeep
│   ├── backups/               # 备份目录
│   │   ├── dev/
│   │   └── prod/
│   └── storage/               # 存储文件
├── logs/                       # 日志目录(不提交到Git)
│   ├── backend/
│   ├── queue/
│   └── scheduler/
├── scripts/                    # 管理脚本
│   ├── init-data-dirs.sh      # 初始化目录
│   ├── init-data-dirs.bat     # Windows版本
│   ├── backup-database.sh     # 备份脚本
│   ├── backup-database.bat    # Windows版本
│   └── restore-database.sh    # 恢复脚本
├── docker-compose.yml          # 开发环境配置
├── docker-compose.prod.yml     # 生产环境配置
├── env.development.template    # 开发环境配置模板
├── env.production.template     # 生产环境配置模板
├── .gitignore                  # Git忽略文件(已更新)
└── DOCKER_DATA_ISOLATION_GUIDE.md  # 本文档
```

### 关键文件说明

| 文件/目录 | 说明 | 是否提交Git |
|----------|------|------------|
| `backend/` | 后端代码 | ✅ 是 |
| `data/dev/` | 开发环境数据 | ❌ 否 |
| `data/prod/` | 生产环境数据 | ❌ 否 |
| `data/backups/` | 备份文件 | ❌ 否 |
| `logs/` | 日志文件 | ❌ 否 |
| `*.sqlite` | 任何SQLite文件 | ❌ 否 |
| `.env` | 环境变量 | ❌ 否 |
| `*.template` | 配置模板 | ✅ 是 |

---

## 首次部署

### Step 1: 初始化目录结构

**Linux/Mac:**
```bash
cd ExchangeSystem
chmod +x scripts/init-data-dirs.sh
./scripts/init-data-dirs.sh
```

**Windows:**
```cmd
cd ExchangeSystem
scripts\init-data-dirs.bat
```

这将创建:
- `data/dev/` - 开发环境数据目录
- `data/prod/` - 生产环境数据目录
- `data/backups/` - 备份目录
- `logs/` - 日志目录

### Step 2: 配置环境变量

**开发环境:**
```bash
cp env.development.template backend/.env
cd backend
php artisan key:generate
```

**生产环境:**
```bash
cp env.production.template .env.production
# 编辑 .env.production,设置 APP_KEY 和其他配置
```

### Step 3: 启动容器

**开发环境:**
```bash
docker-compose up -d
```

**生产环境:**
```bash
docker-compose -f docker-compose.prod.yml up -d
```

### Step 4: 验证数据隔离

**检查开发环境数据库:**
```bash
ls -lh data/dev/database.sqlite
```

**检查生产环境数据库:**
```bash
ls -lh data/prod/database.sqlite
```

两个文件应该独立存在,互不影响。

---

## 日常开发

### 启动开发环境

```bash
docker-compose up -d
```

### 查看日志

```bash
docker-compose logs -f backend
```

### 停止开发环境

```bash
docker-compose down
# 注意: 不要使用 -v 选项,那会删除volume
```

### 重启服务

```bash
docker-compose restart backend
```

### 执行迁移

```bash
docker-compose exec backend php artisan migrate
```

### 查看数据库

```bash
docker-compose exec backend php artisan tinker
# 或者
sqlite3 data/dev/database.sqlite
```

---

## 生产部署

### 首次部署

```bash
# 1. 初始化目录
./scripts/init-data-dirs.sh

# 2. 配置环境变量
cp env.production.template .env.production
vi .env.production  # 设置 APP_KEY, APP_URL 等

# 3. 构建和启动
docker-compose -f docker-compose.prod.yml up -d

# 4. 检查服务状态
docker-compose -f docker-compose.prod.yml ps

# 5. 查看日志
docker-compose -f docker-compose.prod.yml logs -f
```

### 健康检查

```bash
# 检查数据库文件
docker-compose -f docker-compose.prod.yml exec backend ls -lh /var/www/html/database/

# 检查应用状态
docker-compose -f docker-compose.prod.yml exec backend php artisan --version
```

---

## 代码更新流程

### ⚠️ 重要: 更新前必读

更新代码**不会**影响数据库,因为:
1. ✅ 数据库文件在容器外部(`data/prod/`)
2. ✅ Volume挂载确保数据持久化
3. ✅ 代码更新只影响`backend/`目录

### 安全更新流程

#### Step 1: 备份当前数据库

```bash
# 自动备份(推荐)
./scripts/backup-database.sh prod --docker

# 或手动备份
cp data/prod/database.sqlite data/backups/prod/backup_$(date +%Y%m%d).sqlite
```

#### Step 2: 拉取最新代码

```bash
git pull origin main
```

#### Step 3: 重新构建镜像(如果有Dockerfile更改)

```bash
docker-compose -f docker-compose.prod.yml build backend
```

#### Step 4: 更新容器

```bash
# 停止容器
docker-compose -f docker-compose.prod.yml down

# 启动新容器(数据库volume保持不变)
docker-compose -f docker-compose.prod.yml up -d
```

#### Step 5: 验证数据完整性

```bash
# 检查数据库文件仍然存在
ls -lh data/prod/database.sqlite

# 检查应用能否正常访问数据
docker-compose -f docker-compose.prod.yml exec backend php artisan tinker
>>> \App\Models\User::count()
```

### 验证清单

- [ ] 备份已创建
- [ ] 代码已更新
- [ ] 容器已重启
- [ ] 数据库文件未被删除
- [ ] 应用可以正常访问数据
- [ ] 新功能正常工作

---

## 数据备份与恢复

### 自动备份

#### 备份开发环境

```bash
# Linux/Mac
./scripts/backup-database.sh dev

# Windows
scripts\backup-database.bat dev
```

#### 备份生产环境(Docker)

```bash
# Linux/Mac
./scripts/backup-database.sh prod --docker

# Windows
scripts\backup-database.bat prod --docker
```

#### 设置定时备份(Linux)

```bash
# 编辑crontab
crontab -e

# 添加定时任务(每天凌晨2点备份)
0 2 * * * cd /path/to/ExchangeSystem && ./scripts/backup-database.sh prod --docker
```

### 手动备份

```bash
# 开发环境
cp data/dev/database.sqlite data/backups/dev/manual_backup_$(date +%Y%m%d).sqlite

# 生产环境(Docker)
docker cp exchange-backend-prod:/var/www/html/database/database.sqlite data/backups/prod/manual_backup_$(date +%Y%m%d).sqlite
```

### 恢复数据库

#### 恢复最新备份

```bash
# 恢复到开发环境
./scripts/restore-database.sh dev latest

# 恢复到生产环境(需要确认)
./scripts/restore-database.sh prod latest --docker
```

#### 恢复指定备份

```bash
# 列出所有备份
ls -lh data/backups/prod/

# 恢复指定备份
./scripts/restore-database.sh prod database_20241105_120000.sqlite --docker
```

#### 强制恢复(不提示确认)

```bash
./scripts/restore-database.sh prod latest --docker --force
```

---

## 常见问题

### Q1: 更新代码后数据会丢失吗?

**A:** 不会! 因为:
- 数据库文件在 `data/prod/` 或 `data/dev/`,与代码目录 `backend/` 分离
- Docker volume 挂载确保数据持久化
- 更新代码只影响 `backend/` 目录

### Q2: 重新构建Docker镜像会覆盖数据吗?

**A:** 不会! 因为:
- 新的 Dockerfile 不再将数据库文件打包进镜像
- 数据库由容器启动时的 `docker-entrypoint.sh` 初始化
- 已存在的数据库不会被覆盖

### Q3: 开发环境的数据会影响生产环境吗?

**A:** 不会! 因为:
- 开发环境使用 `data/dev/database.sqlite`
- 生产环境使用 `data/prod/database.sqlite`
- 两个文件完全独立,互不影响

### Q4: 如何从开发环境迁移数据到生产环境?

**A:** 使用备份恢复:
```bash
# 1. 备份开发环境
./scripts/backup-database.sh dev

# 2. 将备份文件复制到生产备份目录
cp data/backups/dev/database_latest.sqlite data/backups/prod/from_dev.sqlite

# 3. 恢复到生产环境
./scripts/restore-database.sh prod from_dev.sqlite --docker
```

### Q5: 误删了数据怎么办?

**A:** 从备份恢复:
```bash
# 列出所有备份
ls -lh data/backups/prod/

# 恢复最近的备份
./scripts/restore-database.sh prod latest --docker
```

### Q6: 如何切换到MySQL/PostgreSQL?

**A:** 修改环境变量和Docker配置:
```bash
# 1. 修改 .env 或 docker-compose.prod.yml
DB_CONNECTION=mysql
DB_HOST=mysql
DB_DATABASE=exchange_system
DB_USERNAME=root
DB_PASSWORD=password

# 2. 添加MySQL服务到 docker-compose.prod.yml
# 3. 导出SQLite数据,导入MySQL
```

### Q7: volume删除后数据还在吗?

**A:** 在! 因为我们使用bind mount,数据在宿主机:
```yaml
volumes:
  - ./data/prod:/var/www/html/database:rw
```
即使容器和volume被删除,`./data/prod/` 中的文件仍然存在。

### Q8: 如何验证数据隔离配置是否正确?

**A:** 运行验证脚本:
```bash
# 检查目录结构
ls -lh data/
# 应该看到 dev/, prod/, backups/

# 检查开发环境
docker-compose exec backend ls -lh /var/www/html/database/

# 检查生产环境
docker-compose -f docker-compose.prod.yml exec backend ls -lh /var/www/html/database/

# 检查数据库文件不在backend目录
ls backend/database/database.sqlite
# 应该提示文件不存在
```

---

## 最佳实践

### 1. 定期备份

```bash
# 设置自动备份(crontab)
0 2 * * * cd /path/to/ExchangeSystem && ./scripts/backup-database.sh prod --docker
0 3 * * * cd /path/to/ExchangeSystem && ./scripts/backup-database.sh dev
```

### 2. 更新前备份

每次更新代码前,执行:
```bash
./scripts/backup-database.sh prod --docker
```

### 3. 测试后再上生产

1. 在开发环境测试新功能
2. 确认没有问题后再部署到生产
3. 生产部署后立即验证

### 4. 监控数据库大小

```bash
# 定期检查数据库大小
du -h data/prod/database.sqlite

# 如果过大,考虑归档旧数据或迁移到MySQL
```

### 5. 保留多个备份

```bash
# 备份脚本默认保留30天内的备份
# 可以手动保留重要时间点的备份
cp data/backups/prod/database_20241105.sqlite /external/backup/critical_backup.sqlite
```

### 6. 使用版本标签

```bash
# 给重要备份添加标签
cp data/backups/prod/database_latest.sqlite data/backups/prod/v1.0_release.sqlite
```

### 7. 文档化特殊操作

记录所有手动数据操作:
```bash
# 在 data/backups/CHANGELOG.md 中记录
echo "2024-11-05: 恢复备份 - 修复数据错误" >> data/backups/CHANGELOG.md
```

---

## 总结

### ✅ 已解决的问题

1. ✅ **环境隔离** - 开发和生产数据完全分离
2. ✅ **数据安全** - 更新代码不会影响数据库
3. ✅ **持久化** - 数据永远在容器外部
4. ✅ **版本控制** - 数据文件不提交到Git
5. ✅ **备份恢复** - 自动化备份和恢复流程
6. ✅ **文档齐全** - 完整的操作指南

### 🎯 核心要点

1. 数据库文件**永远不在**backend目录
2. 使用**独立volume**挂载数据库目录
3. Docker镜像**不包含**数据
4. **定期备份**是最后防线
5. **先测试后生产**是黄金法则

### 📞 需要帮助?

如有问题,请查看:
1. 本文档的[常见问题](#常见问题)章节
2. Docker日志: `docker-compose logs -f`
3. 数据库日志: `logs/backend/laravel.log`

---

**最后更新**: 2024-11-05  
**版本**: 1.0

