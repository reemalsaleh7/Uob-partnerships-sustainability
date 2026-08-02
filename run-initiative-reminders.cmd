@echo off
setlocal
"C:\xampp\php\php.exe" "%~dp0uob-agreements\bin\initiative-reminders.php" 100
set exit_code=%ERRORLEVEL%
endlocal & exit /b %exit_code%
