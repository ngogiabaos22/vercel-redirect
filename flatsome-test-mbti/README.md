# Test MBTI cho Flatsome (UX Builder)

| File | Dán vào đâu |
|---|---|
| `1-custom-css.css` | Flatsome → Advanced → Custom CSS → All screens |
| `2-footer-scripts.html` | Flatsome → Advanced → Global Settings → Footer Scripts |
| `3-ux-builder-shortcode.txt` | Trang mới → tab **Văn bản/Code** → dán → mở UX Builder |

Vì sao tách 3 file: WordPress (wpautop) tự chèn `<br />`, `<p>`, `</p>` vào nội dung trang.
Trong file 3 chỉ còn element Flatsome (Section, Row, Col, Text, Button, Accordion, Scroll To)
và các thẻ `<span data-mbti="...">` rỗng — wpautop không làm hỏng được. Phần HTML phức tạp
(hình minh họa, tab 16 tính cách, 4 khía cạnh, popup bài test) do script ở file 2 dựng vào các span đó.

- Nút Flatsome nào có link `#lam-bai-test` đều mở bài test. Link `/test-mbti/#lam-bai-test` mở bài test ngay khi vào trang.
- Không xoá các element HTML có nhãn "MBTI • …" và không xoá class `mbti` của Section.

## Bản đang chạy trên tracnghiemplus.com/test-mbti (trang ID 510)

Nội dung trang = `page-content.txt`: các element Flatsome + 1 thẻ `<link>` (mbti.css) và 1 thẻ `<script>` (mbti.js)
tải qua jsDelivr, ghim theo commit `294bc41`. Sửa CSS/JS thì sửa `mbti.css` / `mbti.js`, commit, rồi đổi mã commit trong 2 link.
Muốn bỏ phụ thuộc jsDelivr: dán `1-custom-css.css` vào Custom CSS, `2-footer-scripts.html` vào Footer Scripts, rồi xoá 2 element "MBTI • CSS/Script (jsDelivr)".
