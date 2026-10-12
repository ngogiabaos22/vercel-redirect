# Trang Trắc nghiệm nghề nghiệp (giao diện "La Bàn Nghề Nghiệp")
URL: https://tracnghiemplus.com/trac-nghiem-nghe-nghiep/ (page ID 609, template "Page - Full Width")

| File | Dùng để |
|---|---|
| `page-content.txt` | Nội dung **đang chạy trên site**: khối CSS ở đầu + code UX Builder (JS nạp từ `functions.php` của theme con) |
| `ux-builder.txt` | Chỉ phần kéo thả (không CSS/JS) – dùng nếu chuyển CSS/JS ra ngoài |
| `style.css` | CSS riêng – có thể dán vào Flatsome → Advanced → Custom CSS |
| `script.js` | JS riêng – có thể dán vào Flatsome → Advanced → Global Settings → Footer Scripts (bọc `<script>…</script>`) |
| `preview.html` | Xem trước offline |

Nếu chuyển CSS/JS ra Flatsome → Advanced: dán `style.css` + `script.js` vào đó rồi **xóa 2 khối HTML "CSS trang" và "JS trang"** trong UX Builder để không bị nạp 2 lần.

Link bài test: Holland `https://tracnghiemplus.com/bai-thi/test-holland/`, MBTI `https://tracnghiemplus.com/bai-thi/test-mbti-mien-phi/`.
Mọi class có tiền tố `lbn-` nên không ảnh hưởng trang khác.

## Lưu ý wpautop
WordPress tự chèn `<p>`/`<br>` vào HTML trong khối `[ux_html]`. Markup đã được viết để chịu được việc này (dùng `div` thay `span` ở các vị trí khối, CSS ẩn `br` và `p` rỗng trong `.lbn`). Khi sửa trong UX Builder, giữ nguyên các thẻ `div` đó.
