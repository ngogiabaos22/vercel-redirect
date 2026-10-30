"""Tách kịch bản thành Cảnh → Câu thoại, và gộp lại với dữ liệu cũ (giữ ảnh, giọng đã làm).

Cú pháp kịch bản (đơn giản, gõ tay được):
    ## Tên cảnh            → bắt đầu cảnh mới (# hoặc ## đều được)
    > mô tả hình ảnh        → ghi chú hình cho cảnh (dùng làm prompt tạo ảnh)
    Câu thoại bình thường   → tự tách theo dấu . ! ? …
    (dòng trống)            → nếu không có tiêu đề ##, mỗi đoạn là một cảnh
"""
from __future__ import annotations

import re

from .store import uid

MAX_CHARS = 110   # câu dài hơn sẽ được cắt ở dấu phẩy
MIN_CHARS = 14    # câu quá ngắn sẽ gộp với câu sau

_SENT_END = re.compile(r"(?<=[\.\!\?…])\s+(?=[^\s])")


def _split_long(sentence: str) -> list[str]:
    if len(sentence) <= MAX_CHARS:
        return [sentence]
    parts = re.split(r"(?<=[,;:])\s+", sentence)
    out, cur = [], ""
    for p in parts:
        if cur and len(cur) + 1 + len(p) > MAX_CHARS:
            out.append(cur)
            cur = p
        else:
            cur = f"{cur} {p}".strip()
    if cur:
        out.append(cur)
    return out


def split_sentences(text: str) -> list[str]:
    res: list[str] = []
    for raw_line in text.split("\n"):
        raw_line = raw_line.strip()
        if not raw_line:
            continue
        for s in _SENT_END.split(raw_line):
            s = s.strip()
            if s:
                res.extend(_split_long(s))
    merged: list[str] = []
    for s in res:
        if merged and len(merged[-1]) < MIN_CHARS:
            merged[-1] = f"{merged[-1]} {s}"
        else:
            merged.append(s)
    return merged


def parse_script(raw: str) -> list[dict]:
    """Trả về [{title, imageNote, lines:[text]}]."""
    raw = (raw or "").replace("\r\n", "\n")
    has_heading = any(re.match(r"^\s*#{1,3}\s+", ln) for ln in raw.split("\n"))
    scenes: list[dict] = []

    def new_scene(title=""):
        scenes.append({"title": title, "imageNote": "", "body": []})

    if has_heading:
        for ln in raw.split("\n"):
            m = re.match(r"^\s*#{1,3}\s+(.*)$", ln)
            if m:
                new_scene(m.group(1).strip())
                continue
            if not scenes:
                if not ln.strip():
                    continue
                new_scene("Mở đầu")
            if ln.strip().startswith(">"):
                note = ln.strip()[1:].strip()
                scenes[-1]["imageNote"] = (scenes[-1]["imageNote"] + " " + note).strip()
            else:
                scenes[-1]["body"].append(ln)
    else:
        for block in re.split(r"\n\s*\n", raw.strip()):
            if not block.strip():
                continue
            new_scene("")
            for ln in block.split("\n"):
                if ln.strip().startswith(">"):
                    scenes[-1]["imageNote"] = (scenes[-1]["imageNote"] + " " + ln.strip()[1:].strip()).strip()
                else:
                    scenes[-1]["body"].append(ln)

    out = []
    for i, sc in enumerate(scenes, 1):
        lines = split_sentences("\n".join(sc["body"]))
        if not lines and not sc["imageNote"]:
            continue
        out.append({"title": sc["title"] or f"Cảnh {i}", "imageNote": sc["imageNote"], "lines": lines})
    return out


def merge_into_project(proj: dict, parsed: list[dict]) -> dict:
    """Cập nhật proj['scenes'] theo kịch bản mới, giữ lại ảnh/vùng/giọng đã có khi có thể."""
    old_scenes = proj.get("scenes", [])
    new_scenes = []
    for i, ps in enumerate(parsed):
        old = old_scenes[i] if i < len(old_scenes) else None
        old_lines = {ln["text"]: ln for ln in (old or {}).get("lines", [])}
        lines = []
        for text in ps["lines"]:
            if text in old_lines:
                lines.append(old_lines.pop(text))
            else:
                lines.append({"id": uid("l"), "text": text, "audio": None})
        if old:
            sc = old
            sc["title"] = ps["title"]
            sc["imageNote"] = ps["imageNote"] or sc.get("imageNote", "")
            valid = {ln["id"] for ln in lines}
            for el in sc.get("elements", []):
                el["lineIds"] = [x for x in el.get("lineIds", []) if x in valid]
            sc["lines"] = lines
        else:
            sc = {
                "id": uid("s"), "title": ps["title"], "imageNote": ps["imageNote"],
                "imagePrompt": "", "image": "", "canvas": None, "elements": [], "lines": lines,
            }
        new_scenes.append(sc)
    proj["scenes"] = new_scenes
    return proj


