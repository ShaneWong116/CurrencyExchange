@echo off
REM Laravel Backend Startup Script
REM Using PowerShell for better output control

powershell -NoProfile -ExecutionPolicy Bypass -Command ^
"cd e:\PROJECT\CurrencyExSystem\CurrencyExSystem\ExchangeSystem\backend; ^
Write-Host '========================================' -ForegroundColor Cyan; ^
Write-Host ' Starting Laravel Backend Server' -ForegroundColor Green; ^
Write-Host '========================================' -ForegroundColor Cyan; ^
Write-Host ''; ^
Write-Host '[1/3] Clearing configuration cache...' -ForegroundColor Yellow; ^
php artisan config:clear 2>$null; ^
Write-Host '[2/3] Clearing application cache...' -ForegroundColor Yellow; ^
php artisan cache:clear 2>$null; ^
Write-Host '[3/3] Starting PHP built-in server...' -ForegroundColor Yellow; ^
Write-Host ''; ^
Write-Host '========================================' -ForegroundColor Cyan; ^
Write-Host ' Backend Server is Running' -ForegroundColor Green; ^
Write-Host '========================================' -ForegroundColor Cyan; ^
Write-Host '  API Endpoint: http://127.0.0.1:8000/api' -ForegroundColor White; ^
Write-Host '  Admin Panel:  http://127.0.0.1:8000/admin' -ForegroundColor White; ^
Write-Host '========================================' -ForegroundColor Cyan; ^
Write-Host ''; ^
Write-Host 'Press Ctrl+C to stop the server' -ForegroundColor Yellow; ^
Write-Host ''; ^
php -S 127.0.0.1:8000 -t public"

pause
