"""Máy chủ web cục bộ (chỉ dùng thư viện chuẩn của Python) + API cho giao diện."""
from __future__ import annotations

import hashlib
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

from . import ENGINE_DIR, HANDS_DIR, MUSIC_DIR, WEB_DIR, __version__
from . import audio as A
from . import integrations as AI
from . import jobs, regions, render, store, tts
from .extras import description_text, make_thumbnail
from .subtitles import pick_font
from .scriptsplit import TEMPLATES, merge_into_project, parse_script
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


def _update(pid: str, fn):
    with store._lock:
        p = store.load_project(pid)
        fn(p)
        return store.save_project(p)


def _build_elements(rects: list[dict], cov: float, w: int, h: int) -> list[dict]:
    els = []
    if cov < 0.97:  # phần nét nằm ngoài các vùng (đường nền…) → vẽ trước tiên như bối cảnh
        els.append({"id": store.uid("e"), "label": "Nền & phần còn lại", "bg": True,
                    "region": {"x": 0, "y": 0, "width": w, "height": h}, "lineIds": [], "protectedRegions": []})
    for i, r in enumerate(rects):
        els.append({"id": store.uid("e"), "label": f"Vùng {i + 1}", "region": r, "lineIds": [],
                    "protectedRegions": []})
    return els


def _auto_assign(scene: dict) -> None:
    for el in scene.get("elements", []):
        if el.get("bg"):
            el["lineIds"] = []
    els = [e for e in scene.get("elements", []) if not e.get("bg")]
    lines = scene.get("lines", [])
    n, m = len(els), len(lines)
    for i, el in enumerate(els):
        a, b = (i * m) // n if n else 0, ((i + 1) * m) // n if n else 0
        el["lineIds"] = [ln["id"] for ln in lines[a:b]]


def _save_image(pid: str, sid: str, data: bytes) -> dict:
    arr = np.frombuffer(data, dtype=np.uint8)
    img = cv2.imdecode(arr, cv2.IMREAD_UNCHANGED)
    if img is None:
        raise HttpError(400, "File không phải ảnh hợp lệ")
    if img.ndim == 3 and img.shape[2] == 4:  # ảnh trong suốt → nền giấy
        paper = store.load_settings().get("paperColor", "#F5EBD7").lstrip("#")
        bg = np.array([int(paper[4:6], 16), int(paper[2:4], 16), int(paper[0:2], 16)], np.float32)
        a = img[:, :, 3:4].astype(np.float32) / 255
        img = (img[:, :, :3] * a + bg * (1 - a)).astype(np.uint8)
    elif img.ndim == 2:
        img = cv2.cvtColor(img, cv2.COLOR_GRAY2BGR)
    h, w = img.shape[:2]
    if max(w, h) > 2400:
        s = 2400 / max(w, h)
        img = cv2.resize(img, (int(w * s), int(h * s)), interpolation=cv2.INTER_AREA)
        h, w = img.shape[:2]
    name = f"images/scene-{sid}-{int(time.time())}.png"
    dst = store.project_dir(pid) / name
    dst.parent.mkdir(exist_ok=True)
    ok, buf = cv2.imencode(".png", img)
    dst.write_bytes(buf.tobytes())
    detected = regions.detect_regions(dst)
    cov = regions.coverage(dst, detected)

    def fn(p):
        sc = store.find_scene(p, sid)
        old = sc.get("image")
        same_size = sc.get("canvas") == {"width": w, "height": h}
        sc["image"] = name
        sc["canvas"] = {"width": w, "height": h}
        if not sc.get("elements") or not same_size:
            sc["elements"] = _build_elements(detected, cov, w, h)
            _auto_assign(sc)
        if old and old != name:
            (store.project_dir(pid) / old).unlink(missing_ok=True)

    return _update(pid, fn)


# ───────────────────────── API: hệ thống ─────────────────────────
@route("GET", r"/api/system")
def api_system(h, m, q):
    return {
        "version": __version__, "python": sys.version.split()[0], "platform": platform.system(),
        "ffmpeg": A.find_ffmpeg(), "tts": tts.engines_status(), "services": AI.services_status(),
        "font": pick_font(store.load_settings().get("fontPath", "")),
    }


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
    p = store.new_project(body.get("name", "Dự án mới"), body.get("aspect", "16:9"), tpl["script"])
    if p["script"]:
        merge_into_project(p, parse_script(p["script"]))
        store.save_project(p)
    return p


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
    return {"scenes": parse_script(h.json().get("script", ""))}


