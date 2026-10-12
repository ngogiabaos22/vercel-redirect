/* Đại học – trang chính (lọc/tìm môn) + trang môn (dựng danh sách đề từ TestSpace). Không dùng ký tự "nhỏ hơn" và dòng trống để WordPress không làm hỏng mã. */
(function () {
  function norm(s) { s = (s || '').toLowerCase().normalize('NFD'); var o = ''; for (var i = 0; i !== s.length; i++) { var c = s.charCodeAt(i); if (c >= 768 && 879 >= c) continue; o += s[i]; } return o.replace(/đ/g, 'd').trim(); }
  function el(tag, cls, text) { var n = document.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; }
  function fmt(n) { return Number(n || 0).toLocaleString('vi-VN'); }
  function each(list, fn) { Array.prototype.forEach.call(list, fn); }
  function setStat(name, val) { each(document.querySelectorAll('[data-dh-stat="' + name + '"]'), function (n) { n.textContent = val; }); }
  /* ---------- Trang chính /dai-hoc/ ---------- */
  function initHub() {
    var cards = document.querySelectorAll('.dh-subject');
    if (!cards.length) return;
    var input = document.querySelector('.dh-search input');
    var tabs = document.querySelectorAll('.dh-tab');
    var groups = document.querySelectorAll('.dh-group');
    var noResult = document.querySelector('.dh-noresult');
    var active = 'all';
    var counts = {};
    each(cards, function (c) { var g = c.getAttribute('data-group'); counts[g] = (counts[g] || 0) + 1; });
    setStat('total', fmt(cards.length));
    setStat('open', fmt(document.querySelectorAll('.dh-subject .dh-cover').length));
    Object.keys(counts).forEach(function (g) { setStat(g, fmt(counts[g])); });
    function groupOf(sec) { var m = sec.className.match(/dh-g-([a-z-]+)/); return m ? m[1] : ''; }
    function filter() {
      var q = norm(input ? input.value : '');
      var shown = 0;
      each(groups, function (sec) {
        var g = groupOf(sec), vis = 0;
        each(sec.querySelectorAll('.dh-subject'), function (c) {
          var ok = (active === 'all' || active === g) && (!q || norm(c.textContent).indexOf(q) !== -1);
          c.style.display = ok ? '' : 'none';
          if (ok) vis++;
        });
        sec.style.display = vis ? '' : 'none';
        shown += vis;
      });
      if (noResult) noResult.style.display = shown ? 'none' : 'block';
    }
    function goResults() {
      var first = null;
      each(groups, function (sec) { if (!first && sec.style.display !== 'none') first = sec; });
      var target = first || noResult;
      if (target) window.scrollTo({ top: target.getBoundingClientRect().top + window.pageYOffset - 130, behavior: 'smooth' });
    }
    each(tabs, function (t) {
      t.addEventListener('click', function () {
        each(tabs, function (x) { x.classList.remove('is-active'); });
        t.classList.add('is-active');
        active = t.getAttribute('data-group') || 'all';
        filter();
        goResults();
      });
    });
    if (input) {
      input.addEventListener('input', filter);
      input.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); filter(); goResults(); } });
    }
    each(document.querySelectorAll('.dh-search button'), function (b) { b.addEventListener('click', function (e) { e.preventDefault(); filter(); goResults(); }); });
    each(document.querySelectorAll('.dh-chip'), function (chip) {
      chip.addEventListener('click', function () { if (input) input.value = chip.getAttribute('data-q') || chip.textContent; filter(); goResults(); });
    });
  }
  /* ---------- Trang môn học: đọc thẻ đề của TestSpace ---------- */
  function readCards(src) {
    var out = [];
    each(src.querySelectorAll('.ts-portal-card'), function (card) {
      var h = card.querySelector('.ts-portal-card-title'), a = card.querySelector('a[href]');
      if (!h || !a) return;
      var d = { title: h.textContent.replace(/\s+/g, ' ').trim(), href: a.href, min: 0, q: 0 };
      each(card.querySelectorAll('.ts-portal-meta-item'), function (it) {
        var s = it.querySelector('strong'), n = s ? parseInt(s.textContent, 10) || 0 : 0, t = norm(it.textContent);
        if (t.indexOf('phut') !== -1) d.min = n; else if (t.indexOf('cau') !== -1) d.q = n;
      });
      var ch = norm(d.title).match(/chuong\s*(\d+)/), de = norm(d.title).match(/de\s*#?\s*(\d+)/);
      d.chuong = ch ? parseInt(ch[1], 10) : 0;
      d.de = de ? parseInt(de[1], 10) : 0;
      out.push(d);
    });
    return out;
  }
  function shortTitle(t) {
    var x = t.replace(/^thi thử\s+/i, '').replace(/^trắc nghiệm\s+/i, '').replace(/^ôn tập\s+/i, '').replace(/^môn\s+/i, '').replace(/\s+online(?=\s|$)/i, '').replace(/\s+-\s+/g, ' – ');
    return 'Trắc nghiệm ' + x.charAt(0).toUpperCase() + x.slice(1);
  }
  function schoolCode(title) {
    var w = title.split(/[\s\-–]+/).filter(Boolean), last = w[w.length - 1] || '';
    return /^[A-ZĐ0-9]{2,7}$/.test(last) ? last : 'ĐH';
  }
  function row(d, badge, isSchool) {
    var a = el('a', 'dh-item');
    a.href = d.href;
    a.appendChild(el('div', 'dh-num' + (isSchool ? ' is-school' : ''), badge));
    var body = el('div', 'dh-item-body');
    body.appendChild(el('div', 'dh-item-title', isSchool ? d.title : shortTitle(d.title)));
    a.title = d.title;
    var meta = el('div', 'dh-item-meta');
    if (d.q) meta.appendChild(el('span', '', '☰ ' + d.q + ' câu'));
    if (d.min) meta.appendChild(el('span', '', '◷ ' + d.min + ' phút'));
    body.appendChild(meta);
    a.appendChild(body);
    a.appendChild(el('span', 'dh-chev', '›'));
    return a;
  }
  function fill(id, items, school) {
    var box = document.getElementById(id);
    if (!box) return;
    while (box.firstChild) box.removeChild(box.firstChild);
    items.forEach(function (d, i) { box.appendChild(row(d, school ? schoolCode(d.title) : String(i + 1), school)); });
    var panel = box.closest('.dh-panel');
    var head = panel ? panel.querySelector('.dh-panel-title') : null;
    if (head && !head.querySelector('[data-dh-count]')) { var c = el('span', ''); c.setAttribute('data-dh-count', id); head.appendChild(c); }
    each(document.querySelectorAll('[data-dh-count="' + id + '"]'), function (n) { n.textContent = items.length + ' đề'; });
    if (panel) panel.style.display = items.length ? '' : 'none';
  }
  function initSubject() {
    var srcMon = document.querySelector('.dh-src-mon'), srcTruong = document.querySelector('.dh-src-truong');
    if (!srcMon && !srcTruong) return;
    var mon = srcMon ? readCards(srcMon) : [], truong = srcTruong ? readCards(srcTruong) : [];
    var byDe = function (a, b) { return (a.de - b.de) || a.title.localeCompare(b.title, 'vi'); };
    var chuong = mon.filter(function (d) { return d.chuong; }).sort(function (a, b) { return (a.chuong - b.chuong) || byDe(a, b); });
    var tong = mon.filter(function (d) { return !d.chuong; }).sort(byDe);
    fill('dh-list-tonghop', tong, false);
    fill('dh-list-chuong', chuong, false);
    fill('dh-list-truong', truong, true);
    if (srcMon) srcMon.classList.add('is-built');
    if (srcTruong) srcTruong.classList.add('is-built');
    var emptyMon = document.getElementById('dh-empty-mon');
    if (emptyMon) emptyMon.style.display = mon.length ? 'none' : '';
    var all = mon.concat(truong);
    setStat('de', fmt(all.length));
    setStat('cau', fmt(all.reduce(function (s, d) { return s + d.q; }, 0)));
    setStat('chuong', fmt(chuong.length));
    setStat('truong', fmt(truong.length));
    setStat('mon', mon.length + ' đề');
    setStat('truong-count', truong.length + ' đề');
    var first = tong[0] || chuong[0] || truong[0];
    each(document.querySelectorAll('[data-dh-first]'), function (b) { if (first) b.href = first.href; });
    if (!truong.length) {
      each(document.querySelectorAll('.dh-g-truong'), function (s) { s.style.display = 'none'; });
      each(document.querySelectorAll('[data-dh-schools]'), function (b) { b.style.display = 'none'; });
    }
  }
  function init() { initHub(); initSubject(); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
