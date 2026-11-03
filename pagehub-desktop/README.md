# PageHub (Windows)

App máy tính đăng bài tự động lên nhiều Fanpage. Ý tưởng bài lấy từ Google Sheet "Soi Page Đối Thủ", caption mới do bạn hoặc AI viết, ảnh lấy từ thư mục Drive của chính Fanpage. Mỗi Fanpage đăng bằng nick quản trị của nó qua Facebook Graph API, nên dòng "Người đăng" (chỉ admin thấy) ghi đúng tên nick đó.

## Cài đặt

1. Chạy `PageHub-Setup-<phiên bản>.exe`. Windows SmartScreen có thể cảnh báo vì bộ cài chưa ký số: bấm **More info → Run anyway**.
2. Mở PageHub từ Desktop hoặc Start Menu.
3. App phải đang mở, hoặc đang chạy ở khay hệ thống (bấm X sẽ thu nhỏ xuống khay), thì bài mới được đăng đúng giờ. Bật "Tự chạy khi bật máy" trong **Cài đặt kết nối › Lịch đăng và ứng dụng**.

## Thiết lập lần đầu (theo thứ tự trong app)

1. **Cài đặt kết nối › Google Sheet**: tạo hoặc mở Sheet, dán mã Apps Script, triển khai thành Ứng dụng web, dán URL vào app rồi bấm **Kết nối**. Dòng 1 của Sheet cần đủ 9 cột: `Ngày đăng`, `Page`, `Link bài viết`, `Caption`, `Link ảnh (Drive)`, `Reaction`, `Comment`, `Share`, `Trạng thái sử dụng`. App chỉ ghi vào cột `Trạng thái sử dụng`.
2. **Cài đặt kết nối › Facebook App**: tạo App loại Business ở developers.facebook.com, giữ chế độ **Development**, nhập App ID và App Secret, bấm **Lưu & kiểm tra**.
3. **Nick & Fanpage**: thêm từng nick làm Tester trong App Roles. Nick chấp nhận lời mời tại developers.facebook.com/requests, rồi đăng nhập qua nút trong app. Để thêm nick khác, dùng **Copy link đăng nhập** và mở bằng cửa sổ ẩn danh.
4. **Cài đặt kết nối › mục 2**: ghép mỗi Page trong cột "Page" của Sheet với Fanpage nhận ý tưởng, và dán link thư mục Drive chứa ảnh của chính từng Fanpage.
5. **Cài đặt kết nối › AI** (không bắt buộc): chọn Gemini, Groq hoặc OpenRouter (có gói miễn phí), hoặc OpenAI, Claude, rồi dán API key.

## Quy tắc khi lên lịch

- Caption phải khác bài gốc: độ giống tối đa 40% (đổi được thành 30% hoặc 50%).
- Ảnh chỉ được chọn trong thư mục Drive của chính Fanpage nhận bài. Ảnh ở cột "Link ảnh (Drive)" của Sheet không được đăng lại.
- Giờ đăng ngẫu nhiên trong khung giờ đã cài, có giới hạn số bài mỗi ngày và khoảng cách tối thiểu giữa hai bài, hoặc bạn chọn giờ cố định.
- Bật "Chỉ đăng bằng nick này" thì khi nick lỗi, bài được giữ lại cho tới khi nick đăng nhập lại.

## Dữ liệu và bảo mật

Dữ liệu nằm trong `%APPDATA%\PageHub\pagehub-data.json`. App Secret, API key và token Facebook được mã hóa bằng Windows DPAPI, chỉ tài khoản Windows của bạn giải mã được. App không lưu mật khẩu Facebook.

## Phát triển

```bash
npm install
npm start
```

Đóng gói bộ cài Windows (trên Linux cần `wine64` và `wine32`):

```bash
npm run dist:win
```
