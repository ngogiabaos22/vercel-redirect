/* APTIS landing – JS dùng chung (dán vào UX Block riêng tư, trong thẻ <script>) */
(function () {
  var root = document.documentElement;
  root.classList.add('apt-js');

  function ready(fn){ document.readyState !== 'loading' ? fn() : document.addEventListener('DOMContentLoaded', fn); }

  ready(function () {
    // 1) Tab phiên bản (General / Advanced / for Teens): đổi mô tả + danh sách đề
    var tabs = document.querySelectorAll('[data-apt-tab]');
    function showVersion(v) {
      tabs.forEach(function (t) {
        var on = t.getAttribute('data-apt-tab') === v;
        t.classList.toggle('is-active', on);
        t.setAttribute('aria-selected', on ? 'true' : 'false');
      });
      document.querySelectorAll('[data-apt-panel]').forEach(function (p) {
        p.classList.toggle('is-active', p.getAttribute('data-apt-panel') === v);
      });
    }
    tabs.forEach(function (t) {
      t.addEventListener('click', function () { showVersion(t.getAttribute('data-apt-tab')); });
    });

    // 2) Hiệu ứng hiện dần khi cuộn
    var items = document.querySelectorAll('.apt-reveal');
    if ('IntersectionObserver' in window) {
      var io = new IntersectionObserver(function (es) {
        es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('is-in'); io.unobserve(e.target); } });
      }, { threshold: 0.12 });
      items.forEach(function (el) { io.observe(el); });
    } else {
      items.forEach(function (el) { el.classList.add('is-in'); });
    }
  });
})();
