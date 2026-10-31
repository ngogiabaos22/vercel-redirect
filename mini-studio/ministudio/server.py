"""Máy chủ web cục bộ (chỉ dùng thư viện chuẩn của Python) + API cho giao diện."""
from __future__ import annotations

import json
import mimetypes
import os
import platform
import re
import shutil
import subprocess
import sys
import tempfile
import time
import traceback
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import parse_qs, unquote, urlparse

import cv2
import numpy as np

from . import ENGINE_DIR, FROZEN, HANDS_DIR, MUSIC_DIR, WEB_DIR, __version__
from . import audio as A
from . import integrations as AI
from . import imagegen, jobs, notify, pipeline, regions, render, store, tts
from .scenes import auto_assign as _auto_assign, build_elements as _build_elements, save_image as _save_image, update as _update
from .extras import description_text, make_thumbnail
from .subtitles import pick_font
from .scriptsplit import TEMPLATES
from .timeline import build_annotation, project_cues, project_timeline, scene_timeline, to_srt

mimetypes.add_type("application/javascript", ".js")
mimetypes.add_type("text/css", ".css")
mimetypes.add_type("image/svg+xml", ".svg")

ROUTES: list[tuple[str, re.Pattern, callable]] = []


def route(method: str, pattern: str):
    def deco(fn):
        ROUTES.append((method, re.compile("^" + pattern + "$"), fn))
        return fn
    return deco


class HttpError(Exception):
    def __init__(self, code: int, msg: str):
        super().__init__(msg)
        self.code = code


# ───────────────────────── tiện ích ─────────────────────────
def _merge_server_fields(incoming: dict, disk: dict) -> dict:
    """Giao diện gửi cả dự án; giữ nguyên các trường do máy chủ quản lý (ảnh, giọng, bản xuất)."""
    d_scenes = {s["id"]: s for s in disk.get("scenes", [])}
    for sc in incoming.get("scenes", []):
        ds = d_scenes.get(sc["id"])
        if not ds:
            continue
        sc["image"] = ds.get("image", "")
        sc["canvas"] = ds.get("canvas")
        d_lines = {ln["id"]: ln for ln in ds.get("lines", [])}
        for ln in sc.get("lines", []):
            dl = d_lines.get(ln["id"])
            if dl is not None:
                ln["audio"] = dl.get("audio")
    incoming["renders"] = disk.get("renders", [])
    incoming["lastDurationMs"] = disk.get("lastDurationMs", 0)
    incoming["id"] = disk["id"]
    incoming["created"] = disk.get("created")
    return incoming


# ───────────────────────── API: hệ thống ─────────────────────────
@route("GET", r"/api/system")
def api_system(h, m, q):
    return {
        "version": __version__, "python": sys.version.split()[0], "platform": platform.system(),
        "ffmpeg": A.find_ffmpeg(), "tts": tts.engines_status(), "services": AI.services_status(),
        "font": pick_font(store.load_settings().get("fontPath", "")),
        "imageProviders": [p.get("name") or p["type"] for p in imagegen.providers()],
        "encoder": render.encoder_cached() or "đang kiểm tra…",
        "frozen": FROZEN,
    }


# Giao diện báo "còn mở" định kỳ và báo "đã đóng" khi tắt cửa sổ → app tự tắt khi không còn ai dùng
ACTIVITY = {"seen": 0.0, "bye": 0.0}


@route("GET", r"/api/ping")
def api_ping(h, m, q):
    return {"ok": True, "jobs": len(jobs.active())}


@route("POST", r"/api/bye")
def api_bye(h, m, q):
    ACTIVITY["bye"] = time.time()
    return {"ok": True}


@route("GET", r"/api/settings")
def api_settings(h, m, q):
    return store.load_settings()


@route("PUT", r"/api/settings")
def api_settings_put(h, m, q):
    return store.save_settings(h.json())


@route("GET", r"/api/templates")
def api_templates(h, m, q):
    return {k: v for k, v in TEMPLATES.items()}


