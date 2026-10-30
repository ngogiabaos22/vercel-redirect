#!/usr/bin/env bash
# Dựng bộ cài đặt Windows (MiniStudio-Setup.exe) ngay trên Linux/macOS – không cần máy Windows.
# Gói kèm Python cho Windows (python-build-standalone) + thư viện bản Windows từ PyPI → cài xong chạy luôn, không cần mạng.
#   sudo apt install nsis        # (macOS: brew install makensis)
#   ./packaging/build_installer.sh
# Kết quả: dist/MiniStudio-Setup.exe
set -euo pipefail
cd "$(dirname "$0")/.."

PY_VER="3.11.10"
PBS_TAG="20241016"
PY_URL="https://github.com/astral-sh/python-build-standalone/releases/download/${PBS_TAG}/cpython-${PY_VER}+${PBS_TAG}-x86_64-pc-windows-msvc-install_only_stripped.tar.gz"
VERSION=$(python3 -c "import re;print(re.search(r'__version__ = \"(.+?)\"', open('ministudio/__init__.py').read()).group(1))")
PIP=${PIP:-pip3}

BUILD=build/installer
PAYLOAD=$BUILD/payload
rm -rf "$PAYLOAD"
mkdir -p "$PAYLOAD/app" "$BUILD/cache" dist

echo "[1/4] Python ${PY_VER} cho Windows"
[ -f "$BUILD/cache/python.tar.gz" ] || curl -fL --retry 3 -o "$BUILD/cache/python.tar.gz" "$PY_URL"
tar -xzf "$BUILD/cache/python.tar.gz" -C "$PAYLOAD"   # → $PAYLOAD/python/python.exe

echo "[2/4] Thư viện bản Windows (win_amd64, cp311)"
"$PIP" install --quiet --no-compile --only-binary=:all: --platform win_amd64 --python-version 3.11 \
  --implementation cp --target "$PAYLOAD/python/Lib/site-packages" \
  -r requirements.txt -r requirements-tts.txt
rm -rf "$PAYLOAD/python/Lib/site-packages/bin"

echo "[3/4] Mã nguồn Mini Studio"
cp -r app.py ministudio engine web "$PAYLOAD/app/"
mkdir -p "$PAYLOAD/app/data/music" "$PAYLOAD/app/data/hands" "$PAYLOAD/app/data/voices"
find "$PAYLOAD/app" -name "__pycache__" -type d -prune -exec rm -rf {} +
cp README.md "$PAYLOAD/HUONG-DAN.md"
printf '@echo off\r\ncd /d "%%~dp0app"\r\n"%%~dp0python\\python.exe" app.py %%*\r\n' > "$PAYLOAD/MiniStudio.bat"

echo "[4/4] Đóng gói bộ cài đặt (NSIS)"
cp packaging/ministudio.ico "$BUILD/"
cp packaging/installer.nsi "$BUILD/"
(cd "$BUILD" && makensis -V2 -DVERSION="$VERSION" -DPAYLOAD=payload -DOUTFILE="MiniStudio-Setup.exe" installer.nsi)
mv "$BUILD/MiniStudio-Setup.exe" "dist/MiniStudio-${VERSION}-Setup.exe"
ls -lh "dist/MiniStudio-${VERSION}-Setup.exe"
