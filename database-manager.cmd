@echo off
setlocal
cd /d "%~dp0"
set "UOB_DB_PAUSE=1"

if not "%~1"=="" (
    set "UOB_DB_PAUSE=0"
    goto run_with_arguments
)

echo.
echo UOB Database Manager
echo ====================
echo 1. Check only
echo 2. Install missing required updates
echo 3. Install updates plus local demo users and showcase data
echo 4. Create a new versioned database migration
echo.
set /p UOB_DB_CHOICE=Choose 1, 2, 3, or 4 [2]:

if "%UOB_DB_CHOICE%"=="" set UOB_DB_CHOICE=2
if "%UOB_DB_CHOICE%"=="1" goto check_only
if "%UOB_DB_CHOICE%"=="2" goto install
if "%UOB_DB_CHOICE%"=="3" goto install_demo
if "%UOB_DB_CHOICE%"=="4" goto create_migration

echo Invalid choice.
exit /b 1

:check_only
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\database_manager.ps1" -CheckOnly
set "UOB_DB_EXIT=%ERRORLEVEL%"
goto finished

:install
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\database_manager.ps1"
set "UOB_DB_EXIT=%ERRORLEVEL%"
goto finished

:install_demo
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\database_manager.ps1" -IncludeDevelopmentData
set "UOB_DB_EXIT=%ERRORLEVEL%"
goto finished

:create_migration
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\new_database_migration.ps1"
set "UOB_DB_EXIT=%ERRORLEVEL%"
goto finished

:run_with_arguments
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\database_manager.ps1" %*
set "UOB_DB_EXIT=%ERRORLEVEL%"
goto finished

:finished
if not defined UOB_DB_EXIT set "UOB_DB_EXIT=1"
echo.
if "%UOB_DB_EXIT%"=="0" (
    echo Database manager completed successfully.
) else (
    echo Database manager stopped with exit code %UOB_DB_EXIT%.
)
if "%UOB_DB_PAUSE%"=="1" pause
exit /b %UOB_DB_EXIT%
