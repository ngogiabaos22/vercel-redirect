# Đại học – trang chính + trang môn học (Flatsome UX Builder + TestSpace)

Giao diện theo mẫu `…vercel.app/dai-hoc` (nền kem, chữ navy, điểm nhấn đỏ, tiêu đề serif).

## Đã đưa lên web
| Nội dung | Ở đâu | File |
|---|---|---|
| CSS + JS dùng chung | **UX Blocks › "Đại học – CSS + JS dùng chung"** (ID 671, slug `dai-hoc-assets`) | `block-dai-hoc-assets.txt` (sinh từ `style.css` + `script.js`) |
| Trang chính `/dai-hoc/` | Trang 423, template Page – Full Width | `page-dai-hoc.txt` |
| 6 trang môn `/dai-hoc/…/` | Trang 584, 582, 590, 588, 580, 586 | `mon-hoc/*.txt` |

Mỗi trang bắt đầu bằng `[block id="dai-hoc-assets"]` → sửa CSS/JS một chỗ (trong UX Block) là áp dụng cho tất cả.

## Kết nối dữ liệu TestSpace
Trang môn dùng shortcode của plugin:
```
[col span="12" class="dh-src dh-src-mon"]    [tqp_category_landing category="Tư tưởng Hồ Chí Minh"]    [/col]
[col span="12" class="dh-src dh-src-truong"] [tqp_category_landing category="Tư tưởng Hồ Chí Minh - Đề các trường"] [/col]
```
JS đọc các thẻ `.ts-portal-card` mà plugin xuất ra rồi dựng lại theo giao diện mẫu:
- Tiêu đề đề thi có **"Chương N"** → vào danh sách *theo chương* (sắp theo số chương); còn lại → *đề tổng hợp*.
- Chuyên mục `… - Đề các trường` → *Nhóm đề 2*; mã trường lấy từ chữ in hoa cuối tiêu đề (vd "… UFM"). Chuyên mục trống thì Nhóm đề 2 và nút "Đề của các trường" tự ẩn.
- Thống kê (tổng đề, câu hỏi, đề theo chương, đề các trường) và nút "Làm đề số 1 ngay" tính từ dữ liệu thật.

| Trang | Chuyên mục TestSpace (môn) | Chuyên mục đề các trường |
|---|---|---|
| Tư tưởng Hồ Chí Minh | `Tư tưởng Hồ Chí Minh` | `Tư tưởng Hồ Chí Minh - Đề các trường` |
| Triết học Mác – Lênin | `Mác Lênin` | `Mác Lênin - Đề các trường` |
| Kinh tế chính trị | `Kinh tế chính trị` | `Kinh tế chính trị - Đề các trường` |
| Lịch sử Đảng | `Lịch sử Đảng` | `Lịch sử Đảng - Đề các trường` |
| Pháp luật đại cương | `Pháp luật đại cương` | `Pháp luật đại cương - Đề các trường` |
| Tin học đại cương | `Tin học đại cương` | `Tin học đại cương - Đề các trường` |

Đổi tên chuyên mục: mở trang trong UX Builder → sửa shortcode trong cột `dh-src-…`.

## Thêm môn mới
1. Tạo trang con của "Đại học", nhân bản một trang môn có sẵn (hoặc dán `mon-hoc/*.txt`), sửa tên môn, mô tả và `category`.
2. Trong `subjects.json` thêm `page_id` cho môn đó rồi chạy `python3 build.py`, dán lại `page-dai-hoc.txt` vào trang 423 (hoặc trong UX Builder sửa thẻ môn "Sắp có" thành thẻ có link).

`preview-dai-hoc.html`, `preview-mon-hoc.html`: bản xem thử offline (mô phỏng Flatsome + wpautop, dữ liệu mẫu 2 đề TTHCM).
