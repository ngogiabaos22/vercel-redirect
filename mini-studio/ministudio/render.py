"""Xuất video: gọi bộ vẽ whiteboard (skill gốc) cho từng cảnh → ghép cảnh, phụ đề, giọng, nhạc nền."""
from __future__ import annotations

import hashlib
import json
import os
import re
import subprocess
import sys
import time
from pathlib import Path

import cv2
import numpy as np

from . import ENGINE_DIR, HAND_PNG, HANDS_DIR
from . import audio as A
from . import store
from .subtitles import composite, pick_font, render_overlay
from .timeline import build_annotation, project_timeline, scene_timeline, to_srt

RENDERER = ENGINE_DIR / "render_stream_whiteboard.py"
QUALITY_EDGE = {"480": 854, "720": 1280, "1080": 1920}


class Cancelled(Exception):
    pass


def output_size(aspect: str, quality: str) -> tuple[int, int]:
    aw, ah = store.ASPECTS.get(aspect, (1920, 1080))
    short = int(quality) if str(quality) in QUALITY_EDGE else 720
    if aw >= ah:
        h = short
        w = int(round(short * aw / ah))
    else:
        w = short
        h = int(round(short * ah / aw))
    return w - w % 2, h - h % 2


def _scene_image(proj: dict, scene: dict) -> Path:
    if not scene.get("image"):
        raise RuntimeError(f"Cảnh '{scene.get('title')}' chưa có ảnh")
    p = store.project_dir(proj["id"]) / scene["image"]
    if not p.exists():
        raise RuntimeError(f"Mất file ảnh của cảnh '{scene.get('title')}'")
    return p


def _ensure_elements(scene: dict) -> dict:
    if not scene.get("elements"):
        c = scene.get("canvas") or {"width": 1920, "height": 1080}
        scene = dict(scene)
        scene["elements"] = [{"id": "all", "label": "Toàn cảnh",
                              "region": {"x": 0, "y": 0, "width": c["width"], "height": c["height"]},
                              "lineIds": [ln["id"] for ln in scene["lines"]]}]
    return scene


def hand_path(name: str) -> Path:
    for d in (HANDS_DIR, ENGINE_DIR / "assets"):
        p = d / Path(name or "").name
        if name and p.exists():
            return p
    return HAND_PNG


def list_hands() -> list[str]:
    names = [p.name for p in (ENGINE_DIR / "assets").glob("*.png")]
    names += [p.name for p in HANDS_DIR.glob("*.png")]
    return sorted(set(names))


def video_info(path: Path) -> tuple[int, float]:
    cap = cv2.VideoCapture(str(path))
    n = int(cap.get(cv2.CAP_PROP_FRAME_COUNT))
    fps = cap.get(cv2.CAP_PROP_FPS) or 30
    cap.release()
    return n, fps


def render_scene(proj: dict, scene: dict, opts: dict, job=None, prog=(0.0, 1.0), quality: str | None = None) -> Path:
    """Render 1 cảnh (không tiếng). Có bộ nhớ đệm: không đổi gì thì không render lại."""
    img = _scene_image(proj, scene)
    scene = _ensure_elements(scene)
    tl = scene_timeline(proj, scene)
    ann = build_annotation(proj, scene, tl)
    q = str(quality or opts.get("quality", "720"))
    cap = QUALITY_EDGE.get(q, 1280)
    fps = int(opts.get("fps", 30))
    settings = store.load_settings()
    paper = settings.get("paperColor", "#F5EBD7")
    args = ["--ink-path", opts.get("inkPath", "grid"), "--color-fill", opts.get("colorFill", "contour-wipe"),
            "--fps", str(fps), "--cap-long-edge", str(cap), "--hand-height", str(int(cap * 493 / 1080)),
            "--canvas-hex", paper]
    if not opts.get("hand", True):
        args.append("--bare-tip")

    st = img.stat()
    key = hashlib.sha1(json.dumps([ann, args, st.st_size, st.st_mtime_ns, settings.get("hand", "")],
                                  sort_keys=True).encode()).hexdigest()[:10]
    rdir = store.project_dir(proj["id"]) / "renders"
    rdir.mkdir(exist_ok=True)
    out = rdir / f"scene-{scene['id']}-{q}-{key}.mp4"
    if out.exists() and out.stat().st_size > 0:
        if job:
            job.update(prog[1], f"Cảnh '{scene['title']}': dùng lại bản đã render")
        return out
    for old in rdir.glob(f"scene-{scene['id']}-{q}-*.mp4"):
        old.unlink(missing_ok=True)

    ann_path = rdir / f"scene-{scene['id']}.annotation.json"
    ann_path.write_text(json.dumps(ann, ensure_ascii=False, indent=2), encoding="utf-8")
    env = dict(os.environ)
    ff = A.find_ffmpeg()
    if ff:
        env["MINISTUDIO_FFMPEG"] = ff
    env["PYTHONIOENCODING"] = "utf-8"
    hand = hand_path(settings.get("hand", ""))
    cmd = [sys.executable, str(RENDERER), str(img), str(ann_path), str(out), str(hand), *args]
    if job:
        job.update(prog[0], f"Đang vẽ cảnh '{scene['title']}' ({len(ann['elements'])} vùng, {ann['sceneDurationMs']/1000:.1f}s)")
    proc = subprocess.Popen(cmd, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, env=env,
                            text=True, encoding="utf-8", errors="replace")
    tail: list[str] = []
    for line in proc.stdout:  # type: ignore[union-attr]
        line = line.rstrip()
        tail.append(line)
        tail = tail[-30:]
        m = re.match(r"PROGRESS=(\d+)/(\d+)", line)
        if m and job:
            a, b = int(m.group(1)), max(1, int(m.group(2)))
            job.update(prog[0] + (prog[1] - prog[0]) * a / b, f"Cảnh '{scene['title']}': vẽ vùng {a + 1}/{b}")
        if job and job.cancelled:
            proc.kill()
            raise Cancelled()
    proc.wait()
    if proc.returncode != 0 or not out.exists():
        raise RuntimeError("Bộ vẽ lỗi:\n" + "\n".join(tail[-12:]))
    return out


