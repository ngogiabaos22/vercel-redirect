/* TQP TOPIK / EPS - JS dung chung
   Doc cac the de tu shortcode [tqp_category_landing] (nam trong .tp-src), gom theo "De N" va ky nang,
   roi dung danh sach the de trong #tp-exam-list.
   Ten de nen co dang: "Topik I - De 1 - Reading" (Reading/Listening/Writing/Vocabulary/Grammar; khong co ky nang = de FULL).
   Khong dung ky tu "nho hon" va dong trong de WordPress khong lam hong ma. */
(function () {
  function norm(s) { s = (s || '').toLowerCase().normalize('NFD'); var o = ''; for (var i = 0; i !== s.length; i++) { var c = s.charCodeAt(i); if (c >= 768 && 879 >= c) continue; o += s[i]; } return o.replace(/đ/g, 'd').replace(/\s+/g, ' ').trim(); }
  function el(tag, cls, text) { var n = document.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; }
  function each(list, fn) { Array.prototype.forEach.call(list, fn); }
  function pad(n) { return n > 9 ? String(n) : '0' + n; }
  var SK = {
    doc: { label: 'Phần Đọc', ico: 'fa-book-open', re: /reading|phan doc|ky nang doc|doc hieu/ },
    nghe: { label: 'Phần Nghe', ico: 'fa-headphones', re: /listening|phan nghe|ky nang nghe|nghe hieu/ },
    viet: { label: 'Phần Viết', ico: 'fa-pen-nib', re: /writing|phan viet|ky nang viet/ },
    tuvung: { label: 'Từ vựng', ico: 'fa-spell-check', re: /vocab|tu vung/ },
    nguphap: { label: 'Ngữ pháp', ico: 'fa-puzzle-piece', re: /grammar|ngu phap/ }
  };
  function readCards() {
    var out = [], seen = {};
    each(document.querySelectorAll('.tp-src .ts-portal-card'), function (card) {
      var h = card.querySelector('.ts-portal-card-title'), a = card.querySelector('a[href]');
      if (!h || !a || seen[a.href]) return;
      seen[a.href] = 1;
      var t = norm(h.textContent), d = { href: a.href, skill: 'full', level: '', no: 0 };
      Object.keys(SK).forEach(function (k) { if (d.skill === 'full' && SK[k].re.test(t)) d.skill = k; });
      var lv = t.match(/topik\s*(ii|i|1|2)(?![a-z0-9])/);
      if (lv) d.level = (lv[1] === 'ii' || lv[1] === '2') ? 'ii' : 'i';
      var m = t.match(/(?:de|test)\s*(?:so\s*)?#?\s*(\d+)/) || t.match(/(\d+)\s*$/);
      d.no = m ? parseInt(m[1], 10) : 0;
      out.push(d);
    });
    return out;
  }
  function row(skill, d) {
    var s = SK[skill];
    var a = el(d ? 'a' : 'span', 'tp-row tp-row--' + skill + (d ? '' : ' is-off'));
    if (d) a.href = d.href;
    var ico = el('span', 'tp-ico'); ico.appendChild(el('i', 'fa-solid ' + s.ico));
    a.appendChild(ico);
    a.appendChild(el('span', 'tp-rl', s.label));
    var go = el('span', 'tp-go', d ? 'Xem đề ' : 'Sắp có');
    if (d) go.appendChild(el('i', 'fa-solid fa-chevron-right'));
    a.appendChild(go);
    return a;
  }
  function build(box) {
    var level = box.getAttribute('data-level'), name = level === 'ii' ? 'TOPIK II' : 'TOPIK I';
    var order = (box.getAttribute('data-skills') || 'doc,nghe,tuvung,nguphap').split(',');
    var cards = readCards().filter(function (d) { return d.level === level; });
    var nums = [];
    cards.forEach(function (d) { if (nums.indexOf(d.no) === -1) nums.push(d.no); });
    nums.sort(function (a, b) { return a - b; });
    while (box.firstChild) box.removeChild(box.firstChild);
    if (!nums.length) { box.appendChild(el('div', 'tp-empty', 'Đề ' + name + ' đang được cập nhật. Bạn quay lại sau nhé!')); return; }
    nums.forEach(function (n) {
      var mine = cards.filter(function (d) { return d.no === n; });
      function pick(sk) { for (var i = 0; i !== mine.length; i++) if (mine[i].skill === sk) return mine[i]; return null; }
      var art = el('article', 'tp-exam');
      var head = el('header', 'tp-exam-h');
      head.appendChild(el('span', 'tp-num', pad(n)));
      var hd = el('div');
      hd.appendChild(el('h3', '', 'Đề ' + n + ' thi thử ' + name));
      hd.appendChild(el('p', '', box.getAttribute('data-desc') || ''));
      head.appendChild(hd);
      art.appendChild(head);
      var rows = el('div', 'tp-rows');
      order.forEach(function (sk) { rows.appendChild(row(sk, pick(sk))); });
      art.appendChild(rows);
      var f = pick('full');
      var foot = el('div', 'tp-fullwrap');
      var b = el(f ? 'a' : 'span', 'tp-fullbtn' + (f ? '' : ' is-off'));
      if (f) b.href = f.href;
      b.appendChild(el('i', 'fa-solid fa-circle-play'));
      b.appendChild(document.createTextNode(f ? ' Thi full đề ' : ' Đề full sắp có '));
      if (f) b.appendChild(el('i', 'fa-solid fa-arrow-right'));
      foot.appendChild(b);
      art.appendChild(foot);
      box.appendChild(art);
    });
  }
  function init() {
    var box = document.getElementById('tp-exam-list');
    if (box) { build(box); each(document.querySelectorAll('.tp-src'), function (s) { s.classList.add('is-built'); }); }
    each(document.querySelectorAll('.tqp-tp a[href^="#"]'), function (a) {
      var id = a.getAttribute('href');
      if (id.length === 1) return;
      a.addEventListener('click', function (e) {
        var t = document.querySelector(id);
        if (!t) return;
        e.preventDefault();
        t.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
