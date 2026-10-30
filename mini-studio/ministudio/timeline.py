"""Tính dòng thời gian: giọng đọc quyết định thời lượng, vùng vẽ bám theo câu thoại.

Ý tưởng cốt lõi so với skill gốc: thay vì đọc mốc thời gian từ file SRT có sẵn, app
tạo giọng đọc trước → đo độ dài từng câu → tự sinh mốc thời gian, SRT và annotation.json
cho bộ vẽ whiteboard. Mỗi vùng vẽ được gán với một hoặc nhiều câu; khi giọng đọc câu nào,
bàn tay vẽ vùng tương ứng.
"""
from __future__ import annotations

from .store import load_settings


def estimate_ms(text: str, syllables_per_min: int = 230) -> int:
    words = max(1, len(text.split()))
    ms = int(words * 60000 / max(60, syllables_per_min))
    return max(900, ms + 150)


def line_duration(line: dict, spm: int) -> int:
    a = line.get("audio") or {}
    if a.get("file") and a.get("durationMs"):
        return int(a["durationMs"])
    return estimate_ms(line["text"], spm)


def scene_timeline(proj: dict, scene: dict, spm: int | None = None) -> dict:
    exp = proj.get("export", {})
    lead = int(exp.get("leadMs", 400))
    tail = int(exp.get("tailMs", 900))
    gap = int(proj.get("voice", {}).get("gapMs", 250))
    if spm is None:
        spm = int(load_settings().get("readingSpeed", 230))

    cur = lead
    lines = []
    for ln in scene["lines"]:
        d = line_duration(ln, spm)
        lines.append({"id": ln["id"], "startMs": cur, "durMs": d, "text": ln["text"],
                      "hasAudio": bool((ln.get("audio") or {}).get("file"))})
        cur += d + gap
    total = max(cur - gap + tail, lead + tail + 1000)
    return {"sceneId": scene["id"], "durationMs": total, "lines": lines}


def element_spans(scene: dict, tl: dict) -> list[dict]:
    """Thời gian vẽ cho từng vùng (theo thứ tự trong danh sách)."""
    by_id = {ln["id"]: ln for ln in tl["lines"]}
    order = [ln["id"] for ln in tl["lines"]]
    elements = scene.get("elements", [])
    spans = []
    prev_lines: list[str] = []
    for el in elements:
        ids = [x for x in el.get("lineIds", []) if x in by_id]
        if not ids:
            # vùng chưa gán câu → đi cùng câu của vùng trước (hoặc câu đầu)
            ids = prev_lines or (order[:1] if order else [])
        prev_lines = ids
        if ids:
            s = min(by_id[x]["startMs"] for x in ids)
            e = max(by_id[x]["startMs"] + by_id[x]["durMs"] for x in ids)
        else:
            s, e = 300, 2300
        spans.append({"id": el["id"], "s": s, "e": e})

    # các vùng dùng chung khoảng thời gian → chia đều; chống chồng lấn
    i = 0
    while i < len(spans):
        j = i
        while j + 1 < len(spans) and spans[j + 1]["s"] < spans[i]["e"] and spans[j + 1]["s"] <= spans[j]["s"] + 1:
            j += 1
        group = spans[i:j + 1]
        if len(group) > 1:
            s0 = group[0]["s"]
            e0 = max(g["e"] for g in group)
            step = (e0 - s0) / len(group)
            for k, g in enumerate(group):
                g["s"] = int(s0 + k * step)
                g["e"] = int(s0 + (k + 1) * step)
        i = j + 1
    for k in range(len(spans) - 1):
        if spans[k]["e"] > spans[k + 1]["s"]:
            spans[k]["e"] = spans[k + 1]["s"]
    for sp in spans:
        sp["e"] = max(sp["e"], sp["s"] + 400)
    return spans


def build_annotation(proj: dict, scene: dict, tl: dict) -> dict:
    """Sinh annotation.json đúng định dạng của skill srt-whiteboard-animation."""
    canvas = scene.get("canvas") or {"width": 1920, "height": 1080}
    spans = element_spans(scene, tl)
    by_line = {ln["id"]: ln for ln in scene["lines"]}
    elements = []
    last_end = 0
    for seq, (el, sp) in enumerate(zip(scene.get("elements", []), spans), 1):
        dur = max(400, int((sp["e"] - sp["s"]) * 0.92))  # chừa 8% để "thở"
        r = el["region"]
        x = max(0, min(canvas["width"] - 1, int(r["x"])))
        y = max(0, min(canvas["height"] - 1, int(r["y"])))
        w = max(1, min(canvas["width"] - x, int(r["width"])))
        h = max(1, min(canvas["height"] - y, int(r["height"])))
        elements.append({
            "id": el["id"],
            "label": el.get("label") or f"Vùng {seq}",
            "sequence": seq,
            "narrativeRole": el.get("label", ""),
            "subtitle": " ".join(by_line[x]["text"] for x in el.get("lineIds", []) if x in by_line),
            "type": "object",
            "region": {"x": x, "y": y, "width": w, "height": h},
            "reveal": {
                "direction": "left_to_right",
                "startMs": int(sp["s"]),
                "durationMs": dur,
                "maskPaddingPx": 0,
                "protectedRegions": el.get("protectedRegions", []),
            },
            "handPath": {"start": [x, y], "end": [x + w, y + h], "easing": "easeInOut"},
        })
        last_end = max(last_end, sp["s"] + dur)
    duration = max(tl["durationMs"], last_end + 600)
    return {
        "sceneId": scene["id"],
        "canvas": {"width": int(canvas["width"]), "height": int(canvas["height"])},
        "storyBasis": " ".join(ln["text"] for ln in scene["lines"]),
        "sceneDurationMs": int(duration),
        "elements": elements,
    }


def project_timeline(proj: dict) -> dict:
    spm = int(load_settings().get("readingSpeed", 230))
    scenes = []
    offset = 0
    for sc in proj["scenes"]:
        tl = scene_timeline(proj, sc, spm)
        tl["offsetMs"] = offset
        tl["title"] = sc.get("title", "")
        scenes.append(tl)
        offset += tl["durationMs"]
    return {"totalMs": offset, "scenes": scenes}


def fmt_srt_time(ms: int) -> str:
    ms = max(0, int(ms))
    h, rem = divmod(ms, 3600000)
    m, rem = divmod(rem, 60000)
    s, ms = divmod(rem, 1000)
    return f"{h:02d}:{m:02d}:{s:02d},{ms:03d}"


def to_srt(cues: list[tuple[int, int, str]]) -> str:
    out = []
    for i, (s, e, text) in enumerate(cues, 1):
        out.append(f"{i}\n{fmt_srt_time(s)} --> {fmt_srt_time(e)}\n{text}\n")
    return "\n".join(out)


def project_cues(proj: dict, scene_offsets: list[int] | None = None) -> list[tuple[int, int, str]]:
    ptl = project_timeline(proj)
    cues = []
    for i, tl in enumerate(ptl["scenes"]):
        off = scene_offsets[i] if scene_offsets else tl["offsetMs"]
        for ln in tl["lines"]:
            cues.append((off + ln["startMs"], off + ln["startMs"] + ln["durMs"], ln["text"]))
    return cues
