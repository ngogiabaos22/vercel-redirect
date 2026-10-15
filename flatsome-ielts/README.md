# IELTS Landing cho Flatsome (UX Builder)

## Cách dùng
1. **UX Block riêng tư**: tạo block "IELTS Assets", dán `ux-block/00-assets-block.html`, rồi dán `css/ielts.css` và `js/ielts.js` vào 2 chỗ đánh dấu. Chèn `[block id="..."]` đầu mỗi trang.
2. **Mỗi trang** (home, listening, reading, writing, speaking): tạo trang → UX Builder → chế độ Text/Source → dán lần lượt các file `pages/<trang>/NN-*.html` (mỗi file = 1 section, sửa độc lập).
3. Link `#` là chỗ để bạn điền URL thật.

## Tag đề (đổi tên tuỳ ý)
| Vị trí | Tag |
|---|---|
| Listening / Reading / Writing / Speaking | `[tqp_category_landing category="ielts-listening"]` (…`-reading`, `-writing`, `-speaking`) |
| Speaking – Recent Actual | `ielts-speaking-recent-actual-tests` |
| Trang chủ – tab bộ đề × kỹ năng | `ielts-{cambridge-17-19 \| recent-actual-tests \| forecast \| mini-test}-{listening \| reading \| writing \| speaking}` |
| Trang chủ – tab "Tất cả" | `ielts-all` |

Lưu ý: tag trang chủ render 17 danh sách cùng lúc (ẩn bằng CSS). Nếu nặng, bỏ bớt tab/kỹ năng bạn chưa có đề.
