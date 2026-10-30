@echo off
chcp 65001 >nul
REM Đóng gói Mini Studio thành phần mềm chạy độc lập trên Windows (máy đích KHÔNG cần cài Python).
REM Chạy từ thư mục mini-studio:  packaging\build_windows.bat
REM Kết quả:
REM   dist\MiniStudio\MiniStudio.exe        – bản chạy luôn (copy cả thư mục sang máy khác)
REM   dist\MiniStudio-portable.zip          – bản nén để gửi đi
REM   dist\MiniStudio-Setup.exe             – bộ cài đặt (nếu máy có Inno Setup 6)
cd /d "%~dp0\.."

where python >nul 2>nul || (echo Cần Python 3.10+ để build. Tải ở https://www.python.org/downloads/ & pause & exit /b 1)

if not exist .build-venv\Scripts\python.exe (
  echo [1/4] Tạo môi trường build...
  python -m venv .build-venv || (pause & exit /b 1)
)
echo [2/4] Cài thư viện...
.build-venv\Scripts\python -m pip install -q --upgrade pip
.build-venv\Scripts\python -m pip install -q -r requirements.txt -r requirements-tts.txt pyinstaller || (pause & exit /b 1)

echo [3/4] Đóng gói bằng PyInstaller...
.build-venv\Scripts\pyinstaller packaging\ministudio.spec --noconfirm --clean || (pause & exit /b 1)
copy /y README.md dist\MiniStudio\HUONG-DAN.md >nul
powershell -NoProfile -Command "Compress-Archive -Path dist\MiniStudio -DestinationPath dist\MiniStudio-portable.zip -Force"

echo [4/4] Tạo bộ cài đặt...
set ISCC="%ProgramFiles(x86)%\Inno Setup 6\ISCC.exe"
if exist %ISCC% (
  %ISCC% /Qp packaging\installer.iss
) else (
  echo   Bỏ qua: chưa cài Inno Setup 6 ^(https://jrsoftware.org/isdl.php^). Vẫn dùng được bản zip.
)
echo.
echo Xong! Xem thư mục dist\
pause
