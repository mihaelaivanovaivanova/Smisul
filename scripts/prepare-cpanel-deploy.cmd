@echo off
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0prepare-cpanel-deploy.ps1" %*
exit /b %ERRORLEVEL%