@route("GET", r"/api/ollama/models")
def api_ollama_models(h, m, q):
    return {"models": AI.ollama_models()}


@route("POST", r"/api/ai/(\w+)")
def api_ai(h, m, q):
    return {"text": AI.ai_task(m.group(1), h.json())}


@route("GET", r"/api/jobs/(\w+)")
def api_job(h, m, q):
    j = jobs.get(m.group(1))
    if not j:
        raise HttpError(404, "Không có công việc này")
    return j.to_dict()


@route("POST", r"/api/jobs/(\w+)/cancel")
def api_job_cancel(h, m, q):
    j = jobs.get(m.group(1))
    if j:
        j.cancelled = True
    return {"ok": True}


@route("POST", r"/api/voices/download")
def api_voice_download(h, m, q):
    vid = h.json().get("id", "")
    job = jobs.start("download", lambda j: str(tts.download_piper_voice(vid, j.update)), heavy=False)
    return job.to_dict()


@route("GET", r"/api/hands")
def api_hands(h, m, q):
    return {"hands": render.list_hands()}


@route("POST", r"/api/hands")
def api_hand_upload(h, m, q):
    name = re.sub(r"[^\w\.\-]+", "_", unquote(h.headers.get("X-Filename", "hand.png")))
    if not name.lower().endswith(".png"):
        raise HttpError(400, "Bàn tay phải là ảnh PNG nền trong suốt, đầu bút ở góc trên-trái")
    (HANDS_DIR / name).write_bytes(h.body())
    store.save_settings({"hand": name})
    return {"hands": render.list_hands(), "hand": name}


@route("GET", r"/api/music")
def api_music(h, m, q):
    exts = {".mp3", ".wav", ".ogg", ".m4a", ".flac"}
    return {"files": sorted(p.name for p in MUSIC_DIR.iterdir() if p.suffix.lower() in exts)}


# ───────────────────────── API: dự án ─────────────────────────
@route("GET", r"/api/projects")
def api_projects(h, m, q):
    return {"projects": store.list_projects()}


@route("POST", r"/api/projects")
def api_project_create(h, m, q):
    body = h.json()
    tpl = TEMPLATES.get(body.get("template", "blank"), TEMPLATES["blank"])
    return _new_project(body.get("name", "Dự án mới"), body.get("aspect", "16:9"), tpl["script"],
                        body.get("preset"))


def _new_project(name: str, aspect: str, script: str, preset: str | None = None) -> dict:
    p = store.new_project(name, aspect, script)
    preset = preset if preset is not None else store.load_settings().get("defaultPreset", "")
    if preset:
        pipeline.apply_preset(p, preset)
        if aspect:  # khung hình chọn lúc tạo dự án được ưu tiên hơn preset
            p["aspect"] = aspect if aspect in store.ASPECTS else p["aspect"]
    if p["script"]:
        from .scriptsplit import merge_into_project
        merge_into_project(p, pipeline.split_script(p["script"]))
    return store.save_project(p)


@route("GET", r"/api/projects/([a-z0-9\-]+)")
def api_project_get(h, m, q):
    return store.load_project(m.group(1))


@route("PUT", r"/api/projects/([a-z0-9\-]+)")
def api_project_put(h, m, q):
    pid = m.group(1)
    with store._lock:
        disk = store.load_project(pid)
        return store.save_project(_merge_server_fields(h.json(), disk))


@route("DELETE", r"/api/projects/([a-z0-9\-]+)")
def api_project_delete(h, m, q):
    store.delete_project(m.group(1))
    return {"ok": True}


@route("POST", r"/api/projects/([a-z0-9\-]+)/duplicate")
def api_project_dup(h, m, q):
    return store.duplicate_project(m.group(1))


@route("GET", r"/api/projects/([a-z0-9\-]+)/versions")
def api_versions(h, m, q):
    return {"versions": store.list_snapshots(m.group(1))}


@route("POST", r"/api/projects/([a-z0-9\-]+)/versions")
def api_version_create(h, m, q):
    return {"name": store.snapshot(store.load_project(m.group(1)), h.json().get("label", ""))}


