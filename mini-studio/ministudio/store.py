"""Lưu trữ dự án và cài đặt dưới dạng file JSON trong thư mục data/ (không cần CSDL)."""
from __future__ import annotations

import json
import re
import secrets
import shutil
import threading
import time
import unicodedata
from copy import deepcopy
from pathlib import Path

from . import DATA_DIR, PROJECTS_DIR

_lock = threading.RLock()

SETTINGS_FILE = DATA_DIR / "settings.json"

DEFAULT_SETTINGS = {
    "ollamaUrl": "http://127.0.0.1:11434",
    "ollamaModel": "qwen2.5:7b",
    "a1111Url": "http://127.0.0.1:7860",
    "comfyUrl": "http://127.0.0.1:8188",
    "comfyCheckpoint": "",
    "imageBackend": "a1111",
    "fontPath": "",
    "readingSpeed": 230,  # âm tiết / phút dùng để ước lượng khi chưa có giọng
    "paperColor": "#F5EBD7",
    "hand": "drawing-hand-clean.png",
    # ── tự động hoá ──
    # Nguồn tạo ảnh, thử lần lượt từ trên xuống (nguồn lỗi → chuyển nguồn sau)
    "imageProviders": [
        {"id": "pollinations", "type": "pollinations", "name": "Pollinations (miễn phí, không cần key)",
         "enabled": True, "model": "flux", "apiKey": ""},
    ],
    "imageConcurrency": 2,      # số cảnh tạo ảnh cùng lúc
    "imageRetries": 2,          # số lần thử lại mỗi nguồn trước khi chuyển nguồn sau
    # AI viết chữ (kịch bản, prompt ảnh): ollama | pollinations | gemini | openai
    "llmBackend": "pollinations",
    "llmBaseUrl": "",
    "llmApiKey": "",
    "llmModel": "",
    "autoSceneSeconds": 12,     # kịch bản không có ## → tự chia cảnh dài khoảng N giây
    "autoVoiceEngine": "auto",  # auto | edge | piper | espeak | estimate
    "autoVoice": "",
    "videoEncoder": "auto",     # auto | libx264 | h264_nvenc | h264_qsv | h264_amf
    # ── thông báo Telegram ──
    "telegramEnabled": False,
    "telegramToken": "",
    "telegramChatId": "",
    "telegramSendVideo": True,
    "telegramOnError": True,
    # ── preset cài đặt dự án ──
    "presets": {},
    "defaultPreset": "",
}

ASPECTS = {
    "16:9": (1920, 1080),
    "9:16": (1080, 1920),
    "1:1": (1080, 1080),
    "4:5": (1080, 1350),
}


def uid(prefix: str = "") -> str:
    return prefix + secrets.token_hex(4)


def now() -> float:
    return round(time.time(), 3)


def slugify(text: str) -> str:
    text = unicodedata.normalize("NFD", text.replace("đ", "d").replace("Đ", "D"))
    text = "".join(c for c in text if unicodedata.category(c) != "Mn")
    text = re.sub(r"[^a-zA-Z0-9]+", "-", text).strip("-").lower()
    return text[:40] or "du-an"


# ───────────────────────── cài đặt ─────────────────────────
def load_settings() -> dict:
    s = deepcopy(DEFAULT_SETTINGS)
    if SETTINGS_FILE.exists():
        try:
            s.update(json.loads(SETTINGS_FILE.read_text(encoding="utf-8")))
        except Exception:
            pass
    return s


def save_settings(data: dict) -> dict:
    s = load_settings()
    s.update({k: v for k, v in data.items() if k in DEFAULT_SETTINGS})
    _atomic_write(SETTINGS_FILE, s)
    return s


# ───────────────────────── dự án ─────────────────────────
def project_dir(pid: str) -> Path:
    if not re.fullmatch(r"[a-z0-9\-]+", pid or ""):
        raise ValueError("ID dự án không hợp lệ")
    return PROJECTS_DIR / pid


def _atomic_write(path: Path, data: dict) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    tmp = path.with_suffix(".tmp")
    tmp.write_text(json.dumps(data, ensure_ascii=False, indent=2), encoding="utf-8")
    tmp.replace(path)


def default_export() -> dict:
    return {
        "quality": "720",          # 480 | 720 | 1080
        "fps": 30,
        "inkPath": "grid",          # grid | skeleton
        "colorFill": "contour-wipe",  # contour-wipe | brush
        "hand": True,
        "subtitles": {"enabled": True, "size": 100, "position": "bottom", "style": "box"},
        "bgm": {"file": "", "volume": 18, "duck": True},
        "leadMs": 400,
        "tailMs": 900,
    }


