"""Giọng đọc. Nhiều "động cơ" miễn phí, ưu tiên chạy offline:

- piper     : Piper TTS (offline, nhanh, có giọng tiếng Việt). pip install piper-tts
- espeak    : eSpeak NG (offline, giọng máy), nếu đã cài trên hệ thống
- edge      : Microsoft Edge TTS (miễn phí, giọng rất tự nhiên, CẦN INTERNET). pip install edge-tts
- record    : tự thu âm trong trình duyệt / tải file lên (xử lý ở server.py)
- estimate  : chưa có giọng, chỉ ước lượng thời lượng để dựng nháp
"""
from __future__ import annotations

import asyncio
import importlib.util
import json
import shutil
import subprocess
import tempfile
import threading
import urllib.request
import wave
from pathlib import Path

from . import NO_WINDOW, VOICES_DIR
from .audio import duration_ms, to_wav

PIPER_BASE = "https://huggingface.co/rhasspy/piper-voices/resolve/main"

# Danh mục giọng Piper gợi ý (tải về thư mục data/voices)
PIPER_CATALOG = [
    {"id": "vi_VN-vais1000-medium", "lang": "vi", "label": "Tiếng Việt – VAIS1000 (nữ, chất lượng vừa)"},
    {"id": "vi_VN-25hours_single-low", "lang": "vi", "label": "Tiếng Việt – 25hours (nữ, nhẹ)"},
    {"id": "vi_VN-vivos-x_low", "lang": "vi", "label": "Tiếng Việt – VIVOS (nhiều giọng, rất nhẹ)"},
    {"id": "en_US-lessac-medium", "lang": "en", "label": "English US – Lessac"},
    {"id": "en_US-ryan-medium", "lang": "en", "label": "English US – Ryan (nam)"},
]

EDGE_VOICES = [
    {"id": "vi-VN-HoaiMyNeural", "label": "Hoài My (nữ, miền Bắc)"},
    {"id": "vi-VN-NamMinhNeural", "label": "Nam Minh (nam, miền Bắc)"},
    {"id": "en-US-AriaNeural", "label": "Aria (English, nữ)"},
    {"id": "en-US-GuyNeural", "label": "Guy (English, nam)"},
]


def _piper_url(vid: str) -> str:
    lang_region, name, quality = vid.split("-", 2)
    lang = lang_region.split("_")[0]
    return f"{PIPER_BASE}/{lang}/{lang_region}/{name}/{quality}/{vid}.onnx"


def has_module(name: str) -> bool:
    return importlib.util.find_spec(name) is not None


def engines_status() -> dict:
    piper_voices = sorted(p.stem for p in VOICES_DIR.glob("*.onnx"))
    return {
        "estimate": {"available": True, "offline": True, "label": "Ước lượng (chưa có giọng)", "voices": []},
        "piper": {"available": has_module("piper"), "offline": True, "label": "Piper TTS (offline)",
                  "voices": [{"id": v, "label": v} for v in piper_voices],
                  "catalog": [dict(c, installed=c["id"] in piper_voices) for c in PIPER_CATALOG],
                  "install": "pip install piper-tts"},
        "espeak": {"available": bool(shutil.which("espeak-ng") or shutil.which("espeak")), "offline": True,
                   "label": "eSpeak NG (offline, giọng máy)",
                   "voices": [{"id": "vi", "label": "Tiếng Việt"}, {"id": "en", "label": "English"}]},
        "edge": {"available": has_module("edge_tts"), "offline": False,
                 "label": "Edge TTS (miễn phí, cần internet)", "voices": EDGE_VOICES,
                 "install": "pip install edge-tts"},
        "record": {"available": True, "offline": True, "label": "Tự thu âm / tải file lên", "voices": []},
    }


# ───────────────────────── tải giọng Piper ─────────────────────────
_dl_lock = threading.Lock()