@route("POST", r"/api/projects/([a-z0-9\-]+)/versions/([A-Za-z0-9\-]+)/restore")
def api_version_restore(h, m, q):
    return store.restore_snapshot(m.group(1), m.group(2))


@route("POST", r"/api/parse")
def api_parse(h, m, q):
    return {"scenes": pipeline.split_script(h.json().get("script", ""))}


@route("POST", r"/api/projects/([a-z0-9\-]+)/split")
def api_split(h, m, q):
    return pipeline.split_project(m.group(1), h.json().get("script"))


@route("GET", r"/api/projects/([a-z0-9\-]+)/timeline")
def api_timeline(h, m, q):
    return project_timeline(store.load_project(m.group(1)))


@route("GET", r"/api/projects/([a-z0-9\-]+)/srt")
def api_srt(h, m, q):
    p = store.load_project(m.group(1))
    h.send_bytes(to_srt(project_cues(p)).encode("utf-8"), "application/x-subrip; charset=utf-8",
                 filename=f"{store.slugify(p['name'])}.srt")


@route("GET", r"/api/projects/([a-z0-9\-]+)/description")
def api_desc(h, m, q):
    return {"text": description_text(store.load_project(m.group(1)))}


@route("POST", r"/api/projects/([a-z0-9\-]+)/open")
def api_open_folder(h, m, q):
    d = store.project_dir(m.group(1))
    sub = h.json().get("sub", "")
    target = d / sub if sub in ("exports", "images", "audio") else d
    if platform.system() == "Windows":
        os.startfile(str(target))  # type: ignore[attr-defined]
    elif platform.system() == "Darwin":
        subprocess.Popen(["open", str(target)])
    else:
        subprocess.Popen(["xdg-open", str(target)])
    return {"ok": True, "path": str(target)}


# ───────────────────────── API: storyboard ─────────────────────────
@route("POST", r"/api/projects/([a-z0-9\-]+)/scenes/(\w+)/image")
def api_scene_image(h, m, q):
    return _save_image(m.group(1), m.group(2), h.body())


@route("DELETE", r"/api/projects/([a-z0-9\-]+)/scenes/(\w+)/image")
def api_scene_image_del(h, m, q):
    def fn(p):
        sc = store.find_scene(p, m.group(2))
        sc["image"], sc["canvas"], sc["elements"] = "", None, []
    return _update(m.group(1), fn)


@route("POST", r"/api/projects/([a-z0-9\-]+)/scenes/(\w+)/detect")
def api_detect(h, m, q):
    pid, sid = m.group(1), m.group(2)
    body = h.json()
    p = store.load_project(pid)
    sc = store.find_scene(p, sid)
    if not sc.get("image"):
        raise HttpError(400, "Cảnh chưa có ảnh")
    img = store.project_dir(pid) / sc["image"]
    rs = regions.detect_regions(img, int(body.get("max", 6)), float(body.get("sensitivity", 1.0)))
    cov = regions.coverage(img, rs)
    tmp = {"elements": _build_elements(rs, cov, sc["canvas"]["width"], sc["canvas"]["height"]),
           "lines": sc["lines"]}
    _auto_assign(tmp)
    return {"elements": tmp["elements"], "coverage": cov}


@route("POST", r"/api/projects/([a-z0-9\-]+)/scenes/(\w+)/coverage")
def api_coverage(h, m, q):
    p = store.load_project(m.group(1))
    sc = store.find_scene(p, m.group(2))
    if not sc.get("image"):
        return {"coverage": 0}
    rs = [e["region"] for e in h.json().get("elements", sc.get("elements", []))]
    return {"coverage": regions.coverage(store.project_dir(p["id"]) / sc["image"], rs)}


