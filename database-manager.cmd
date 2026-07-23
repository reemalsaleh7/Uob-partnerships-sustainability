@echo off
setlocal
cd /d "%~dp0"

if not "%~1"=="" goto run_with_arguments

echo.
echo UOB Database Manager
echo ====================
echo 1. Check only
echo 2. Install missing required updates
echo 3. Install updates plus local demo users and showcase data
echo.
set /p UOB_DB_CHOICE=Choose 1, 2, or 3 [2]: 

if "%UOB_DB_CHOICE%"=="" set UOB_DB_CHOICE=2
if "%UOB_DB_CHOICE%"=="1" goto check_only
if "%UOB_DB_CHOICE%"=="2" goto install
if "%UOB_DB_CHOICE%"=="3" goto install_demo

echo Invalid choice.
exit /b 1

:check_only
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\database_manager.ps1" -CheckOnly
goto finished

:install
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\database_manager.ps1"
goto finished

:install_demo
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\database_manager.ps1" -IncludeDevelopmentData
goto finished

:run_with_arguments
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\database_manager.ps1" %*

:finished
set UOB_DB_EXIT=%ERRORLEVEL%
echo.
if "%UOB_DB_EXIT%"=="0" (
    echo Database manager completed successfully.
) else (
    echo Database manager stopped with exit code %UOB_DB_EXIT%.
)
pause
exit /b %UOB_DB_EXIT%
