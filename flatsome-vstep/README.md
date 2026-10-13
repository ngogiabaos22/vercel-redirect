# Thi thử VSTEP – giao diện Flatsome (tracnghiemplus.com)

Giao diện dùng UX Builder của Flatsome, lấy dữ liệu đề từ plugin **TestSpace – Thi Thử Online** (`traffic-quiz-pro`) qua shortcode `[tqp_category_landing category="..."]`. Cách làm giống trang TOEIC / Đại học hiện có: shortcode nằm trong cột ẩn `.vs-src`, JS đọc thẻ đề của plugin rồi dựng lại giao diện. Nút/link của từng đề trỏ thẳng tới bài test của plugin.

| File | Dán vào đâu |
|---|---|
| `1-ux-block-vstep-assets.txt` | **UX Blocks → Thêm mới**, slug `vstep-assets` (trạng thái Riêng tư, giống `toeic-assets`) |
| `2-trang-thi-thu-vstep.txt` | Trang **Thi thử VSTEP** (ID 11) – tab *Văn bản*, template **Page – Blank** |
| `3-trang-truong-mau-van-lang.txt` | Trang **Thi thử VSTEP Văn Lang** (ID 565) – làm mẫu cho các trường khác |

## Quy ước đặt tên đề trong TestSpace

JS tự phân loại theo **tên đề**:

- Kỹ năng: có chữ `Listening` / `Reading` / `Writing` / `Speaking` (hoặc `Nghe hiểu`, `Đọc hiểu`, `Kỹ năng viết`, `Kỹ năng nói`).
- Bậc: `B1` / `B2` / `C1` → tự hiện bộ lọc bậc.
- Số đề: `Đề 01`, `Đề số 3`, `Test 2`…
- Đề **không** có tên kỹ năng = đề **full 4 kỹ năng** (hiện dạng thẻ "BẮT ĐẦU THI").

Ví dụ: `VSTEP Văn Lang B2 - Listening - Đề 01`, `VSTEP Văn Lang B2 - Đề 01` (full).

## Trang chính `/thi-thu-vstep/`

1. Hero + 4 ô thông tin (số đề và số trường tự đếm).
2. Bảng cấu trúc đề + 3 bậc B1/B2/C1.
3. **Danh sách đề Thi thử VSTEP**: 4 tab 🎧 Listening · 📖 Reading · ✍️ Writing · 🎙️ Speaking (tab "Full 4 kỹ năng" chỉ hiện khi có đề full). Dữ liệu: category `VSTEP`.
4. Đề theo trường: lọc Tất cả / Miền Bắc / Miền Trung / Miền Nam. Mỗi trường là một thẻ `<a class="vs-school" data-region="bac|trung|nam" href="...">` – thêm/bớt trường bằng cách copy một thẻ.

## Trang từng trường

Copy nội dung file 3 sang trang của trường, rồi thay:

1. Tên trường / địa điểm / bậc trong khối "Giới thiệu", tiêu đề các khối.
2. `category="VSTEP Văn Lang"` → đúng tên danh mục đề của trường trong TestSpace.

| Trang | ID | Gợi ý category |
|---|---|---|
| Thi thử VSTEP Văn Lang | 565 | `VSTEP Văn Lang` |
| Thi thử VSTEP Đại Học Sư Phạm | 593 | `VSTEP Sư Phạm` |
| Thi thử VSTEP Đại Học Ngân Hàng | 595 | `VSTEP Ngân Hàng` |
| Thi thử VSTEP Đại Học Ngoại Ngữ (HN) | 599 | `VSTEP Ngoại Ngữ HN` |
| Thi thử VSTEP Đại Học Công Nghiệp | 601 | `VSTEP Công Nghiệp` |
| Thi thử VSTEP Đại Học Bách Khoa | 603 | `VSTEP Bách Khoa` |
| Thi thử VSTEP Đại học Cần Thơ | 605 | `VSTEP Cần Thơ` |
| Thi thử VSTEP Đại học Trà Vinh | 607 | `VSTEP Trà Vinh` |

> Trang 597 (`thi-thu-vstep-dai-hoc-ngan-hang-2`) đang trùng với 595 – nên xoá hoặc chuyển hướng.

## Lưu ý khi sửa code

Trong `<script>` / `<style>` của block **không** dùng ký tự "nhỏ hơn" và **không** để dòng trống (WordPress `wpautop` sẽ làm hỏng mã) – giống quy ước của `toeic-assets`.