@route("POST", r"/api/projects/([a-z0-9\-]+)/scenes/(\w+)/generate")
def api_generate_image(h, m, q):
    pid, sid = m.group(1), m.group(2)
    prompt = h.json().get("prompt", "")

    def work(job):
        p = store.load_project(pid)
        job.update(0.1, "Đang tạo ảnh…")
        data, src = AI.generate_image(prompt, p.get("aspect", "16:9"), log=lambda x: job.update(None, x),
                                      cancelled=lambda: job.cancelled)
        job.update(0.9, f"Đã tạo bằng {src}. Đang lưu ảnh và tách vùng")
        _save_image(pid, sid, data)
        return {"ok": True, "source": src}
    return jobs.start("image", work, pid, heavy=False).to_dict()


@route("POST", r"/api/projects/([a-z0-9\-]+)/images/auto")
def api_images_auto(h, m, q):
    pid, force = m.group(1), bool(h.json().get("force"))
    return jobs.start("images", lambda job: pipeline.images_all(pid, job, force), pid, heavy=False).to_dict()


@route("POST", r"/api/projects/([a-z0-9\-]+)/autorun")
def api_autorun(h, m, q):
    pid, script = m.group(1), h.json().get("script")
    return jobs.start("pipeline", lambda job: pipeline.run(pid, job, script), pid).to_dict()


@route("POST", r"/api/projects/([a-z0-9\-]+)/scenes/(\w+)/prompt")
def api_image_prompt(h, m, q):
    p = store.load_project(m.group(1))
    sc = store.find_scene(p, m.group(2))
    note = sc.get("imageNote") or " ".join(ln["text"] for ln in sc["lines"])
    english = sc.get("imagePrompt") or ""
    if h.json().get("useAI"):
        english = AI.ai_task("image_prompt", {"text": note})
    return {"prompt": AI.build_image_prompt(note, english), "negative": AI.NEGATIVE_PROMPT}


@route("GET", r"/api/projects/([a-z0-9\-]+)/scenes/(\w+)/audio")
def api_scene_audio(h, m, q):
    p = store.load_project(m.group(1))
    sc = store.find_scene(p, m.group(2))
    wav = render.scene_audio(p, sc)
    tl = scene_timeline(p, sc)
    ann = build_annotation(p, render._ensure_elements(sc), tl) if sc.get("canvas") else None
    return {"file": f"renders/{wav.name}", "v": int(time.time() * 1000), "timeline": tl, "annotation": ann,
            "hasAudio": any(t["hasAudio"] for t in tl["lines"])}


@route("POST", r"/api/projects/([a-z0-9\-]+)/scenes/(\w+)/preview")
def api_scene_preview(h, m, q):
    pid, sid = m.group(1), m.group(2)

    def work(job):
        p = store.load_project(pid)
        return render.preview_scene(p, sid, p["export"], job)
    return jobs.start("preview", work, pid).to_dict()


# ───────────────────────── API: giọng đọc ─────────────────────────
@route("POST", r"/api/projects/([a-z0-9\-]+)/tts")
def api_tts(h, m, q):
    pid = m.group(1)
    body = h.json()
    only = set(body.get("lineIds") or [])
    force = bool(body.get("force"))
    return jobs.start("tts", lambda job: pipeline.voice_all(pid, job, only, force), pid, heavy=False).to_dict()


@route("POST", r"/api/projects/([a-z0-9\-]+)/lines/(\w+)/audio")
def api_line_audio_upload(h, m, q):
    pid, lid = m.group(1), m.group(2)
    data = h.body()
    if not data:
        raise HttpError(400, "Không có dữ liệu âm thanh")
    ext = Path(h.headers.get("X-Filename", "rec.webm")).suffix or ".webm"
    with tempfile.TemporaryDirectory() as td:
        src = Path(td) / f"in{ext}"
        src.write_bytes(data)
        rel = f"audio/{lid}-rec{int(time.time())}.wav"
        dst = store.project_dir(pid) / rel
        A.to_wav(src, dst)
    dur = A.duration_ms(dst)

    def fn(p):
        _, ln = store.find_line(p, lid)
        old = (ln.get("audio") or {}).get("file")
        ln["audio"] = {"file": rel, "durationMs": dur, "engine": "record", "voice": "", "hash": "rec"}
        if old and old != rel:
            (store.project_dir(pid) / old).unlink(missing_ok=True)
    return _update(pid, fn)


