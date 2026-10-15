# MBTI – 3 UX Block (tracnghiemplus.com/test-mbti, trang ID 510)

| UX Block (slug) | ID | Nội dung | Có cần sửa? |
|---|---|---|---|
| `mbti-css` | 864 | CSS giao diện | Khi đổi màu/cỡ chữ |
| `mbti-data` | 863 | Chữ & dữ liệu: 16 tính cách, popup, 4 khía cạnh, các khối chữ | Khi đổi nội dung |
| `mbti-script` | 865 | Script dựng giao diện | Không |

Trang nhúng bằng `[block id="mbti-css"]`, `[block id="mbti-data"]` (đầu trang) và `[block id="mbti-script"]` (cuối trang).
Cả ba block đều ở chế độ **private** (giống các block CSS/JS khác của site).

Quy tắc khi sửa trong UX Builder (WordPress tự chèn `<p>`/`<br>` vào nội dung):
- Không chèn dòng trống trong CSS/JS; CSS mỗi quy tắc một dòng.
- Không dùng ký tự nhỏ hơn trong CSS và dữ liệu. Trong dữ liệu không dùng dấu ngoặc kép thẳng bên trong chuỗi.
- Xuống dòng trong tiêu đề 4 khía cạnh dùng `\n`.

Mã nguồn: `build.py` sinh `out/*.txt` (nội dung block) từ `../mbti.js` + `../mbti.css`; `data.js` và `main.js` là bản đọc được.
