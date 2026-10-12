# Trang Trắc nghiệm nghề nghiệp (giao diện "La Bàn Nghề Nghiệp")
URL: https://tracnghiemplus.com/trac-nghiem-nghe-nghiep/ (page ID 609, template "Page - Full Width")

| File | Dùng để |
|---|---|
| `page-content.txt` | Nội dung **đang chạy trên site**: khối CSS ở đầu + code UX Builder + khối JS "Ngành HOT" (JS phần Khám phá/FAQ nạp từ `functions.php` của theme con) |
| `ux-builder.txt` | Chỉ phần kéo thả (không CSS/JS) – dùng nếu chuyển CSS/JS ra ngoài |
| `style.css` | CSS riêng – có thể dán vào Flatsome → Advanced → Custom CSS |
| `script.js` | JS riêng – có thể dán vào Flatsome → Advanced → Global Settings → Footer Scripts (bọc `<script>…</script>`) |
| `hot.js` / `hot.css` | Riêng phần "Ngành HOT" (đã nhúng trong trang; `style.css`/`script.js` là bản gộp đầy đủ) |
| `preview.html` | Xem trước offline (mô phỏng cả wpautop của WordPress) |

Nếu chuyển CSS/JS ra Flatsome → Advanced: dán `style.css` + `script.js` vào đó rồi **xóa 2 khối HTML "CSS trang" và "JS trang"** trong UX Builder để không bị nạp 2 lần.

Link bài test: Holland `https://tracnghiemplus.com/bai-thi/test-holland/`, MBTI `https://tracnghiemplus.com/bai-thi/test-mbti-mien-phi/`.
Mọi class có tiền tố `lbn-` nên không ảnh hưởng trang khác.

## Lưu ý wpautop
WordPress tự chèn `<p>`/`<br>` vào HTML trong khối `[ux_html]`. Markup đã được viết để chịu được việc này (dùng `div` thay `span` ở các vị trí khối, CSS ẩn `br` và `p` rỗng trong `.lbn`). Khi sửa trong UX Builder, giữ nguyên các thẻ `div` đó.

## Ngành HOT
Dữ liệu 8 ngành (mã Holland, xu hướng MBTI 4 cặp, nhóm MBTI tiêu biểu, tố chất, vị trí, ảnh) nằm trong biến `careers` của `hot.js`. Thêm ngành: thêm 1 nút `data-career="..."` trong khối "Ngành HOT – bảng tương tác" và 1 mục cùng tên trong `careers`. JS không dùng chuỗi HTML để tránh wpautop làm hỏng.