@route("DELETE", r"/api/projects/([a-z0-9\-]+)/lines/(\w+)/audio")
def api_line_audio_delete(h, m, q):
    pid, lid = m.group(1), m.group(2)

    def fn(p):
        _, ln = store.find_line(p, lid)
        old = (ln.get("audio") or {}).get("file")
        ln["audio"] = None
        if old:
            (store.project_dir(pid) / old).unlink(missing_ok=True)
    return _update(pid, fn)


# ───────────────────────── API: xuất video ─────────────────────────
@route("POST", r"/api/projects/([a-z0-9\-]+)/render")
def api_render(h, m, q):
    pid = m.group(1)
    return jobs.start("render", lambda job: pipeline.render_and_notify(pid, job), pid).to_dict()


@route("GET", r"/api/projects/([a-z0-9\-]+)/jobs")
def api_project_jobs(h, m, q):
    return {"jobs": jobs.active(m.group(1))}


@route("POST", r"/api/projects/([a-z0-9\-]+)/bgm")
def api_bgm(h, m, q):
    pid = m.group(1)
    name = re.sub(r"[^\w\.\-]+", "_", unquote(h.headers.get("X-Filename", "music.mp3")))
    dst = store.project_dir(pid) / "music" / name
    dst.parent.mkdir(exist_ok=True)
    dst.write_bytes(h.body())
    if h.headers.get("X-Library") == "1":
        shutil.copy(dst, MUSIC_DIR / name)
    return _update(pid, lambda p: p["export"]["bgm"].__setitem__("file", f"music/{name}"))


@route("POST", r"/api/projects/([a-z0-9\-]+)/bgm/library")
def api_bgm_lib(h, m, q):
    pid = m.group(1)
    name = Path(h.json().get("name", "")).name
    src = MUSIC_DIR / name
    if not src.exists():
        raise HttpError(404, "Không có file nhạc này")
    dst = store.project_dir(pid) / "music" / name
    dst.parent.mkdir(exist_ok=True)
    shutil.copy(src, dst)
    return _update(pid, lambda p: p["export"]["bgm"].__setitem__("file", f"music/{name}"))


@route("POST", r"/api/projects/([a-z0-9\-]+)/thumbnail")
def api_thumb(h, m, q):
    pid = m.group(1)
    body = h.json()
    p = store.load_project(pid)
    out = store.project_dir(pid) / "exports" / f"thumbnail-{int(time.time())}.png"
    make_thumbnail(p, int(body.get("scene", 0)), body.get("title", p["name"]), out)
    return {"file": f"exports/{out.name}"}


@route("DELETE", r"/api/projects/([a-z0-9\-]+)/renders/(\d+)")
def api_render_delete(h, m, q):
    pid, idx = m.group(1), int(m.group(2))

    def fn(p):
        if 0 <= idx < len(p["renders"]):
            r = p["renders"].pop(idx)
            for k in ("file", "srt", "desc", "thumb"):
                if r.get(k):
                    (store.project_dir(pid) / r[k]).unlink(missing_ok=True)
    return _update(pid, fn)


# ───────────────────────── API: tự động hoá ─────────────────────────
@route("POST", r"/api/quick")
def api_quick(h, m, q):
    """Tạo dự án từ kịch bản và chạy trọn luồng ngay."""
    b = h.json()
    if not (b.get("script") or "").strip():
        raise HttpError(400, "Hãy dán kịch bản")
    p = _new_project(b.get("name") or _guess_name(b["script"]), b.get("aspect", ""), b["script"], b.get("preset"))
    job = jobs.start("pipeline", lambda job: pipeline.run(p["id"], job), p["id"])
    return {"project": p, "job": job.to_dict()}


def _guess_name(script: str) -> str:
    first = next((ln.strip("#> ").strip() for ln in script.splitlines() if ln.strip()), "")
    return (first[:60] + ("…" if len(first) > 60 else "")) or "Dự án mới"


