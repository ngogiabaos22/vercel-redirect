"""Tiện ích âm thanh: tìm ffmpeg, chuẩn hoá WAV, đo thời lượng, trộn giọng + nhạc nền."""
from __future__ import annotations

import os
import shutil
import subprocess
import wave
from pathlib import Path

import numpy as np

SR = 44100  # tần số mẫu chuẩn của toàn app (mono, 16-bit)


def find_ffmpeg() -> str | None:
    env = os.environ.get("MINISTUDIO_FFMPEG")
    if env and Path(env).exists():
        return env
    found = shutil.which("ffmpeg")
    if found:
        return found
    try:
        import imageio_ffmpeg
        return imageio_ffmpeg.get_ffmpeg_exe()
    except Exception:
        return None


def ffmpeg(*args: str, check: bool = True) -> subprocess.CompletedProcess:
    exe = find_ffmpeg()
    if not exe:
        raise RuntimeError("Không tìm thấy ffmpeg. Chạy: pip install imageio-ffmpeg")
    res = subprocess.run([exe, "-y", "-hide_banner", "-loglevel", "error", *args],
                         capture_output=True, text=True, encoding="utf-8", errors="replace")
    if check and res.returncode != 0:
        raise RuntimeError(f"ffmpeg lỗi: {res.stderr.strip()[:400]}")
    return res


def to_wav(src: Path, dst: Path, trim_silence: bool = True) -> Path:
    """Chuyển mọi định dạng (mp3, webm, m4a…) về WAV mono 44.1kHz; cắt khoảng lặng đầu/cuối."""
    dst.parent.mkdir(parents=True, exist_ok=True)
    filters = []
    if trim_silence:
        # cắt lặng đầu, đảo ngược cắt lặng cuối rồi đảo lại
        rm = "silenceremove=start_periods=1:start_threshold=-45dB:start_silence=0.05"
        filters = [rm, "areverse", rm, "areverse"]
    args = ["-i", str(src)]
    if filters:
        args += ["-af", ",".join(filters)]
    args += ["-ac", "1", "-ar", str(SR), "-sample_fmt", "s16", str(dst)]
    ffmpeg(*args)
    return dst


def duration_ms(path: Path) -> int:
    with wave.open(str(path), "rb") as w:
        return int(round(w.getnframes() * 1000 / w.getframerate()))


def read_wav(path: Path) -> np.ndarray:
    with wave.open(str(path), "rb") as w:
        n = w.getnframes()
        ch = w.getnchannels()
        sw = w.getsampwidth()
        rate = w.getframerate()
        raw = w.readframes(n)
    if sw != 2 or rate != SR or ch != 1:
        tmp = path.with_name(path.stem + "_norm.wav")
        to_wav(path, tmp, trim_silence=False)
        return read_wav(tmp)
    return np.frombuffer(raw, dtype=np.int16).astype(np.float32) / 32768.0


def write_wav(path: Path, samples: np.ndarray) -> Path:
    path.parent.mkdir(parents=True, exist_ok=True)
    peak = float(np.max(np.abs(samples))) if samples.size else 0.0
    if peak > 0.98:
        samples = samples * (0.98 / peak)
    data = (np.clip(samples, -1, 1) * 32767).astype(np.int16)
    with wave.open(str(path), "wb") as w:
        w.setnchannels(1)
        w.setsampwidth(2)
        w.setframerate(SR)
        w.writeframes(data.tobytes())
    return path


def build_track(total_ms: int, clips: list[tuple[int, Path]], bgm: Path | None = None,
                bgm_volume: float = 0.18, duck: bool = True) -> np.ndarray:
    """Đặt các đoạn giọng đọc vào đúng mốc thời gian, trộn nhạc nền (tự hạ nhỏ khi có giọng)."""
    n = int(total_ms * SR / 1000) + 1
    voice = np.zeros(n, dtype=np.float32)
    for start_ms, path in clips:
        if not path or not Path(path).exists():
            continue
        s = read_wav(Path(path))
        i0 = int(start_ms * SR / 1000)
        i1 = min(n, i0 + len(s))
        if i1 > i0:
            voice[i0:i1] += s[: i1 - i0]
    if bgm and Path(bgm).exists():
        tmp = Path(bgm).with_suffix(".mix.wav")
        if not tmp.exists() or tmp.stat().st_mtime < Path(bgm).stat().st_mtime:
            to_wav(Path(bgm), tmp, trim_silence=False)
        music = read_wav(tmp)
        if music.size:
            reps = int(np.ceil(n / music.size))
            music = np.tile(music, reps)[:n]
            gain = np.full(n, bgm_volume, dtype=np.float32)
            if duck:
                # đường bao giọng nói, làm mượt ~250ms, hạ nhạc còn 35% khi đang đọc
                env = (np.abs(voice) > 0.01).astype(np.float32)
                win = int(0.25 * SR)
                kernel = np.ones(win, dtype=np.float32) / win
                env = np.convolve(env, kernel, mode="same")
                env = np.clip(env * 4, 0, 1)
                gain = bgm_volume * (1 - 0.65 * env)
            # fade in/out nhạc nền
            f = min(int(1.5 * SR), n // 4)
            if f > 0:
                gain[:f] *= np.linspace(0, 1, f)
                gain[-f:] *= np.linspace(1, 0, f)
            voice = voice + music * gain
    return voice


def silence_wav(path: Path, ms: int) -> Path:
    return write_wav(path, np.zeros(int(ms * SR / 1000), dtype=np.float32))
