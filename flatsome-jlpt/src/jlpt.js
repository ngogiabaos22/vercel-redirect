/* ===== JLPT landing – JS trong UX Block "jlpt-style" ===== */
(function () {
  function closeAll(except) {
    document.querySelectorAll('.jl-panel.is-open').forEach(function (p) {
      if (p !== except) p.classList.remove('is-open');
    });
  }
  document.addEventListener('click', function (e) {
    var open = e.target.closest('[data-jl-open]');
    if (open) {
      e.preventDefault();
      var panel = document.getElementById(open.getAttribute('data-jl-open'));
      if (!panel) return;
      closeAll(panel);
      panel.classList.add('is-open');
      panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
      return;
    }
    if (e.target.closest('[data-jl-close]')) {
      e.preventDefault();
      closeAll();
      return;
    }
    var go = e.target.closest('[data-jl-scroll]');
    if (go) {
      var t = document.querySelector(go.getAttribute('data-jl-scroll'));
      if (t) { e.preventDefault(); t.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    }
  });
})();