def _split_batch(text: str) -> list[dict]:
    """Nhiều kịch bản trong một ô: ngăn cách bằng dòng '---'. Dòng đầu dạng 'Tiêu đề: …' thành tên dự án."""
    items = []
    for block in re.split(r"(?m)^\s*-{3,}\s*$", text or ""):
        block = block.strip()
        if not block:
            continue
        name = ""
        mm = re.match(r"^(?:tiêu đề|tieu de|title)\s*:\s*(.+)$", block.splitlines()[0], re.I)
        if mm:
            name = mm.group(1).strip()
            block = "\n".join(block.splitlines()[1:]).strip()
        items.append({"name": name or _guess_name(block), "script": block})
    return items


@route("POST", r"/api/batch")
def api_batch(h, m, q):
    """Hàng loạt: tạo nhiều dự án, xếp hàng chạy lần lượt (máy không bị quá tải)."""
    b = h.json()
    items = b.get("items") or _split_batch(b.get("text", ""))
    if not items:
        raise HttpError(400, "Không có kịch bản nào")
    out = []
    for it in items:
        p = _new_project(it.get("name") or _guess_name(it["script"]), b.get("aspect", ""), it["script"], b.get("preset"))
        job = jobs.start("pipeline", lambda job, pid=p["id"]: pipeline.run(pid, job), p["id"])
        out.append({"project": {"id": p["id"], "name": p["name"]}, "job": job.to_dict()})
    return {"items": out}


@route("POST", r"/api/batch/preview")
def api_batch_preview(h, m, q):
    return {"items": [{"name": it["name"], "scenes": len(pipeline.split_script(it["script"]))}
                      for it in _split_batch(h.json().get("text", ""))]}


@route("GET", r"/api/jobs")
def api_jobs(h, m, q):
    return {"jobs": jobs.recent()}


@route("GET", r"/api/presets")
def api_presets(h, m, q):
    s = store.load_settings()
    return {"presets": sorted((s.get("presets") or {}).keys()), "default": s.get("defaultPreset", "")}


@route("POST", r"/api/presets")
def api_preset_save(h, m, q):
    b = h.json()
    name = (b.get("name") or "").strip()[:60]
    if not name:
        raise HttpError(400, "Hãy đặt tên preset")
    pipeline.save_preset(name, store.load_project(b["project"]))
    if b.get("default"):
        store.save_settings({"defaultPreset": name})
    return api_presets(h, m, q)


@route("DELETE", r"/api/presets/(.+)")
def api_preset_delete(h, m, q):
    s = store.load_settings()
    presets = dict(s.get("presets") or {})
    presets.pop(m.group(1), None)
    patch = {"presets": presets}
    if s.get("defaultPreset") == m.group(1):
        patch["defaultPreset"] = ""
    store.save_settings(patch)
    return api_presets(h, m, q)


@route("POST", r"/api/projects/([a-z0-9\-]+)/preset")
def api_preset_apply(h, m, q):
    return _update(m.group(1), lambda p: pipeline.apply_preset(p, h.json().get("name", "")))


@route("GET", r"/api/image/types")
def api_image_types(h, m, q):
    return {"types": imagegen.PROVIDER_TYPES}


@route("POST", r"/api/image/test")
def api_image_test(h, m, q):
    """Thử một nguồn ảnh với prompt mẫu; trả ảnh dạng base64 để xem ngay."""
    import base64
    b = h.json()
    prov = b.get("provider") or {}
    if prov.get("type") not in imagegen.HANDLERS:
        raise HttpError(400, "Loại nguồn không hợp lệ")
    t0 = time.time()
    try:
        data = imagegen.generate_with(prov, AI.build_image_prompt(b.get("prompt") or "a cat reading a book under a tree"),
                                      "1:1", AI.NEGATIVE_PROMPT)
    except Exception as e:  # noqa: BLE001
        raise HttpError(502, str(e))
    return {"image": "data:image/png;base64," + base64.b64encode(data).decode(), "seconds": round(time.time() - t0, 1)}


