# Giao diện Thi thử HSK – tracnghiemplus.com (Flatsome)

Bản sao của code đang chạy trên site. Sửa trên site thì nên cập nhật lại file ở đây.

| File | Nơi dùng trên site |
|---|---|
| `00-ux-block-hsk-assets.txt` | UX Block riêng tư **"HSK – CSS + JS dùng chung"** (ID 957, slug `hsk-assets`) |
| `trang-chinh_thi-thu-hsk.txt` | Trang `/thi-thu-hsk/` (ID 12) |
| `thi-thu-hsk-1.txt` … `thi-thu-hsk-6.txt` | Trang `/thi-thu-hsk-1/` … `/thi-thu-hsk-6/` (ID 516, 518, 523, 520, 525, 527) |

Mỗi trang dùng template `page-blank.php`. Mỗi section Flatsome có `label` riêng (vd. "HSK3 - 2. Bộ đề thi thử") để dễ tìm trong UX Builder.
CSS + JS được nạp bằng `[block id="hsk-assets"]` ở section đầu mỗi trang.

## Cách đề thi được nối vào trang

Giống trang JLPT: mỗi trang có một cột ẩn `hs-src` chứa

```
[tqp_category_landing category="HSK"]
```

JS đọc các thẻ đề `.ts-portal-card` mà shortcode xuất ra, phân loại theo **tiêu đề đề thi**:

- **Cấp độ**: có `HSK 1` … `HSK 6` trong tiêu đề.
- **Số đề**: `Đề 1`, `Đề 2`, … → gom thành thẻ "Đề N thi thử HSK X".
- **Kỹ năng**: `Đọc hiểu` / `Phần đọc`, `Nghe hiểu` / `Phần nghe`, `Từ vựng`, `Ngữ pháp`, `Viết` / `Phần viết`.
- Tiêu đề không chứa kỹ năng nào → coi là **đề Full** (nút "Thi Full đề").

Ví dụ đặt tên đề trong plugin (đưa vào chuyên mục **HSK**):

```
Đề 1 thi thử HSK 1 - Đọc hiểu
Đề 1 thi thử HSK 1 - Nghe hiểu
Đề 1 thi thử HSK 1 - Từ vựng
Đề 1 thi thử HSK 1 - Ngữ pháp
Đề 1 thi thử HSK 1            ← đề Full
Đề 1 thi thử HSK 3 - Phần viết
```

Kỹ năng nào chưa có đề thì **ẩn hẳn** (cả trong thẻ đề lẫn khối "Luyện theo kỹ năng");
cấp chưa có đề nào sẽ hiện "Đề HSK X đang được cập nhật".
Muốn nhận thêm từ khóa kỹ năng: sửa biến `SK` trong JS của UX Block.

## Lưu ý khi sửa HTML

WordPress tự chèn `<p>` quanh thẻ inline (`<span>`, `<a>`, `<small>`, `<b>`) đứng đầu khối.
Vì vậy phần tử đầu khối luôn là `<div>`; nút/link đứng một mình được bọc trong `<div>`
(vd. `<div class="hs-skill__go"><a …>Test ngay</a></div>`).
