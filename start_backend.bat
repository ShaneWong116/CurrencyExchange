@echo off
setlocal enabledelayedexpansion

REM Get the directory where this script is located
set "SCRIPT_DIR=%~dp0"
set "BACKEND_DIR=%SCRIPT_DIR%backend"

REM Check if backend directory exists
if not exist "%BACKEND_DIR%" (
    echo Error: Backend directory not found at %BACKEND_DIR%
    echo Please make sure you run this script from the correct location.
    pause
    exit /b 1
)

REM Change to backend directory
cd /d "%BACKEND_DIR%"

REM Check if public directory exists
if not exist "public" (
    echo Error: public directory not found in %BACKEND_DIR%
    pause
    exit /b 1
)

REM Display startup message
echo ========================================
echo  Starting Laravel Backend Server
echo ========================================
echo.

REM Clear cache
echo [1/3] Clearing configuration cache...
php artisan config:clear 2>nul
if errorlevel 1 (
    echo Warning: Failed to clear config cache
)

echo [2/3] Clearing application cache...
php artisan cache:clear 2>nul
if errorlevel 1 (
    echo Warning: Failed to clear application cache
)

echo [3/3] Starting PHP built-in server...
echo.
echo ========================================
echo  Backend Server is Running
echo ========================================
echo   API Endpoint: http://127.0.0.1:8000/api
echo   Admin Panel:  http://127.0.0.1:8000/admin
echo ========================================
echo.
echo Press Ctrl+C to stop the server
echo.

REM Start PHP built-in server
php -S 127.0.0.1:8000 -t public

REM If server stops, show message
echo.
echo Server stopped.
pause
