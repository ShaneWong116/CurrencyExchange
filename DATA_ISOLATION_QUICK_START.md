# Docker 数据隔离 - 快速开始

## 🚀 快速开始(3分钟)

### 1. 初始化数据目录

**Linux/Mac:**
```bash
chmod +x scripts/init-data-dirs.sh
./scripts/init-data-dirs.sh
```

**Windows:**
```cmd
scripts\init-data-dirs.bat
```

### 2. 启动服务

**开发环境:**
```bash
docker-compose up -d
```

**生产环境:**
```bash
docker-compose -f docker-compose.prod.yml up -d
```

### 3. 验证数据隔离

```bash
# 检查开发环境数据库
ls -lh data/dev/database.sqlite

# 检查生产环境数据库  
ls -lh data/prod/database.sqlite
```

## ✅ 核心要点

1. **开发数据**: `data/dev/database.sqlite`
2. **生产数据**: `data/prod/database.sqlite`  
3. **两者完全隔离**, 互不影响
4. **更新代码不会影响数据库**

## 🛡️ 数据保护

### 更新代码前备份

```bash
# 备份开发环境
./scripts/backup-database.sh dev

# 备份生产环境
./scripts/backup-database.sh prod --docker
```

### 更新代码

```bash
git pull origin main
docker-compose down
docker-compose up -d
```

**✅ 数据库不会被影响!**

##  📖 完整文档

详细说明请查看: [DOCKER_DATA_ISOLATION_GUIDE.md](./DOCKER_DATA_ISOLATION_GUIDE.md)

## 🆘 常见问题

**Q: 更新代码会丢失数据吗?**  
A: 不会! 数据在 `data/` 目录,与代码完全分离。

**Q: 开发数据会影响生产吗?**  
A: 不会! 开发用 `data/dev/`, 生产用 `data/prod/`。

**Q: 如何恢复备份?**  
A: `./scripts/restore-database.sh dev latest`

## 📞 需要帮助?

1. 查看[完整指南](./DOCKER_DATA_ISOLATION_GUIDE.md)
2. 查看容器日志: `docker-compose logs -f`
3. 检查数据目录: `ls -lh data/`

