"""Tiện ích đăng bài: ảnh bìa (thumbnail), mô tả + mốc chương (YouTube chapters)."""
from __future__ import annotations

from pathlib import Path

from PIL import Image, ImageDraw, ImageOps

from . import store
from .subtitles import _font, _wrap, pick_font
from .timeline import project_timeline


def fmt_clock(ms: int) -> str:
    s = int(ms // 1000)
    h, s = divmod(s, 3600)
    m, s = divmod(s, 60)
    return f"{h}:{m:02d}:{s:02d}" if h else f"{m:02d}:{s:02d}"


def description_text(proj: dict, offsets: list[int] | None = None) -> str:
    ptl = project_timeline(proj)
    out = [proj["name"], ""]
    out.append("Mục lục:")
    for i, tl in enumerate(ptl["scenes"]):
        off = offsets[i] if offsets else tl["offsetMs"]
        if i == 0:
            off = 0  # YouTube yêu cầu chương đầu bắt đầu ở 00:00
        out.append(f"{fmt_clock(off)} {tl['title'] or f'Cảnh {i + 1}'}")
    out += ["", "Nội dung:"]
    for sc in proj["scenes"]:
        out.append(" ".join(ln["text"] for ln in sc["lines"]))
    out += ["", "#video #giaithich #whiteboard"]
    return "\n".join(out)


def make_thumbnail(proj: dict, scene_idx: int, title: str, out: Path, style: str = "paper") -> Path:
    aspect = proj.get("aspect", "16:9")
    aw, ah = store.ASPECTS.get(aspect, (1920, 1080))
    W, H = (1280, 720) if aw >= ah else (720, int(720 * ah / aw))
    paper = store.load_settings().get("paperColor", "#F5EBD7")
    canvas = Image.new("RGB", (W, H), paper)
    scenes = proj["scenes"]
    sc = scenes[min(scene_idx, len(scenes) - 1)] if scenes else None
    if sc and sc.get("image"):
        p = store.project_dir(proj["id"]) / sc["image"]
        if p.exists():
            im = Image.open(p).convert("RGB")
            im = ImageOps.contain(im, (W, H))
            canvas.paste(im, ((W - im.width) // 2, (H - im.height) // 2))
    if title:
        d = ImageDraw.Draw(canvas)
        font = _font(pick_font(store.load_settings().get("fontPath", "")), int(min(W, H) * 0.11))
        lines = _wrap(d, title.upper(), font, int(W * 0.86))[:3]
        asc, desc = font.getmetrics()
        lh = int((asc + desc) * 1.08)
        bh = lh * len(lines) + int(lh * 0.5)
        y0 = int(H * 0.06)
        d.rectangle([0, y0, W, y0 + bh], fill=(255, 250, 238))
        d.line([0, y0 + bh, W, y0 + bh], fill=(40, 40, 40), width=4)
        for i, ln in enumerate(lines):
            tw = d.textlength(ln, font=font)
            d.text(((W - tw) / 2, y0 + int(lh * 0.25) + i * lh), ln, font=font, fill=(30, 30, 30),
                   stroke_width=0)
        d.rectangle([int(W * 0.06), y0 + bh - 10, int(W * 0.3), y0 + bh - 4], fill=(232, 93, 4))
    out.parent.mkdir(parents=True, exist_ok=True)
    canvas.save(out)
    return out
