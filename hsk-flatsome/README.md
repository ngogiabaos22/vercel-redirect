# Giao diện Thi thử HSK cho Flatsome (UX Builder)

## Cấu trúc
```
00-ux-block-css-js.html     CSS + JS + Font Awesome  -> dán vào UX Block riêng tư
trang-chinh/                Trang /de-thi-hsk/  (5 section)
hsk1/ ... hsk6/             Trang /thi-thu-hsk-N/ (4 section mỗi trang)
```
Mỗi file = 1 section. Dán lần lượt theo số thứ tự vào trang (chế độ **Text** của UX Builder / editor).

## Cách cài
1. Flatsome → **UX Blocks → Add New** (đặt tên "HSK Assets", để riêng tư). Thêm element **HTML**, dán toàn bộ `00-ux-block-css-js.html`. Ghi lại ID block.
2. Ở đầu mỗi trang HSK, thêm `[block id="hsk-assets"]` (slug hoặc ID của UX Block).
   Hoặc chèn block vào mọi trang HSK bằng *Flatsome → Theme Options → Header / Footer scripts* nếu không muốn thêm tay.
3. Dán các file section vào từng trang. Mỗi file đã bọc `[section][row][col][ux_html]`.
   - Các thẻ đầu khối dùng `<div>` (không dùng `<span>`) và **không có dòng trống** để WordPress không chèn `<p>` thừa.
4. Trang chính: `/de-thi-hsk/`. Trang con: `/thi-thu-hsk-1/` … `/thi-thu-hsk-6/` (đổi trong script nếu slug khác).

## Quy ước tag/category cho plugin đề
Đề thứ `i` của cấp `n`:

| Mục | Category |
|---|---|
| Đọc hiểu | `hskN-deI-doc` |
| Nghe hiểu | `hskN-deI-nghe` |
| Từ vựng | `hskN-deI-tu-vung` |
| Ngữ pháp | `hskN-deI-ngu-phap` |
| Viết (HSK 3-6) | `hskN-deI-viet` |
| Thi Full đề | `hskN-deI-full` |
| Cả cấp (tab trang chính) | `hskN` |

Ví dụ: `[tqp_category_landing category="hsk1-de1-doc"]`.
Mỗi trang cấp độ hiển thị sẵn 5 đề. Muốn thêm đề: copy một khối `<div class="hs-card">…</div>` rồi đổi số `deI`.

## Tự ẩn kỹ năng chưa có đề
`hs-skill` chứa shortcode trong `.hs-skill__slot`. JS kiểm tra kết quả shortcode:
không có link / rỗng / có chữ "chưa có, không có…" → ẩn dòng đó; thẻ đề nào không còn kỹ năng nào → ẩn cả thẻ.

## Cần chỉnh theo plugin của bạn
- Mình chưa thấy HTML thật mà `[tqp_category_landing]` xuất ra, nên CSS đang style **mọi thẻ `<a>`** trong slot thành nút.
  Nếu plugin xuất danh sách nhiều đề, đổi `renameLinks:false` trong JS để giữ nguyên tất cả link.
- Link “Từ vựng HSK N” trên hero đang là `#`: thay bằng trang từ vựng.