def new_project(name: str, aspect: str = "16:9", script: str = "") -> dict:
    pid = f"{slugify(name)}-{secrets.token_hex(2)}"
    proj = {
        "id": pid,
        "name": name.strip() or "Dự án mới",
        "aspect": aspect if aspect in ASPECTS else "16:9",
        "created": now(),
        "updated": now(),
        "script": script,
        "scenes": [],
        "voice": {"engine": "estimate", "voice": "", "speed": 1.0, "gapMs": 250},
        "export": default_export(),
        "renders": [],
    }
    d = project_dir(pid)
    for sub in ("images", "audio", "renders", "exports", "music"):
        (d / sub).mkdir(parents=True, exist_ok=True)
    save_project(proj, touch=False)
    return proj


def load_project(pid: str) -> dict:
    with _lock:
        f = project_dir(pid) / "project.json"
        if not f.exists():
            raise FileNotFoundError("Không tìm thấy dự án")
        proj = json.loads(f.read_text(encoding="utf-8"))
    # bổ sung trường mới cho dự án cũ
    exp = default_export()
    exp.update(proj.get("export") or {})
    proj["export"] = exp
    proj.setdefault("renders", [])
    return proj


def save_project(proj: dict, touch: bool = True) -> dict:
    with _lock:
        if touch:
            proj["updated"] = now()
        _atomic_write(project_dir(proj["id"]) / "project.json", proj)
    return proj


def list_projects() -> list[dict]:
    out = []
    for d in sorted(PROJECTS_DIR.iterdir() if PROJECTS_DIR.exists() else []):
        f = d / "project.json"
        if not f.exists():
            continue
        try:
            p = json.loads(f.read_text(encoding="utf-8"))
        except Exception:
            continue
        thumb = ""
        for sc in p.get("scenes", []):
            if sc.get("image"):
                thumb = sc["image"]
                break
        lines = [ln for sc in p.get("scenes", []) for ln in sc.get("lines", [])]
        voiced = sum(1 for ln in lines if (ln.get("audio") or {}).get("file"))
        out.append({
            "id": p["id"], "name": p["name"], "aspect": p.get("aspect", "16:9"),
            "updated": p.get("updated", 0), "created": p.get("created", 0),
            "scenes": len(p.get("scenes", [])), "lines": len(lines), "voiced": voiced,
            "thumb": thumb, "renders": len(p.get("renders", [])),
            "durationMs": p.get("lastDurationMs", 0),
        })
    out.sort(key=lambda x: x["updated"], reverse=True)
    return out


def delete_project(pid: str) -> None:
    d = project_dir(pid)
    trash = DATA_DIR / "trash"
    trash.mkdir(exist_ok=True)
    if d.exists():
        shutil.move(str(d), str(trash / f"{pid}-{int(time.time())}"))


def duplicate_project(pid: str) -> dict:
    src = load_project(pid)
    new_id = f"{slugify(src['name'])}-{secrets.token_hex(2)}"
    shutil.copytree(project_dir(pid), project_dir(new_id),
                    ignore=shutil.ignore_patterns("renders", "exports"))
    for sub in ("renders", "exports"):
        (project_dir(new_id) / sub).mkdir(exist_ok=True)
    src["id"] = new_id
    src["name"] = src["name"] + " (bản sao)"
    src["created"] = now()
    src["renders"] = []
    return save_project(src)


def snapshot(proj: dict, label: str = "") -> str:
    """Lưu một phiên bản (để quay lại khi lỡ tay sửa)."""
    d = project_dir(proj["id"]) / "versions"
    d.mkdir(exist_ok=True)
    name = time.strftime("%Y%m%d-%H%M%S") + (f"-{slugify(label)}" if label else "")
    _atomic_write(d / f"{name}.json", proj)
    files = sorted(d.glob("*.json"))
    for old in files[:-30]:  # giữ 30 bản gần nhất
        old.unlink(missing_ok=True)
    return name


def list_snapshots(pid: str) -> list[str]:
    d = project_dir(pid) / "versions"
    return [f.stem for f in sorted(d.glob("*.json"), reverse=True)] if d.exists() else []


def restore_snapshot(pid: str, name: str) -> dict:
    f = project_dir(pid) / "versions" / f"{name}.json"
    if not re.fullmatch(r"[A-Za-z0-9\-]+", name) or not f.exists():
        raise FileNotFoundError("Không có phiên bản này")
    cur = load_project(pid)
    snapshot(cur, "truoc-khi-khoi-phuc")
    data = json.loads(f.read_text(encoding="utf-8"))
    data["id"] = pid
    return save_project(data)


def find_scene(proj: dict, sid: str) -> dict:
    for sc in proj["scenes"]:
        if sc["id"] == sid:
            return sc
    raise KeyError("Không tìm thấy cảnh")


def find_line(proj: dict, lid: str) -> tuple[dict, dict]:
    for sc in proj["scenes"]:
        for ln in sc["lines"]:
            if ln["id"] == lid:
                return sc, ln
    raise KeyError("Không tìm thấy câu")
