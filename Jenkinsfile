pipeline {
  agent any
  
  // 环境变量
  environment {
    DOCKER_REGISTRY = 'zhlibai-docker.pkg.coding.net'
    DOCKER_NAMESPACE = 'currencyexchange'
    PROJECT_NAME = 'exchange-system'
    // Docker 凭证从构建计划的环境变量中获取
    // 在构建计划设置中配置: DOCKER_USER 和 DOCKER_PWD
    DOCKER_USER = "${env.DOCKER_USER ?: 'currencyexchange-1762153618987'}"
    DOCKER_PWD = "${env.DOCKER_PWD ?: 'f2ed7fac940b86ac8b194be3a71798c81f628c08'}"
  }
  
  stages {
    // ============================================
    // 阶段1: 检出代码
    // ============================================
    stage('检出') {
      steps {
        checkout([
          $class: 'GitSCM',
          branches: [[name: GIT_BUILD_REF]],
          userRemoteConfigs: [[
            url: GIT_REPO_URL,
            credentialsId: CREDENTIALS_ID
          ]]
        ])
      }
    }
    
    // ============================================
    // 阶段2: 构建前端应用
    // ============================================
    stage('构建前端') {
      agent {
        docker {
          image 'node:18-alpine'
          args '-v /var/run/docker.sock:/var/run/docker.sock'
        }
      }
      steps {
        script {
          echo '📱 开始构建前端应用...'
          dir('frontend') {
            sh 'npm install'
            sh 'npm run build'
          }
          echo '✅ 前端构建完成'
          sh 'ls -la frontend/dist/'
        }
      }
    }
    
    // ============================================
    // 阶段3: 构建后端 Docker 镜像
    // ============================================
    stage('构建后端镜像') {
      agent any
      steps {
        script {
          echo '🏗️ 开始构建后端镜像...'
          
          // 设置镜像标签
          def commitShort = env.GIT_COMMIT.take(7)
          def imageTag = "${DOCKER_REGISTRY}/${DOCKER_NAMESPACE}/${PROJECT_NAME}/backend:${commitShort}"
          def imageLatest = "${DOCKER_REGISTRY}/${DOCKER_NAMESPACE}/${PROJECT_NAME}/backend:latest"
          
          echo "镜像标签: ${imageTag}"
          echo "镜像标签: ${imageLatest}"
          
          // 构建后端镜像
          dir('backend') {
            sh """
              docker build --no-cache \
                -t ${imageTag} \
                -t ${imageLatest} \
                .
            """
          }
          
          echo '✅ 后端镜像构建完成'
          
          // 保存镜像标签供后续使用
          env.BACKEND_IMAGE_TAG = imageTag
          env.BACKEND_IMAGE_LATEST = imageLatest
        }
      }
    }
    
    // ============================================
    // 阶段4: 创建 Nginx Dockerfile
    // ============================================
    stage('创建 Nginx Dockerfile') {
      agent any
      steps {
        script {
          echo '📝 创建 Nginx Dockerfile...'
          
          sh '''
            cat > Dockerfile.nginx <<'EOF'
            FROM nginx:alpine
            
            # 安装 wget 用于健康检查
            RUN apk add --no-cache wget
            
            # 复制前端构建产物
            COPY frontend/dist /var/www/html/frontend
            
            # 复制 Nginx 配置
            COPY docker/nginx/conf.d /etc/nginx/conf.d
            
            # 设置权限
            RUN chmod -R 755 /var/www/html
            
            # 健康检查
            HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \\
              CMD wget --no-verbose --tries=1 --spider http://localhost || exit 1
            
            EXPOSE 80 443
            
            CMD ["nginx", "-g", "daemon off;"]
            EOF
          '''
          
          echo '✅ Dockerfile.nginx 创建完成'
          
          // 验证文件
          sh 'ls -la Dockerfile.nginx'
          sh 'ls -la frontend/dist/ || exit 1'
          sh 'ls -la docker/nginx/conf.d/ || exit 1'
        }
      }
    }
    
    // ============================================
    // 阶段5: 构建 Nginx Docker 镜像
    // ============================================
    stage('构建 Nginx 镜像') {
      agent any
      steps {
        script {
          echo '🏗️ 开始构建 Nginx 镜像...'
          
          // 设置镜像标签
          def commitShort = env.GIT_COMMIT.take(7)
          def imageTag = "${DOCKER_REGISTRY}/${DOCKER_NAMESPACE}/${PROJECT_NAME}/nginx:${commitShort}"
          def imageLatest = "${DOCKER_REGISTRY}/${DOCKER_NAMESPACE}/${PROJECT_NAME}/nginx:latest"
          
          echo "镜像标签: ${imageTag}"
          echo "镜像标签: ${imageLatest}"
          
          // 构建 Nginx 镜像
          sh """
            docker build --no-cache \
              -f Dockerfile.nginx \
              -t ${imageTag} \
              -t ${imageLatest} \
              .
          """
          
          echo '✅ Nginx 镜像构建完成'
          
          // 保存镜像标签供后续使用
          env.NGINX_IMAGE_TAG = imageTag
          env.NGINX_IMAGE_LATEST = imageLatest
        }
      }
    }
    
    // ============================================
    // 阶段6: 登录 Docker 仓库
    // ============================================
    stage('Docker 登录') {
      agent any
      steps {
        script {
          echo '🔐 登录 Docker 仓库...'
          sh """
            docker login -u ${DOCKER_USER} -p ${DOCKER_PWD} ${DOCKER_REGISTRY}
          """
          echo '✅ Docker 登录成功'
        }
      }
    }
    
    // ============================================
    // 阶段7: 推送后端镜像
    // ============================================
    stage('推送后端镜像') {
      agent any
      steps {
        script {
          echo '📤 推送后端镜像...'
          sh """
            docker push ${env.BACKEND_IMAGE_TAG}
            docker push ${env.BACKEND_IMAGE_LATEST}
          """
          echo '✅ 后端镜像推送完成'
          echo "镜像地址:"
          echo "  - ${env.BACKEND_IMAGE_TAG}"
          echo "  - ${env.BACKEND_IMAGE_LATEST}"
        }
      }
    }
    
    // ============================================
    // 阶段8: 推送 Nginx 镜像
    // ============================================
    stage('推送 Nginx 镜像') {
      agent any
      steps {
        script {
          echo '📤 推送 Nginx 镜像...'
          sh """
            docker push ${env.NGINX_IMAGE_TAG}
            docker push ${env.NGINX_IMAGE_LATEST}
          """
          echo '✅ Nginx 镜像推送完成'
          echo "镜像地址:"
          echo "  - ${env.NGINX_IMAGE_TAG}"
          echo "  - ${env.NGINX_IMAGE_LATEST}"
        }
      }
    }
  }
  
  // ============================================
  // 构建后处理
  // ============================================
  post {
    success {
      echo '🎉 构建成功！'
    }
    failure {
      echo '❌ 构建失败！'
    }
    always {
      echo '构建流程结束'
      // 清理临时文件（可选）
      sh 'rm -f Dockerfile.nginx || true'
    }
  }
}

