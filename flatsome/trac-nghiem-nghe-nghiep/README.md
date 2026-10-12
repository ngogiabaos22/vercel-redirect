# Trang Trắc nghiệm nghề nghiệp – tracnghiemplus.com/trac-nghiem-nghe-nghiep/

| File | Dán vào đâu |
|---|---|
| `ux-builder.txt` | Trang `trac-nghiem-nghe-nghiep` → Edit with UX Builder → menu ⋯ → **Edit Content** → dán → Apply → Update |
| `style.css` | Nơi bạn muốn đặt CSS: Flatsome → Advanced → Custom CSS, Giao diện → Tùy biến → CSS bổ sung, hoặc `style.css` của child theme |
| `script.js` | Flatsome → Advanced → Global Settings → **Footer Scripts** (bọc trong `<script>...</script>`), hoặc enqueue trong child theme. Script chỉ chạy khi trang có `#tnn-hot` |
| `preview.html` | Chỉ để xem trước giao diện trên trình duyệt, không cần đưa lên site |

Ảnh dùng từ thư viện Media của tracnghiemplus.com:
- Hero: ảnh ID 611 `2026/10/trac-nghiem-nghe-nghiep.png`
- Ngành HOT: các ảnh môn học trong `2026/08/` (khai báo trong `script.js` và ảnh mặc định trong `ux-builder.txt`)

Mọi class đều có tiền tố `tnn-` nên CSS chỉ tác động lên trang này.