@route("POST", r"/api/notify/test")
def api_notify_test(h, m, q):
    s = {**store.load_settings(), **h.json()}
    try:
        notify.send_message("🔔 <b>Mini Studio</b>: kết nối Telegram thành công! Bạn sẽ nhận thông báo khi video xong.", s)
    except Exception as e:  # noqa: BLE001
        raise HttpError(502, str(e))
    return {"ok": True}


@route("POST", r"/api/notify/chats")
def api_notify_chats(h, m, q):
    token = (h.json().get("token") or store.load_settings().get("telegramToken") or "").strip()
    if not token:
        raise HttpError(400, "Hãy điền Bot Token trước")
    try:
        return {"chats": notify.find_chats(token)}
    except Exception as e:  # noqa: BLE001
        raise HttpError(502, str(e))


def _thumb(base: Path, src: Path, w: int) -> Path:
    """Ảnh thu nhỏ JPEG, lưu trong <dự án>/thumbs; ảnh gốc đổi (tên mới) thì tạo bản mới."""
    w = max(64, min(1280, w))
    dst = base / "thumbs" / f"{src.stem}-{w}.jpg"
    if not dst.exists() or dst.stat().st_mtime < src.stat().st_mtime:
        img = cv2.imdecode(np.fromfile(str(src), np.uint8), cv2.IMREAD_COLOR)
        if img is None:
            return src
        hh, ww = img.shape[:2]
        if ww > w:
            img = cv2.resize(img, (w, max(1, round(hh * w / ww))), interpolation=cv2.INTER_AREA)
        dst.parent.mkdir(exist_ok=True)
        dst.write_bytes(cv2.imencode(".jpg", img, [cv2.IMWRITE_JPEG_QUALITY, 82])[1].tobytes())
    return dst


