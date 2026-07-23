@echo off
setlocal
cd /d "%~dp0"

powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\new_database_migration.ps1" %*
set UOB_MIGRATION_EXIT=%ERRORLEVEL%

echo.
if "%UOB_MIGRATION_EXIT%"=="0" (
    echo Migration file created successfully.
) else (
    echo Migration creation stopped with exit code %UOB_MIGRATION_EXIT%.
)
pause
exit /b %UOB_MIGRATION_EXIT%
