@echo off
rem Build DaryaSync.exe natively on Windows.
rem v1.0.1+: -H=windowsgui = no black console window; the Persian panel opens in the browser.
cd /d "%~dp0"
if not exist dist mkdir dist
set GOOS=windows
go build -trimpath -ldflags "-s -w -H=windowsgui" -o dist\DaryaSync.exe .
if errorlevel 1 (echo BUILD FAILED & exit /b 1)
echo OK -^> dist\DaryaSync.exe (windowsgui, no console)
