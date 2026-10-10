@echo off
REM Double-click me (or run "find-database.bat start" to also start the right MySQL
REM if none is running). Finds the Green Valley database on this PC and saves the
REM answer to DATABASE-STATUS.txt. See DATABASE.md for the background.
cd /d "%~dp0"
if /i "%~1"=="start" (
    powershell -NoProfile -ExecutionPolicy Bypass -File "scripts\find-database.ps1" -Start
) else (
    powershell -NoProfile -ExecutionPolicy Bypass -File "scripts\find-database.ps1"
)
echo.
pause
