/* TOEIC – dựng thẻ đề từ dữ liệu TestSpace ([tqp_category_landing category="TOEIC"]). Không dùng ký tự "nhỏ hơn" và dòng trống để WordPress không làm hỏng mã. */
(function () {
  function norm(s) { s = (s || '').toLowerCase().normalize('NFD'); var o = ''; for (var i = 0; i !== s.length; i++) { var c = s.charCodeAt(i); if (c >= 768 && 879 >= c) continue; o += s[i]; } return o.replace(/đ/g, 'd').trim(); }
  function el(tag, cls, text) { var n = document.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; }
  function each(list, fn) { Array.prototype.forEach.call(list, fn); }
  function fmt(n) { return Number(n || 0).toLocaleString('vi-VN'); }
  function setStat(name, val) { each(document.querySelectorAll('[data-tc-stat="' + name + '"]'), function (n) { n.textContent = val; }); }
  var SKILL = {
    listening: { label: 'TOEIC Listening', pill: '', kn: 'Nghe', parts: ['Part 1', 'Part 2', 'Part 3', 'Part 4'], partsLabel: 'Listening · Part 1–4' },
    reading: { label: 'TOEIC Reading', pill: ' is-reading', kn: 'Đọc', parts: ['Part 5', 'Part 6', 'Part 7'], partsLabel: 'Reading · Part 5–7' },
    general: { label: 'TOEIC', pill: '', kn: 'Nghe + Đọc', parts: [], partsLabel: '' }
  };
  function readCards() {
    var out = [];
    each(document.querySelectorAll('.tc-src .ts-portal-card'), function (card) {
      var h = card.querySelector('.ts-portal-card-title'), a = card.querySelector('a[href]');
      if (!h || !a) return;
      var d = { title: h.textContent.replace(/\s+/g, ' ').trim(), href: a.href, min: 0, q: 0 };
      each(card.querySelectorAll('.ts-portal-meta-item'), function (it) {
        var s = it.querySelector('strong'), n = s ? parseInt(s.textContent, 10) || 0 : 0, t = norm(it.textContent);
        if (t.indexOf('phut') !== -1) d.min = n; else if (t.indexOf('cau') !== -1) d.q = n;
      });
      var t = norm(d.title), m = t.match(/de\s*#?\s*(\d+)/);
      d.no = m ? parseInt(m[1], 10) : 0;
      d.skill = t.indexOf('listening') !== -1 ? 'listening' : (t.indexOf('reading') !== -1 ? 'reading' : 'general');
      d.name = d.title.replace(/\s+-\s+(listening|reading)\s*$/i, '').replace(/\s+-\s+/g, ' – ');
      if (d.no && d.skill !== 'general') d.name = 'TOEIC ' + (d.skill === 'listening' ? 'Listening' : 'Reading') + ' – Đề ' + d.no;
      out.push(d);
    });
    var order = { listening: 1, reading: 2, general: 3 };
    out.sort(function (a, b) { return (order[a.skill] - order[b.skill]) || (a.no - b.no) || a.title.localeCompare(b.title, 'vi'); });
    return out;
  }
  function card(d) {
    var k = SKILL[d.skill], c = el('div', 'tc-card');
    c.setAttribute('data-skill', d.skill);
    var top = el('div', 'tc-card-top');
    top.appendChild(el('span', 'tc-pill' + k.pill, k.label));
    if (d.no) top.appendChild(el('span', 'tc-card-no', 'Đề ' + d.no));
    c.appendChild(top);
    c.appendChild(el('h3', '', d.name));
    var st = el('div', 'tc-card-stats');
    [['Số câu', d.q ? d.q + ' câu' : '–'], ['Thời gian', d.min ? d.min + ' phút' : '–'], ['Kỹ năng', k.kn]].forEach(function (x) {
      var b = el('div'); b.appendChild(el('small', '', x[0])); b.appendChild(el('b', '', x[1])); st.appendChild(b);
    });
    c.appendChild(st);
    if (k.parts.length) {
      c.appendChild(el('div', 'tc-card-parts-label', k.partsLabel));
      var ps = el('div', 'tc-card-parts');
      k.parts.forEach(function (p) { ps.appendChild(el('span', '', p)); });
      c.appendChild(ps);
    }
    var foot = el('div', 'tc-card-foot'), kn = el('span', '', 'Kỹ năng: ');
    kn.appendChild(el('b', '', k.kn));
    foot.appendChild(kn);
    var go = el('a', 'tc-go', 'Làm bài →');
    go.href = d.href;
    foot.appendChild(go);
    c.appendChild(foot);
    return c;
  }
  function init() {
    var src = document.querySelectorAll('.tc-src');
    if (!src.length) return;
    var all = readCards();
    var by = { listening: [], reading: [], general: [] };
    all.forEach(function (d) { by[d.skill].push(d); });
    setStat('total', fmt(all.length));
    setStat('listening', fmt(by.listening.length));
    setStat('reading', fmt(by.reading.length));
    each(document.querySelectorAll('.tc-cards'), function (box) {
      var want = box.getAttribute('data-skill') || 'all';
      var list = want === 'all' ? all : (by[want] || []);
      while (box.firstChild) box.removeChild(box.firstChild);
      list.forEach(function (d) { box.appendChild(card(d)); });
      if (!list.length) box.appendChild(el('div', 'tc-empty', 'Đề thi đang được cập nhật. Bạn quay lại sau nhé!'));
    });
    each(document.querySelectorAll('.tc-tabs'), function (tabs) {
      var box = document.querySelector(tabs.getAttribute('data-target') || '.tc-cards');
      var buttons = tabs.querySelectorAll('.tc-tab');
      each(buttons, function (b) {
        var sk = b.getAttribute('data-skill');
        var n = sk === 'all' ? all.length : (by[sk] || []).length;
        b.appendChild(el('small', '', '(' + n + ')'));
        b.addEventListener('click', function () {
          each(buttons, function (x) { x.classList.remove('is-active'); });
          b.classList.add('is-active');
          if (box) each(box.querySelectorAll('.tc-card'), function (c) { c.style.display = (sk === 'all' || c.getAttribute('data-skill') === sk) ? '' : 'none'; });
        });
      });
    });
    each(document.querySelectorAll('[data-tc-first]'), function (b) {
      var sk = b.getAttribute('data-tc-first'), list = sk === 'all' || !sk ? all : (by[sk] || []);
      if (list[0]) b.href = list[0].href;
    });
    each(src, function (s) { s.classList.add('is-built'); });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
