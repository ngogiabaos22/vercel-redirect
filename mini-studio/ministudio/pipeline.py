"""Luồng tự động "1 chạm": kịch bản → tách cảnh → prompt ảnh → tạo ảnh hàng loạt → giọng đọc → xuất video
→ báo Telegram. Bước nào đã làm rồi (ảnh có sẵn, giọng không đổi, cảnh đã render) thì bỏ qua.
"""
from __future__ import annotations

import hashlib
import shutil
import time
from copy import deepcopy
from concurrent.futures import ThreadPoolExecutor, as_completed

from . import MUSIC_DIR
from . import integrations as AI
from . import notify, render, store, tts
from .scenes import save_image, update
from .scriptsplit import auto_scenes, has_markup, merge_into_project, parse_script


class Cancelled(RuntimeError):
    pass


class Sub:
    """Bọc Job để bước con báo tiến độ 0→1 trong một khoảng [a, b] của job cha."""

    def __init__(self, job, a: float, b: float):
        self.job, self.a, self.b = job, a, b

    def update(self, progress: float | None = None, message: str | None = None):
        self.job.update(None if progress is None else self.a + (self.b - self.a) * progress, message)

    @property
    def cancelled(self) -> bool:
        return self.job.cancelled


def _logger(job):
    return lambda msg: job.update(None, msg)


def _check(job) -> None:
    if job.cancelled:
        raise Cancelled("Đã huỷ")


# ───────────────────────── kịch bản → cảnh ─────────────────────────
def split_script(raw: str) -> list[dict]:
    """Có cú pháp ## / > thì tách theo cú pháp; văn xuôi thì tự chia cảnh theo thời lượng."""
    if has_markup(raw):
        return parse_script(raw)
    s = store.load_settings()
    return auto_scenes(raw, float(s.get("autoSceneSeconds", 12)), int(s.get("readingSpeed", 230)))


def split_project(pid: str, script: str | None = None, snapshot: bool = True) -> dict:
    def fn(p):
        if script is not None:
            p["script"] = script
        if snapshot:
            store.snapshot(p, "truoc-khi-tach-canh")
        merge_into_project(p, split_script(p["script"]))
    return update(pid, fn)


# ───────────────────────── ảnh ─────────────────────────
def _fallback_subject(sc: dict) -> str:
    return sc.get("imageNote") or " ".join(ln["text"] for ln in sc["lines"])[:400]


def ensure_prompts(pid: str, job, force: bool = False) -> None:
    """Viết prompt ảnh tiếng Anh cho các cảnh chưa có (một lần gọi AI cho cả dự án)."""
    p = store.load_project(pid)
    todo = [sc for sc in p["scenes"] if force or (not sc.get("image") and not sc.get("imagePrompt"))]
    if not todo:
        return
    job.update(None, f"AI đang viết prompt ảnh cho {len(todo)} cảnh…")
    try:
        prompts = AI.scene_prompts(todo)
    except Exception as e:  # noqa: BLE001
        job.update(None, f"Không nhờ được AI viết prompt ({e}); dùng mô tả/câu thoại làm prompt")
        prompts = [""] * len(todo)
    ids = {sc["id"]: pr for sc, pr in zip(todo, prompts)}

    def fn(pp):
        for sc in pp["scenes"]:
            if sc["id"] in ids:
                sc["imagePrompt"] = ids[sc["id"]] or ""
    update(pid, fn)


def scene_prompt(sc: dict) -> str:
    return AI.build_image_prompt(_fallback_subject(sc), sc.get("imagePrompt") or "")


