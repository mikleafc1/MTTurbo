@echo off
rem Build Darya.exe natively on Windows.
cd /d "%~dp0"
if not exist dist mkdir dist
set GOOS=windows
go build -trimpath -ldflags "-s -w -H=windowsgui" -o dist\Darya.exe .
if errorlevel 1 (echo BUILD FAILED & exit /b 1)
echo OK -^> dist\Darya.exe