@route("POST", r"/api/projects/([a-z0-9\-]+)/split")
def api_split(h, m, q):
    pid = m.group(1)
    body = h.json()

    def fn(p):
        if "script" in body:
            p["script"] = body["script"]
        store.snapshot(p, "truoc-khi-tach-canh")
        merge_into_project(p, parse_script(p["script"]))
    return _update(pid, fn)


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
        job.update(0.1, "Đang tạo ảnh bằng Stable Diffusion…")
        data = AI.generate_image(prompt, p.get("aspect", "16:9"))
        job.update(0.9, "Đang lưu ảnh và tách vùng")
        _save_image(pid, sid, data)
        return {"ok": True}
    return jobs.start("image", work, pid).to_dict()


@route("POST", r"/api/projects/([a-z0-9\-]+)/scenes/(\w+)/prompt")
def api_image_prompt(h, m, q):
    p = store.load_project(m.group(1))
    sc = store.find_scene(p, m.group(2))
    note = sc.get("imageNote") or " ".join(ln["text"] for ln in sc["lines"])
    english = ""
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
def _line_hash(text: str, engine: str, voice: str, speed: float) -> str:
    return hashlib.sha1(f"{text}|{engine}|{voice}|{speed}".encode()).hexdigest()[:10]


@route("POST", r"/api/projects/([a-z0-9\-]+)/tts")
def api_tts(h, m, q):
    pid = m.group(1)
    body = h.json()
    only = set(body.get("lineIds") or [])
    force = bool(body.get("force"))

    def work(job):
        p = store.load_project(pid)
        v = p["voice"]
        engine, voice, speed = v.get("engine", "estimate"), v.get("voice", ""), float(v.get("speed", 1.0))
        if engine in ("estimate", "record"):
            raise RuntimeError("Hãy chọn một động cơ giọng (Piper / eSpeak / Edge TTS) để tạo tự động")
        todo = []
        for sc in p["scenes"]:
            for ln in sc["lines"]:
                if only and ln["id"] not in only:
                    continue
                hsh = _line_hash(ln["text"], engine, voice, speed)
                if not force and (ln.get("audio") or {}).get("hash") == hsh:
                    continue
                todo.append((ln["id"], ln["text"], hsh))
        if not todo:
            job.update(1.0, "Tất cả câu đã có giọng, không cần tạo lại")
            return {"count": 0}
        for i, (lid, text, hsh) in enumerate(todo):
            if job.cancelled:
                raise RuntimeError("Đã huỷ")
            job.update(i / len(todo), f"Đọc câu {i + 1}/{len(todo)}: {text[:50]}")
            rel = f"audio/{lid}-{hsh}.wav"
            dst = store.project_dir(pid) / rel
            dur = tts.synthesize(engine, text, voice, speed, dst)

            def fn(pp, lid=lid, rel=rel, dur=dur, hsh=hsh):
                _, ln = store.find_line(pp, lid)
                old = (ln.get("audio") or {}).get("file")
                ln["audio"] = {"file": rel, "durationMs": dur, "engine": engine, "voice": voice, "hash": hsh}
                if old and old != rel:
                    (store.project_dir(pid) / old).unlink(missing_ok=True)
            _update(pid, fn)
        return {"count": len(todo)}
    return jobs.start("tts", work, pid, heavy=False).to_dict()


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
    return jobs.start("render", lambda job: render.render_project(pid, job), pid).to_dict()


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


# ───────────────────────── HTTP ─────────────────────────
class Handler(BaseHTTPRequestHandler):
    server_version = "MiniStudio/" + __version__

    def log_message(self, fmt, *args):  # gọn log
        if "/api/jobs/" in (args[0] if args else ""):
            return
        sys.stderr.write("  %s\n" % (fmt % args))

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
        size = path.stat().st_size
        ctype = mimetypes.guess_type(path.name)[0] or "application/octet-stream"
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
        self.send_header("Cache-Control", "no-cache")
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