def images_all(pid: str, job, force: bool = False) -> dict:
    """Tạo ảnh cho mọi cảnh còn thiếu, chạy song song, tự thử lại và chuyển nguồn khi lỗi."""
    ensure_prompts(pid, job)
    p = store.load_project(pid)
    todo = [sc for sc in p["scenes"] if force or not sc.get("image")]
    if not todo:
        job.update(1.0, "Tất cả cảnh đã có ảnh")
        return {"count": 0}
    workers = max(1, min(6, int(store.load_settings().get("imageConcurrency", 2))))
    job.update(0.02, f"Tạo ảnh cho {len(todo)} cảnh ({workers} luồng song song)")
    done, failed = 0, []

    def one(sc):
        _check(job)
        data, src = AI.generate_image(scene_prompt(sc), p.get("aspect", "16:9"),
                                      log=lambda msg: job.update(None, f"'{sc['title']}': {msg}"),
                                      cancelled=lambda: job.cancelled)
        _check(job)
        save_image(pid, sc["id"], data)
        return src

    with ThreadPoolExecutor(max_workers=workers) as ex:
        futs = {ex.submit(one, sc): sc for sc in todo}
        for f in as_completed(futs):
            sc = futs[f]
            try:
                src = f.result()
                done += 1
                job.update(done / len(todo), f"Ảnh {done}/{len(todo)} xong: '{sc['title']}' ({src})")
            except Exception as e:  # noqa: BLE001
                if job.cancelled:
                    for other in futs:
                        other.cancel()
                    raise Cancelled("Đã huỷ") from None
                failed.append(sc["title"])
                job.update(None, f"Không tạo được ảnh '{sc['title']}': {str(e)[:300]}")
    if failed:
        raise RuntimeError(f"{len(failed)} cảnh chưa có ảnh: {', '.join(failed)}. "
                           "Kiểm tra Cài đặt → Nguồn ảnh, hoặc thả ảnh tay cho các cảnh này.")
    return {"count": done}


# ───────────────────────── giọng đọc ─────────────────────────
def line_hash(text: str, engine: str, voice: str, speed: float) -> str:
    return hashlib.sha1(f"{text}|{engine}|{voice}|{speed}".encode()).hexdigest()[:10]


def pick_voice(pid: str, job) -> dict:
    """Dự án chưa chọn giọng tự động → chọn theo Cài đặt (auto: Edge → Piper → eSpeak)."""
    p = store.load_project(pid)
    v = p["voice"]
    if v.get("engine") not in ("estimate", "record", "", None):
        return v
    s = store.load_settings()
    st = tts.engines_status()
    want = s.get("autoVoiceEngine", "auto")
    order = [want] if want != "auto" else ["edge", "piper", "espeak"]
    for eng in order:
        info = st.get(eng) or {}
        if not info.get("available") or eng in ("estimate", "record"):
            continue
        voices = [x["id"] for x in info.get("voices", [])]
        if eng == "piper" and not voices:
            continue
        voice = s.get("autoVoice") if s.get("autoVoice") in voices else (voices[0] if voices else "")
        if eng == "piper":
            voice = next((x for x in voices if x.startswith("vi_")), voice)

        def fn(pp, eng=eng, voice=voice):
            pp["voice"].update({"engine": eng, "voice": voice})
        update(pid, fn)
        job.update(None, f"Tự chọn giọng: {info.get('label', eng)} – {voice}")
        return {**v, "engine": eng, "voice": voice}
    job.update(None, "Chưa có giọng đọc tự động (cài edge-tts hoặc piper-tts) → video sẽ không có tiếng")
    return v


