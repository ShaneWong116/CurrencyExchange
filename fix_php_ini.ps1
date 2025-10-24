# PHP.ini 自动修复脚本
# 修复重复加载和错误的扩展配置

$phpIniPath = "D:\ServBay\etc\php\current\php.ini"
$backupPath = "D:\ServBay\etc\php\current\php.ini.backup." + (Get-Date -Format "yyyyMMdd_HHmmss")

Write-Host "=== PHP.ini 修复脚本 ===" -ForegroundColor Cyan
Write-Host ""

# 备份原文件
Write-Host "1. 备份原配置文件..." -ForegroundColor Yellow
Copy-Item $phpIniPath $backupPath
Write-Host "   备份保存到: $backupPath" -ForegroundColor Green
Write-Host ""

# 读取文件内容
$content = Get-Content $phpIniPath -Raw

# 需要注释掉的核心扩展（这些是PHP内置的）
$coreExtensions = @('pdo', 'tokenizer', 'xml', 'ctype', 'json', 'iconv')

Write-Host "2. 修复核心扩展配置..." -ForegroundColor Yellow

foreach ($ext in $coreExtensions) {
    # 注释掉 extension=xxx
    $content = $content -replace "(?m)^extension=$ext\s*$", ";extension=$ext  ; 已注释(核心扩展)"
    $content = $content -replace "(?m)^extension=php_$ext\.dll\s*$", ";extension=php_$ext.dll  ; 已注释(核心扩展)"
    Write-Host "   ✓ 已注释: extension=$ext" -ForegroundColor Green
}

# bcmath 可能需要也可能不需要，取决于PHP版本
$content = $content -replace "(?m)^extension=bcmath\s*$", ";extension=bcmath  ; 已注释(PHP 8.4已内置)"
Write-Host "   ✓ 已注释: extension=bcmath" -ForegroundColor Green

Write-Host ""
Write-Host "3. 修复 OPcache 配置..." -ForegroundColor Yellow

# 修复opcache加载方式
$content = $content -replace "(?m)^extension=opcache\s*$", "zend_extension=opcache"
Write-Host "   ✓ 已修改: extension=opcache -> zend_extension=opcache" -ForegroundColor Green

Write-Host ""
Write-Host "4. 保存修改..." -ForegroundColor Yellow

# 保存修改后的内容
Set-Content $phpIniPath $content -Encoding UTF8
Write-Host "   ✓ 配置文件已更新" -ForegroundColor Green

Write-Host ""
Write-Host "=== 修复完成 ===" -ForegroundColor Cyan
Write-Host ""
Write-Host "下一步:" -ForegroundColor Yellow
Write-Host "1. 重启 ServBay 服务" -ForegroundColor White
Write-Host "2. 运行: php -v  (应该没有警告)" -ForegroundColor White
Write-Host "3. 运行: php check_php_extensions.php" -ForegroundColor White
Write-Host ""
Write-Host "如需恢复原配置: Copy-Item '$backupPath' '$phpIniPath'" -ForegroundColor Gray

