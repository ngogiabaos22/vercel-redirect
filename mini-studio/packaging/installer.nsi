; Bộ cài đặt Windows cho Mini Studio (NSIS 3) – dựng bằng packaging/build_installer.sh (chạy được trên Linux).
; Gói kèm: Python cho Windows + toàn bộ thư viện + Mini Studio → máy đích không cần mạng, không cần cài Python.
; Cài vào thư mục người dùng (không cần quyền admin) để app ghi được dữ liệu.
Unicode true
!include "MUI2.nsh"

!ifndef VERSION
  !define VERSION "1.1.0"
!endif
!ifndef PAYLOAD
  !define PAYLOAD "payload"
!endif
!ifndef OUTFILE
  !define OUTFILE "MiniStudio-Setup.exe"
!endif

Name "Mini Studio ${VERSION}"
OutFile "${OUTFILE}"
InstallDir "$LOCALAPPDATA\Programs\MiniStudio"
InstallDirRegKey HKCU "Software\MiniStudio" "InstallDir"
RequestExecutionLevel user
SetCompressor /SOLID lzma
SetCompressorDictSize 64
BrandingText "Mini Studio ${VERSION}"

!define MUI_ICON "ministudio.ico"
!define MUI_UNICON "ministudio.ico"
!define MUI_ABORTWARNING
; mở bằng pythonw.exe: không có cửa sổ cmd, chỉ hiện cửa sổ Mini Studio
!define MUI_FINISHPAGE_RUN
!define MUI_FINISHPAGE_RUN_FUNCTION LaunchApp
!define MUI_FINISHPAGE_RUN_TEXT "Mở Mini Studio ngay"

!insertmacro MUI_PAGE_WELCOME
!insertmacro MUI_PAGE_DIRECTORY
!insertmacro MUI_PAGE_INSTFILES
!insertmacro MUI_PAGE_FINISH
!insertmacro MUI_UNPAGE_CONFIRM
!insertmacro MUI_UNPAGE_INSTFILES
!insertmacro MUI_LANGUAGE "Vietnamese"

Function LaunchApp
  Exec '"$INSTDIR\python\pythonw.exe" "$INSTDIR\app\app.py"'
FunctionEnd

Section "Mini Studio" SecMain
  SetOutPath "$INSTDIR"
  ; cập nhật bản cũ: thay mã chương trình, giữ nguyên dữ liệu người dùng (app\data)
  RMDir /r "$INSTDIR\python"
  RMDir /r "$INSTDIR\app\ministudio"
  RMDir /r "$INSTDIR\app\web"
  RMDir /r "$INSTDIR\app\engine"
  File /r "${PAYLOAD}\*.*"
  File "ministudio.ico"

  CreateDirectory "$SMPROGRAMS\Mini Studio"
  CreateShortcut "$SMPROGRAMS\Mini Studio\Mini Studio.lnk" "$INSTDIR\python\pythonw.exe" '"$INSTDIR\app\app.py"' "$INSTDIR\ministudio.ico" 0
  ; bản có cửa sổ console để xem log khi cần báo lỗi
  CreateShortcut "$SMPROGRAMS\Mini Studio\Mini Studio (chế độ gỡ lỗi).lnk" "$INSTDIR\python\python.exe" '"$INSTDIR\app\app.py"' "$INSTDIR\ministudio.ico" 0
  CreateShortcut "$SMPROGRAMS\Mini Studio\Thư mục dữ liệu.lnk" "$INSTDIR\app\data"
  CreateShortcut "$SMPROGRAMS\Mini Studio\Gỡ cài đặt Mini Studio.lnk" "$INSTDIR\Uninstall.exe"
  CreateShortcut "$DESKTOP\Mini Studio.lnk" "$INSTDIR\python\pythonw.exe" '"$INSTDIR\app\app.py"' "$INSTDIR\ministudio.ico" 0

  WriteRegStr HKCU "Software\MiniStudio" "InstallDir" "$INSTDIR"
  WriteUninstaller "$INSTDIR\Uninstall.exe"
  !define UNINST_KEY "Software\Microsoft\Windows\CurrentVersion\Uninstall\MiniStudio"
  WriteRegStr HKCU "${UNINST_KEY}" "DisplayName" "Mini Studio"
  WriteRegStr HKCU "${UNINST_KEY}" "DisplayVersion" "${VERSION}"
  WriteRegStr HKCU "${UNINST_KEY}" "Publisher" "Mini Studio"
  WriteRegStr HKCU "${UNINST_KEY}" "DisplayIcon" "$INSTDIR\ministudio.ico"
  WriteRegStr HKCU "${UNINST_KEY}" "UninstallString" '"$INSTDIR\Uninstall.exe"'
  WriteRegDWORD HKCU "${UNINST_KEY}" "NoModify" 1
  WriteRegDWORD HKCU "${UNINST_KEY}" "NoRepair" 1
SectionEnd

Section "Uninstall"
  ; giữ lại app\data (dự án, cài đặt, key) – xoá tay nếu không cần nữa
  RMDir /r "$INSTDIR\python"
  RMDir /r "$INSTDIR\app\ministudio"
  RMDir /r "$INSTDIR\app\web"
  RMDir /r "$INSTDIR\app\engine"
  Delete "$INSTDIR\app\*.*"
  Delete "$INSTDIR\*.bat"
  Delete "$INSTDIR\*.ico"
  Delete "$INSTDIR\*.md"
  Delete "$INSTDIR\Uninstall.exe"
  RMDir "$INSTDIR\app"
  RMDir "$INSTDIR"
  RMDir /r "$SMPROGRAMS\Mini Studio"
  Delete "$DESKTOP\Mini Studio.lnk"
  DeleteRegKey HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\MiniStudio"
  DeleteRegKey HKCU "Software\MiniStudio"
SectionEnd
