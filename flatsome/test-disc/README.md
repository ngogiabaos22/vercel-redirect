# Trang Test DISC – Flatsome UX Builder

Trang: https://tracnghiemplus.com/test-disc/ (page ID 504, template **Page - Full Width** / `page-blank.php`).
Giao diện chuyển từ mẫu `trac-nghiem-disc-ui` (màu cam/kem, font DM Sans + Space Grotesk). Chỉ làm giao diện, không làm phần trắc nghiệm:
mọi nút làm bài trỏ tới **https://tracnghiemplus.com/bai-thi/test-disc/** (đổi link này nếu bài thi DISC trên TestSpace có đường dẫn khác – có 4 chỗ).

## File
| File | Dùng ở đâu |
|---|---|
| `page-content.txt` | Toàn bộ nội dung trang (khối CSS + 6 section). Đã đưa lên trang 504. |
| `ux-builder.txt` | Chỉ phần kéo thả (6 section), không kèm CSS. |
| `style.css` | CSS riêng (tiền tố `dsc-`). Có thể chuyển sang *Flatsome > Advanced > Custom CSS*, khi đó xóa khối `[ux_html label="CSS trang Test DISC…"]` đầu trang. |
| `preview.html` | Bản xem thử offline (mô phỏng Flatsome + wpautop). |

Không cần JS: FAQ dùng `<details>/<summary>`. Popup làm bài (`app.js`), header/footer của mẫu được bỏ (dùng header/footer của web).

## Các section
1. Hero (minh họa DISC, nút "Làm bài test DISC")
2. Bắt đầu bài test (`#test-picker`): 3 thẻ – Mô hình DISC · **Bài test chính** · Hướng dẫn; dải 4 nhóm D/I/S/C
3. DISC là gì (`#about`): 4 thẻ màu
4. Hướng dẫn làm bài (`#guide`, nền tối)
5. FAQ (`#faq`)
6. CTA
