# Trang Test Holland – Flatsome UX Builder

Trang: https://tracnghiemplus.com/test-holland/ (page ID 512, template **Page - Full Width** / `page-blank.php`).

Chỉ làm giao diện. Mọi nút làm bài trỏ tới bài thi có sẵn:
https://tracnghiemplus.com/bai-thi/test-holland/

## File
| File | Dùng ở đâu |
|---|---|
| `page-content.txt` | Toàn bộ nội dung trang (khối CSS + 8 section). Đã đưa lên trang 512. |
| `ux-builder.txt` | Chỉ phần kéo thả (8 section), không kèm CSS. |
| `style.css` | CSS riêng (tiền tố `hld-`). Có thể chuyển sang *Flatsome > Advanced > Custom CSS*, khi đó xóa khối `[ux_html label="CSS trang Test Holland…"]` đầu trang. |
| `preview.html` | Bản xem thử offline (mô phỏng Flatsome + wpautop). |

Không cần JS: FAQ dùng `<details>/<summary>`.

## Các section
1. Hero (lục giác RIASEC, nút "Bắt đầu test ngay")
2. Dải chạy chữ
3. Về bài test (`#about`)
4. 6 nhóm RIASEC (`#types`)
5. Cách làm bài
6. Làm bài test (`#test`): thẻ giới thiệu + nút sang bài thi, kết quả mẫu (minh họa)
7. FAQ (`#faq`)
8. CTA: Holland + MBTI
