/* La Bàn Nghề Nghiệp – tracnghiemplus.com/trac-nghiem-nghe-nghiep/
 * Tab khám phá Holland/MBTI + FAQ. Chỉ chạy khi trang có #lbn-explore hoặc .lbn-faq-list.
 */
(function () {
  var HOLLAND_URL = 'https://tracnghiemplus.com/bai-thi/test-holland/';
  var MBTI_URL = 'https://tracnghiemplus.com/bai-thi/test-mbti-mien-phi/';

  function each(list, fn) { Array.prototype.forEach.call(list, fn); }

  function initExplore() {
    var root = document.getElementById('lbn-explore');
    if (!root) return;

    function activateTab(name) {
      each(root.querySelectorAll('[data-tab]'), function (tab) {
        var active = tab.getAttribute('data-tab') === name;
        tab.classList.toggle('active', active);
        tab.setAttribute('aria-selected', String(active));
      });
      document.getElementById('lbn-panel-holland').hidden = name !== 'holland';
      document.getElementById('lbn-panel-mbti').hidden = name !== 'mbti';
    }
    each(root.querySelectorAll('[data-tab]'), function (tab) {
      tab.addEventListener('click', function () { activateTab(tab.getAttribute('data-tab')); });
    });
    each(document.querySelectorAll('[data-open-tab]'), function (link) {
      link.addEventListener('click', function () { activateTab(link.getAttribute('data-open-tab')); });
    });

    var hollandNames = { R: 'Thực tế', I: 'Nghiên cứu', A: 'Nghệ thuật', S: 'Xã hội', E: 'Quản lý', C: 'Nghiệp vụ' };
    var interests = [];
    var hollandResult = document.getElementById('lbn-holland-result');
    var hollandEmpty = hollandResult.innerHTML;
    each(root.querySelectorAll('.lbn-interest'), function (option) {
      option.addEventListener('click', function () {
        var type = option.getAttribute('data-type');
        var index = interests.indexOf(type);
        if (index !== -1) {
          interests.splice(index, 1);
          option.classList.remove('selected');
          option.setAttribute('aria-pressed', 'false');
        } else if (interests.length < 3) {
          interests.push(type);
          option.classList.add('selected');
          option.setAttribute('aria-pressed', 'true');
        } else {
          hollandResult.textContent = 'Bạn đã chọn 3 nhóm. Bỏ chọn một nhóm để thử lựa chọn khác nhé.';
          return;
        }
        if (!interests.length) {
          hollandResult.innerHTML = hollandEmpty;
        } else {
          hollandResult.innerHTML = '✦ Nhóm bạn đang quan tâm: <b>' +
            interests.map(function (t) { return hollandNames[t] + ' (' + t + ')'; }).join(' · ') +
            '</b>. Làm <a href="' + HOLLAND_URL + '">test Holland đầy đủ</a> để biết chính xác mã Holland và nhóm ngành phù hợp.';
        }
      });
    });

    var mbtiChoices = [null, null, null, null];
    var mbtiResult = document.getElementById('lbn-mbti-result');
    each(root.querySelectorAll('.lbn-pair'), function (pair) {
      each(pair.querySelectorAll('button'), function (button) {
        button.addEventListener('click', function () {
          each(pair.querySelectorAll('button'), function (item) {
            item.classList.remove('selected');
            item.setAttribute('aria-pressed', 'false');
          });
          button.classList.add('selected');
          button.setAttribute('aria-pressed', 'true');
          mbtiChoices[Number(pair.getAttribute('data-dimension'))] = button.getAttribute('data-value');
          var remaining = mbtiChoices.filter(function (v) { return !v; }).length;
          mbtiResult.innerHTML = remaining
            ? '✦ Còn <b>' + remaining + ' cặp xu hướng</b> để bạn lựa chọn.'
            : '✦ Mã xu hướng minh họa của bạn: <b>' + mbtiChoices.join('') + '</b>. Đây chỉ là điểm khởi đầu — làm <a href="' + MBTI_URL + '">test MBTI đầy đủ</a> để có kết quả chính xác.';
        });
      });
    });
  }

  function initFaq() {
    each(document.querySelectorAll('.lbn-faq-list'), function (list) {
      each(list.querySelectorAll('.lbn-faq-item > button'), function (button) {
        button.addEventListener('click', function () {
          var item = button.parentNode;
          var willOpen = !item.classList.contains('open');
          each(list.querySelectorAll('.lbn-faq-item'), function (other) {
            other.classList.remove('open');
            other.querySelector('button').setAttribute('aria-expanded', 'false');
          });
          if (willOpen) {
            item.classList.add('open');
            button.setAttribute('aria-expanded', 'true');
          }
        });
      });
    });
  }

  function init() { initExplore(); initFaq(); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();

/* Ngành HOT – đối chiếu Holland & MBTI. Chỉ chạy khi trang có #lbn-hot. */
(function () {
  var IMG = 'https://tracnghiemplus.com/wp-content/uploads/2026/08/';
  /* holland: 3 chữ cái nổi bật; mbti: xu hướng ở 4 cặp (EI, SN, TF, JP), dùng "TF" nếu cả hai đều phù hợp */
  var careers = {
    marketing: { title: 'Digital Marketing', tag: 'Ngành sáng tạo', img: 'Quan-tri-marketing.jpg', holland: 'AES', mbti: ['E', 'N', 'TF', 'P'], types: 'ENFP · ENTP',
      desc: 'Phù hợp với người sáng tạo, giao tiếp tốt, thích tìm hiểu hành vi khách hàng và xây dựng nội dung, chiến dịch truyền thông trên các nền tảng số.',
      skills: ['Sáng tạo nội dung, ý tưởng truyền thông', 'Giao tiếp, thuyết phục, hiểu tâm lý khách hàng', 'Đọc và phân tích số liệu quảng cáo', 'Thích nghi nhanh với xu hướng mới'],
      jobs: ['Content Marketing', 'Performance Marketing', 'Social Media', 'SEO Specialist'] },
    it: { title: 'Công nghệ thông tin', tag: 'Ngành công nghệ', img: 'Tin-hoc-dai-cuong.jpg', holland: 'IRC', mbti: ['I', 'N', 'T', 'JP'], types: 'INTJ · INTP · ISTP',
      desc: 'Phù hợp với người có tư duy logic, thích giải quyết vấn đề, kiên trì tìm lỗi và xây dựng sản phẩm số như website, ứng dụng, hệ thống dữ liệu.',
      skills: ['Tư duy logic, nền tảng toán tốt', 'Kiên trì, tập trung khi xử lý vấn đề', 'Tự học nhanh công nghệ mới', 'Đọc hiểu tài liệu tiếng Anh chuyên ngành'],
      jobs: ['Lập trình viên', 'Kỹ sư phần mềm', 'Phân tích dữ liệu', 'Kiểm thử phần mềm'] },
    ecommerce: { title: 'Thương mại điện tử', tag: 'Ngành kinh tế số', img: 'Thuong-mai-dien-tu.jpg', holland: 'ECI', mbti: ['E', 'SN', 'T', 'JP'], types: 'ESTP · ENTJ · ESTJ',
      desc: 'Kết hợp kinh doanh, marketing và công nghệ. Phù hợp với người nhạy bén thị trường, thích bán hàng online, vận hành gian hàng và phân tích dữ liệu khách hàng.',
      skills: ['Nhạy bén kinh doanh, nắm bắt xu hướng', 'Sử dụng tốt công cụ số, sàn TMĐT', 'Phân tích số liệu bán hàng', 'Chăm sóc khách hàng, xử lý tình huống'],
      jobs: ['Vận hành sàn TMĐT', 'Chuyên viên E-commerce', 'Quản lý gian hàng', 'Phân tích bán hàng'] },
    logistics: { title: 'Logistics', tag: 'Ngành vận hành', img: 'Quan-tri-logistics.jpg', holland: 'CER', mbti: ['EI', 'S', 'T', 'J'], types: 'ESTJ · ISTJ',
      desc: 'Phù hợp với người giỏi tổ chức, điều phối, quản lý quy trình, xử lý tình huống nhanh và tối ưu dòng hàng, chi phí, thời gian trong chuỗi cung ứng.',
      skills: ['Tổ chức, điều phối công việc tốt', 'Quản lý thời gian và quy trình', 'Xử lý tình huống phát sinh nhanh', 'Tiếng Anh giao tiếp, chứng từ'],
      jobs: ['Logistics Executive', 'Supply Chain', 'Chứng từ xuất nhập khẩu', 'Điều phối kho vận'] },
    accounting: { title: 'Kế toán – Kiểm toán', tag: 'Ngành ổn định', img: 'Ke-toan-doanh-nghiep.jpg', holland: 'CIE', mbti: ['I', 'S', 'T', 'J'], types: 'ISTJ · ESTJ · ISFJ',
      desc: 'Phù hợp với người cẩn thận, chính xác, thích làm việc với số liệu, tôn trọng nguyên tắc và quy trình. Đây là ngành doanh nghiệp nào cũng cần.',
      skills: ['Cẩn thận, tỉ mỉ, chính xác', 'Tư duy số liệu tốt', 'Kỷ luật, tuân thủ quy định', 'Thành thạo Excel, phần mềm kế toán'],
      jobs: ['Kế toán viên', 'Kế toán thuế', 'Kiểm toán viên', 'Kế toán tổng hợp'] },
    banking: { title: 'Tài chính – Ngân hàng', tag: 'Ngành tài chính', img: 'Tai-chinh-ngan-hang.jpg', holland: 'CEI', mbti: ['E', 'S', 'T', 'J'], types: 'ESTJ · ENTJ · ISTJ',
      desc: 'Phù hợp với người có tư duy phân tích, nhạy bén với con số và thị trường, giao tiếp tốt với khách hàng và chịu được áp lực chỉ tiêu.',
      skills: ['Phân tích tài chính, rủi ro', 'Giao tiếp, tư vấn khách hàng', 'Chịu áp lực doanh số', 'Trung thực, cẩn trọng'],
      jobs: ['Giao dịch viên', 'Chuyên viên tín dụng', 'Phân tích tài chính', 'Tư vấn đầu tư'] },
    business: { title: 'Quản trị kinh doanh', tag: 'Ngành quản lý', img: 'Quan-tri-hoc.jpg', holland: 'ESC', mbti: ['E', 'SN', 'T', 'J'], types: 'ENTJ · ESTJ · ENFJ',
      desc: 'Phù hợp với người có tư duy tổ chức, thích dẫn dắt, ra quyết định và làm việc với con người, thị trường và mục tiêu tăng trưởng.',
      skills: ['Lập kế hoạch, tổ chức nguồn lực', 'Giao tiếp, đàm phán, thuyết phục', 'Ra quyết định nhanh', 'Tư duy chiến lược'],
      jobs: ['Quản lý kinh doanh', 'Business Development', 'Quản lý vận hành', 'Khởi nghiệp'] },
    pr: { title: 'Truyền thông – PR', tag: 'Ngành truyền thông', img: 'Quan-he-cong-chung-PR.jpg', holland: 'SAE', mbti: ['E', 'N', 'F', 'J'], types: 'ENFJ · ESFJ · ENFP',
      desc: 'Phù hợp với người hoạt ngôn, tự tin, thích kết nối, tổ chức sự kiện và xây dựng hình ảnh thương hiệu cho doanh nghiệp, tổ chức.',
      skills: ['Giao tiếp, thuyết trình tự tin', 'Viết tốt, kể chuyện hấp dẫn', 'Xây dựng mối quan hệ', 'Xử lý khủng hoảng truyền thông'],
      jobs: ['Chuyên viên PR', 'Tổ chức sự kiện', 'Truyền thông nội bộ', 'Quản lý thương hiệu'] }
  };
  var PAIRS = [['E', 'I'], ['S', 'N'], ['T', 'F'], ['J', 'P']];

  /* Tạo phần tử bằng DOM (không dùng chuỗi HTML) để WordPress wpautop không chèn thẻ p vào JS */
  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text != null) n.textContent = text;
    return n;
  }
  function fill(box, items) {
    while (box.firstChild) box.removeChild(box.firstChild);
    items.forEach(function (n) { box.appendChild(n); });
  }

  function init() {
    var root = document.getElementById('lbn-hot');
    if (!root) return;
    var q = function (id) { return document.getElementById(id); };
    var card = root.querySelector('.lbn-hot-card');
    var buttons = root.querySelectorAll('.lbn-job');

    function render(key) {
      var d = careers[key];
      if (!d) return;
      q('lbn-hot-img').src = IMG + d.img;
      q('lbn-hot-img').alt = 'Ngành ' + d.title;
      q('lbn-hot-tag').textContent = d.tag;
      q('lbn-hot-title').textContent = d.title;
      q('lbn-hot-desc').textContent = d.desc;
      q('lbn-hot-hcode').textContent = d.holland.split('').join(' – ');
      Array.prototype.forEach.call(q('lbn-hot-riasec').children, function (chip) {
        chip.classList.toggle('is-on', d.holland.indexOf(chip.getAttribute('data-l')) !== -1);
      });
      q('lbn-hot-mcode').textContent = d.types;
      fill(q('lbn-hot-pairs'), PAIRS.map(function (p, i) {
        var pair = el('div', 'lbn-mp');
        p.forEach(function (l) { pair.appendChild(el('div', 'lbn-mp-l' + (d.mbti[i].indexOf(l) !== -1 ? ' is-on' : ''), l)); });
        return pair;
      }));
      fill(q('lbn-hot-skills'), d.skills.map(function (t) { return el('li', '', t); }));
      fill(q('lbn-hot-jobs'), d.jobs.map(function (t) { return el('div', '', t); }));
      card.classList.remove('is-swap');
      void card.offsetWidth;
      card.classList.add('is-swap');
    }

    Array.prototype.forEach.call(buttons, function (btn) {
      btn.addEventListener('click', function () {
        Array.prototype.forEach.call(buttons, function (b) { b.classList.remove('is-active'); });
        btn.classList.add('is-active');
        render(btn.getAttribute('data-career'));
        if (window.innerWidth < 850) card.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