def _clips_for_scene(proj: dict, scene: dict, offset_ms: int) -> list[tuple[int, Path]]:
    tl = scene_timeline(proj, scene)
    pdir = store.project_dir(proj["id"])
    by_id = {ln["id"]: ln for ln in scene["lines"]}
    clips = []
    for t in tl["lines"]:
        a = by_id[t["id"]].get("audio") or {}
        if a.get("file"):
            clips.append((offset_ms + t["startMs"], pdir / a["file"]))
    return clips


def scene_audio(proj: dict, scene: dict) -> Path:
    tl = scene_timeline(proj, scene)
    out = store.project_dir(proj["id"]) / "renders" / f"audio-{scene['id']}.wav"
    A.write_wav(out, A.build_track(tl["durationMs"], _clips_for_scene(proj, scene, 0)))
    return out


def preview_scene(proj: dict, sid: str, opts: dict, job) -> dict:
    scene = store.find_scene(proj, sid)
    vid = render_scene(proj, scene, opts, job, (0.0, 0.9), quality="480")
    wav = scene_audio(proj, scene)
    out = store.project_dir(proj["id"]) / "renders" / f"preview-{sid}.mp4"
    A.ffmpeg("-i", str(vid), "-i", str(wav), "-c:v", "copy", "-c:a", "aac", "-b:a", "160k", str(out))
    return {"file": f"renders/{out.name}", "v": int(time.time())}


