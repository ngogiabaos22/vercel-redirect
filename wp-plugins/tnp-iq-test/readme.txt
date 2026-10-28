=== TNP Trắc nghiệm (IQ, EQ, DISC, MBTI, Holland) ===
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 2.6.1

Tạo bài test IQ, EQ, DISC, MBTI, Holland cho WordPress: quản trị câu hỏi, chấm điểm trên máy chủ, trang kết quả chi tiết cho từng loại, lưu và thống kê kết quả.

== Cài đặt ==
1. Plugin → Cài mới → Tải plugin lên → chọn tnp-iq-test.zip → Kích hoạt. Menu mới: "Trắc nghiệm".
2. Trắc nghiệm → Cài đặt: bấm "Thêm 4 câu hỏi mẫu" (IQ) và "Tạo 4 bài test mẫu: EQ, DISC, MBTI, Holland" để xem thử.
3. Trắc nghiệm → Bài test: tạo bài test, đặt tên và chọn Loại. Bảng có sẵn shortcode để chép, ví dụ [tnp_test test="test-mbti"].
   Bài IQ chung: [tnp_iq_test] (các câu IQ không thuộc bài test nào).
   Tuỳ chọn: count="20" (số câu), time="15" (số phút tối đa; đồng hồ luôn đếm xuôi).

== Các loại bài test ==
* IQ: mỗi câu có 1 đáp án đúng. Bước cuối hỏi tuổi; kết quả là điểm IQ quy đổi theo độ tuổi.
* EQ: mỗi đáp án có Nhóm (Tự nhận thức, Tự điều chỉnh, Động lực, Đồng cảm, Kỹ năng xã hội) và Điểm (vd 1–4, phản ứng càng trưởng thành càng nhiều điểm). Kết quả: điểm EQ /100, mức độ, % từng nhóm, nhóm mạnh/yếu kèm gợi ý.
* DISC: mỗi đáp án thuộc D, I, S hoặc C (Điểm 1). Kết quả: nhóm chính (+ nhóm phụ nếu sát), biểu đồ DISC, điểm mạnh, điều cần lưu ý, phong cách làm việc.
* MBTI: mỗi câu thường 2 đáp án thuộc hai cực của một cặp (E/I, S/N, T/F, J/P). Nên có số câu đều cho 4 cặp. Kết quả: 1 trong 16 kiểu (vd INTJ – Nhà kiến trúc), % từng cặp, điểm mạnh, lưu ý, nghề gợi ý.
* Holland: mỗi câu là một hoạt động thuộc R, I, A, S, E hoặc C; đáp án là mức độ thích (vd Rất thích 3 … Không thích 0), cùng nhóm với câu. Kết quả: mã 3 chữ (vd RIA), mô tả và nghề gợi ý cho 3 nhóm, biểu đồ 6 nhóm.
Test tính cách không hỏi tuổi, không hiện đồng hồ (trừ khi đặt time="…").

== Nhập câu hỏi hàng loạt ==
Trắc nghiệm → Nhập câu hỏi: chọn bài test, dán văn bản, bấm "Kiểm tra" để xem trước rồi "Nhập câu hỏi".
  CÂU 1
  Nội dung câu hỏi
  A
  Đáp án A            (hoặc viết liền: A. Đáp án A)
  B
  Đáp án B
  ĐÁP ÁN: B                     ← bài IQ
  NHÓM: Tự điều chỉnh           ← EQ, Holland: một nhóm cho cả câu
  NHÓM: A=D, B=I, C=S, D=C      ← DISC, MBTI: nhóm từng đáp án
  ĐIỂM: A=1, B=3, C=1, D=0      ← điểm từng đáp án (bỏ trống = 1)
Nhóm ghi bằng chữ cái/khoá (sr, D, E…) hoặc tên (Tự điều chỉnh, Thủ lĩnh, Influence…). Dòng bắt đầu bằng # là ghi chú.
Ảnh (tải lên Thư viện Media trước, ghi theo tên ảnh, tên file, đường dẫn hoặc ID):
  ẢNH: câu 1                    ← đặt trước các đáp án: ảnh đề bài
  A. [ảnh: 1.1]                 ← đáp án là ảnh (có thể thêm chữ phía sau)
