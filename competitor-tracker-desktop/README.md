# Competitor Tracker — ứng dụng desktop

Phần mềm cài trên máy tính (Windows / macOS) để theo dõi Facebook Page đối thủ:

- **Nhóm đối thủ**: tạo, đổi tên, xoá nhóm (mặc định Nhóm 1–3). Một Page thuộc được nhiều nhóm; xoá nhóm không xoá Page hay bài viết.
- **Danh sách Page**: thêm bằng link hoặc Page ID, nhập CSV, chọn nhiều nhóm, chuyển nhóm hàng loạt, bật/tắt theo dõi, thu thập ngay, cập nhật thông tin Page, xoá (giữ hoặc xoá dữ liệu).
- **Thu thập dữ liệu** qua Meta Graph API: caption, ngày giờ đăng, reactions, comments, shares, link ảnh. Có phân trang, checkpoint (dừng giữa chừng thì chạy tiếp), lưu lịch sử tương tác theo từng lần thu thập, chống trùng bài.
- **Tải ảnh theo điều kiện**: reaction ≥ / comment ≥ / share ≥ (để trống = 0), kết hợp VÀ / HOẶC, điều kiện riêng theo nhóm, lịch tải theo ngày, giới hạn số ảnh. Lưu trên máy, Google Drive, hoặc cả hai.
- **Google Sheets**: mỗi nhóm là 1 tab (+ tab "Tổng hợp"), gồm ngày đăng, Page, link bài viết, caption, link ảnh, reaction, comment, share và 2 cột để bạn tự điền (Trạng thái sử dụng, Ghi chú).
- **Dashboard**: lọc theo thời gian / nhóm / Page; số bài theo ngày, tương tác theo nhóm, xu hướng, top bài, top Page, tỷ lệ loại bài.
- **Lịch sử thu thập**: tiến độ từng Page, lỗi riêng từng Page, chạy lại từ checkpoint.
- Tự động chạy hằng ngày (mặc định 02:00 thu thập, 03:00 tải ảnh rồi đồng bộ Sheet, giờ Việt Nam), chạy ẩn dưới khay hệ thống, tuỳ chọn khởi động cùng máy tính.

Số liệu nào API không trả về được ghi **"Không khả dụng"**, không tự gán bằng 0.

Dữ liệu lưu trong một file SQLite trên máy (không cần cài PostgreSQL / Redis). Token Meta và khoá Google được mã hoá bằng cơ chế bảo mật của hệ điều hành và không bao giờ hiển thị lại trên giao diện.

---

## 1. Cài đặt phần mềm

**Cách 1 — tải file cài đặt đã đóng gói sẵn**

1. Vào tab **Actions** của repository trên GitHub → workflow **Competitor Tracker Desktop** → lần chạy mới nhất có dấu ✓.
2. Ở mục **Artifacts**, tải `CompetitorTracker-Windows` (file `.exe`) hoặc `CompetitorTracker-macOS` (file `.dmg`).
3. Windows: chạy `CompetitorTracker-Setup-x.y.z.exe`. Nếu Windows SmartScreen cảnh báo (do bản cài chưa ký số), bấm **More info → Run anyway**.
   macOS: mở file `.dmg`, kéo app vào Applications. Lần đầu mở: chuột phải vào app → **Open** (do chưa ký số Apple).

**Cách 2 — tự đóng gói trên máy của bạn** (cần Node.js 22+)

```bash
cd competitor-tracker-desktop
npm install
npm run dist:win     # trên Windows → release/CompetitorTracker-Setup-1.0.0.exe
npm run dist:mac     # trên macOS   → release/CompetitorTracker-1.0.0-<arch>.dmg
```

Chạy thử không cần đóng gói: `npm start`. Kiểm thử phần lõi: `npm test`.

## 2. Nhập các kết nối (Cài đặt)

