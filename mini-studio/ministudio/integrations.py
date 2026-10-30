"""Kết nối AI tuỳ chọn:
- AI viết chữ (llm): Ollama trên máy, Pollinations (miễn phí), Gemini hoặc API kiểu OpenAI –
  viết kịch bản, viết lại văn nói, gợi ý tiêu đề, tạo prompt ảnh cho từng cảnh
- Tạo ảnh: nhiều nguồn, tự chuyển nguồn khi lỗi (xem imagegen.py)
Không bật thì app vẫn chạy đầy đủ (viết tay, tải ảnh lên / dán ảnh).
"""
from __future__ import annotations

import json
import re
import urllib.parse
import urllib.request

from .store import load_settings

STYLE_PROMPT = ("minimal hand-drawn sketch illustration, Notion-style doodle, dark gray pencil line art "
                "on warm beige paper background (#F5EBD7), clean composition, lots of empty space, "
                "subjects well separated, small accents of red, orange and blue only, flat, no shading")
NEGATIVE_PROMPT = ("text, letters, words, numbers, labels, watermark, signature, logo, photo, realistic, 3d, "
                   "render, painting, heavy texture, complex background, crowded, high saturation, gradient")


def _http(url: str, data: dict | None = None, timeout: int = 300) -> bytes:
    req = urllib.request.Request(url, data=json.dumps(data).encode() if data is not None else None,
                                 headers={"Content-Type": "application/json"})
    with urllib.request.urlopen(req, timeout=timeout) as r:
        return r.read()


def ping(url: str) -> bool:
    try:
        urllib.request.urlopen(url, timeout=1.5)
        return True
    except Exception:
        return False


# ───────────────────────── Ollama ─────────────────────────
def ollama_models() -> list[str]:
    s = load_settings()
    try:
        data = json.loads(_http(s["ollamaUrl"].rstrip("/") + "/api/tags", timeout=3))
        return [m["name"] for m in data.get("models", [])]
    except Exception:
        return []


def ollama(prompt: str, system: str = "") -> str:
    s = load_settings()
    body = {"model": s["ollamaModel"], "prompt": prompt, "system": system, "stream": False,
            "options": {"temperature": 0.7}}
    try:
        data = json.loads(_http(s["ollamaUrl"].rstrip("/") + "/api/generate", body))
    except Exception as e:
        raise RuntimeError(f"Không kết nối được Ollama ({s['ollamaUrl']}). Hãy cài Ollama và chạy "
                           f"'ollama pull {s['ollamaModel']}'. Chi tiết: {e}")
    return data.get("response", "").strip()


LLM_DEFAULTS = {
    "pollinations": ("https://text.pollinations.ai/openai", "openai"),
    "gemini": ("https://generativelanguage.googleapis.com/v1beta", "gemini-2.5-flash"),
    "openai": ("https://api.openai.com/v1", "gpt-4o-mini"),
}


def _chat_openai(url: str, key: str, model: str, prompt: str, system: str) -> str:
    msgs = ([{"role": "system", "content": system}] if system else []) + [{"role": "user", "content": prompt}]
    req = urllib.request.Request(url, data=json.dumps({"model": model, "messages": msgs, "temperature": 0.7}).encode(),
                                 headers={"Content-Type": "application/json", "User-Agent": "MiniStudio/1.1",
                                          **({"Authorization": f"Bearer {key}"} if key else {})})
    with urllib.request.urlopen(req, timeout=180) as r:
        raw = r.read().decode("utf-8", "replace")
    try:
        return json.loads(raw)["choices"][0]["message"]["content"].strip()
    except (ValueError, KeyError, IndexError):
        return raw.strip()  # vài máy chủ trả thẳng văn bản


def llm(prompt: str, system: str = "") -> str:
    """Gọi AI viết chữ theo Cài đặt → AI (ollama | pollinations | gemini | openai)."""
    s = load_settings()
    backend = s.get("llmBackend") or "ollama"
    if backend == "ollama":
        return ollama(prompt, system)
    base, model = LLM_DEFAULTS.get(backend, LLM_DEFAULTS["openai"])
    base = (s.get("llmBaseUrl") or base).rstrip("/")
    model = s.get("llmModel") or model
    key = (s.get("llmApiKey") or "").strip()
    try:
        if backend == "pollinations":
            return _chat_openai(base, key, model, prompt, system)
        if backend == "gemini":
            if not key:
                raise RuntimeError("Thiếu API key Gemini (Cài đặt → AI)")
            body = {"contents": [{"parts": [{"text": prompt}]}]}
            if system:
                body["systemInstruction"] = {"parts": [{"text": system}]}
            req = urllib.request.Request(f"{base}/models/{model}:generateContent", data=json.dumps(body).encode(),
                                         headers={"Content-Type": "application/json", "x-goog-api-key": key})
            with urllib.request.urlopen(req, timeout=180) as r:
                js = json.loads(r.read())
            return "".join(pt.get("text", "") for c in js.get("candidates", [])
                           for pt in (c.get("content") or {}).get("parts", [])).strip()
        return _chat_openai(base + "/chat/completions", key, model, prompt, system)
    except RuntimeError:
        raise
    except Exception as e:  # noqa: BLE001
        raise RuntimeError(f"Không gọi được AI viết chữ ({backend}): {e}") from None