Quản trị viên cũng có thể nhập qua REST: POST /wp-json/tnp-iq/v1/admin/import (test, name, type, text, status, dry_run, replace, allow_no_answer).
GET /wp-json/tnp-iq/v1/admin/questions?test=… liệt kê câu hỏi; POST cùng đường dẫn với items=[{id, category, difficulty, explain}] để sửa hàng loạt.
Bài IQ: ghi "ĐÁP ÁN: ?" (hoặc bỏ trống) và bật allow_no_answer → câu được lưu Bản nháp; vào Sửa câu hỏi, chọn "Đáp án đúng" rồi Đăng.

== Xáo trộn đáp án ==
Trong Bài test → Sửa: "Xáo trộn thứ tự đáp án mỗi lượt làm" (mặc định bật khi tạo bài mới). Nhãn A, B, C… theo thứ tự hiển thị; máy chủ chấm theo đáp án gốc.

== Nhiệm vụ (kết nối Traffic Hila) ==
Bắt người làm bài làm nhiệm vụ của Traffic Hila (traffic.io.vn) và nhập mã đúng mới xem được kết quả, giống TestSpace.
* Trắc nghiệm → Cài đặt → "Web nhiệm vụ (Traffic Hila)": địa chỉ web cài Traffic Hila (mặc định https://traffic.io.vn).
* Trắc nghiệm → Bài test → Sửa (hoặc khi thêm mới) → mục "Nhiệm vụ":
  - Công tắc "Yêu cầu làm nhiệm vụ trước khi xem kết quả": bật/tắt yêu cầu.
  - Ô Tag: trùng "Tag phân loại" của nhiệm vụ bên Traffic Hila (để trống = nhiệm vụ bất kỳ). Tắt công tắc vẫn giữ Tag.
  Bảng danh sách Bài test có cột "Nhiệm vụ": bấm công tắc để bật/tắt ngay (lưu tự động, không cần mở trang Sửa), kèm tag.
* Khi nộp bài: hiện popup nhiệm vụ của Traffic Hila (giao diện chỉnh ở Hila → Cài đặt → Giao diện Popup). Người dùng nhập mã → máy chủ web này
  gửi mã sang Hila (/iats/v1/code/verify) để kiểm tra; đúng mới chấm điểm và trả kết quả, sai thì popup báo lỗi để nhập lại.
* Kết quả chỉ được tính sau khi mã đúng; mỗi mã dùng một lần, mỗi lượt làm bài cần một mã.
* Nếu máy chủ Traffic Hila không phản hồi hoặc lỗi, kết quả không được mở: popup báo "Chưa kiểm tra được mã nhiệm vụ…" để người làm bài thử lại sau (bài làm vẫn giữ trên trang).

== Mở bài test bằng nút (cửa sổ bật lên) ==
Bấm nút là bài test hiện ngay trong cửa sổ bật lên, vào thẳng câu 1, không cần trang riêng. Dùng được với mọi nút/liên kết trên web (Flatsome Button, menu, ảnh…):
* Đặt Link của nút là:  #tnp-test=test-iq   (thay test-iq bằng slug bài test; "#tnp-test" = bài IQ chung).
  Có thể giữ link cũ phía trước, ví dụ https://tracnghiemplus.com/bai-thi/test-iq-mien-phi/#tnp-test=test-iq — khi plugin tắt, nút vẫn mở trang cũ.
* Hoặc thêm thuộc tính data-tnp-test="test-iq" cho phần tử bất kỳ.
* Hoặc dùng shortcode nút: [tnp_button test="test-iq" text="Test IQ nhanh"] (class="primary" mặc định, đổi được).
* Gửi link thẳng tới bài test: https://tracnghiemplus.com/trang-bat-ky/#tnp-test=test-iq
Đóng bằng nút ×, phím Esc hoặc bấm ra ngoài. Mã mở cửa sổ rất nhẹ (~3 KB); phần bài test chỉ tải khi bấm nút.

== Câu hỏi trong bài test ==
Trắc nghiệm → Bài test → bấm Sửa một bài: cuối trang có bảng toàn bộ câu hỏi của bài (hình, đề bài, nhóm, độ khó, đáp án, trạng thái, thứ tự), số câu đang được dùng trong bài thi, nút "Thêm câu hỏi vào bài này" (tự chọn sẵn bài test) và "Mở trong Quản lý câu hỏi".

== Hiển thị trên điện thoại ==
Mỗi câu (đề, ảnh đề, đáp án và nút Câu trước/Câu tiếp) tự co ảnh để vừa một màn hình, không cần cuộn; trang tự cuộn để câu hỏi nằm ngay dưới thanh menu.

== Thêm câu hỏi ==
Trắc nghiệm → Thêm câu hỏi (chọn bài test ở khung "Bài test" bên phải trước):
* Tiêu đề = câu hỏi (ví dụ: Hình nào điền vào ô trống?).
* Kiểu đề bài:
  - Câu hỏi: đề bằng chữ; ô "Nội dung câu hỏi" cho đề dài (đoạn văn, dữ kiện, dãy số) và ảnh minh hoạ tuỳ chọn.
  - Ảnh đề bài: đề nằm trong một ảnh (ví dụ ma trận hình đã ghép sẵn), ảnh là bắt buộc.
* Đáp án: từ 2 đến 8 (A–H). Bấm "+ Thêm đáp án" để thêm, "Xoá" để bỏ; chữ cái tự đánh lại. Mỗi đáp án là chữ, ảnh hoặc cả hai.
  - Bài IQ: chọn "Đáp án đúng" ở một đáp án.
  - Bài EQ, DISC, MBTI, Holland: mỗi đáp án chọn "Nhóm" và nhập "Điểm".
* Hiển thị đáp án tự động: có ảnh → lưới thẻ 2–4 cột; chỉ có chữ → danh sách (2 cột nếu đáp án ngắn, 1 cột trên điện thoại).
* Giải thích (ghi chú nội bộ, không hiện cho người làm). Với IQ: Nhóm năng lực, Độ khó 1–5 (trọng số điểm).
* Trường "Thứ tự" bên phải quyết định thứ tự câu khi không bật ngẫu nhiên.
Câu thiếu dữ liệu (thiếu đáp án đúng với IQ, đáp án chưa chọn nhóm với test tính cách…) được đánh dấu "Thiếu dữ liệu" và không đưa vào bài thi.

Mẹo ảnh: ảnh đáp án nên vuông, nền trong suốt (PNG/WebP), hình nằm giữa và cùng kích thước. Câu tạo bằng kiểu "Lưới hình" ở bản cũ vẫn hiển thị bình thường.

== Thống kê ==
Trắc nghiệm → Thống kê: theo từng bài test (Tên bài test | Loại | Lượt xem đề | Lượt xem kết quả | Tỉ lệ hoàn thành | IQ TB hoặc kết quả phổ biến nhất | Shortcode), lọc Hôm nay / 7 ngày / 30 ngày / Tất cả, kèm bảng theo ngày.
* Lượt xem đề: mỗi lần đề được tải (bài test hiện thẳng câu 1, không có màn hình giới thiệu: mở trang có shortcode hoặc bấm nút mở bài test là tính một lượt).
* Lượt xem kết quả: mỗi lần làm xong và xem kết quả.
Trắc nghiệm → Kết quả: danh sách từng lượt làm (IQ hoặc mã kết quả như INTJ, DI, RIA, EQ 80), thống kê IQ theo nhóm tuổi, xuất CSV.

== Trang kết quả IQ ==
Hiển thị: điểm IQ, xếp loại, phần trăm người cùng độ tuổi có điểm thấp hơn, vị trí trên đường cong phân phối chuẩn, ý nghĩa kết quả (đặc điểm + gợi ý phát triển), phân tích theo nhóm năng lực (điểm mạnh nhất, nên luyện thêm) và thang xếp loại. Không hiện số câu đúng, thời gian, tuổi hay đáp án.

== Cách tính IQ ==
Tỉ lệ điểm = tổng độ khó các câu đúng / tổng độ khó tất cả câu.
IQ = 100 + 15 × (tỉ lệ điểm − trung bình nhóm tuổi) / độ lệch chuẩn nhóm tuổi, cộng thưởng tốc độ (nếu bật; so với thời gian chuẩn = số câu × số giây chuẩn mỗi câu), giới hạn trong khoảng cài đặt.
Bảng chuẩn theo nhóm tuổi chỉnh ở Cài đặt. Trang Kết quả hiển thị tỉ lệ trung bình thực tế theo từng nhóm tuổi để hiệu chỉnh khi đã có đủ dữ liệu.

== Bảo mật ==
Đáp án đúng không gửi xuống trình duyệt; bài làm được chấm trên máy chủ. Mỗi lượt làm có mã ký số (không sửa được danh sách câu và giờ bắt đầu). Giới hạn 30 lượt nộp/giờ/IP. Địa chỉ IP chỉ lưu dạng mã băm.
