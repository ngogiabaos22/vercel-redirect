/* Trang Trắc nghiệm nghề nghiệp – tracnghiemplus.com
 * Khối "Ngành HOT phù hợp với bạn" (#tnn-hot)
 * Chỉ chạy khi trang có phần tử #tnn-hot nên có thể nạp toàn site.
 */
(function () {
  var IMG = 'https://tracnghiemplus.com/wp-content/uploads/2026/08/';

  var careers = {
    marketing: {
      title: 'Digital Marketing', tag: '🔥 Ngành sáng tạo', img: IMG + 'Quan-tri-marketing.jpg',
      level: 'Rất cao', p: 90,
      desc: 'Phù hợp với người có tư duy sáng tạo, giao tiếp tốt, thích phân tích hành vi khách hàng, xây dựng nội dung và tối ưu hiệu quả truyền thông trên các nền tảng số.',
      holland: 'A – E – S', mbti: 'ENFP / ENTP', block: 'A00 – A01 – D01',
      skills: ['Sáng tạo nội dung, ý tưởng truyền thông', 'Giao tiếp, thuyết phục, nắm bắt tâm lý khách hàng', 'Phân tích số liệu quảng cáo, hành vi người dùng', 'Thích nghi nhanh với xu hướng mới'],
      jobs: ['Content Marketing', 'Performance Marketing', 'Social Media', 'SEO Specialist']
    },
    it: {
      title: 'Công nghệ thông tin', tag: '💻 Ngành công nghệ', img: IMG + 'Tin-hoc-dai-cuong.jpg',
      level: 'Rất cao', p: 92,
      desc: 'Phù hợp với người có tư duy logic mạnh, thích giải quyết vấn đề, kiên trì tìm lỗi và xây dựng sản phẩm số như website, ứng dụng, hệ thống dữ liệu.',
      holland: 'I – R – C', mbti: 'INTP / INTJ / ISTP', block: 'A00 – A01 – D07',
      skills: ['Tư duy logic, toán học tốt', 'Kiên trì, tập trung cao khi xử lý vấn đề', 'Tự học nhanh công nghệ, ngôn ngữ mới', 'Đọc hiểu tài liệu tiếng Anh chuyên ngành'],
      jobs: ['Lập trình viên', 'Kỹ sư phần mềm', 'Phân tích dữ liệu', 'Kiểm thử phần mềm']
    },
    ecommerce: {
      title: 'Thương mại điện tử', tag: '🛒 Ngành kinh tế số', img: IMG + 'Thuong-mai-dien-tu.jpg',
      level: 'Rất cao', p: 88,
      desc: 'Kết hợp kinh doanh, marketing và công nghệ. Phù hợp với người nhạy bén thị trường, thích bán hàng online, vận hành gian hàng và phân tích dữ liệu khách hàng.',
      holland: 'E – C – I', mbti: 'ESTP / ENTJ / ESTJ', block: 'A00 – A01 – D01',
      skills: ['Nhạy bén kinh doanh, nắm bắt xu hướng', 'Sử dụng tốt công cụ số, sàn TMĐT', 'Phân tích số liệu bán hàng', 'Chăm sóc khách hàng, xử lý tình huống'],
      jobs: ['Vận hành sàn TMĐT', 'Chuyên viên E-commerce', 'Quản lý gian hàng', 'Phân tích bán hàng']
    },
    logistics: {
      title: 'Logistics', tag: '🚚 Ngành vận hành', img: IMG + 'Quan-tri-logistics.jpg',
      level: 'Cao', p: 82,
      desc: 'Phù hợp với người có khả năng tổ chức, điều phối, quản lý quy trình, xử lý tình huống nhanh và tối ưu dòng hàng, chi phí, thời gian trong chuỗi cung ứng.',
      holland: 'C – E – R', mbti: 'ESTJ / ISTJ', block: 'A00 – A01 – D01',
      skills: ['Tổ chức, điều phối công việc tốt', 'Quản lý thời gian và quy trình', 'Xử lý tình huống phát sinh nhanh', 'Tiếng Anh giao tiếp, chứng từ'],
      jobs: ['Logistics Executive', 'Supply Chain', 'Chứng từ xuất nhập khẩu', 'Điều phối kho vận']
    },
    accounting: {
      title: 'Kế toán – Kiểm toán', tag: '🧮 Ngành ổn định', img: IMG + 'Ke-toan-doanh-nghiep.jpg',
      level: 'Cao', p: 80,
      desc: 'Phù hợp với người cẩn thận, chính xác, thích làm việc với số liệu, tôn trọng nguyên tắc và quy trình. Đây là ngành doanh nghiệp nào cũng cần.',
      holland: 'C – I – E', mbti: 'ISTJ / ESTJ / ISFJ', block: 'A00 – A01 – D01',
      skills: ['Cẩn thận, tỉ mỉ, chính xác', 'Tư duy số liệu tốt', 'Kỷ luật, tuân thủ quy định', 'Sử dụng thành thạo Excel, phần mềm kế toán'],
      jobs: ['Kế toán viên', 'Kế toán thuế', 'Kiểm toán viên', 'Kế toán tổng hợp']
    },
    banking: {
      title: 'Tài chính – Ngân hàng', tag: '🏦 Ngành tài chính', img: IMG + 'Tai-chinh-ngan-hang.jpg',
      level: 'Cao', p: 80,
      desc: 'Phù hợp với người có tư duy phân tích, nhạy bén với con số và thị trường, giao tiếp tốt với khách hàng và chịu được áp lực chỉ tiêu.',
      holland: 'C – E – I', mbti: 'ESTJ / ENTJ / ISTJ', block: 'A00 – A01 – D01',
      skills: ['Phân tích tài chính, rủi ro', 'Giao tiếp, tư vấn khách hàng', 'Chịu áp lực doanh số', 'Trung thực, cẩn trọng'],
      jobs: ['Giao dịch viên', 'Chuyên viên tín dụng', 'Phân tích tài chính', 'Tư vấn đầu tư']
    },
    business: {
      title: 'Quản trị kinh doanh', tag: '💼 Ngành quản lý', img: IMG + 'Quan-tri-hoc.jpg',
      level: 'Cao', p: 84,
      desc: 'Phù hợp với người có tư duy tổ chức, khả năng lãnh đạo, ra quyết định, thích làm việc với con người, thị trường và mục tiêu tăng trưởng.',
      holland: 'E – S – C', mbti: 'ENTJ / ESTJ / ENFJ', block: 'A00 – A01 – D01',
      skills: ['Lập kế hoạch, tổ chức nguồn lực', 'Giao tiếp, đàm phán, thuyết phục', 'Ra quyết định nhanh', 'Tư duy chiến lược'],
      jobs: ['Quản lý kinh doanh', 'Business Development', 'Quản lý vận hành', 'Khởi nghiệp']
    },
    pr: {
      title: 'Quan hệ công chúng (PR)', tag: '🎤 Ngành truyền thông', img: IMG + 'Quan-he-cong-chung-PR.jpg',
      level: 'Cao', p: 78,
      desc: 'Phù hợp với người hoạt ngôn, tự tin, thích kết nối, tổ chức sự kiện và xây dựng hình ảnh thương hiệu cho doanh nghiệp, tổ chức.',
      holland: 'S – A – E', mbti: 'ENFJ / ESFJ / ENFP', block: 'C00 – D01 – D14',
      skills: ['Giao tiếp, thuyết trình tự tin', 'Viết tốt, kể chuyện hấp dẫn', 'Xây dựng mối quan hệ', 'Xử lý khủng hoảng truyền thông'],
      jobs: ['Chuyên viên PR', 'Tổ chức sự kiện', 'Truyền thông nội bộ', 'Quản lý thương hiệu']
    }
  };

  function esc(t) {
    return String(t).replace(/[&<>"]/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
    });
  }

  function init() {
    var root = document.getElementById('tnn-hot');
    if (!root) return;

    var $ = function (id) { return root.querySelector('#' + id); };
    var f = {
      img: $('tnn-hot-img'), tag: $('tnn-hot-tag'), title: $('tnn-hot-title'), desc: $('tnn-hot-desc'),
      ring: $('tnn-hot-ring'), score: $('tnn-hot-score'), holland: $('tnn-hot-holland'),
      mbti: $('tnn-hot-mbti'), block: $('tnn-hot-block'), skills: $('tnn-hot-skills'), jobs: $('tnn-hot-jobs')
    };
    var result = root.querySelector('.tnn-hot-result');
    var buttons = root.querySelectorAll('.tnn-job');

    function render(key) {
      var d = careers[key];
      if (!d) return;
      f.img.src = d.img;
      f.img.alt = 'Ngành ' + d.title;
      f.tag.textContent = d.tag;
      f.title.textContent = d.title;
      f.desc.textContent = d.desc;
      f.ring.style.setProperty('--p', d.p);
      f.score.textContent = d.level;
      f.holland.textContent = d.holland;
      f.mbti.textContent = d.mbti;
      f.block.textContent = d.block;
      f.skills.innerHTML = d.skills.map(function (s) { return '<li>' + esc(s) + '</li>'; }).join('');
      f.jobs.innerHTML = d.jobs.map(function (s) { return '<span>' + esc(s) + '</span>'; }).join('');
      result.classList.remove('is-swap');
      void result.offsetWidth;
      result.classList.add('is-swap');
    }

    Array.prototype.forEach.call(buttons, function (btn) {
      btn.addEventListener('click', function () {
        Array.prototype.forEach.call(buttons, function (b) { b.classList.remove('is-active'); });
        btn.classList.add('is-active');
        render(btn.getAttribute('data-career'));
        if (window.innerWidth < 850) result.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
