#!/usr/bin/env bash
# Cross-compile DaryaSync.exe for Windows (amd64) from Linux/macOS.
# v1.0.1+: -H=windowsgui => no black console window; the Persian web panel
# opens automatically in the browser (full RTL/Persian support there).
set -euo pipefail
cd "$(dirname "$0")"
mkdir -p dist
GOOS=windows GOARCH=amd64 go build -trimpath -ldflags "-s -w -H=windowsgui" -o dist/DaryaSync.exe .
echo "OK -> dist/DaryaSync.exe (windowsgui, no console)"
