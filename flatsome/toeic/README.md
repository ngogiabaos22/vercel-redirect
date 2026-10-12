# TOEIC – Thi thử TOEIC / Listening / Reading (Flatsome UX Builder + TestSpace)

Giao diện theo mẫu `…vercel.app/thi-thu-toeic` (nền kem ô lưới, xanh dương, tiêu đề serif Lora).

| Nội dung | Ở đâu trên web | File |
|---|---|---|
| CSS + JS dùng chung | **UX Blocks › "TOEIC – CSS + JS dùng chung"** (ID 728, slug `toeic-assets`) | `block-toeic-assets.txt` (sinh từ `style.css` + `script.js`) |
| `/thi-thu-toeic/` | Trang 8 – hero + minh họa phòng thi, cấu trúc 7 Part, kho đề có tab Listening/Reading/Tất cả | `page-thi-thu-toeic.txt` |
| `/toeic-listening/` | Trang 535 – 4 Part Listening + đề Listening | `page-toeic-listening.txt` |
| `/toeic-reading/` | Trang 537 – 3 Part Reading + đề Reading | `page-toeic-reading.txt` |

Tất cả dùng template **Page – Full Width**. `[block id="toeic-assets"]` nằm trong cột đầu của section 1 – **không xoá**.

## Dữ liệu TestSpace
Mỗi trang có cột `class="tc-src"` chứa `[tqp_category_landing category="TOEIC"]`. JS đọc thẻ `.ts-portal-card` rồi dựng lại:
- Tiêu đề có "Listening" → đề Listening (Part 1–4), có "Reading" → đề Reading (Part 5–7); số đề lấy từ "Đề N".
- Số câu / thời gian lấy từ dữ liệu thật; thống kê (tổng đề, số đề Listening/Reading) tự đếm.
- Nút "Bắt đầu thi ngay" / "Thi thử … ngay" trỏ tới đề đầu tiên tương ứng.

Hiện chỉ chuyên mục `TOEIC` có dữ liệu (19 đề: Listening 1–10, Reading 1–9). Các chuyên mục `ETS 2026`, `ETS 2025`, `ETS`, `TOEIC Listening`, `TOEIC Reading` đang trống nên các trang ETS chưa làm.

Không dùng số liệu bịa (lượt thi, số thí sinh, độ khó): mọi con số là cấu trúc đề TOEIC chuẩn hoặc đếm từ TestSpace. Các nút "Part 1…7" trong thẻ đề chỉ là nhãn (đề hiện là đề trọn kỹ năng, chưa có đề theo từng Part).

Sửa nội dung: `python3 build.py` sinh lại các file `.txt`.
