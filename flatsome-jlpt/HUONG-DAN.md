# Giao diện JLPT cho Flatsome (6 trang)

1. **UX Block**: Blocks → Thêm mới → tên/ID `jlpt-style` (riêng tư) → dán nội dung `ux-block-jlpt-style.txt` (CSS + JS + Font Awesome). Nếu ID khác, sửa `[block id="..."]` ở đầu mỗi trang.
2. **Trang**: tạo 6 trang, mở UX Builder → chế độ Code/Text → dán lần lượt `01…06`. Mỗi `[section label="..."]` là 1 phần, kéo thả sửa riêng được.
3. **Tag plugin** (ở nút "Làm ngay"): `[tqp_category_landing category="n5-doc-hieu"]`; các tag: `{n5..n1}-doc-hieu`, `-nghe-hieu`, `-tu-vung`, `-tu-vung-giao-tiep`, `-ngu-phap`. Đề Full gợi ý: `{n5..n1}-thi-full` (đang để comment cuối mỗi trang).
4. Link chưa có để `#` — sửa trong khối `[ux_html]`. Số đề/thời gian/điểm đạt sửa ở `build.js` rồi chạy `node build.js` (hoặc sửa trực tiếp trong builder).
