@echo off
REM Start PHP built-in server for this project on port 8000
REM Usage: double-click this file or run from PowerShell/CMD.
setlocal

:: Try to find a PHP executable in common locations or on PATH
set "PHP_EXE="
if exist "%~dp0php\php.exe" set "PHP_EXE=%~dp0php\php.exe"
if not defined PHP_EXE if exist "C:\xampp_old\php\php.exe" set "PHP_EXE=C:\xampp_old\php\php.exe"
if not defined PHP_EXE if exist "C:\xampp\php\php.exe" set "PHP_EXE=C:\xampp\php\php.exe"
if not defined PHP_EXE (
	where php >nul 2>&1 && set "PHP_EXE=php"
)

if not defined PHP_EXE (
	echo PHP executable not found. Install PHP or add it to PATH.
	pause
	exit /b 1
)

echo Starting PHP built-in server using %PHP_EXE% ...
start "PHP Server" "%PHP_EXE%" -S 127.0.0.1:8000 -t "%~dp0"
timeout /t 1 >nul
echo Opening browser at http://127.0.0.1:8000/
start "" "http://127.0.0.1:8000/"
echo Server started. Close this window to stop the server (if you started it manually stop the php process).
pause