TEMPLATES = {
    "explainer": {
        "label": "Giải thích kiến thức",
        "script": """## Mở đầu: đặt câu hỏi
> một người đứng suy nghĩ, phía trên đầu có dấu hỏi lớn
Bạn có bao giờ tự hỏi vì sao bầu trời lại có màu xanh không? Câu trả lời nằm ở cách ánh sáng đi qua không khí.

## Giải thích cốt lõi
> mặt trời bên trái, các tia sáng nhiều màu đi vào bầu khí quyển, tia xanh bị tán xạ khắp nơi
Ánh sáng mặt trời thật ra gồm nhiều màu trộn lại. Khi đi vào bầu khí quyển, ánh sáng xanh bị các phân tử khí tán xạ mạnh hơn các màu khác.

## Ví dụ đời thường
> hoàng hôn trên biển, mặt trời đỏ cam sát đường chân trời
Đó cũng là lý do lúc hoàng hôn, bầu trời chuyển sang màu cam đỏ. Ánh sáng phải đi một quãng dài hơn nên màu xanh đã bị tán xạ hết trước khi đến mắt ta.

## Tóm tắt
> một bóng đèn sáng, xung quanh là ba biểu tượng nhỏ mặt trời, giọt nước, con mắt
Tóm lại, bầu trời xanh là nhờ hiện tượng tán xạ ánh sáng. Hãy theo dõi kênh để khám phá thêm nhiều điều thú vị nhé!
""",
    },
    "story": {
        "label": "Kể chuyện",
        "script": """## Ngày xửa ngày xưa
> một ngôi làng nhỏ dưới chân núi, vài mái nhà và cây cối
Ngày xửa ngày xưa, ở một ngôi làng nhỏ dưới chân núi, có một cậu bé tên là Tí.

## Biến cố
> cậu bé nhìn lên ngọn núi cao, mây đen kéo đến
Một hôm, dòng suối của làng bỗng cạn khô. Mọi người đều lo lắng không biết phải làm sao.

## Hành trình
> cậu bé đeo ba lô leo lên núi, phía trước là một tảng đá chắn dòng nước
Tí quyết định leo lên núi tìm nguyên nhân. Cậu phát hiện một tảng đá lớn đã chặn mất dòng nước.

## Kết thúc
> dân làng vui mừng bên dòng suối chảy trở lại
Cùng dân làng, Tí dời tảng đá đi. Dòng suối lại chảy, và cả làng có một bài học về lòng dũng cảm.
""",
    },
    "lesson": {
        "label": "Bài giảng",
        "script": """## Mục tiêu bài học
> một tấm bảng nhỏ và ba ô vuông đánh dấu tích
Hôm nay chúng ta sẽ tìm hiểu ba ý chính về quy tắc 80/20.

## Ý 1: Quy tắc là gì
> một chiếc bánh tròn chia làm hai phần, một phần nhỏ tô màu cam
Quy tắc 80/20 nói rằng khoảng tám mươi phần trăm kết quả đến từ hai mươi phần trăm nguyên nhân.

## Ý 2: Áp dụng
> danh sách công việc, vài mục được khoanh tròn đỏ
Khi lập kế hoạch, hãy tìm ra những việc nhỏ nhưng tạo ra nhiều giá trị nhất và làm chúng trước.

## Ý 3: Lưu ý
> cán cân với hai đĩa, một bên có dấu chấm than
Đây là quy luật gần đúng, không phải con số chính xác. Hãy dùng nó như một gợi ý để ưu tiên.

## Tổng kết
> một học sinh giơ tay vui vẻ
Như vậy, chúng ta đã nắm được quy tắc 80/20. Hãy thử áp dụng vào việc học của bạn ngay hôm nay.
""",
    },
    "blank": {"label": "Trống", "script": ""},
}
