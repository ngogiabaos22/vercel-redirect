"""Kết nối tuỳ chọn tới công cụ AI chạy trên máy (miễn phí):
- Ollama (LLM): viết kịch bản, viết lại văn nói, gợi ý tiêu đề, tạo prompt ảnh
- Stable Diffusion WebUI / Forge (API /sdapi/v1/txt2img) hoặc ComfyUI: tạo ảnh nét vẽ
Không bật thì app vẫn chạy đầy đủ (viết tay, tải ảnh lên / dán ảnh).
"""
from __future__ import annotations

import base64
import json
import random
import time
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
        return ollama(f"Viết kịch bản {kind} khoảng {secs} giây (khoảng {n} cảnh) về chủ đề: "
                      f"{payload.get('topic', '')}. Đối tượng: {payload.get('audience', 'người xem phổ thông')}.",
                      SCRIPT_SYSTEM)
    if task == "polish":
        return ollama("Viết lại kịch bản sau cho tự nhiên khi đọc thành tiếng, giữ nguyên định dạng "
                      "## cảnh và > mô tả hình, câu ngắn hơn, giữ ý:\n\n" + payload.get("text", ""), SCRIPT_SYSTEM)
    if task == "hook":
        return ollama("Đề xuất 5 câu mở đầu (hook) dưới 15 từ, gây tò mò, cho video về: "
                      + payload.get("text", "")[:1500] + "\nMỗi câu một dòng, không đánh số.")
    if task == "titles":
        return ollama("Từ kịch bản sau, đề xuất 5 tiêu đề video hấp dẫn (dưới 70 ký tự), 1 đoạn mô tả 2 câu "
                      "và 8 hashtag tiếng Việt:\n\n" + payload.get("text", "")[:3000])
    if task == "image_prompt":
        out = ollama("Convert this Vietnamese scene description into ONE short English prompt for an image "
                     "generator (objects, composition, actions only; no style words, no text in image). "
                     "Answer with the prompt only.\n\nScene: " + payload.get("text", ""))
        return out.strip().strip('"')
    raise ValueError("Tác vụ không hỗ trợ")


def build_image_prompt(scene_note: str, english: str = "") -> str:
    subject = english or scene_note
    return f"{subject}. {STYLE_PROMPT}"


# ───────────────────────── tạo ảnh ─────────────────────────
def a1111_txt2img(prompt: str, w: int, h: int) -> bytes:
    s = load_settings()
    body = {"prompt": prompt, "negative_prompt": NEGATIVE_PROMPT, "width": w, "height": h,
            "steps": 28, "cfg_scale": 6.5, "sampler_name": "DPM++ 2M"}
    try:
        data = json.loads(_http(s["a1111Url"].rstrip("/") + "/sdapi/v1/txt2img", body, timeout=900))
    except Exception as e:
        raise RuntimeError(f"Không kết nối được Stable Diffusion WebUI ({s['a1111Url']}). "
                           f"Hãy chạy WebUI/Forge với tham số --api. Chi tiết: {e}")
    return base64.b64decode(data["images"][0].split(",", 1)[-1])


def comfy_txt2img(prompt: str, w: int, h: int) -> bytes:
    s = load_settings()
    base = s["comfyUrl"].rstrip("/")
    ckpt = s.get("comfyCheckpoint") or ""
    if not ckpt:
        raise RuntimeError("Chưa chọn checkpoint ComfyUI trong Cài đặt")
    seed = random.randint(1, 2**31)
    wf = {
        "1": {"class_type": "CheckpointLoaderSimple", "inputs": {"ckpt_name": ckpt}},
        "2": {"class_type": "CLIPTextEncode", "inputs": {"text": prompt, "clip": ["1", 1]}},
        "3": {"class_type": "CLIPTextEncode", "inputs": {"text": NEGATIVE_PROMPT, "clip": ["1", 1]}},
        "4": {"class_type": "EmptyLatentImage", "inputs": {"width": w, "height": h, "batch_size": 1}},
        "5": {"class_type": "KSampler", "inputs": {"seed": seed, "steps": 28, "cfg": 6.5,
                                                    "sampler_name": "dpmpp_2m", "scheduler": "karras",
                                                    "denoise": 1.0, "model": ["1", 0], "positive": ["2", 0],
                                                    "negative": ["3", 0], "latent_image": ["4", 0]}},
        "6": {"class_type": "VAEDecode", "inputs": {"samples": ["5", 0], "vae": ["1", 2]}},
        "7": {"class_type": "SaveImage", "inputs": {"filename_prefix": "ministudio", "images": ["6", 0]}},
    }
    try:
        pid = json.loads(_http(base + "/prompt", {"prompt": wf}))["prompt_id"]
    except Exception as e:
        raise RuntimeError(f"Không kết nối được ComfyUI ({base}). Chi tiết: {e}")
    for _ in range(900):
        time.sleep(1)
        hist = json.loads(_http(f"{base}/history/{pid}"))
        if pid in hist:
            for node in hist[pid]["outputs"].values():
                for im in node.get("images", []):
                    q = urllib.parse.urlencode({"filename": im["filename"], "subfolder": im["subfolder"],
                                                "type": im["type"]})
                    return _http(f"{base}/view?{q}")
    raise RuntimeError("ComfyUI quá thời gian")


def generate_image(prompt: str, aspect: str) -> bytes:
    s = load_settings()
    w, h = {"16:9": (1344, 768), "9:16": (768, 1344), "1:1": (1024, 1024), "4:5": (896, 1120)}.get(aspect, (1344, 768))
    if s.get("imageBackend") == "comfy":
        return comfy_txt2img(prompt, w, h)
    return a1111_txt2img(prompt, w, h)


def services_status() -> dict:
    s = load_settings()
    return {
        "ollama": ping(s["ollamaUrl"].rstrip("/") + "/api/tags"),
        "a1111": ping(s["a1111Url"].rstrip("/") + "/sdapi/v1/sd-models"),
        "comfy": ping(s["comfyUrl"].rstrip("/") + "/system_stats"),
    }
