"""Vẽ phụ đề lên khung hình bằng Pillow (hỗ trợ tiếng Việt, không phụ thuộc libass)."""
from __future__ import annotations

import os
import sys
from functools import lru_cache
from pathlib import Path

import numpy as np
from PIL import Image, ImageDraw, ImageFont

from . import ROOT

FONT_CANDIDATES = [
    str(ROOT / "web" / "fonts" / "BeVietnamPro-SemiBold.ttf"),
    # Windows
    r"C:\Windows\Fonts\segoeuib.ttf", r"C:\Windows\Fonts\arialbd.ttf", r"C:\Windows\Fonts\arial.ttf",
    # macOS
    "/System/Library/Fonts/Supplemental/Arial Bold.ttf", "/Library/Fonts/Arial Unicode.ttf",
    "/System/Library/Fonts/Supplemental/Arial.ttf",
    # Linux
    "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf",
    "/usr/share/fonts/truetype/noto/NotoSans-Bold.ttf",
    "/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf",
]


def pick_font(custom: str = "") -> str | None:
    if custom and Path(custom).exists():
        return custom
    for f in FONT_CANDIDATES:
        if os.path.exists(f):
            return f
    return None


@lru_cache(maxsize=16)
def _font(path: str | None, size: int):
    if path:
        try:
            return ImageFont.truetype(path, size)
        except Exception:
            pass
    return ImageFont.load_default()


def _wrap(draw: ImageDraw.ImageDraw, text: str, font, max_w: int) -> list[str]:
    words = text.split()
    lines, cur = [], ""
    for w in words:
        t = f"{cur} {w}".strip()
        if draw.textlength(t, font=font) <= max_w or not cur:
            cur = t
        else:
            lines.append(cur)
            cur = w
    if cur:
        lines.append(cur)
    # cân bằng 2 dòng cho đẹp
    if len(lines) == 2 and len(lines[1]) < len(lines[0]) * 0.4:
        allw = text.split()
        half = len(allw) // 2 + 1
        lines = [" ".join(allw[:half]), " ".join(allw[half:])]
    return lines


@lru_cache(maxsize=512)
def render_overlay(text: str, frame_w: int, frame_h: int, size_pct: int = 100,
                   position: str = "bottom", style: str = "box", font_path: str | None = None):
    """Trả về (rgba ndarray, x, y) để dán lên khung hình."""
    base = int(min(frame_w, frame_h) * 0.045 * size_pct / 100)
    font = _font(font_path, max(14, base))
    tmp = Image.new("RGBA", (10, 10))
    d = ImageDraw.Draw(tmp)
    max_w = int(frame_w * 0.84)
    lines = _wrap(d, text, font, max_w)
    asc, desc = font.getmetrics() if hasattr(font, "getmetrics") else (base, base // 4)
    lh = int((asc + desc) * 1.12)
    tw = int(max(d.textlength(ln, font=font) for ln in lines)) if lines else 0
    padx, pady = int(base * 0.6), int(base * 0.35)
    bw, bh = tw + 2 * padx, lh * len(lines) + 2 * pady
    img = Image.new("RGBA", (bw, bh), (0, 0, 0, 0))
    d = ImageDraw.Draw(img)
    if style == "box":
        d.rounded_rectangle([0, 0, bw - 1, bh - 1], radius=int(base * 0.35), fill=(20, 20, 24, 190))
        fill, stroke, sw = (255, 255, 255, 255), None, 0
    elif style == "paper":
        d.rounded_rectangle([0, 0, bw - 1, bh - 1], radius=int(base * 0.35), fill=(255, 250, 238, 235),
                            outline=(60, 60, 60, 255), width=max(1, base // 14))
        fill, stroke, sw = (40, 40, 40, 255), None, 0
    else:  # outline
        fill, stroke, sw = (255, 255, 255, 255), (0, 0, 0, 255), max(2, base // 9)
    for i, ln in enumerate(lines):
        lw = d.textlength(ln, font=font)
        x = (bw - lw) / 2
        y = pady + i * lh
        if stroke:
            d.text((x, y), ln, font=font, fill=fill, stroke_width=sw, stroke_fill=stroke)
        else:
            d.text((x, y), ln, font=font, fill=fill)
    arr = np.array(img)
    x = (frame_w - bw) // 2
    margin = int(frame_h * 0.06)
    y = margin if position == "top" else (frame_h - bh) // 2 if position == "middle" else frame_h - bh - margin
    return arr, x, y


def composite(frame: np.ndarray, overlay) -> None:
    arr, x, y = overlay
    h, w = arr.shape[:2]
    fh, fw = frame.shape[:2]
    x0, y0 = max(0, x), max(0, y)
    x1, y1 = min(fw, x + w), min(fh, y + h)
    if x1 <= x0 or y1 <= y0:
        return
    sub = arr[y0 - y:y1 - y, x0 - x:x1 - x]
    a = sub[:, :, 3:4].astype(np.float32) / 255.0
    region = frame[y0:y1, x0:x1].astype(np.float32)
    frame[y0:y1, x0:x1] = (region * (1 - a) + sub[:, :, :3].astype(np.float32) * a).astype(np.uint8)


if __name__ == "__main__":  # thử nhanh
    f = np.full((720, 1280, 3), 230, np.uint8)
    composite(f, render_overlay("Xin chào! Đây là phụ đề tiếng Việt có dấu đầy đủ.", 1280, 720, font_path=pick_font()))
    Image.fromarray(f).save(sys.argv[1] if len(sys.argv) > 1 else "sub_test.png")
