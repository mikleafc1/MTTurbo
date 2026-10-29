@echo off
rem Build DaryaSync.exe natively on Windows.
cd /d "%~dp0"
if not exist dist mkdir dist
set GOOS=windows
go build -trimpath -ldflags "-s -w" -o dist\DaryaSync.exe .
if errorlevel 1 (echo BUILD FAILED & exit /b 1)
echo OK -^> dist\DaryaSync.exe
