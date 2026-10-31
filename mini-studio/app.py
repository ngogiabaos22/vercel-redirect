#!/usr/bin/env python3
"""Mini Studio – khởi động máy chủ cục bộ và mở giao diện.

    python app.py              # mở http://127.0.0.1:8765 trong cửa sổ riêng (Edge/Chrome chế độ app)
    python app.py --port 9000  # đổi cổng
    python app.py --browser    # mở bằng tab trình duyệt thường
    python app.py --no-browser
"""
import argparse
import os
import runpy
import shutil
import socket
import subprocess
import sys
import threading
import webbrowser
from pathlib import Path

sys.dont_write_bytecode = True


def _run_engine() -> None:
    """Bản .exe gọi lại chính nó để chạy bộ vẽ whiteboard: MiniStudio.exe --engine <script.py> <tham số…>"""
    script = sys.argv[2]
    sys.argv = [script, *sys.argv[3:]]
    sys.path.insert(0, str(Path(script).parent))
    runpy.run_path(script, run_name="__main__")


if len(sys.argv) > 2 and sys.argv[1] == "--engine":
    _run_engine()
    sys.exit(0)

# chạy bằng pythonw.exe / bản .exe không console → không có cửa sổ đen; app tự tắt khi đóng giao diện
WINDOWED = sys.stdout is None

for _s in (sys.stdout, sys.stderr):  # tránh lỗi in tiếng Việt trên console Windows cũ
    try:
        _s.reconfigure(encoding="utf-8", errors="replace")
    except Exception:
        pass

from ministudio import DATA_DIR, __version__  # noqa: E402

if sys.stdout is None or sys.stderr is None:  # bản không có cửa sổ console → ghi log ra file
    _lp = DATA_DIR / "ministudio.log"
    _log = open(_lp, "a" if _lp.exists() and _lp.stat().st_size < 2_000_000 else "w", encoding="utf-8", buffering=1)
    sys.stdout = sys.stdout or _log
    sys.stderr = sys.stderr or _log

from ministudio.audio import find_ffmpeg  # noqa: E402
from ministudio import jobs  # noqa: E402
from ministudio.server import ACTIVITY, serve  # noqa: E402

APP_BROWSERS = [
    "msedge", "chrome", "google-chrome", "chromium", "chromium-browser",
    r"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe",
    r"C:\Program Files\Microsoft\Edge\Application\msedge.exe",
    r"C:\Program Files\Google\Chrome\Application\chrome.exe",
    r"C:\Program Files (x86)\Google\Chrome\Application\chrome.exe",
    os.path.expandvars(r"%LOCALAPPDATA%\Google\Chrome\Application\chrome.exe"),
    "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome",
    "/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge",
]


def open_ui(url: str, app_window: bool = True) -> None:
    """Mở giao diện trong cửa sổ riêng (không thanh địa chỉ) nếu có Edge/Chrome, không thì tab thường."""
    if app_window:
        for b in APP_BROWSERS:
            exe = shutil.which(b) or (b if os.path.isabs(b) and os.path.exists(b) else None)
            if exe:
                try:
                    subprocess.Popen([exe, f"--app={url}", "--window-size=1440,900"],
                                     stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
                    return
                except OSError:
                    continue
    webbrowser.open(url)


def message_box(text: str, title: str = "Mini Studio") -> None:
    """Báo lỗi bằng hộp thoại khi chạy không có console (Windows)."""
    print(text)
    if WINDOWED and sys.platform == "win32":
        try:
            import ctypes
            ctypes.windll.user32.MessageBoxW(None, text, title, 0x10)
        except Exception:
            pass


def watch_and_exit(httpd, started: float) -> None:
    """Tắt app khi giao diện đã đóng: cửa sổ báo 'bye' (đóng) hoặc im lặng quá lâu. Đang render thì đợi xong."""
    import time
    while True:
        time.sleep(2)
        now = time.time()
        if jobs.active():
            continue
        seen, bye = ACTIVITY["seen"], ACTIVITY["bye"]
        closed = bye and bye >= seen and now - bye > 10       # đóng cửa sổ, không mở lại trong 10 giây
        idle = seen and now - seen > 240                      # không còn giao diện nào gửi tín hiệu
        never = not seen and now - started > 180              # không mở được giao diện
        if closed or idle or never:
            print("Giao diện đã đóng – tắt Mini Studio.")
            httpd.shutdown()
            os._exit(0)


def port_in_use(host: str, port: int) -> bool:
    with socket.socket() as s:
        s.settimeout(0.5)
        return s.connect_ex((host, port)) == 0


def main():
    ap = argparse.ArgumentParser(description="Mini Studio – xưởng video whiteboard chạy trên máy")
    ap.add_argument("--host", default="127.0.0.1")
    ap.add_argument("--port", type=int, default=8765)
    ap.add_argument("--no-browser", action="store_true")
    ap.add_argument("--browser", action="store_true", help="mở bằng tab trình duyệt thường thay vì cửa sổ riêng")
    ap.add_argument("--auto-exit", action="store_true", help="tự tắt khi đóng giao diện (mặc định khi chạy không console)")
    a = ap.parse_args()
    url = f"http://{a.host}:{a.port}"

    if port_in_use(a.host, a.port):  # đã mở rồi → chỉ bật lại giao diện
        print(f"Mini Studio đã chạy tại {url} – mở lại giao diện.")
        if not a.no_browser:
            open_ui(url, not a.browser)
        return

    if not find_ffmpeg():
        print("[!] Chưa có ffmpeg. Chạy: pip install imageio-ffmpeg  (hoặc cài ffmpeg vào PATH)")
    try:
        httpd = serve(a.host, a.port)
    except OSError as e:
        message_box(f"Không mở được cổng {a.port}: {e}\nCó thể một phần mềm khác đang dùng cổng này.")
        return
    print("=" * 56)
    print(f"  Mini Studio {__version__} đang chạy tại: {url}")
    print(f"  Dữ liệu: {DATA_DIR}")
    print("  Đóng cửa sổ này (hoặc Ctrl+C) để tắt Mini Studio.")
    print("=" * 56)
    if not a.no_browser:
        threading.Timer(0.8, lambda: open_ui(url, not a.browser)).start()
    if WINDOWED or a.auto_exit:
        import time
        threading.Thread(target=watch_and_exit, args=(httpd, time.time()), daemon=True).start()
    try:
        httpd.serve_forever()
    except KeyboardInterrupt:
        print("\nĐã tắt Mini Studio.")


if __name__ == "__main__":
    try:
        main()
    except Exception as e:  # noqa: BLE001
        import traceback
        traceback.print_exc()
        message_box(f"Mini Studio gặp lỗi khi khởi động:\n{e}\n\nChi tiết: {DATA_DIR / 'ministudio.log'}")
