# -*- mode: python ; coding: utf-8 -*-
# Đóng gói Mini Studio thành thư mục chạy độc lập (không cần cài Python):
#     pyinstaller packaging/ministudio.spec --noconfirm
# Kết quả: dist/MiniStudio/MiniStudio.exe (+ thư mục _internal). Dữ liệu người dùng nằm ở dist/MiniStudio/data.
from pathlib import Path

from PyInstaller.utils.hooks import collect_all, collect_data_files

ROOT = Path(SPECPATH).parent  # noqa: F821 (SPECPATH do PyInstaller cung cấp)

datas = [
    (str(ROOT / "web"), "web"),
    (str(ROOT / "engine"), "engine"),
]
binaries = []
# bộ vẽ chạy dưới dạng script (.py) nên khai báo các thư viện nó dùng
hiddenimports = ["argparse", "datetime", "dataclasses", "math", "json", "typing",
                 "stream_render", "render_stream_whiteboard", "cv2", "numpy", "PIL", "imageio_ffmpeg"]

datas += collect_data_files("imageio_ffmpeg")  # kèm sẵn ffmpeg, máy đích không cần cài

# giọng đọc tự động (tuỳ chọn) – có cài thì đóng gói luôn
for pkg in ("piper", "onnxruntime", "edge_tts", "aiohttp", "certifi"):
    try:
        d, b, h = collect_all(pkg)
        datas += d
        binaries += b
        hiddenimports += h
    except Exception:
        pass

a = Analysis(  # noqa: F821
    [str(ROOT / "app.py")],
    pathex=[str(ROOT), str(ROOT / "engine")],
    binaries=binaries,
    datas=datas,
    hiddenimports=hiddenimports,
    excludes=["tkinter", "matplotlib", "IPython", "pytest"],
    noarchive=False,
)
pyz = PYZ(a.pure)  # noqa: F821

exe = EXE(  # noqa: F821
    pyz,
    a.scripts,
    [],
    exclude_binaries=True,
    name="MiniStudio",
    icon=str(ROOT / "packaging" / "ministudio.ico"),
    console=True,  # cửa sổ console hiện trạng thái; đóng cửa sổ = tắt app
    upx=False,
)

coll = COLLECT(  # noqa: F821
    exe,
    a.binaries,
    a.datas,
    strip=False,
    upx=False,
    name="MiniStudio",
)
