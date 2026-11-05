import sys; sys.path.insert(0, __file__.rsplit('/',1)[0])
from build import build, check
L = lambda p: f'<a href="https://tracnghiemplus.com/{p}/">'
pages = {
 "holland": dict(src="../mockup/test-holland-mockup.html", prefix="ho", links=[
   ('href="#bat-dau-test">Làm test Holland ngay ↓', 'href="#tnp-test=test-holland">Làm test Holland ngay →'),
   ('href="#">Xem 6 nhóm nghề', 'href="#nhom-holland">Xem 6 nhóm nghề'),
   ('href="#bat-dau-test">Làm test Holland ngay ↑', 'href="#tnp-test=test-holland">Làm test Holland ngay →')],
  faq={
   "Mã Holland có thay đổi theo thời gian không?": "Sở thích nghề nghiệp khá ổn định ở tuổi trưởng thành nhưng vẫn có thể thay đổi theo trải nghiệm học tập và làm việc. Bạn nên làm lại sau 6–12 tháng để theo dõi.",
   "Nếu điểm các nhóm gần bằng nhau thì sao?": "Điều đó cho thấy bạn có sở thích đa dạng. Hãy xem các nghề phù hợp với nhiều tổ hợp mã khác nhau và trải nghiệm thực tế để thu hẹp lựa chọn.",
   "Holland khác gì với MBTI và DISC?": f"Holland đo sở thích nghề nghiệp, {L('test-mbti')}MBTI</a> mô tả cách bạn suy nghĩ và ra quyết định, còn {L('test-disc')}DISC</a> mô tả hành vi khi làm việc và giao tiếp. Kết hợp cả ba cho bức tranh đầy đủ hơn về bản thân.",
   "Có nên chọn ngành chỉ dựa vào test Holland?": "Không nên. Hãy kết hợp kết quả với năng lực học tập, điều kiện bản thân, nhu cầu thị trường lao động và lời khuyên từ thầy cô, gia đình."}),
 "mbti": dict(src="../mockup/test-mbti-mockup.html", prefix="mb", links=[
   ('href="#">Làm test MBTI ngay →', 'href="#tnp-test=test-mbti">Làm test MBTI ngay →'),
   ('href="#">Xem 16 nhóm tính cách', 'href="#16-nhom">Xem 16 nhóm tính cách'),
   ('href="#">Làm test MBTI miễn phí →', 'href="#tnp-test=test-mbti">Làm test MBTI miễn phí →')],
  faq={
   "Kết quả MBTI có thay đổi theo thời gian không?": "Có thể. Nhóm tính cách khá ổn định, nhưng kết quả có thể khác đi khi bạn ở giai đoạn sống khác hoặc khi điểm giữa hai xu hướng gần cân bằng.",
   "Nhóm tính cách nào hiếm nhất?": "Trong các khảo sát phổ biến, INFJ thường được nhắc đến là nhóm hiếm nhất, chỉ khoảng 1–2% dân số. Tỉ lệ cụ thể khác nhau tuỳ nghiên cứu và quốc gia.",
   "MBTI khác gì với Big Five?": "MBTI xếp bạn vào 1 trong 16 nhóm tính cách, còn Big Five đo mức độ của 5 đặc điểm (cởi mở, tận tâm, hướng ngoại, dễ chịu, nhạy cảm cảm xúc) trên một thang liên tục.",
   "Có nên dùng MBTI để chọn nghề?": f"Không nên dùng MBTI làm căn cứ duy nhất. Hãy kết hợp với sở thích, năng lực và {L('test-holland')}test Holland</a> để định hướng nghề nghiệp toàn diện hơn."},
  after_faq='<div class="mb-wrap"><p class="mb-note">Bài tự đánh giá mang tính tham khảo, không phải công cụ MBTI® chính thức hay chẩn đoán tâm lý.</p></div>',
  extra_css=".mb-note{font-size:13px!important;color:var(--muted)!important;text-align:center;margin-top:18px}"),
 "disc": dict(src="../mockup/test-disc-mockup.html", prefix="ds", links=[
   ('href="#disc-20"', 'href="#tnp-test=test-disc-20-cau"'),
   ('href="#disc-30"', 'href="#tnp-test=test-disc"'),
   ('href="#disc-40"', 'href="#tnp-test=test-disc-40-cau"')],
  extra_css=".ds-ver .ds-wrap{display:flex;flex-direction:column;flex:1}",
  faq={
   "Nên làm bài 20, 30 hay 40 câu?": "Bài 20 câu giúp biết nhanh nhóm nổi trội. Bài 30 câu phù hợp với đa số người dùng. Bài 40 câu cho kết quả chi tiết nhất khi bạn cần định hướng nghề nghiệp.",
   "Kết quả DISC có thay đổi theo thời gian không?": "Phong cách hành vi khá ổn định nhưng có thể thay đổi theo vai trò, môi trường làm việc và giai đoạn sống. Bạn có thể làm lại để so sánh.",
   "Có nhóm DISC nào tốt hơn nhóm nào không?": "Không. Mỗi nhóm có điểm mạnh riêng. Hiệu quả phụ thuộc vào việc bạn hiểu và vận dụng phong cách của mình phù hợp với từng tình huống.",
   "Doanh nghiệp dùng DISC để làm gì?": f"DISC hỗ trợ tuyển dụng, phân công đội nhóm, đào tạo lãnh đạo và cải thiện giao tiếp nội bộ. Để định hướng nghề nghiệp, bạn có thể kết hợp với {L('test-holland')}test Holland</a> và {L('test-mbti')}test MBTI</a>."}),
}
for name, cfg in pages.items():
    c = build(cfg["src"], cfg["prefix"], cfg["links"], cfg["faq"], cfg.get("extra_css",""), cfg.get("after_faq",""))
    check(c)
    open(f"test-{name}.txt", "w").write(c)
    print(name, len(c), "tnp links:", c.count("#tnp-test="))
