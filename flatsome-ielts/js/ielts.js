/* IELTS Landing – tabs (lồng nhau được) + accordion */
(function () {
  function init() {
    document.querySelectorAll('[data-dtt-tabs]').forEach(function (box) {
      var list = box.querySelector(':scope > .dtt-tablist');
      var wrap = box.querySelector(':scope > .dtt-panels');
      if (!list || !wrap) return;
      var btns = list.querySelectorAll(':scope > [data-tab]');
      var panels = wrap.querySelectorAll(':scope > [data-panel]');
      btns.forEach(function (b) {
        b.addEventListener('click', function () {
          var id = b.getAttribute('data-tab');
          btns.forEach(function (x) { x.classList.toggle('is-active', x === b); });
          panels.forEach(function (p) { p.classList.toggle('is-active', p.getAttribute('data-panel') === id); });
        });
      });
    });
    document.querySelectorAll('[data-dtt-acc] .dtt-acc-head').forEach(function (h) {
      h.addEventListener('click', function () { h.parentNode.classList.toggle('is-open'); });
    });
  }
  if (document.readyState !== 'loading') init();
  else document.addEventListener('DOMContentLoaded', init);
})();
