@echo off
chcp 65001 >nul
cd /d "%~dp0"
title Mini Studio
if not exist ".venv\Scripts\python.exe" (
  echo [1/3] Tao moi truong Python rieng .venv ...
  py -3 -m venv .venv 2>nul || python -m venv .venv
  if errorlevel 1 ( echo Khong tim thay Python. Hay cai Python 3.10+ tu python.org va tick "Add to PATH". & pause & exit /b 1 )
)
echo [2/3] Kiem tra thu vien ...
".venv\Scripts\python.exe" -m pip install -q --disable-pip-version-check -r requirements.txt
".venv\Scripts\python.exe" -m pip install -q --disable-pip-version-check -r requirements-tts.txt || echo (Bo qua: chua cai duoc giong doc tu dong, van dung duoc thu am)
echo [3/3] Mo Mini Studio ...
".venv\Scripts\python.exe" app.py %*
pause
