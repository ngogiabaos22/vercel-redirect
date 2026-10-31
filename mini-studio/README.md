# Mini Studio – xưởng video whiteboard có giọng đọc, chạy 100% trên máy

Mini Studio là app chạy local để làm **video vẽ tay kiểu whiteboard** có giọng đọc: video giải thích kiến thức, kể chuyện, bài giảng, video ngắn TikTok/Reels/Shorts.

App miễn phí và chạy trên máy của bạn. Không cần tài khoản, không giới hạn số video. Các nguồn AI trên mạng (tạo ảnh, viết prompt, Edge TTS, Telegram) là tuỳ chọn; chỉ khi bạn bật chúng thì nội dung tương ứng mới được gửi đi.

App xây trên bộ vẽ của skill **srt-whiteboard-animation** (giấy phép MIT). Skill gốc đọc mốc thời gian từ một file SRT có sẵn. Mini Studio làm theo chiều ngược lại:

> **Kịch bản → giọng đọc → tự sinh mốc thời gian, phụ đề SRT và kịch bản vẽ (annotation.json) → bàn tay vẽ đúng lúc câu thoại được đọc.**

---

## Mới ở bản 1.1: tự động hoá tối đa

| Tính năng | Bạn làm gì | App tự làm gì |
|---|---|---|
| **Tạo video 1 chạm** | Dán kịch bản (văn xuôi cũng được), bấm **Tạo video 1 chạm** | Tự chia cảnh theo độ dài → AI viết prompt ảnh → tạo ảnh mọi cảnh → khoanh vùng vẽ → giọng đọc → vẽ, ghép video → báo Telegram |
| **Hàng loạt** | Dán nhiều kịch bản, ngăn cách bằng dòng `---` | Tạo từng dự án, chạy lần lượt (để chạy qua đêm được) |
| **Nhiều nguồn ảnh** | Bật/xếp thứ tự nguồn ở **Cài đặt → Nguồn ảnh** | Thử nguồn số 1, lỗi/hết hạn mức thì tự chuyển nguồn sau; thử lại có giãn cách; tạo song song nhiều cảnh |
| **AI viết chữ** | Chọn ở **Cài đặt → AI viết** | Pollinations (miễn phí, mặc định), Ollama (trên máy), Gemini hoặc API kiểu OpenAI |
| **Thông báo Telegram** | Điền Bot Token, bấm *Tìm Chat ID*, *Gửi thử* | Gửi tin + file video (≤ 50 MB, lớn hơn thì gửi ảnh bìa) khi xong; báo cả khi lỗi |
| **Mã hoá GPU** | Không cần làm gì | Tự dùng NVIDIA NVENC / Intel QSV / AMD AMF nếu máy có, không thì CPU |
| **Preset** | Bấm *Lưu preset* ở bước Xuất video | Áp khung hình, giọng, cài đặt xuất cho dự án mới / hàng loạt |

### Tạo ảnh miễn phí như thế nào?

Kết hợp nhiều nguồn trong **Cài đặt → Nguồn ảnh**; app tự chuyển khi một nguồn hết hạn mức:

1. **Pollinations.ai**: miễn phí, không cần đăng ký (bật sẵn). Có thể bị giới hạn tốc độ; để *Số cảnh tạo cùng lúc* = 1–2.
2. **Cloudflare Workers AI**: đăng ký miễn phí, có hạn mức hằng ngày (đủ vài trăm ảnh FLUX). Cần *Account ID* và *API token*.
3. **Google Gemini API** (aistudio.google.com) / **Hugging Face**: key miễn phí, hạn mức nhỏ. Dùng làm nguồn dự phòng.
4. **Máy có GPU**: Stable Diffusion WebUI/Forge hoặc ComfyUI. Miễn phí, không giới hạn.
5. **Trả phí khi cần**: OpenAI hoặc bất kỳ API kiểu OpenAI nào (Together, OpenRouter…).

Hạn mức miễn phí do từng nhà cung cấp quyết định và có thể thay đổi. Bấm **Thử** ở từng nguồn để kiểm tra nhanh.

### Thông báo Telegram

1. Trong Telegram, nhắn **@BotFather** → `/newbot` → nhận **Bot Token**.
2. Mở bot vừa tạo, bấm *Start*, nhắn một tin bất kỳ. Muốn báo vào nhóm thì thêm bot vào nhóm rồi nhắn trong nhóm.
3. **Cài đặt → Thông báo**: dán token → **Tìm Chat ID** → chọn → **Gửi thử**.