# ───────────────────────── HTTP ─────────────────────────
class Handler(BaseHTTPRequestHandler):
    server_version = "MiniStudio/" + __version__

    def log_message(self, fmt, *args):  # chỉ ghi request lỗi – in ra console Windows rất chậm
        if len(args) >= 2 and str(args[1]).isdigit() and int(args[1]) < 400:
            return
        try:
            sys.stderr.write("  %s\n" % (fmt % args))
        except Exception:
            pass

    def body(self) -> bytes:
        n = int(self.headers.get("Content-Length") or 0)
        return self.rfile.read(n) if n else b""

    def json(self) -> dict:
        raw = self.body()
        return json.loads(raw.decode("utf-8")) if raw else {}

    def send_json(self, data, code=200):
        raw = json.dumps(data, ensure_ascii=False).encode("utf-8")
        self.send_response(code)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(raw)))
        self.send_header("Cache-Control", "no-store")
        self.end_headers()
        self.wfile.write(raw)
        self._sent = True

    def send_bytes(self, raw: bytes, ctype: str, filename: str = ""):
        self.send_response(200)
        self.send_header("Content-Type", ctype)
        self.send_header("Content-Length", str(len(raw)))
        if filename:
            self.send_header("Content-Disposition", f'attachment; filename="{filename}"')
        self.end_headers()
        self.wfile.write(raw)
        self._sent = True

    def send_file(self, path: Path, download: bool = False):
        if not path.exists() or not path.is_file():
            return self.send_json({"error": "Không tìm thấy file"}, 404)
        st = path.stat()
        size = st.st_size
        ctype = mimetypes.guess_type(path.name)[0] or "application/octet-stream"
        # trình duyệt dùng lại bản đã tải nếu file chưa đổi (tránh tải lại ảnh vài MB mỗi lần vẽ lại giao diện)
        etag = f'"{st.st_mtime_ns:x}-{size:x}"'
        if self.headers.get("If-None-Match") == etag and not download:
            self.send_response(304)
            self.send_header("ETag", etag)
            self.end_headers()
            return
        rng = self.headers.get("Range")
        start, end = 0, size - 1
        if rng and (mm := re.match(r"bytes=(\d*)-(\d*)", rng)):
            if mm.group(1):
                start = int(mm.group(1))
                end = int(mm.group(2)) if mm.group(2) else size - 1
            else:
                start = size - int(mm.group(2))
            end = min(end, size - 1)
            self.send_response(206)
            self.send_header("Content-Range", f"bytes {start}-{end}/{size}")
        else:
            self.send_response(200)
        self.send_header("Content-Type", ctype)
        self.send_header("Accept-Ranges", "bytes")
        self.send_header("Content-Length", str(end - start + 1))
        self.send_header("ETag", etag)
        # ảnh cảnh có tên kèm mốc thời gian (đổi ảnh = đổi tên) → cho trình duyệt giữ lâu
        immutable = ("/images/" in self.path or "/thumbs/" in str(path)) and ctype.startswith("image/")
        self.send_header("Cache-Control", "public, max-age=31536000, immutable" if immutable else "no-cache")
        if download:
            self.send_header("Content-Disposition", f'attachment; filename="{path.name}"')
        self.end_headers()
        with open(path, "rb") as f:
            f.seek(start)
            remaining = end - start + 1
            try:
                while remaining > 0:
                    chunk = f.read(min(1 << 16, remaining))
                    if not chunk:
                        break
                    self.wfile.write(chunk)
                    remaining -= len(chunk)
            except (BrokenPipeError, ConnectionResetError):
                pass

    def _dispatch(self, method: str):
        url = urlparse(self.path)
        path = unquote(url.path)
        q = parse_qs(url.query)
        self._sent = False
        try:
            if path.startswith("/api/"):
                if path != "/api/bye":
                    ACTIVITY["seen"] = time.time()
                for mth, rx, fn in ROUTES:
                    if mth == method and (mm := rx.match(path)):
                        res = fn(self, mm, q)
                        if not self._sent:
                            self.send_json(res if res is not None else {"ok": True})
                        return
                raise HttpError(404, "API không tồn tại")
            if method != "GET":
                raise HttpError(405, "Phương thức không hỗ trợ")
            if path.startswith("/files/"):
                _, _, pid, *rest = path.split("/")
                base = store.project_dir(pid).resolve()
                target = (base / "/".join(rest)).resolve()
                if base not in target.parents:
                    raise HttpError(403, "Không được phép")
                if "w" in q and target.suffix.lower() in (".png", ".jpg", ".jpeg", ".webp") and target.exists():
                    target = _thumb(base, target, int(q["w"][0]))
                return self.send_file(target, download="download" in q)
            if path.startswith("/hands/"):
                nm = Path(path).name
                t = HANDS_DIR / nm if (HANDS_DIR / nm).exists() else ENGINE_DIR / "assets" / nm
                return self.send_file(t)
            if path.startswith("/music/"):
                target = (MUSIC_DIR / Path(path).name).resolve()
                return self.send_file(target)
            rel = path.lstrip("/") or "index.html"
            target = (WEB_DIR / rel).resolve()
            if WEB_DIR.resolve() not in target.parents or not target.exists():
                target = WEB_DIR / "index.html"
            return self.send_file(target)
        except HttpError as e:
            self.send_json({"error": str(e)}, e.code)
        except ValueError as e:
            self.send_json({"error": str(e)}, 400)
        except (FileNotFoundError, KeyError) as e:
            self.send_json({"error": str(e).strip("'")}, 404)
        except (BrokenPipeError, ConnectionResetError):
            pass
        except Exception as e:  # noqa: BLE001
            traceback.print_exc()
            try:
                self.send_json({"error": str(e)}, 500)
            except Exception:
                pass

    def do_GET(self):
        self._dispatch("GET")

    def do_POST(self):
        self._dispatch("POST")

    def do_PUT(self):
        self._dispatch("PUT")

    def do_DELETE(self):
        self._dispatch("DELETE")


def serve(host: str = "127.0.0.1", port: int = 8765) -> ThreadingHTTPServer:
    httpd = ThreadingHTTPServer((host, port), Handler)
    httpd.daemon_threads = True
    return httpd