SCENES_SYSTEM = ("You write prompts for an image generator that draws simple hand-drawn whiteboard sketches. "
                 "For each scene, describe 1-3 concrete objects or characters and what they do, "
                 "well separated on an empty background. No text, letters or numbers in the image. "
                 "No style words. Answer ONLY with a JSON array of English strings, one per scene, same order.")


def scene_prompts(scenes: list[dict]) -> list[str]:
    """Nhờ AI viết prompt ảnh tiếng Anh cho nhiều cảnh trong một lần gọi."""
    parts = []
    for i, sc in enumerate(scenes, 1):
        note = sc.get("imageNote") or ""
        text = " ".join(ln["text"] for ln in sc.get("lines", []))
        parts.append(f"Scene {i}: {note + ' | ' if note else ''}{text}")
    out = llm("\n".join(parts), SCENES_SYSTEM)
    m = re.search(r"\[.*\]", out, re.S)
    arr = json.loads(m.group(0)) if m else []
    if not isinstance(arr, list) or len(arr) != len(scenes):
        raise RuntimeError("AI trả về sai số lượng prompt")
    return [str(x).strip().strip('"') for x in arr]


SCRIPT_SYSTEM = """Bạn là biên kịch video ngắn tiếng Việt. Viết kịch bản để ĐỌC THÀNH TIẾNG, câu ngắn, dễ hiểu.
Định dạng BẮT BUỘC:
## Tên cảnh
> mô tả hình minh hoạ đơn giản cho cảnh (1-3 đối tượng, không có chữ)
Câu thoại 1. Câu thoại 2.
Mỗi cảnh 2-4 câu. Không dùng gạch đầu dòng, không ghi chú khác."""

KINDS = {"explainer": "video giải thích kiến thức", "story": "video kể chuyện",
         "lesson": "bài giảng ngắn", "short": "video ngắn mạng xã hội có câu mở đầu gây tò mò"}


def ai_task(task: str, payload: dict) -> str:
    if task == "script":
        kind = KINDS.get(payload.get("kind", "explainer"), "video giải thích")
        secs = int(payload.get("seconds", 60))
        n = max(2, round(secs / 15))
        return llm(f"Viết kịch bản {kind} khoảng {secs} giây (khoảng {n} cảnh) về chủ đề: "
                      f"{payload.get('topic', '')}. Đối tượng: {payload.get('audience', 'người xem phổ thông')}.",
                      SCRIPT_SYSTEM)
    if task == "polish":
        return llm("Viết lại kịch bản sau cho tự nhiên khi đọc thành tiếng, giữ nguyên định dạng "
                      "## cảnh và > mô tả hình, câu ngắn hơn, giữ ý:\n\n" + payload.get("text", ""), SCRIPT_SYSTEM)
    if task == "hook":
        return llm("Đề xuất 5 câu mở đầu (hook) dưới 15 từ, gây tò mò, cho video về: "
                      + payload.get("text", "")[:1500] + "\nMỗi câu một dòng, không đánh số.")
    if task == "titles":
        return llm("Từ kịch bản sau, đề xuất 5 tiêu đề video hấp dẫn (dưới 70 ký tự), 1 đoạn mô tả 2 câu "
                      "và 8 hashtag tiếng Việt:\n\n" + payload.get("text", "")[:3000])
    if task == "image_prompt":
        out = llm("Convert this Vietnamese scene description into ONE short English prompt for an image "
                     "generator (objects, composition, actions only; no style words, no text in image). "
                     "Answer with the prompt only.\n\nScene: " + payload.get("text", ""))
        return out.strip().strip('"')
    raise ValueError("Tác vụ không hỗ trợ")


def build_image_prompt(scene_note: str, english: str = "") -> str:
    subject = english or scene_note
    return f"{subject}. {STYLE_PROMPT}"


# ───────────────────────── tạo ảnh ─────────────────────────
def generate_image(prompt: str, aspect: str, log=None, cancelled=lambda: False) -> tuple[bytes, str]:
    from . import imagegen
    return imagegen.generate(prompt, aspect, NEGATIVE_PROMPT, log, cancelled)


def services_status() -> dict:
    s = load_settings()
    return {
        "ollama": ping(s["ollamaUrl"].rstrip("/") + "/api/tags"),
        "a1111": ping(s["a1111Url"].rstrip("/") + "/sdapi/v1/sd-models"),
        "comfy": ping(s["comfyUrl"].rstrip("/") + "/system_stats"),
    }
