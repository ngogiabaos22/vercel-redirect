# APTIS – giao diện Flatsome (UX Builder)

| Thư mục | Nội dung |
|---|---|
| `css/aptis.css`, `js/aptis.js` | CSS/JS dạng file thuần (để chỉnh/đọc) |
| `ux-block-css-js.html` | **Bản dán vào UX Block riêng tư**: `<style>` + `<script>` gộp sẵn |
| `trang-chu-aptis/` | 4 section trang chủ: hero · phiên bản · đề thi thử · kỹ năng |
| `speaking/ writing/ reading/ listening/` | 5 section mỗi trang con: hero · đề full · mẹo · tiêu chí & gợi ý · kỹ năng khác |
| `ux-builder-layout.txt` | Cách ghép section vào Flatsome |

## Tag plugin (đổi nếu bạn đặt tên khác)
| Vị trí | Shortcode |
|---|---|
| Trang chủ – General | `[tqp_category_landing category="aptis-general"]` |
| Trang chủ – Advanced | `[tqp_category_landing category="aptis-advanced"]` |
| Trang chủ – for Teens | `[tqp_category_landing category="aptis-for-teens"]` |
| Speaking | `[tqp_category_landing category="aptis-speaking"]` |
| Writing | `[tqp_category_landing category="aptis-writing"]` |
| Reading | `[tqp_category_landing category="aptis-reading"]` |
| Listening | `[tqp_category_landing category="aptis-listening"]` |

Mọi link chưa có đều để `#` (có comment `<!-- -->` ở đầu mỗi section). Icon dùng Font Awesome 6 (tự nạp bằng `@import` trong CSS).
Phần "Các dạng bài…" (Part 1–4) đã được bỏ khỏi 4 trang con.
