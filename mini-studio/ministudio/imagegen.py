"""Tạo ảnh qua nhiều nguồn (provider), có thứ tự ưu tiên và tự chuyển nguồn khi lỗi.

Mỗi nguồn là một dict trong settings["imageProviders"]:
    {"id", "type", "name", "enabled", "apiKey", "model", "baseUrl", "accountId", "size", "extra"}

Các loại (type) hỗ trợ:
- pollinations : Pollinations.ai – miễn phí, không cần key (có key thì ít bị giới hạn hơn)
- cloudflare   : Cloudflare Workers AI – gói miễn phí theo ngày (cần Account ID + API token)
- huggingface  : Hugging Face Inference – token miễn phí, có hạn mức tháng
- gemini       : Google Gemini API (AI Studio) – có hạn mức miễn phí tuỳ mô hình/thời điểm
- openai       : API kiểu OpenAI /images/generations (OpenAI, Together, OpenRouter… đổi Base URL)
- a1111        : Stable Diffusion WebUI / Forge trên máy (miễn phí, cần GPU)
- comfy        : ComfyUI trên máy (miễn phí, cần GPU)
"""
from __future__ import annotations

import base64
import json
import random
import time
import urllib.error
import urllib.parse
import urllib.request

from .store import load_settings

UA = "MiniStudio/1.1 (+local app)"

SIZES = {"16:9": (1344, 768), "9:16": (768, 1344), "1:1": (1024, 1024), "4:5": (896, 1120)}

PROVIDER_TYPES = {
    "pollinations": {"label": "Pollinations.ai", "free": "Miễn phí, không cần key",
                     "fields": ["model", "apiKey", "baseUrl"], "model": "flux",
                     "baseUrl": "https://image.pollinations.ai",
                     "help": "Không cần đăng ký. Có thể bị giới hạn tốc độ khi tạo nhiều ảnh liên tục; "
                             "đăng ký token miễn phí tại auth.pollinations.ai để ổn định hơn."},
    "cloudflare": {"label": "Cloudflare Workers AI", "free": "Miễn phí ~10.000 neuron/ngày",
                   "fields": ["accountId", "apiKey", "model"], "model": "@cf/black-forest-labs/flux-1-schnell",
                   "help": "dash.cloudflare.com → AI → Workers AI → lấy Account ID và tạo API token "
                           "(quyền Workers AI). Gói miễn phí đủ vài trăm ảnh/ngày."},
    "huggingface": {"label": "Hugging Face Inference", "free": "Token miễn phí, hạn mức nhỏ mỗi tháng",
                    "fields": ["apiKey", "model", "baseUrl"], "model": "black-forest-labs/FLUX.1-schnell",
                    "baseUrl": "https://router.huggingface.co/hf-inference/models",
                    "help": "huggingface.co → Settings → Access Tokens → tạo token (quyền Inference)."},
    "gemini": {"label": "Google Gemini API", "free": "Có hạn mức miễn phí tuỳ mô hình",
               "fields": ["apiKey", "model"], "model": "gemini-2.5-flash-image",
               "help": "aistudio.google.com → Get API key. Hạn mức miễn phí của mô hình tạo ảnh có thể "
                       "thay đổi; hết hạn mức app tự chuyển sang nguồn kế tiếp."},
    "openai": {"label": "API kiểu OpenAI", "free": "Trả phí (tuỳ nhà cung cấp)",
               "fields": ["baseUrl", "apiKey", "model", "size", "extra"], "model": "gpt-image-1",
               "baseUrl": "https://api.openai.com/v1",
               "help": "Dùng cho OpenAI, Together, OpenRouter… hoặc máy chủ tương thích. "
                       "'Thêm JSON' để gửi tham số riêng, ví dụ {\"steps\": 4}."},
    "a1111": {"label": "Stable Diffusion WebUI / Forge (trên máy)", "free": "Miễn phí, cần GPU",
              "fields": ["baseUrl"], "baseUrl": "http://127.0.0.1:7860",
              "help": "Chạy WebUI/Forge với tham số --api."},
    "comfy": {"label": "ComfyUI (trên máy)", "free": "Miễn phí, cần GPU",
              "fields": ["baseUrl", "model"], "baseUrl": "http://127.0.0.1:8188", "model": "",
              "help": "Điền tên checkpoint vào ô Mô hình, ví dụ sd_xl_base_1.0.safetensors."},
}


