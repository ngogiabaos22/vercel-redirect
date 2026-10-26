=== TNP IQ Test ===
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 1.1.0

Bài test IQ dạng ma trận hình cho WordPress: quản trị câu hỏi, tính giờ, hỏi tuổi ở bước cuối để quy đổi IQ theo độ tuổi, lưu và thống kê kết quả.

== Cài đặt ==
1. Plugin → Cài mới → Tải plugin lên → chọn tnp-iq-test.zip → Kích hoạt.
2. Menu "Test IQ" → Cài đặt → bấm "Thêm 3 câu hỏi mẫu" để xem thử (xoá đi khi không cần).
3. Chèn shortcode [tnp_iq_test] vào trang làm bài (dùng toàn bộ câu hỏi).
   Nhiều bài test riêng: tạo Bộ đề (Test IQ → Bộ đề), gán câu hỏi vào bộ đề, rồi dùng [tnp_iq_test test="slug-bo-de"].
   Tuỳ chọn: count="20" (số câu), time="15" (số phút tối đa; đồng hồ luôn đếm xuôi).

== Thêm câu hỏi ==
Test IQ → Thêm câu hỏi:
* Tiêu đề = đề bài (ví dụ: Hình nào điền vào ô trống?).
* Kiểu đề bài: Lưới hình (2×2, 3×3, 4×4 — chọn ảnh cho từng ô, ô cuối tự là "?"), Một ảnh đề bài, hoặc Chỉ có chữ.
* 4 đáp án A–D: ảnh và/hoặc chữ. Đánh dấu đáp án đúng.
* Giải thích (ghi chú nội bộ, không hiện cho người làm), Nhóm năng lực, Độ khó 1–5 (trọng số điểm).
* Trường "Thứ tự" bên phải quyết định thứ tự câu khi không bật ngẫu nhiên.
Câu thiếu dữ liệu được đánh dấu "Thiếu dữ liệu" trong danh sách và không đưa vào bài thi.

Mẹo ảnh: dùng ảnh vuông, nền trong suốt (PNG/WebP), hình nằm giữa, cùng kích thước cho mọi ô.

== Thống kê ==
Test IQ → Thống kê: theo từng bài test (Tên bài test | Lượt xem đề | Lượt xem kết quả IQ | Tỉ lệ hoàn thành | IQ trung bình), lọc Hôm nay / 7 ngày / 30 ngày / Tất cả, kèm bảng theo ngày.
* Lượt xem đề: mỗi lần người dùng bấm bắt đầu và tải đề.
* Lượt xem kết quả IQ: mỗi lần làm xong, nhập tuổi và xem điểm.
Test IQ → Kết quả: danh sách từng lượt làm, thống kê theo nhóm tuổi, xuất CSV.

== Trang kết quả ==
Hiển thị: điểm IQ, xếp loại, phần trăm người cùng độ tuổi có điểm thấp hơn, vị trí trên đường cong phân phối chuẩn, ý nghĩa kết quả (đặc điểm + gợi ý phát triển), phân tích theo nhóm năng lực (điểm mạnh nhất, nên luyện thêm) và thang xếp loại. Không hiện số câu đúng, thời gian, tuổi hay đáp án.

== Cách tính IQ ==
Tỉ lệ điểm = tổng độ khó các câu đúng / tổng độ khó tất cả câu.
IQ = 100 + 15 × (tỉ lệ điểm − trung bình nhóm tuổi) / độ lệch chuẩn nhóm tuổi, cộng thưởng tốc độ (nếu bật; so với thời gian chuẩn = số câu × số giây chuẩn mỗi câu), giới hạn trong khoảng cài đặt.
Bảng chuẩn theo nhóm tuổi chỉnh ở Cài đặt. Trang Kết quả hiển thị tỉ lệ trung bình thực tế theo từng nhóm tuổi để hiệu chỉnh khi đã có đủ dữ liệu.

== Bảo mật ==
Đáp án đúng không gửi xuống trình duyệt; bài làm được chấm trên máy chủ. Mỗi lượt làm có mã ký số (không sửa được danh sách câu và giờ bắt đầu). Giới hạn 30 lượt nộp/giờ/IP. Địa chỉ IP chỉ lưu dạng mã băm.