### Đóng gói thành phần mềm cho máy khác (không cần cài Python)

- **Bộ cài đặt dựng trên Linux/macOS** (không cần máy Windows): cài `nsis`, chạy `./packaging/build_installer.sh`. Ra file `dist/MiniStudio-<phiên bản>-Setup.exe`, đã gói sẵn Python cho Windows và mọi thư viện nên máy đích không cần mạng.
- **Tự build trên Windows**: chạy `packaging\build_windows.bat`. Kết quả:
  - `dist\MiniStudio\MiniStudio.exe`: chép cả thư mục sang máy khác là chạy.
  - `dist\MiniStudio-portable.zip`.
  - `dist\MiniStudio-Setup.exe`, nếu máy có Inno Setup 6.
- **Build trên GitHub** (không cần máy Windows): vào tab **Actions → Mini Studio – build Windows → Run workflow**, xong tải về ở mục *Artifacts*. Đẩy tag `mini-studio-v1.1.0` thì GitHub tự tạo bản phát hành (Release) kèm file cài đặt.
- Bản đóng gói đã kèm sẵn ffmpeg, Piper và Edge TTS. Dữ liệu (dự án, cài đặt, key) nằm trong thư mục `data` cạnh `MiniStudio.exe`; muốn sao lưu thì chép thư mục này.
- Mở app bằng shortcut **Mini Studio**: chỉ hiện cửa sổ Mini Studio, không có cửa sổ cmd. Giao diện mở trong cửa sổ riêng (Edge/Chrome chế độ app).
- **Đóng cửa sổ Mini Studio là app tự tắt** sau khoảng 10 giây. Nếu đang tạo video thì app chạy nền cho tới khi xong, rồi mới tắt; bật Telegram để nhận video.
- Cần xem log để báo lỗi: mở **Mini Studio (chế độ gỡ lỗi)** trong Start Menu, hoặc chạy `MiniStudio.bat debug`. Log của chế độ thường nằm ở `app\data\ministudio.log`.

---

## 4 bước chính

| Bước | Bạn làm gì | App tự làm gì |
|---|---|---|
| **1. Kịch bản** | Gõ kịch bản, chọn mẫu, hoặc nhờ AI cục bộ (Ollama) viết nháp | Tách thành Cảnh → Câu, ước tính thời lượng, xem trước dàn cảnh trực tiếp |
| **2. Storyboard** | Thả, dán hoặc tạo ảnh nét vẽ cho mỗi cảnh; chỉnh vùng, kéo đổi thứ tự | **Tự khoanh vùng** từng nhân vật/đồ vật, **tự gán câu thoại** cho vùng, xem thử chuyển động ngay trong trình duyệt |
| **3. Giọng đọc** | Chọn giọng (Piper offline / Edge TTS / tự thu âm) | Tạo giọng từng câu, cắt khoảng lặng, đo độ dài, cập nhật nhịp vẽ |
| **4. Xuất video** | Chọn chất lượng, kiểu phụ đề, nhạc nền | Vẽ từng cảnh, chèn phụ đề tiếng Việt, trộn giọng với nhạc nền (tự hạ nhạc khi đang đọc), xuất MP4 + SRT + mô tả có mốc chương + ảnh bìa |

### Tính năng thêm cho người làm video ngắn

- **Quản lý dự án**: nhân bản, xoá (có thùng rác), tự lưu, **lịch sử phiên bản** (tự lưu một bản mỗi lần tách cảnh).
- **4 khung hình**: 16:9 (YouTube), 9:16 (TikTok/Reels/Shorts), 1:1, 4:5.
- **Mẫu kịch bản**: giải thích kiến thức, kể chuyện, bài giảng.
- **Trợ lý AI cục bộ (Ollama, tuỳ chọn)**: viết kịch bản theo chủ đề, chuốt văn nói, gợi ý câu mở đầu (hook), tiêu đề và hashtag, dịch mô tả cảnh thành prompt ảnh.
- **Tạo ảnh**:
  - Stable Diffusion WebUI/Forge hoặc ComfyUI trên máy (tuỳ chọn).
  - Không có GPU: bấm "Chép prompt", dán vào công cụ tạo ảnh miễn phí, tải ảnh về rồi thả vào app.