def render_project(pid: str, job) -> dict:
    proj = store.load_project(pid)
    opts = proj["export"]
    if not proj["scenes"]:
        raise RuntimeError("Dự án chưa có cảnh nào. Hãy viết kịch bản và bấm 'Tách cảnh'.")
    missing = [sc["title"] for sc in proj["scenes"] if not sc.get("image")]
    if missing:
        raise RuntimeError("Các cảnh chưa có ảnh: " + ", ".join(missing))

    q = str(opts.get("quality", "720"))
    fps = int(opts.get("fps", 30))
    W, H = output_size(proj.get("aspect", "16:9"), q)
    pdir = store.project_dir(pid)
    n = len(proj["scenes"])

    # 1) vẽ từng cảnh
    scene_videos = []
    for i, sc in enumerate(proj["scenes"]):
        span = (0.02 + 0.72 * i / n, 0.02 + 0.72 * (i + 1) / n)
        scene_videos.append(render_scene(proj, sc, opts, job, span))

    # 2) ghép cảnh + phụ đề (giải mã → vẽ phụ đề → mã hoá lại, 1 lượt)
    job.update(0.75, f"Ghép {n} cảnh, chèn phụ đề ({W}x{H}, {fps}fps)")
    sub = opts.get("subtitles", {})
    font = pick_font(store.load_settings().get("fontPath", ""))
    paper = store.load_settings().get("paperColor", "#F5EBD7").lstrip("#")
    tmp_video = pdir / "renders" / "_video_nosound.mp4"
    enc = subprocess.Popen(
        [A.find_ffmpeg(), "-y", "-hide_banner", "-loglevel", "error", "-f", "rawvideo", "-pix_fmt", "rgb24",
         "-s", f"{W}x{H}", "-r", str(fps), "-i", "-", "-c:v", "libx264", "-preset", "veryfast", "-crf", "20",
         "-pix_fmt", "yuv420p", "-movflags", "+faststart", str(tmp_video)],
        stdin=subprocess.PIPE, stderr=subprocess.PIPE)
    frame_bytes = W * H * 3
    total_frames = 0
    offsets = []
    est_total = sum(video_info(v)[0] for v in scene_videos) or 1
    try:
        for i, (sc, vid) in enumerate(zip(proj["scenes"], scene_videos)):
            offsets.append(int(round(total_frames * 1000 / fps)))
            tl = scene_timeline(proj, sc)
            cues = [(t["startMs"], t["startMs"] + t["durMs"], t["text"]) for t in tl["lines"]]
            dec = subprocess.Popen(
                [A.find_ffmpeg(), "-hide_banner", "-loglevel", "error", "-i", str(vid), "-vf",
                 f"scale={W}:{H}:force_original_aspect_ratio=decrease,pad={W}:{H}:(ow-iw)/2:(oh-ih)/2:color=0x{paper},fps={fps}",
                 "-f", "rawvideo", "-pix_fmt", "rgb24", "-"], stdout=subprocess.PIPE)
            k = 0
            while True:
                buf = dec.stdout.read(frame_bytes)  # type: ignore[union-attr]
                if len(buf) < frame_bytes:
                    break
                t = k * 1000 / fps
                if sub.get("enabled", True):
                    frame = np.frombuffer(buf, dtype=np.uint8).reshape(H, W, 3).copy()
                    for s, e, text in cues:
                        if s <= t < e:
                            composite(frame, render_overlay(text, W, H, int(sub.get("size", 100)),
                                                            sub.get("position", "bottom"),
                                                            sub.get("style", "box"), font))
                            break
                    buf = frame.tobytes()
                enc.stdin.write(buf)  # type: ignore[union-attr]
                k += 1
                total_frames += 1
                if total_frames % 30 == 0:
                    job.update(0.75 + 0.17 * total_frames / est_total)
                    if job.cancelled:
                        dec.kill()
                        raise Cancelled()
            dec.wait()
    finally:
        enc.stdin.close()  # type: ignore[union-attr]
        enc.wait()
    if enc.returncode != 0:
        raise RuntimeError("Mã hoá video lỗi: " + enc.stderr.read().decode("utf-8", "replace")[:300])  # type: ignore[union-attr]
    total_ms = int(round(total_frames * 1000 / fps))

    # 3) âm thanh: giọng đọc + nhạc nền tự hạ nhỏ
    job.update(0.93, "Trộn giọng đọc và nhạc nền")
    clips = []
    for sc, off in zip(proj["scenes"], offsets):
        clips += _clips_for_scene(proj, sc, off)
    bgm_cfg = opts.get("bgm", {})
    bgm = pdir / bgm_cfg["file"] if bgm_cfg.get("file") else None
    mix = A.build_track(total_ms, clips, bgm, float(bgm_cfg.get("volume", 18)) / 100, bool(bgm_cfg.get("duck", True)))
    mix_path = pdir / "renders" / "_mix.wav"
    A.write_wav(mix_path, mix)

    # 4) ghép hình + tiếng
    stamp = time.strftime("%Y%m%d-%H%M%S")
    base = f"{store.slugify(proj['name'])}-{stamp}"
    out = pdir / "exports" / f"{base}.mp4"
    out.parent.mkdir(exist_ok=True)
    A.ffmpeg("-i", str(tmp_video), "-i", str(mix_path), "-c:v", "copy", "-c:a", "aac", "-b:a", "192k",
             "-shortest", "-movflags", "+faststart", str(out))
    tmp_video.unlink(missing_ok=True)

    # 5) phụ đề SRT, mô tả + chương, ảnh bìa
    cues = []
    for sc, off in zip(proj["scenes"], offsets):
        for t in scene_timeline(proj, sc)["lines"]:
            cues.append((off + t["startMs"], off + t["startMs"] + t["durMs"], t["text"]))
    srt = out.with_suffix(".srt")
    srt.write_text(to_srt(cues), encoding="utf-8")
    from .extras import description_text, make_thumbnail
    desc = out.with_suffix(".txt")
    desc.write_text(description_text(proj, offsets), encoding="utf-8")
    thumb = out.with_name(base + "-thumbnail.png")
    try:
        make_thumbnail(proj, 0, proj["name"], thumb)
    except Exception as e:  # noqa: BLE001
        job.update(None, f"Không tạo được ảnh bìa: {e}")

    entry = {"file": f"exports/{out.name}", "srt": f"exports/{srt.name}", "desc": f"exports/{desc.name}",
             "thumb": f"exports/{thumb.name}" if thumb.exists() else "", "created": time.time(),
             "durationMs": total_ms, "size": out.stat().st_size, "quality": q, "w": W, "h": H}
    latest = store.load_project(pid)
    latest.setdefault("renders", []).insert(0, entry)
    latest["lastDurationMs"] = total_ms
    store.save_project(latest)
    job.update(1.0, f"Xong! {out.name} ({entry['size'] / 1048576:.1f} MB, {total_ms / 1000:.1f}s)")
    return entry


def estimate_total(proj: dict) -> int:
    return project_timeline(proj)["totalMs"]
