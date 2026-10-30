"""Tự phát hiện các cụm hình (nhân vật, đồ vật) trong ảnh nét vẽ để đề xuất vùng vẽ.

Không cần AI:
 1. tách nét khỏi nền giấy (so màu với 4 góc ảnh)
 2. làm dày nét để các nét gần nhau dính thành cụm (connected components)
 3. cụm quá rộng/cao – thường do một đường nền (mặt đất, đường chân trời) nối các đối tượng –
    được cắt tại những cột/hàng "thưa" (chỉ có 1 nét mảnh chạy qua)
 4. gộp khung lồng nhau, giới hạn số vùng, nới lề
"""
from __future__ import annotations

from pathlib import Path

import cv2
import numpy as np


def _read(path: Path) -> np.ndarray:
    raw = np.fromfile(str(path), dtype=np.uint8)
    img = cv2.imdecode(raw, cv2.IMREAD_COLOR)
    if img is None:
        raise ValueError("Không đọc được ảnh")
    return img


def _ink_mask(img: np.ndarray) -> np.ndarray:
    h, w = img.shape[:2]
    m = max(3, min(h, w) // 50)
    corners = np.concatenate([img[:m, :m].reshape(-1, 3), img[:m, -m:].reshape(-1, 3),
                              img[-m:, :m].reshape(-1, 3), img[-m:, -m:].reshape(-1, 3)])
    bg = np.median(corners, axis=0)
    diff = np.abs(img.astype(np.int16) - bg.astype(np.int16)).sum(axis=2)
    mask = (diff > 60).astype(np.uint8)
    mask = cv2.morphologyEx(mask, cv2.MORPH_OPEN, np.ones((2, 2), np.uint8))
    return mask


def _runs(dense: np.ndarray, min_gap: int) -> list[tuple[int, int]]:
    segs, start, gap, end = [], None, 0, 0
    for i, v in enumerate(dense):
        if v:
            if start is None:
                start = i
            gap, end = 0, i
        elif start is not None:
            gap += 1
            if gap >= min_gap:
                segs.append((start, end))
                start, gap = None, 0
    if start is not None:
        segs.append((start, end))
    return segs


def _bbox(ink: np.ndarray, ox: int, oy: int, noise: int = 2) -> list[int] | None:
    rows = np.where(ink.sum(axis=1) > noise)[0]
    cols = np.where(ink.sum(axis=0) > noise)[0]
    if rows.size == 0 or cols.size == 0:
        return None
    return [ox + int(cols.min()), oy + int(rows.min()), ox + int(cols.max()) + 1, oy + int(rows.max()) + 1]


def _split(ink: np.ndarray, ox: int, oy: int, W: int, H: int, depth: int = 0) -> list[list[int]]:
    """ink: mặt nạ nét của 1 cụm (đã cắt theo khung). Trả về danh sách khung (toạ độ ảnh)."""
    bb = _bbox(ink, 0, 0)
    if bb is None:
        return []
    x0, y0, x1, y1 = bb
    ink = ink[y0:y1, x0:x1]
    ox, oy = ox + x0, oy + y0
    h, w = ink.shape
    if depth < 3:
        for axis in (0, 1):  # 0: cắt theo cột (cụm quá rộng), 1: theo hàng (cụm quá cao)
            size, limit = (w, W) if axis == 0 else (h, H)
            if size < limit * (0.3 if axis == 0 else 0.6):  # hiếm khi cần cắt theo chiều dọc
                continue
            prof = ink.sum(axis=axis).astype(np.float32)
            nz = prof[prof > 0]
            if nz.size == 0:
                continue
            thr = max(5.0, float(np.percentile(nz, 20)) * 2.5)
            segs = _runs(prof > thr, max(8, int(limit * 0.025)))
            if len(segs) <= 1:
                continue
            out: list[list[int]] = []
            for a, b in segs:
                part = ink[:, a:b + 1] if axis == 0 else ink[a:b + 1, :]
                px, py = (ox + a, oy) if axis == 0 else (ox, oy + a)
                out.extend(_split(part, px, py, W, H, depth + 1))
            return out
    return [[ox, oy, ox + w, oy + h]]


def _overlap_ratio(a, b) -> float:
    ix = max(0, min(a[2], b[2]) - max(a[0], b[0]))
    iy = max(0, min(a[3], b[3]) - max(a[1], b[1]))
    inter = ix * iy
    small = min((a[2] - a[0]) * (a[3] - a[1]), (b[2] - b[0]) * (b[3] - b[1])) or 1
    return inter / small


def detect_regions(path: Path, max_regions: int = 6, sensitivity: float = 1.0) -> list[dict]:
    img = _read(path)
    H, W = img.shape[:2]
    mask = _ink_mask(img)
    k = max(9, int(min(W, H) * 0.03 / max(0.3, sensitivity)))
    dil = cv2.dilate(mask, cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (k, k)))
    n, labels, stats, _ = cv2.connectedComponentsWithStats(dil, connectivity=8)
    min_area = W * H * 0.0025
    rects: list[list[int]] = []
    for lb in range(1, n):
        x, y, w, h, _a = stats[lb]
        if w * h < min_area:
            continue
        comp = (labels[y:y + h, x:x + w] == lb) & (mask[y:y + h, x:x + w] > 0)
        rects.extend(_split(comp.astype(np.uint8), x, y, W, H))
    rects = [r for r in rects if (r[2] - r[0]) * (r[3] - r[1]) >= min_area * 0.6]

    # gộp khung lồng nhau / chồng nhiều
    merged = True
    while merged:
        merged = False
        for i in range(len(rects)):
            for j in range(i + 1, len(rects)):
                if _overlap_ratio(rects[i], rects[j]) > 0.55:
                    a, b = rects[i], rects[j]
                    rects[i] = [min(a[0], b[0]), min(a[1], b[1]), max(a[2], b[2]), max(a[3], b[3])]
                    rects.pop(j)
                    merged = True
                    break
            if merged:
                break

    def area(r):
        return (r[2] - r[0]) * (r[3] - r[1])

    def gap(a, b):
        dx = max(0, max(a[0], b[0]) - min(a[2], b[2]))
        dy = max(0, max(a[1], b[1]) - min(a[3], b[3]))
        return max(dx, dy)

    # mảnh vụn nhỏ (vệt chuyển động, cỏ…) nằm sát một vùng lớn → nhập vào vùng đó
    changed = True
    while changed and len(rects) > 1:
        changed = False
        for i, r in enumerate(rects):
            if area(r) < W * H * 0.012:
                j = min((k for k in range(len(rects)) if k != i), key=lambda k: gap(r, rects[k]))
                if gap(r, rects[j]) < min(W, H) * 0.06:
                    b = rects[j]
                    rects[j] = [min(r[0], b[0]), min(r[1], b[1]), max(r[2], b[2]), max(r[3], b[3])]
                    rects.pop(i)
                    changed = True
                    break

    while len(rects) > max_regions:  # quá nhiều → gộp vùng nhỏ nhất vào vùng gần nhất
        rects.sort(key=area)
        a = rects.pop(0)
        cx, cy = (a[0] + a[2]) / 2, (a[1] + a[3]) / 2
        j = min(range(len(rects)), key=lambda i: ((rects[i][0] + rects[i][2]) / 2 - cx) ** 2
                + ((rects[i][1] + rects[i][3]) / 2 - cy) ** 2)
        b = rects[j]
        rects[j] = [min(a[0], b[0]), min(a[1], b[1]), max(a[2], b[2]), max(a[3], b[3])]

    pad = int(min(W, H) * 0.02)
    rects.sort(key=lambda r: ((r[0] + r[2]) / 2, r[1]))  # trái → phải
    out = []
    for r in rects:
        x0, y0 = max(0, r[0] - pad), max(0, r[1] - pad)
        x1, y1 = min(W, r[2] + pad), min(H, r[3] + pad)
        out.append({"x": int(x0), "y": int(y0), "width": int(x1 - x0), "height": int(y1 - y0)})
    if not out:
        out.append({"x": 0, "y": 0, "width": W, "height": H})
    return out


def coverage(path: Path, regions: list[dict]) -> float:
    """Tỉ lệ nét vẽ nằm trong các vùng. Phần ngoài vùng chỉ hiện ra ở cuối cảnh."""
    img = _read(path)
    mask = _ink_mask(img) > 0
    total = int(mask.sum())
    if total == 0:
        return 1.0
    cov = np.zeros_like(mask)
    for r in regions:
        cov[int(r["y"]):int(r["y"] + r["height"]), int(r["x"]):int(r["x"] + r["width"])] = True
    return float((mask & cov).sum() / total)