- **Prompt ảnh đã có sẵn phong cách thống nhất**: nét chì, nền giấy kem, không có chữ. Đây là quy chuẩn hình ảnh của skill gốc.
- **Tự phát hiện vùng vẽ** bằng OpenCV, không cần AI. App cảnh báo "% nét vẽ được phủ" và tự thêm vùng nền cho đường đất, chân trời…
- **Xem thử tức thì** (phím Space): mô phỏng thứ tự vẽ và phụ đề, đồng bộ với giọng đọc, không cần render.
- **Render thử 480p** cho từng cảnh.
- **Bộ nhớ đệm render**: cảnh không đổi sẽ được dùng lại, không vẽ lại.
- **Tự thu âm từng câu** bằng micro ngay trong trình duyệt, hoặc tải file ghi âm có sẵn.
- **Phụ đề chèn vào hình** với 3 kiểu (hộp tối / giấy / viền chữ), 3 vị trí, chỉnh cỡ chữ; hiển thị đúng tiếng Việt có dấu.
- **Nhạc nền**: thư viện nhạc riêng, chỉnh âm lượng, tự hạ nhạc khi có giọng (ducking), tự fade vào/ra.
- **Đóng gói để đăng**: file `.srt`, mô tả YouTube có **mốc chương tự động**, **ảnh bìa** có tiêu đề.
- **Đổi bàn tay cầm bút**: dùng bản không chữ, bản gốc, hoặc tải PNG của riêng bạn. Đổi được cả màu giấy và font phụ đề.

---

## Cài đặt

Yêu cầu: **Python 3.10 trở lên**. Bạn không cần cài ffmpeg riêng vì gói `imageio-ffmpeg` đã kèm sẵn.

### Windows
1. Cài Python từ <https://www.python.org/downloads/>. Khi cài, nhớ tick **"Add python.exe to PATH"**.
2. Giải nén thư mục `mini-studio` và **bấm đúp `start.bat`**.
3. Lần đầu, app tự tạo môi trường riêng (`.venv`) và cài thư viện, mất khoảng 1–3 phút. Sau đó trình duyệt tự mở `http://127.0.0.1:8765`.

### macOS / Linux
```bash
cd mini-studio
./start.sh
```

### Chạy thủ công
```bash
python -m venv .venv
.venv/bin/pip install -r requirements.txt          # Windows: .venv\Scripts\pip ...
.venv/bin/pip install -r requirements-tts.txt      # tuỳ chọn: Piper + Edge TTS
.venv/bin/python app.py                            # --port 9000 để đổi cổng, --no-browser để không mở trình duyệt
```

---

## Giọng đọc

| Nguồn | Offline | Chất lượng | Cách bật |
|---|---|---|---|
| **Piper TTS** | ✅ | Khá, nhanh, nhẹ | `pip install piper-tts`, rồi vào **Cài đặt → Giọng đọc** và bấm *Tải* giọng `vi_VN-vais1000-medium` (~60 MB) |
| **Edge TTS** | ❌ cần mạng | Rất tự nhiên (Hoài My, Nam Minh) | `pip install edge-tts` |
| **eSpeak NG** | ✅ | Giọng máy | Cài từ github.com/espeak-ng/espeak-ng |
| **Tự thu âm** | ✅ | Giọng của bạn | Có sẵn: bấm 🎙 ở từng câu |
| **Ước lượng** | ✅ | Không có tiếng | Dùng để dựng storyboard trước khi có giọng |

Bạn có thể trộn các nguồn: phần lớn câu dùng Piper, vài câu quan trọng tự thu âm lại.

## AI cục bộ (tuỳ chọn, miễn phí)