def voice_all(pid: str, job, only: set | None = None, force: bool = False) -> dict:
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
            hsh = line_hash(ln["text"], engine, voice, speed)
            if not force and (ln.get("audio") or {}).get("hash") == hsh:
                continue
            todo.append((ln["id"], ln["text"], hsh))
    if not todo:
        job.update(1.0, "Tất cả câu đã có giọng, không cần tạo lại")
        return {"count": 0}
    for i, (lid, text, hsh) in enumerate(todo):
        _check(job)
        job.update(i / len(todo), f"Đọc câu {i + 1}/{len(todo)}: {text[:50]}")
        rel = f"audio/{lid}-{hsh}.wav"
        dst = store.project_dir(pid) / rel
        last = None
        for attempt in range(3):  # Edge TTS cần mạng → thử lại khi rớt kết nối
            try:
                dur = tts.synthesize(engine, text, voice, speed, dst)
                break
            except Exception as e:  # noqa: BLE001
                last = e
                time.sleep(2 * (attempt + 1))
        else:
            raise RuntimeError(f"Không tạo được giọng cho câu '{text[:40]}': {last}")

        def fn(pp, lid=lid, rel=rel, dur=dur, hsh=hsh):
            _, ln = store.find_line(pp, lid)
            old = (ln.get("audio") or {}).get("file")
            ln["audio"] = {"file": rel, "durationMs": dur, "engine": engine, "voice": voice, "hash": hsh}
            if old and old != rel:
                (store.project_dir(pid) / old).unlink(missing_ok=True)
        update(pid, fn)
    return {"count": len(todo)}


# ───────────────────────── xuất + thông báo ─────────────────────────
def render_and_notify(pid: str, job, started: float | None = None) -> dict:
    t0 = started or time.time()
    try:
        entry = render.render_project(pid, job)
    except Exception as e:
        notify.video_failed(store.load_project(pid)["name"], str(e) or "Đã huỷ", _logger(job))
        raise
    notify.video_done(store.load_project(pid), entry, store.project_dir(pid), time.time() - t0, _logger(job))
    return entry


def run(pid: str, job, script: str | None = None) -> dict:
    """Chạy trọn luồng cho một dự án."""
    t0 = time.time()
    name = store.load_project(pid)["name"]
    try:
        job.update(0.01, "Bước 1/4: tách kịch bản thành cảnh")
        p = split_project(pid, script)
        if not p["scenes"]:
            raise RuntimeError("Kịch bản trống – hãy dán nội dung trước")
        job.update(0.03, f"Đã có {len(p['scenes'])} cảnh, {sum(len(s['lines']) for s in p['scenes'])} câu")

        job.update(None, "Bước 2/4: tạo ảnh minh hoạ")
        images_all(pid, Sub(job, 0.03, 0.40))

        job.update(0.40, "Bước 3/4: tạo giọng đọc")
        if pick_voice(pid, job).get("engine") not in ("estimate", "record"):
            voice_all(pid, Sub(job, 0.40, 0.55))

        job.update(0.55, "Bước 4/4: vẽ và ghép video")
        entry = render.render_project(pid, Sub(job, 0.55, 1.0))
    except Exception as e:
        notify.video_failed(name, str(e) or "Đã huỷ", _logger(job))
        raise
    notify.video_done(store.load_project(pid), entry, store.project_dir(pid), time.time() - t0, _logger(job))
    return entry


# ───────────────────────── preset ─────────────────────────
PRESET_KEYS = ("aspect", "voice", "export")


def save_preset(name: str, proj: dict) -> dict:
    s = store.load_settings()
    presets = dict(s.get("presets") or {})
    presets[name] = {k: proj[k] for k in PRESET_KEYS if k in proj}
    return store.save_settings({"presets": presets})


def apply_preset(proj: dict, name: str) -> dict:
    pr = (store.load_settings().get("presets") or {}).get(name)
    if not pr:
        return proj
    for k in PRESET_KEYS:
        if k in pr:
            proj[k] = deepcopy(pr[k]) if isinstance(pr[k], dict) else pr[k]
    bgm = (proj.get("export") or {}).get("bgm") or {}
    if bgm.get("file"):  # nhạc nền: chép từ thư viện nhạc sang dự án mới
        src = MUSIC_DIR / bgm["file"].split("/")[-1]
        if src.exists():
            dst = store.project_dir(proj["id"]) / "music" / src.name
            dst.parent.mkdir(exist_ok=True)
            shutil.copy(src, dst)
            bgm["file"] = f"music/{src.name}"
        else:
            bgm["file"] = ""
    return proj
