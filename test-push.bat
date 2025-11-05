@echo off
echo 测试推送到 Coding 制品库
echo.

REM 使用你提供的登录信息
set REGISTRY=zhlibai-docker.pkg.coding.net
set USER=currencyexchange-1762155836976
set PWD=5fe98387123f74b7bb2d1b35234308ac2f23147f

echo [1] 登录 Docker 仓库...
echo %PWD% | docker login %REGISTRY% -u %USER% --password-stdin
echo.

echo [2] 构建测试镜像...
docker build -t %REGISTRY%/currencyexchange/currencyexchange/test-image:v1 -f Dockerfile.test .
echo.

echo [3] 推送测试镜像...
docker push %REGISTRY%/currencyexchange/currencyexchange/test-image:v1
echo.

if %ERRORLEVEL% EQU 0 (
    echo ✅ 推送成功！
) else (
    echo ❌ 推送失败！
    echo 请查看上面的错误信息
)

pause

