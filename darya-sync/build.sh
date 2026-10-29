#!/usr/bin/env bash
# Cross-compile DaryaSync.exe for Windows (amd64) from Linux/macOS.
set -euo pipefail
cd "$(dirname "$0")"
mkdir -p dist
GOOS=windows GOARCH=amd64 go build -trimpath -ldflags "-s -w" -o dist/DaryaSync.exe .
echo "OK -> dist/DaryaSync.exe"
