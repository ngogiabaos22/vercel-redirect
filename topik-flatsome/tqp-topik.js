/* TQP TOPIK / EPS – JS dùng chung (tab + cuộn mượt)
   Dán vào: UX Block (riêng tư) → Raw JS / hoặc Flatsome > Custom JS (footer) */
(function () {
  function init() {
    document.querySelectorAll('.tqp-tp').forEach(function (root) {
      var tabs = root.querySelectorAll('.tp-tab');
      var panels = root.querySelectorAll('.tp-panel');
      if (!tabs.length || !panels.length) return;

      root.classList.add('js-tabs');

      function show(id, scroll) {
        tabs.forEach(function (t) { t.classList.toggle('is-active', t.dataset.tab === id); });
        panels.forEach(function (p) { p.classList.toggle('is-active', p.id === id); });
        if (scroll) {
          var box = root.querySelector('.tp-tabs');
          if (box) box.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      }

      tabs.forEach(function (t) {
        t.addEventListener('click', function () { show(t.dataset.tab, false); });
      });

      // Nút "Luyện riêng phần này" → mở đúng tab
      root.querySelectorAll('[data-open-tab]').forEach(function (a) {
        a.addEventListener('click', function (e) {
          e.preventDefault();
          show(a.dataset.openTab, true);
        });
      });

      show(tabs[0].dataset.tab, false);
    });

    // Cuộn mượt cho anchor nội bộ (#...)
    document.querySelectorAll('.tqp-tp a[href^="#"]').forEach(function (a) {
      var id = a.getAttribute('href');
      if (id.length < 2) return;
      a.addEventListener('click', function (e) {
        var el = document.querySelector(id);
        if (!el) return;
        e.preventDefault();
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
