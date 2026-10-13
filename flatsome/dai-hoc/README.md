# Đại học – trang chính + trang môn học (Flatsome UX Builder + TestSpace)

Giao diện theo mẫu `…vercel.app/dai-hoc` (nền kem, chữ navy, điểm nhấn đỏ, tiêu đề serif).

## Đã đưa lên web
| Nội dung | Ở đâu | File |
|---|---|---|
| CSS + JS dùng chung | **UX Blocks › "Đại học – CSS + JS dùng chung"** (ID 671, slug `dai-hoc-assets`) | `block-dai-hoc-assets.txt` (sinh từ `style.css` + `script.js`) |
| Sidebar môn học (trang môn) | **UX Blocks › "Đại học – Sidebar môn học"** (ID 720, slug `dai-hoc-sidebar`) | `block-dai-hoc-sidebar.txt` (sinh từ `sidebar.json`) |
| Trang chính `/dai-hoc/` | Trang 423, template Page – Full Width | `page-dai-hoc.txt` |
| 6 trang môn `/dai-hoc/…/` | Trang 584, 582, 590, 588, 580, 586 | `mon-hoc/*.txt` |

Mỗi trang gọi `[block id="dai-hoc-assets"]` (nằm **trong cột đầu tiên** của section 1) → sửa CSS/JS một chỗ (trong UX Block) là áp dụng cho tất cả.

> ⚠️ **Không xoá phần tử "Block – Đại học – CSS + JS dùng chung"** trong UX Builder. Nếu mất dòng này, trang sẽ hiện chữ thô, không có giao diện và danh sách đề.
> Nên sửa trang bằng **UX Builder** hoặc tab **Văn bản (Text)** của Classic Editor; tab **Trực quan (Visual)** có thể tự xoá thẻ/thuộc tính.

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

## Lỗi URL `/dai-hoc/<môn>/` hiện trang chính (đã xử lý tạm)
Trên site có một quy tắc URL (rewrite rule) bắt **mọi** đường dẫn `/dai-hoc/...` về trang "Đại học" (423) – kiểm tra bằng oEmbed: `/dai-hoc/khong-ton-tai/` cũng trả về "Đại học", còn `/thi-thu-vstep/khong-ton-tai/` thì 404.
Nhiều khả năng do TestSpace (`[tqp_category_landing]` từng đặt trên trang Đại học) hoặc code thêm trong `functions.php`.

- **Xử lý tạm (đang chạy):** JS trong UX Block nhận ra URL `/dai-hoc/<slug>/` mà trang trả về lại là trang chính → lấy nội dung trang con qua `/wp-json/wp/v2/pages?slug=<slug>` (đã render sẵn shortcode TestSpace) và hiển thị thay thế, đổi luôn tiêu đề tab.
- **Sửa gốc:** WP Admin → *Cài đặt › Đường dẫn tĩnh* → bấm **Lưu thay đổi** (xóa cache rewrite). Nếu vẫn lỗi: xem cài đặt TestSpace có mục trang landing/portal trỏ tới "Đại học" không, hoặc tìm `add_rewrite_rule` có chữ `dai-hoc` trong `functions.php`. Khi đã sửa, trang con tự hiển thị trực tiếp, JS dự phòng không làm gì.

## Sidebar trang môn
Trang môn chia 2 cột: danh sách đề (8/12) + sidebar (4/12, dính khi cuộn) gồm **Các môn học bắt buộc** và **Các môn học HOT**. Trên mobile sidebar tự xuống cuối trang.
- Sửa danh sách: UX Blocks › "Đại học – Sidebar môn học" (hoặc sửa `sidebar.json` rồi `python3 build.py`).
- JS tự đánh dấu môn đang xem và hỏi WordPress (`/wp-json/wp/v2/pages?slug=…`) xem trang có tồn tại không: môn chưa có trang hiện **"Sắp có"** (không bấm được). Tạo trang con của "Đại học" đúng slug (vd `trac-nghiem-marketing-can-ban`) là tự thành link.

## Sửa gốc lỗi URL `/dai-hoc/*` (phía máy chủ)

Mọi URL `/dai-hoc/<bất-kỳ>/` đang được WordPress trả về trang "Đại học" do một quy tắc URL tuỳ chỉnh (không đọc/sửa được qua kết nối MCP vì nhóm công cụ đặc quyền đang tắt).
Cách sửa: dán nội dung `fix-dai-hoc-url.php` vào cuối `wp-content/themes/flatsome-child/functions.php`, rồi vào **Cài đặt → Đường dẫn tĩnh → Lưu thay đổi**.
Sau đó: trang con thật hiển thị đúng nội dung của nó, URL không tồn tại trả về 404 mặc định của WordPress, `/dai-hoc/` và các URL khác không đổi.