### Kết nối Meta
1. Tạo App loại **Business** trên Meta for Developers, xác minh doanh nghiệp, xin quyền **Page Public Content Access** (PPCA).
2. Tạo Access Token dài hạn (khuyên dùng token của **System User** trong Business Manager).
3. Trong app: **Cài đặt → Kết nối Meta** → nhập App ID, phiên bản API (VD `v21.0`), dán Access Token → **Lưu**.
4. Dán link một Page đối thủ vào ô **Kiểm tra với 1 Page mẫu** để xem API trả về những trường nào.

### Google Sheets & Drive (cách đơn giản — không cần Google Cloud)
Vào **Cài đặt → Google Sheets & Drive** và làm theo 4 bước hiện trong app:
1. Bấm **Tạo Google Sheet mới** (hoặc mở file Sheet có sẵn).
2. Trong Sheet: **Tiện ích mở rộng → Apps Script**, xoá mã có sẵn. Trong app bấm **Sao chép mã**, dán vào Apps Script, bấm **Lưu**.
3. Bấm **Triển khai → Tùy chọn triển khai mới → Ứng dụng web**, đặt *Thực thi với tư cách: Tôi* và *Người có quyền truy cập: Bất kỳ ai* → **Triển khai** → cấp quyền (Nâng cao → Đi tới … → Cho phép).
4. Copy **URL ứng dụng web** (dạng `https://script.google.com/macros/s/…/exec`), dán vào app, bấm **Kết nối**.

App tự tạo thư mục **"Competitor Tracker - Ảnh"** trong Google Drive của bạn để lưu ảnh (xếp theo Page / tháng) và ghi dữ liệu vào các tab của Sheet. Link ứng dụng web được bảo vệ bằng khoá kết nối riêng nằm trong mã — không chia sẻ mã và link này.

Cách nâng cao (Service Account / OAuth qua Google Cloud) vẫn có trong app: bấm **Cách khác (nâng cao)**.

## 3. Nhập danh sách Page bằng CSV

Bấm **Tải file CSV mẫu** trong màn hình Danh sách Page. Định dạng:

| Cột | Nội dung |
|---|---|
| 1 | Link Page hoặc Page ID |
| 2 | Tên nhóm, nhiều nhóm cách nhau bởi `|` (nhóm chưa có sẽ được tạo) |
| 3 | Lấy bài từ ngày `YYYY-MM-DD` (tuỳ chọn) |

## 4. Lưu ý vận hành

- Lịch tự động chỉ chạy khi app đang mở hoặc đang chạy ẩn dưới khay hệ thống. Nếu máy tắt đúng giờ, bật "Chạy bù" để app chạy bù khi mở lại trong ngày.
- Không sửa cột **Post ID** trên Google Sheet (app dùng cột này để cập nhật đúng dòng, kể cả khi bạn sắp xếp lại Sheet). Cột "Trạng thái sử dụng" và "Ghi chú" không bị app ghi đè.
- Dữ liệu nằm trong thư mục dữ liệu của app (xem ở **Cài đặt → Chung → Mở thư mục dữ liệu**). Sao lưu file `competitor-tracker.db` và thư mục `images` định kỳ.
- Chỉ dùng Meta Graph API chính thức; không dùng cookie, tài khoản giả hay scraping. Ảnh và caption của đối thủ thuộc bản quyền của họ — dùng để tham khảo nội bộ, không đăng lại.

## Cấu trúc mã nguồn

```
src/main/       tiến trình chính (Electron)
  db.js         SQLite: nhóm, Page, bài viết, snapshot tương tác, ảnh, job, cài đặt
  meta.js       Meta Graph API (phân trang, retry, rate limit)
  engine.js     thu thập theo Page + checkpoint, tải ảnh, Drive, Google Sheets
  rules.js      điều kiện tải ảnh
  appsscript.js kết nối Google đơn giản qua Apps Script (mã dán vào Sheet)
  google.js     Google Drive / Sheets REST + xác thực (cách nâng cao)
  scheduler.js  lịch hằng ngày theo giờ Việt Nam
  ipc.js        lệnh từ giao diện
src/renderer/   giao diện (HTML/CSS/JS thuần)
test/           kiểm thử lõi (npm test)
```
