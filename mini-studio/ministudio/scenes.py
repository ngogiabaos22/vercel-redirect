"""Thao tác trên cảnh dùng chung cho API và luồng tự động: lưu ảnh, khoanh vùng, gán câu."""
from __future__ import annotations

import time

import cv2
import numpy as np

from . import regions, store


def update(pid: str, fn):
    with store._lock:
        p = store.load_project(pid)
        fn(p)
        return store.save_project(p)


def build_elements(rects: list[dict], cov: float, w: int, h: int) -> list[dict]:
    els = []
    if cov < 0.97:  # phần nét nằm ngoài các vùng (đường nền…) → vẽ trước tiên như bối cảnh
        els.append({"id": store.uid("e"), "label": "Nền & phần còn lại", "bg": True,
                    "region": {"x": 0, "y": 0, "width": w, "height": h}, "lineIds": [], "protectedRegions": []})
    for i, r in enumerate(rects):
        els.append({"id": store.uid("e"), "label": f"Vùng {i + 1}", "region": r, "lineIds": [],
                    "protectedRegions": []})
    return els


def auto_assign(scene: dict) -> None:
    for el in scene.get("elements", []):
        if el.get("bg"):
            el["lineIds"] = []
    els = [e for e in scene.get("elements", []) if not e.get("bg")]
    lines = scene.get("lines", [])
    n, m = len(els), len(lines)
    for i, el in enumerate(els):
        a, b = (i * m) // n if n else 0, ((i + 1) * m) // n if n else 0
        el["lineIds"] = [ln["id"] for ln in lines[a:b]]


def save_image(pid: str, sid: str, data: bytes) -> dict:
    arr = np.frombuffer(data, dtype=np.uint8)
    img = cv2.imdecode(arr, cv2.IMREAD_UNCHANGED)
    if img is None:
        raise ValueError("File không phải ảnh hợp lệ")
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
            sc["elements"] = build_elements(detected, cov, w, h)
            auto_assign(sc)
        if old and old != name:
            (store.project_dir(pid) / old).unlink(missing_ok=True)

    return update(pid, fn)