- **Ollama** (<https://ollama.com>): cài xong chạy `ollama pull qwen2.5:7b`. App tự nhận ở `http://127.0.0.1:11434`.
- **Stable Diffusion WebUI / Forge**: chạy với tham số `--api`. App gọi `http://127.0.0.1:7860`.
- **ComfyUI**: điền tên checkpoint trong Cài đặt → AI cục bộ.

Không cài gì thêm thì app vẫn dùng đủ 4 bước: tự viết kịch bản, tự đưa ảnh vào, giọng Piper hoặc thu âm.

---

## Mẹo để video đẹp

1. **Mỗi cảnh một ý**, 2–4 câu, dài 15–30 giây.
2. **Mỗi câu ứng với một đối tượng** trong hình. App gán sẵn theo thứ tự, bạn chỉ cần sửa lại cho đúng mạch kể.
3. **Ảnh**: nền giấy trơn, các đối tượng tách nhau, không có chữ. Chỉ số "Phủ nét" nên ≥ 95%.
4. **Thứ tự vẽ** theo mạch kể: bối cảnh → nhân vật chính → hành động → phản ứng.
5. Dùng **Xem thử (Space)** để chỉnh nhanh. Chỉ **Render 480p** khi cần xem nét bút thật, và xuất 1080p ở bước cuối.
6. Muốn nét bút bám đường vẽ hơn: chọn **Đường bút → Bám nét**.

---

## Cấu trúc thư mục

```
mini-studio/
├── app.py                    # chạy app (--engine: bản .exe tự gọi bộ vẽ)
├── start.bat / start.sh      # khởi động 1 chạm
├── ministudio/               # máy chủ Python (chỉ dùng thư viện chuẩn + OpenCV/NumPy/Pillow)
│   ├── server.py             # HTTP + API
│   ├── store.py              # lưu dự án dạng JSON, phiên bản
│   ├── scriptsplit.py        # tách kịch bản, mẫu
│   ├── timeline.py           # giọng → mốc thời gian → annotation.json + SRT
│   ├── regions.py            # tự phát hiện vùng vẽ
│   ├── tts.py                # Piper / Edge / eSpeak
│   ├── audio.py              # ffmpeg, cắt lặng, trộn nhạc, ducking
│   ├── render.py             # gọi bộ vẽ, ghép cảnh, phụ đề, tiếng
│   ├── subtitles.py          # vẽ phụ đề tiếng Việt bằng Pillow
│   ├── extras.py             # ảnh bìa, mô tả + mốc chương
│   ├── integrations.py       # AI viết chữ (Ollama / Pollinations / Gemini / OpenAI)
│   ├── imagegen.py           # nhiều nguồn tạo ảnh, tự chuyển nguồn khi lỗi
│   ├── pipeline.py           # luồng 1 chạm, tạo ảnh hàng loạt, giọng đọc, preset
│   ├── scenes.py             # lưu ảnh, khoanh vùng, gán câu
│   └── notify.py             # thông báo Telegram
├── engine/                   # bộ vẽ whiteboard (từ skill srt-whiteboard-animation, MIT)
├── web/                      # giao diện (HTML/CSS/JS thuần, chạy offline)
├── packaging/                # đóng gói .exe (PyInstaller), bộ cài đặt (Inno Setup)
└── data/
    ├── projects/<dự án>/     # project.json, images/, audio/, renders/, exports/
    ├── voices/               # giọng Piper đã tải
    ├── music/                # thư viện nhạc nền
    └── hands/                # bàn tay tự thêm
```

**Sao lưu** = copy thư mục `data/`. Mỗi dự án là một thư mục riêng, có thể mang sang máy khác.

## Kiến trúc tóm tắt

```
Trình duyệt (web/)  ⇄  Python http.server (ministudio/)  →  engine/render_stream_whiteboard.py (mỗi cảnh)
                                    │                                         │
                                    ├── TTS (Piper/Edge) → audio/*.wav        └── MP4 không tiếng
                                    └── ffmpeg (imageio-ffmpeg): ghép cảnh + phụ đề + giọng + nhạc → exports/*.mp4
```

## Bản quyền và ghi công

- Bộ vẽ trong `engine/` lấy từ **srt-whiteboard-animation** của "江哥是老登啊", giấy phép MIT (`engine/LICENSE-srt-whiteboard-animation`). Bản gốc đã được thêm tuỳ chọn `--hand-height`, `--canvas-hex`, dòng báo tiến độ và tự tìm ffmpeg từ `imageio-ffmpeg`.
- `drawing-hand-clean.png` là bản đã xoá chữ trên thân bút của ảnh bàn tay gốc.
- Piper TTS (GPL-3.0), edge-tts (LGPL-3.0), OpenCV (Apache-2.0), FFmpeg (LGPL/GPL qua imageio-ffmpeg) là các gói bên ngoài, cài bằng pip.
- Lưu ý: Edge TTS gọi dịch vụ đọc của Microsoft qua mạng. Hãy xem điều khoản sử dụng trước khi dùng cho mục đích thương mại.
