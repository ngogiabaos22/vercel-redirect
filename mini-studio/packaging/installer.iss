; Bộ cài đặt Windows cho Mini Studio (Inno Setup 6) – chạy sau khi PyInstaller đã tạo dist\MiniStudio
;   "C:\Program Files (x86)\Inno Setup 6\ISCC.exe" packaging\installer.iss
; Cài vào thư mục người dùng (không cần quyền admin) để app ghi được dữ liệu cạnh file .exe.

#define AppName "Mini Studio"
#ifndef AppVersion
  #define AppVersion "1.1.0"
#endif

[Setup]
AppId={{6C1A3E52-5B8D-4E0B-9A63-2F1D0B7C4D11}
AppName={#AppName}
AppVersion={#AppVersion}
AppPublisher=Mini Studio
DefaultDirName={localappdata}\Programs\MiniStudio
DefaultGroupName={#AppName}
PrivilegesRequired=lowest
OutputDir=..\dist
OutputBaseFilename=MiniStudio-Setup
SetupIconFile=ministudio.ico
UninstallDisplayIcon={app}\MiniStudio.exe
Compression=lzma2/max
SolidCompression=yes
WizardStyle=modern
DisableProgramGroupPage=yes

[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"

[Tasks]
Name: "desktopicon"; Description: "Tạo biểu tượng ngoài Desktop"; GroupDescription: "Biểu tượng:"

[Files]
Source: "..\dist\MiniStudio\*"; DestDir: "{app}"; Flags: ignoreversion recursesubdirs createallsubdirs

[Dirs]
Name: "{app}\data"

[Icons]
Name: "{group}\{#AppName}"; Filename: "{app}\MiniStudio.exe"; WorkingDir: "{app}"
Name: "{autodesktop}\{#AppName}"; Filename: "{app}\MiniStudio.exe"; WorkingDir: "{app}"; Tasks: desktopicon

[Run]
Filename: "{app}\MiniStudio.exe"; Description: "Mở Mini Studio"; Flags: nowait postinstall skipifsilent

[UninstallDelete]
; giữ lại thư mục data (dự án, cài đặt) khi gỡ cài đặt – xoá tay nếu muốn
Type: filesandordirs; Name: "{app}\_internal"