class ProviderError(RuntimeError):
    pass


def _req(url: str, data: bytes | None = None, headers: dict | None = None, timeout: int = 180) -> tuple[bytes, str]:
    hd = {"User-Agent": UA, **(headers or {})}
    req = urllib.request.Request(url, data=data, headers=hd, method="POST" if data is not None else "GET")
    try:
        with urllib.request.urlopen(req, timeout=timeout) as r:
            return r.read(), r.headers.get("Content-Type", "")
    except urllib.error.HTTPError as e:
        body = e.read()[:400].decode("utf-8", "replace")
        raise ProviderError(f"HTTP {e.code}: {body or e.reason}") from None
    except urllib.error.URLError as e:
        raise ProviderError(f"Không kết nối được: {e.reason}") from None


def _json_post(url: str, body: dict, headers: dict | None = None, timeout: int = 180) -> tuple[bytes, str]:
    return _req(url, json.dumps(body).encode(), {"Content-Type": "application/json", **(headers or {})}, timeout)


def _is_image(data: bytes) -> bool:
    return data[:8].startswith(b"\x89PNG") or data[:3] == b"\xff\xd8\xff" or data[:4] == b"RIFF" or data[:6] in (b"GIF87a", b"GIF89a")


def _need(p: dict, *keys: str) -> None:
    miss = [k for k in keys if not (p.get(k) or "").strip()]
    if miss:
        names = {"apiKey": "API key", "accountId": "Account ID", "model": "Mô hình", "baseUrl": "Địa chỉ"}
        raise ProviderError("Thiếu " + ", ".join(names.get(k, k) for k in miss))


# ───────────────────────── từng nguồn ─────────────────────────
def _pollinations(p: dict, prompt: str, w: int, h: int, negative: str) -> bytes:
    base = (p.get("baseUrl") or PROVIDER_TYPES["pollinations"]["baseUrl"]).rstrip("/")
    q = {"width": w, "height": h, "seed": random.randint(1, 2**31), "model": p.get("model") or "flux",
         "nologo": "true", "private": "true", "enhance": "false"}
    if negative:
        q["negative_prompt"] = negative[:300]
    url = f"{base}/prompt/{urllib.parse.quote(prompt[:1800])}?{urllib.parse.urlencode(q)}"
    hd = {"Authorization": f"Bearer {p['apiKey']}"} if p.get("apiKey") else {}
    data, _ = _req(url, headers=hd, timeout=240)
    return data


def _cloudflare(p: dict, prompt: str, w: int, h: int, negative: str) -> bytes:
    _need(p, "accountId", "apiKey")
    model = p.get("model") or PROVIDER_TYPES["cloudflare"]["model"]
    url = f"https://api.cloudflare.com/client/v4/accounts/{p['accountId'].strip()}/ai/run/{model}"
    body: dict = {"prompt": prompt[:2000]}
    if "flux" in model:
        body["steps"] = 6
    else:
        body.update({"width": w, "height": h, "negative_prompt": negative, "num_steps": 20})
    data, ctype = _json_post(url, body, {"Authorization": f"Bearer {p['apiKey'].strip()}"})
    if "json" in ctype or data[:1] == b"{":
        js = json.loads(data)
        img = (js.get("result") or {}).get("image")
        if not img:
            raise ProviderError("Cloudflare không trả ảnh: " + json.dumps(js.get("errors") or js)[:300])
        return base64.b64decode(img)
    return data


def _huggingface(p: dict, prompt: str, w: int, h: int, negative: str) -> bytes:
    _need(p, "apiKey")
    base = (p.get("baseUrl") or PROVIDER_TYPES["huggingface"]["baseUrl"]).rstrip("/")
    model = p.get("model") or PROVIDER_TYPES["huggingface"]["model"]
    body = {"inputs": prompt, "parameters": {"width": w, "height": h}}
    if negative and "FLUX" not in model.upper():
        body["parameters"]["negative_prompt"] = negative
    data, _ = _json_post(f"{base}/{model}", body,
                         {"Authorization": f"Bearer {p['apiKey'].strip()}", "Accept": "image/png"}, timeout=240)
    return data


