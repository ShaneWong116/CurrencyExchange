@echo off
REM ============================================
REM Docker 部署修复脚本
REM 自动停止、重建、推送、部署
REM ============================================

setlocal enabledelayedexpansion

echo ============================================
echo   Docker 部署修复和重新部署
echo ============================================
echo.

REM 步骤 1：停止现有容器
echo [步骤 1] 停止现有容器...
docker-compose down
if %ERRORLEVEL% NEQ 0 (
    echo [警告] 停止容器时出现错误，继续...
)
echo [成功] 容器已停止
echo.

REM 步骤 2：清理悬空镜像（可选）
echo [步骤 2] 清理悬空镜像...
docker image prune -f
echo [成功] 清理完成
echo.

REM 步骤 3：构建并推送新镜像
echo [步骤 3] 构建并推送新镜像...
echo 这将需要几分钟时间，请耐心等待...
echo.
call build-and-push-local.bat
if %ERRORLEVEL% NEQ 0 (
    echo [错误] 构建和推送失败
    echo 请检查错误信息并重试
    pause
    exit /b 1
)
echo.

REM 步骤 4：拉取最新镜像
echo [步骤 4] 拉取最新镜像...
docker-compose pull
if %ERRORLEVEL% NEQ 0 (
    echo [警告] 拉取镜像时出现错误，继续...
)
echo [成功] 镜像已更新
echo.

REM 步骤 5：启动容器
echo [步骤 5] 启动容器...
docker-compose up -d
if %ERRORLEVEL% NEQ 0 (
    echo [错误] 启动容器失败
    echo 查看日志: docker-compose logs
    pause
    exit /b 1
)
echo [成功] 容器已启动
echo.

REM 步骤 6：等待服务启动
echo [步骤 6] 等待服务启动（10秒）...
timeout /t 10 /nobreak >nul
echo.

REM 步骤 7：检查容器状态
echo [步骤 7] 检查容器状态...
docker-compose ps
echo.

REM 步骤 8：显示日志
echo [步骤 8] 显示最近的日志（按 Ctrl+C 停止查看）...
echo.
echo ============================================
echo   部署完成！
echo ============================================
echo.
echo 访问地址:
echo   前端: http://localhost:8080
echo   API:  http://localhost:8080/api/
echo.
echo 查看日志命令:
echo   docker-compose logs -f
echo.
echo 按任意键查看实时日志，或关闭窗口结束...
pause >nul
docker-compose logs -f

endlocal

