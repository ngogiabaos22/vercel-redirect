"""Mini Studio – xưởng làm video whiteboard có giọng đọc, chạy 100% trên máy."""
from pathlib import Path

__version__ = "1.0.0"

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