def _gemini(p: dict, prompt: str, w: int, h: int, negative: str) -> bytes:
    _need(p, "apiKey")
    model = p.get("model") or PROVIDER_TYPES["gemini"]["model"]
    aspect = min(SIZES, key=lambda k: abs(SIZES[k][0] / SIZES[k][1] - w / h))
    text = f"Generate one image. {prompt}. Aspect ratio {aspect}. Avoid: {negative}"
    if model.startswith("imagen"):
        url = f"https://generativelanguage.googleapis.com/v1beta/models/{model}:predict"
        body = {"instances": [{"prompt": prompt}], "parameters": {"sampleCount": 1, "aspectRatio": aspect}}
        data, _ = _json_post(url, body, {"x-goog-api-key": p["apiKey"].strip()})
        preds = json.loads(data).get("predictions") or []
        if not preds:
            raise ProviderError("Imagen không trả ảnh")
        return base64.b64decode(preds[0]["bytesBase64Encoded"])
    url = f"https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent"
    body = {"contents": [{"parts": [{"text": text}]}],
            "generationConfig": {"responseModalities": ["TEXT", "IMAGE"]}}
    data, _ = _json_post(url, body, {"x-goog-api-key": p["apiKey"].strip()})
    js = json.loads(data)
    for cand in js.get("candidates", []):
        for part in (cand.get("content") or {}).get("parts", []):
            inline = part.get("inlineData") or part.get("inline_data")
            if inline and inline.get("data"):
                return base64.b64decode(inline["data"])
    raise ProviderError("Gemini không trả ảnh: " + json.dumps(js)[:300])


def _openai(p: dict, prompt: str, w: int, h: int, negative: str) -> bytes:
    _need(p, "apiKey")
    base = (p.get("baseUrl") or PROVIDER_TYPES["openai"]["baseUrl"]).rstrip("/")
    size = (p.get("size") or "").strip()
    if not size:
        size = "1536x1024" if w > h * 1.1 else "1024x1536" if h > w * 1.1 else "1024x1024"
    body = {"model": p.get("model") or "gpt-image-1", "prompt": prompt, "n": 1, "size": size}
    extra = (p.get("extra") or "").strip()
    if extra:
        try:
            body.update(json.loads(extra))
        except json.JSONDecodeError as e:
            raise ProviderError(f"'Thêm JSON' không hợp lệ: {e}") from None
    data, _ = _json_post(f"{base}/images/generations", body, {"Authorization": f"Bearer {p['apiKey'].strip()}"}, 300)
    items = json.loads(data).get("data") or []
    if not items:
        raise ProviderError("API không trả ảnh")
    if items[0].get("b64_json"):
        return base64.b64decode(items[0]["b64_json"])
    if items[0].get("url"):
        return _req(items[0]["url"], timeout=120)[0]
    raise ProviderError("API trả dữ liệu không có ảnh")


def _a1111(p: dict, prompt: str, w: int, h: int, negative: str) -> bytes:
    base = (p.get("baseUrl") or "http://127.0.0.1:7860").rstrip("/")
    body = {"prompt": prompt, "negative_prompt": negative, "width": w, "height": h,
            "steps": 28, "cfg_scale": 6.5, "sampler_name": "DPM++ 2M"}
    data, _ = _json_post(base + "/sdapi/v1/txt2img", body, timeout=900)
    return base64.b64decode(json.loads(data)["images"][0].split(",", 1)[-1])