def download_piper_voice(vid: str, progress=None) -> Path:
    if vid not in {c["id"] for c in PIPER_CATALOG} and not vid.count("-") >= 2:
        raise ValueError("Tên giọng không hợp lệ")
    url = _piper_url(vid)
    with _dl_lock:
        for suffix in (".onnx.json", ".onnx"):
            dst = VOICES_DIR / f"{vid}{suffix}"
            if dst.exists() and dst.stat().st_size > 0:
                continue
            tmp = dst.with_suffix(dst.suffix + ".part")
            src_url = url + ".json" if suffix == ".onnx.json" else url
            with urllib.request.urlopen(src_url, timeout=60) as r, open(tmp, "wb") as f:
                total = int(r.headers.get("Content-Length") or 0)
                done = 0
                while True:
                    chunk = r.read(1 << 16)
                    if not chunk:
                        break
                    f.write(chunk)
                    done += len(chunk)
                    if progress and total:
                        progress(done / total, f"Đang tải {dst.name}: {done // 1048576} / {total // 1048576} MB")
            tmp.replace(dst)
    return VOICES_DIR / f"{vid}.onnx"


# ───────────────────────── tổng hợp ─────────────────────────
_piper_cache: dict[str, object] = {}
_piper_lock = threading.Lock()


def _synth_piper(text: str, voice: str, speed: float, out_wav: Path) -> None:
    if not has_module("piper"):
        raise RuntimeError("Chưa cài Piper TTS. Chạy: pip install piper-tts rồi mở lại Mini Studio.")
    model = VOICES_DIR / f"{voice}.onnx"
    if not model.exists():
        raise RuntimeError(f"Chưa có giọng Piper '{voice}'. Vào Cài đặt → Giọng đọc để tải.")
    from piper import PiperVoice  # type: ignore
    with _piper_lock:
        v = _piper_cache.get(voice)
        if v is None:
            v = PiperVoice.load(str(model))
            _piper_cache[voice] = v
        length_scale = 1.0 / max(0.5, min(2.0, speed or 1.0))
        with wave.open(str(out_wav), "wb") as wf:
            if hasattr(v, "synthesize_wav"):  # piper-tts >= 1.3
                try:
                    from piper import SynthesisConfig  # type: ignore
                except ImportError:
                    from piper.config import SynthesisConfig  # type: ignore
                v.synthesize_wav(text, wf, syn_config=SynthesisConfig(length_scale=length_scale))
            else:  # piper-tts 1.2
                v.synthesize(text, wf, length_scale=length_scale)


def _synth_espeak(text: str, voice: str, speed: float, out_wav: Path) -> None:
    exe = shutil.which("espeak-ng") or shutil.which("espeak")
    if not exe:
        raise RuntimeError("Chưa cài eSpeak NG")
    wpm = int(150 * (speed or 1.0))
    subprocess.run([exe, "-v", voice or "vi", "-s", str(wpm), "-w", str(out_wav), text],
                   check=True, capture_output=True, creationflags=NO_WINDOW)


def _synth_edge(text: str, voice: str, speed: float, out_file: Path) -> None:
    if not has_module("edge_tts"):
        raise RuntimeError("Chưa cài Edge TTS. Chạy: pip install edge-tts rồi mở lại Mini Studio.")
    import edge_tts  # type: ignore
    pct = int(round(((speed or 1.0) - 1.0) * 100))
    rate = f"{'+' if pct >= 0 else ''}{pct}%"

    async def run():
        await edge_tts.Communicate(text, voice or "vi-VN-HoaiMyNeural", rate=rate).save(str(out_file))

    asyncio.run(run())


def synthesize(engine: str, text: str, voice: str, speed: float, dst_wav: Path) -> int:
    """Tạo giọng đọc cho 1 câu → dst_wav (mono 44.1kHz). Trả về thời lượng ms."""
    with tempfile.TemporaryDirectory() as td:
        if engine == "piper":
            raw = Path(td) / "raw.wav"
            _synth_piper(text, voice, speed, raw)
        elif engine == "espeak":
            raw = Path(td) / "raw.wav"
            _synth_espeak(text, voice, speed, raw)
        elif engine == "edge":
            raw = Path(td) / "raw.mp3"
            _synth_edge(text, voice, speed, raw)
        else:
            raise RuntimeError(f"Động cơ giọng '{engine}' không tạo được giọng tự động")
        to_wav(raw, dst_wav)
    return duration_ms(dst_wav)


def voice_info_json() -> str:
    return json.dumps(engines_status(), ensure_ascii=False)
