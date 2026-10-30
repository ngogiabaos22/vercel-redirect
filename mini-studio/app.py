#!/usr/bin/env python3
"""Mini Studio – khởi động máy chủ cục bộ và mở trình duyệt.

    python app.py              # mở http://127.0.0.1:8765
    python app.py --port 9000  # đổi cổng
    python app.py --no-browser
"""
import argparse
import sys
import threading
import webbrowser

sys.dont_write_bytecode = True
for _s in (sys.stdout, sys.stderr):  # tránh lỗi in tiếng Việt trên console Windows cũ
    try:
        _s.reconfigure(encoding="utf-8", errors="replace")
    except Exception:
        pass

from ministudio import __version__  # noqa: E402
from ministudio.audio import find_ffmpeg  # noqa: E402
from ministudio.server import serve  # noqa: E402


def main():
    ap = argparse.ArgumentParser(description="Mini Studio – xưởng video whiteboard chạy trên máy")
    ap.add_argument("--host", default="127.0.0.1")
    ap.add_argument("--port", type=int, default=8765)
    ap.add_argument("--no-browser", action="store_true")
    a = ap.parse_args()

    if not find_ffmpeg():
        print("[!] Chưa có ffmpeg. Chạy: pip install imageio-ffmpeg  (hoặc cài ffmpeg vào PATH)")
    httpd = serve(a.host, a.port)
    url = f"http://{a.host}:{a.port}"
    print("=" * 52)
    print(f"  Mini Studio {__version__} đang chạy tại: {url}")
    print("  Nhấn Ctrl+C để tắt.")
    print("=" * 52)
    if not a.no_browser:
        threading.Timer(0.8, lambda: webbrowser.open(url)).start()
    try:
        httpd.serve_forever()
    except KeyboardInterrupt:
        print("\nĐã tắt Mini Studio.")


if __name__ == "__main__":
    main()
