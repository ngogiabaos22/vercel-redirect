#!/usr/bin/env bash
# Mini Studio – khởi động trên macOS / Linux
cd "$(dirname "$0")"
PY=${PYTHON:-python3}
if [ ! -x .venv/bin/python ]; then
  echo "[1/3] Tạo môi trường Python riêng (.venv)…"
  "$PY" -m venv .venv || { echo "Cần Python 3.10+"; exit 1; }
fi
echo "[2/3] Kiểm tra thư viện…"
.venv/bin/python -m pip install -q --disable-pip-version-check -r requirements.txt
.venv/bin/python -m pip install -q --disable-pip-version-check -r requirements-tts.txt || echo "(Bỏ qua: chưa cài được giọng đọc tự động)"
echo "[3/3] Mở Mini Studio…"
exec .venv/bin/python app.py "$@"
