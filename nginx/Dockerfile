FROM nginx:alpine

# 安装 wget 用于健康检查
RUN apk add --no-cache wget

# 复制前端构建产物
COPY frontend/dist /usr/share/nginx/html

# 复制 nginx 配置
COPY docker/nginx/conf.d/default.conf /etc/nginx/conf.d/default.conf

# 暴露端口
EXPOSE 80

# 启动 nginx
CMD ["nginx", "-g", "daemon off;"]

