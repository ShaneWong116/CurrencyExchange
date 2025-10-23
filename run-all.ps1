# run-all.ps1
$ErrorActionPreference = 'Stop'

# Paths
$ROOT = "E:\CurrencyExSystem"
$BACK = Join-Path $ROOT "backend"
$FRONT = Join-Path $ROOT "frontend"

Write-Host "== Backend setup ==" -ForegroundColor Cyan
Set-Location $BACK

# 1) .env
if (!(Test-Path ".env")) {
  Copy-Item ".env.example" ".env"
  Write-Host "Created .env from example."
}

# 2) Composer deps
if (!(Test-Path "vendor")) {
  composer install --no-interaction --prefer-dist
}

# 3) App key
$envText = Get-Content ".env" -Raw
if ($envText -notmatch "APP_KEY=base64:") {
  php artisan key:generate
}

# 4) Use sqlite
$envPath = ".\.env"
$env = Get-Content $envPath
$env = $env `
  -replace '^DB_CONNECTION=.*','DB_CONNECTION=sqlite' `
  -replace '^DB_DATABASE=.*','DB_DATABASE=database/database.sqlite' `
  -replace '^DB_HOST=.*','DB_HOST=127.0.0.1' `
  -replace '^DB_PORT=.*','DB_PORT=3306' `
  -replace '^DB_USERNAME=.*','DB_USERNAME=root' `
  -replace '^DB_PASSWORD=.*','DB_PASSWORD='
$env | Set-Content $envPath -Encoding UTF8

# Ensure sqlite file
$DBFILE = Join-Path $BACK "database\database.sqlite"
if (!(Test-Path $DBFILE)) { New-Item -ItemType File -Path $DBFILE | Out-Null }

# 5) Migrate + seed
php artisan migrate --seed

# 6) Start backend server (new window)
Start-Process powershell -ArgumentList "-NoExit","-Command","cd `"$BACK`"; php artisan serve --host 127.0.0.1 --port 8000"

Write-Host "== Frontend setup ==" -ForegroundColor Cyan
Set-Location $FRONT

# 7) Node deps
if (!(Test-Path "node_modules")) {
  npm ci
}

# 8) Frontend .env for API base
$FEEnv = Join-Path $FRONT ".env.local"
$apiBase = "VITE_API_BASE_URL=http://127.0.0.1:8000/api"
if (Test-Path $FEEnv) {
  $content = Get-Content $FEEnv -Raw
  if ($content -notmatch 'VITE_API_BASE_URL=') {
    Add-Content $FEEnv "`n$apiBase"
  } else {
    ($content -replace 'VITE_API_BASE_URL=.*', $apiBase) | Set-Content $FEEnv -Encoding UTF8
  }
} else {
  Set-Content $FEEnv $apiBase -Encoding UTF8
}

# 9) Start frontend dev server (new window)
Start-Process powershell -ArgumentList "-NoExit","-Command","cd `"$FRONT`"; npm run dev"

Write-Host "`n== All set =="
Write-Host "Backend API: http://127.0.0.1:8000/api"
Write-Host "Frontend:    check terminal output (usually http://localhost:5173)"
Write-Host "`n后台登录: admin/admin123 或 finance/finance123"
Write-Host "前台登录: field001/123456（或 field002/field003 对应密码 123456）"