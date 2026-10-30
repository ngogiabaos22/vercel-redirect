"""Thông báo Telegram khi xuất video xong (hoặc lỗi).

Cách lấy thông tin:
1. Mở Telegram, nhắn @BotFather → /newbot → nhận Bot Token.
2. Nhắn một tin bất kỳ cho bot vừa tạo (hoặc thêm bot vào nhóm).
3. Trong Cài đặt → Thông báo bấm "Tìm Chat ID" để app tự lấy.
"""
from __future__ import annotations

import json
import os
import secrets
import urllib.error
import urllib.request
from pathlib import Path

from .store import load_settings

# có thể trỏ tới máy chủ Bot API riêng (gửi file lớn hơn 50 MB) qua biến môi trường
API = os.environ.get("MINISTUDIO_TELEGRAM_API", "https://api.telegram.org").rstrip("/") + "/bot{token}/{method}"
VIDEO_LIMIT = 49 * 1024 * 1024  # Bot API chỉ nhận file ≤ 50 MB


def _call(token: str, method: str, data: bytes | None = None, ctype: str = "application/json", timeout: int = 60) -> dict:
    req = urllib.request.Request(API.format(token=token.strip(), method=method), data=data,
                                 headers={"Content-Type": ctype} if data is not None else {})
    try:
        with urllib.request.urlopen(req, timeout=timeout) as r:
            js = json.loads(r.read())
    except urllib.error.HTTPError as e:
        try:
            js = json.loads(e.read())
        except Exception:
            js = {"ok": False, "description": f"HTTP {e.code}"}
    except urllib.error.URLError as e:
        raise RuntimeError(f"Không kết nối được Telegram: {e.reason}") from None
    if not js.get("ok"):
        raise RuntimeError("Telegram từ chối: " + str(js.get("description", js)))
    return js.get("result")


def _multipart(fields: dict, file_field: str, path: Path, mime: str) -> tuple[bytes, str]:
    boundary = "----ministudio" + secrets.token_hex(8)
    out = bytearray()
    for k, v in fields.items():
        out += f"--{boundary}\r\nContent-Disposition: form-data; name=\"{k}\"\r\n\r\n{v}\r\n".encode()
    out += (f"--{boundary}\r\nContent-Disposition: form-data; name=\"{file_field}\"; filename=\"{path.name}\"\r\n"
            f"Content-Type: {mime}\r\n\r\n").encode()
    out += path.read_bytes() + f"\r\n--{boundary}--\r\n".encode()
    return bytes(out), f"multipart/form-data; boundary={boundary}"


def _cfg(settings: dict | None = None) -> tuple[str, str]:
    s = settings or load_settings()
    token, chat = (s.get("telegramToken") or "").strip(), str(s.get("telegramChatId") or "").strip()
    if not token or not chat:
        raise RuntimeError("Chưa điền Bot Token và Chat ID (Cài đặt → Thông báo)")
    return token, chat


def send_message(text: str, settings: dict | None = None) -> None:
    token, chat = _cfg(settings)
    _call(token, "sendMessage", json.dumps({"chat_id": chat, "text": text[:4000], "parse_mode": "HTML",
                                            "disable_web_page_preview": True}).encode())


def send_file(path: Path, caption: str, kind: str = "video", settings: dict | None = None) -> None:
    token, chat = _cfg(settings)
    method, field, mime = {"video": ("sendVideo", "video", "video/mp4"),
                           "photo": ("sendPhoto", "photo", "image/png")}[kind]
    fields = {"chat_id": chat, "caption": caption[:1000], "parse_mode": "HTML"}
    if kind == "video":
        fields["supports_streaming"] = "true"
    data, ctype = _multipart(fields, field, path, mime)
    _call(token, method, data, ctype, timeout=600)


def find_chats(token: str) -> list[dict]:
    """Đọc tin nhắn gần đây gửi tới bot để lấy Chat ID."""
    res = _call(token, "getUpdates", json.dumps({"limit": 50}).encode())
    seen: dict[str, dict] = {}
    for u in res or []:
        msg = u.get("message") or u.get("channel_post") or u.get("my_chat_member") or {}
        chat = msg.get("chat") or {}
        if chat.get("id") is not None:
            name = chat.get("title") or " ".join(x for x in (chat.get("first_name"), chat.get("last_name")) if x) \
                or chat.get("username") or ""
            seen[str(chat["id"])] = {"id": str(chat["id"]), "name": name, "type": chat.get("type", "")}
    return list(seen.values())


def _esc(t: str) -> str:
    return t.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;")


def video_done(proj: dict, entry: dict, pdir: Path, elapsed_s: float | None = None, log=None) -> None:
    """Gửi thông báo xuất video thành công. Không bao giờ ném lỗi ra ngoài (chỉ ghi log)."""
    s = load_settings()
    if not s.get("telegramEnabled"):
        return
    secs = entry.get("durationMs", 0) / 1000
    mb = entry.get("size", 0) / 1048576
    text = (f"✅ <b>Video đã xong</b>\n🎬 {_esc(proj.get('name', ''))}\n"
            f"⏱ {secs:.0f}s · {entry.get('w')}×{entry.get('h')} · {mb:.1f} MB"
            + (f"\n🕒 Thời gian làm: {elapsed_s / 60:.1f} phút" if elapsed_s else "")
            + f"\n📁 {_esc(str(pdir / entry['file']))}")
    try:
        video = pdir / entry["file"]
        if s.get("telegramSendVideo", True) and video.exists() and video.stat().st_size <= VIDEO_LIMIT:
            send_file(video, text, "video", s)
        elif entry.get("thumb") and (pdir / entry["thumb"]).exists():
            send_file(pdir / entry["thumb"], text + ("\n(Video > 50 MB nên không gửi kèm)" if s.get("telegramSendVideo", True) else ""), "photo", s)
        else:
            send_message(text, s)
        if log:
            log("Đã gửi thông báo Telegram")
    except Exception as e:  # noqa: BLE001
        if log:
            log(f"Không gửi được Telegram: {e}")


def video_failed(name: str, error: str, log=None) -> None:
    s = load_settings()
    if not (s.get("telegramEnabled") and s.get("telegramOnError", True)):
        return
    try:
        send_message(f"❌ <b>Tạo video lỗi</b>\n🎬 {_esc(name)}\n<code>{_esc(error[:1500])}</code>", s)
    except Exception as e:  # noqa: BLE001
        if log:
            log(f"Không gửi được Telegram: {e}")
