'use strict';
/* Khung ứng dụng: điều hướng, nạp màn hình, làm mới khi có sự kiện từ bộ thu thập. */
(function () {
  const { h, icon } = UI;
  const NAV = [
    ['dashboard', 'Dashboard'], ['groups', 'Nhóm đối thủ'], ['pages', 'Danh sách Page'], ['history', 'Lịch sử thu thập'], ['settings', 'Cài đặt']
  ];
  const state = { view: 'dashboard', info: null };
  const main = document.getElementById('main');

  function renderNav() {
    const nav = document.getElementById('nav');
    nav.replaceChildren(...NAV.map(([id, label]) => h('button', {
      type: 'button', class: state.view === id ? 'on' : '', 'aria-current': state.view === id ? 'page' : null, title: label,
      onclick: () => go(id)
    }, icon(id), h('span', null, label))));
  }

  async function refreshInfo() {
    try {
      state.info = await window.api.call('app:info');
      document.getElementById('next-run').textContent = state.info.nextRun;
    } catch { /* bỏ qua */ }
  }

  let renderSeq = 0;
  async function render() {
    const seq = ++renderSeq;
    const view = window.Views[state.view];
    try {
      const node = await view.render(App);
      if (seq !== renderSeq) return; // đã chuyển màn hình khác
      main.replaceChildren(node);
    } catch (e) {
      if (seq !== renderSeq) return;
      main.replaceChildren(h('div', { class: 'page' }, h('div', { class: 'callout err' }, `Không tải được màn hình: ${e.message}`)));
    }
  }

  function go(view, opts) {
    state.view = view;
    state.opts = opts || {};
    renderNav();
    main.scrollTop = 0;
    render();
  }

  // Làm mới khi job chạy (gộp các sự kiện dồn dập)
  let pending = null;
  window.api.onEvent((ev) => {
    if (ev.type !== 'job') return;
    const v = window.Views[state.view];
    if (!v.live) return;
    clearTimeout(pending);
    pending = setTimeout(() => { if (!document.querySelector('.overlay') && !isTyping()) render(); }, ev.finished ? 100 : 1500);
    if (ev.finished) refreshInfo();
  });
  function isTyping() {
    const a = document.activeElement;
    return a && (a.tagName === 'INPUT' || a.tagName === 'TEXTAREA' || a.tagName === 'SELECT');
  }

  const App = { go, render, state, refreshInfo };
  window.App = App;
  renderNav();
  refreshInfo().then(render);
  setInterval(refreshInfo, 60000);
})();