def _comfy(p: dict, prompt: str, w: int, h: int, negative: str) -> bytes:
    base = (p.get("baseUrl") or "http://127.0.0.1:8188").rstrip("/")
    ckpt = (p.get("model") or load_settings().get("comfyCheckpoint") or "").strip()
    if not ckpt:
        raise ProviderError("Chưa điền checkpoint ComfyUI")
    wf = {
        "1": {"class_type": "CheckpointLoaderSimple", "inputs": {"ckpt_name": ckpt}},
        "2": {"class_type": "CLIPTextEncode", "inputs": {"text": prompt, "clip": ["1", 1]}},
        "3": {"class_type": "CLIPTextEncode", "inputs": {"text": negative, "clip": ["1", 1]}},
        "4": {"class_type": "EmptyLatentImage", "inputs": {"width": w, "height": h, "batch_size": 1}},
        "5": {"class_type": "KSampler", "inputs": {"seed": random.randint(1, 2**31), "steps": 28, "cfg": 6.5,
                                                    "sampler_name": "dpmpp_2m", "scheduler": "karras",
                                                    "denoise": 1.0, "model": ["1", 0], "positive": ["2", 0],
                                                    "negative": ["3", 0], "latent_image": ["4", 0]}},
        "6": {"class_type": "VAEDecode", "inputs": {"samples": ["5", 0], "vae": ["1", 2]}},
        "7": {"class_type": "SaveImage", "inputs": {"filename_prefix": "ministudio", "images": ["6", 0]}},
    }
    pid = json.loads(_json_post(base + "/prompt", {"prompt": wf})[0])["prompt_id"]
    for _ in range(900):
        time.sleep(1)
        hist = json.loads(_req(f"{base}/history/{pid}")[0])
        if pid in hist:
            for node in hist[pid]["outputs"].values():
                for im in node.get("images", []):
                    q = urllib.parse.urlencode({"filename": im["filename"], "subfolder": im["subfolder"],
                                                "type": im["type"]})
                    return _req(f"{base}/view?{q}")[0]
    raise ProviderError("ComfyUI quá thời gian")


HANDLERS = {"pollinations": _pollinations, "cloudflare": _cloudflare, "huggingface": _huggingface,
            "gemini": _gemini, "openai": _openai, "a1111": _a1111, "comfy": _comfy}


# ───────────────────────── điều phối ─────────────────────────
def providers(settings: dict | None = None) -> list[dict]:
    s = settings or load_settings()
    return [p for p in s.get("imageProviders") or [] if p.get("enabled") and p.get("type") in HANDLERS]


def generate_with(p: dict, prompt: str, aspect: str, negative: str = "") -> bytes:
    w, h = SIZES.get(aspect, SIZES["16:9"])
    data = HANDLERS[p["type"]](p, prompt, w, h, negative)
    if not _is_image(data):
        raise ProviderError("Nguồn trả về dữ liệu không phải ảnh: " + data[:160].decode("utf-8", "replace"))
    return data


def generate(prompt: str, aspect: str, negative: str = "", log=None, cancelled=lambda: False) -> tuple[bytes, str]:
    """Thử lần lượt các nguồn đang bật. Trả về (ảnh, tên nguồn đã dùng)."""
    s = load_settings()
    plist = providers(s)
    if not plist:
        raise ProviderError("Chưa bật nguồn tạo ảnh nào (Cài đặt → Nguồn ảnh)")
    retries = max(0, int(s.get("imageRetries", 2)))
    errors = []
    for p in plist:
        name = p.get("name") or p["type"]
        for attempt in range(retries + 1):
            if cancelled():
                raise ProviderError("Đã huỷ")
            try:
                return generate_with(p, prompt, aspect, negative), name
            except Exception as e:  # noqa: BLE001
                msg = str(e)
                errors.append(f"{name}: {msg}")
                if log:
                    log(f"{name} lỗi (lần {attempt + 1}/{retries + 1}): {msg[:160]}")
                # sai key / thiếu cấu hình / hết hạn mức → chuyển nguồn ngay, không thử lại
                if any(k in msg for k in ("Thiếu", "HTTP 400", "HTTP 401", "HTTP 403", "HTTP 404", "không hợp lệ")):
                    break
                if attempt < retries:
                    time.sleep(min(20, 3 * (attempt + 1) + random.random() * 2))
    raise ProviderError("Tất cả nguồn ảnh đều lỗi:\n" + "\n".join(errors[-6:]))
