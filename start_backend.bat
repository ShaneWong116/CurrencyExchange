@echo off
cd /d "%~dp0backend"
echo Starting Laravel Backend Server...
echo.
php artisan serve --port=8000
pause
