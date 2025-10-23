# start-all.ps1 - 一键启动后端与前端（开发环境）
[CmdletBinding()]
param(
	[switch]$UseProxy = $true,
	[string]$BackendHost = "localhost",
	[int]$BackendPort = 8000,
	[int]$FrontendPort = 3000
)

$ErrorActionPreference = 'Stop'

# Paths
$ROOT = $PSScriptRoot
if (-not $ROOT) { $ROOT = "E:\CurrencyExSystem" }
$BACK = Join-Path $ROOT "backend"
$FRONT = Join-Path $ROOT "frontend"

Write-Host "== Backend setup ==" -ForegroundColor Cyan
Set-Location $BACK

# 1) .env 准备
if (!(Test-Path ".env")) {
	if (Test-Path ".env.example") {
		Copy-Item ".env.example" ".env"
		Write-Host "Created .env from example."
	} else {
		@"
APP_NAME=\"财务管理系统\"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://${BackendHost}:$BackendPort

DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite

CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
"@ | Set-Content ".env" -Encoding UTF8
		Write-Host "Created minimal .env for sqlite."
	}
}

# 2) Composer 依赖
if (!(Test-Path "vendor")) {
	composer install --no-interaction --prefer-dist
} else {
	composer dump-autoload -o | Out-Null
}

# 3) 应用密钥
$envText = Get-Content ".env" -Raw
if ($envText -notmatch "APP_KEY=base64:") {
	php artisan key:generate | Out-Null
}

# 4) 强制使用 sqlite 并写回 .env
$envPath = ".\.env"
$lines = Get-Content $envPath
$lines = $lines `
	-replace '^DB_CONNECTION=.*','DB_CONNECTION=sqlite' `
	-replace '^DB_DATABASE=.*','DB_DATABASE=database/database.sqlite' `
	-replace '^DB_HOST=.*','DB_HOST=127.0.0.1' `
	-replace '^DB_PORT=.*','DB_PORT=3306' `
	-replace '^DB_USERNAME=.*','DB_USERNAME=root' `
	-replace '^DB_PASSWORD=.*','DB_PASSWORD='
$lines | Set-Content $envPath -Encoding UTF8

# 5) 确保 sqlite 文件存在
$DBFILE = Join-Path $BACK "database\database.sqlite"
if (!(Test-Path $DBFILE)) { New-Item -ItemType File -Path $DBFILE | Out-Null }

# 6) 清理 Laravel 缓存并迁移/填充
php artisan optimize:clear | Out-Null
php artisan migrate --seed | Out-Null

# 7) 启动后端（新窗口）。为兼容 Vite 代理，默认使用 localhost
$backendCmd = "cd `"$BACK`"; php artisan serve --host $BackendHost --port $BackendPort"
Start-Process powershell -ArgumentList "-NoExit","-Command",$backendCmd | Out-Null

Write-Host "== Frontend setup ==" -ForegroundColor Cyan
Set-Location $FRONT

# 8) Node 依赖
if (!(Test-Path "node_modules")) {
	npm install
}

# 9) 前端环境变量
$feEnvPath = Join-Path $FRONT ".env.local"
if ($UseProxy) {
	# 走 Vite 代理：移除直连配置，避免 CORS
	if (Test-Path $feEnvPath) {
		$feLines = Get-Content $feEnvPath
		$feLines = $feLines | Where-Object { $_ -notmatch '^\s*VITE_API_BASE_URL=' }
		$feLines | Set-Content $feEnvPath -Encoding UTF8
	}
} else {
	$apiBase = "VITE_API_BASE_URL=http://$($BackendHost):$BackendPort/api"
	if (Test-Path $feEnvPath) {
		$content = Get-Content $feEnvPath -Raw
		if ($content -notmatch 'VITE_API_BASE_URL=') {
			Add-Content $feEnvPath "`n$apiBase"
		} else {
			($content -replace 'VITE_API_BASE_URL=.*', $apiBase) | Set-Content $feEnvPath -Encoding UTF8
		}
	} else {
		Set-Content $feEnvPath $apiBase -Encoding UTF8
	}
}

# 10) 启动前端（新窗口），显式指定端口
$frontCmd = "cd `"$FRONT`"; npm run dev -- --port $FrontendPort"
Start-Process powershell -ArgumentList "-NoExit","-Command",$frontCmd | Out-Null

Write-Host "`n== All set =="
if ($UseProxy) {
	Write-Host "Frontend (proxy):   http://localhost:$FrontendPort" -ForegroundColor Green
	Write-Host "Backend (api):     http://${BackendHost}:$BackendPort" -ForegroundColor Green
	Write-Host "Admin panel:        http://${BackendHost}:$BackendPort/admin" -ForegroundColor Green
} else {
	Write-Host "Frontend (direct): http://localhost:$FrontendPort" -ForegroundColor Green
	Write-Host "API base:           http://${BackendHost}:$BackendPort/api" -ForegroundColor Green
	Write-Host "Admin panel:        http://${BackendHost}:$BackendPort/admin" -ForegroundColor Green
}




