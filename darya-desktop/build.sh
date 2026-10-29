#!/usr/bin/env bash
# Build Darya.exe (windows desktop launcher) — cross-compile from Linux/macOS.
# -H=windowsgui => no console window; opens the Persian panel in an app-mode window.
set -euo pipefail
cd "$(dirname "$0")"
mkdir -p dist
GOOS=windows GOARCH=amd64 go build -trimpath -ldflags "-s -w -H=windowsgui" -o dist/Darya.exe .
echo "OK -> dist/Darya.exe"
