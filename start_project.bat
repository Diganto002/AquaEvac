@echo off
setlocal
cd /d "%~dp0"

echo ========================================================
echo AquaEvac - Launching Live Application
echo ========================================================
echo.

:: Detect PHP
set "PHP_CMD=php"
where php >nul 2>&1
if %errorlevel% neq 0 (
    if exist "%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe" (
        set "PHP_CMD=%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
    ) else if exist "C:\xampp\php\php.exe" (
        set "PHP_CMD=C:\xampp\php\php.exe"
    ) else (
        echo [ERROR] PHP executable was not found.
        pause
        exit /b 1
    )
)

:: Detect Python venv
if not exist "venv\Scripts\python.exe" (
    echo [ERROR] Python venv not found. Please run setup_xampp_ai.bat first.
    pause
    exit /b 1
)

echo [1/2] Starting Flask AI Engine on http://127.0.0.1:5000 ...
start "AquaEvac AI Engine" cmd /k "call venv\Scripts\activate.bat && python app.py"

echo [2/2] Starting PHP Web Server on http://127.0.0.1:8080 ...
start "AquaEvac Web App" cmd /k ""%PHP_CMD%" -S 127.0.0.1:8080"

timeout /t 3 >nul
echo.
echo Opening browser to http://localhost:8080/ ...
start http://localhost:8080/

echo.
echo AquaEvac is now LIVE!
echo Web App:    http://localhost:8080/
echo AI Engine:  http://127.0.0.1:5000/
echo.
pause
