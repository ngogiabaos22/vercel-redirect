"""Mini Studio – xưởng làm video whiteboard có giọng đọc, chạy 100% trên máy."""
import sys
from pathlib import Path

__version__ = "1.1.0"

# Bản đóng gói (PyInstaller): file chương trình nằm trong _MEIPASS, dữ liệu ghi cạnh file .exe
FROZEN = getattr(sys, "frozen", False)
if FROZEN:
    ROOT = Path(getattr(sys, "_MEIPASS", Path(sys.executable).parent))
    DATA_DIR = Path(sys.executable).resolve().parent / "data"
else:
    ROOT = Path(__file__).resolve().parent.parent
    DATA_DIR = ROOT / "data"
PROJECTS_DIR = DATA_DIR / "projects"
VOICES_DIR = DATA_DIR / "voices"
MUSIC_DIR = DATA_DIR / "music"
WEB_DIR = ROOT / "web"
ENGINE_DIR = ROOT / "engine"
HAND_PNG = ENGINE_DIR / "assets" / "drawing-hand-clean.png"
HANDS_DIR = DATA_DIR / "hands"

for _d in (DATA_DIR, PROJECTS_DIR, VOICES_DIR, MUSIC_DIR, HANDS_DIR):
    _d.mkdir(parents=True, exist_ok=True)
