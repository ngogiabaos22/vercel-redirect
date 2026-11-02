'use strict';
(function () {
  const { h, call, num, ago, toast, modal, switchBtn, badgeForStatus, nextId } = UI;
  const st = { tab: 'all', q: '', status: '', showAdd: false, sel: new Set(), add: { input: '', startDate: '', groups: new Set(), crawlNow: true } };

  async function render(App) {
    const [groups, all, cfg] = await Promise.all([call('groups:list'), call('pages:list', {}), call('config:get')]);
    const gById = new Map(groups.map((g) => [g.id, g]));
    if (st.tab !== 'all' && !gById.has(Number(st.tab))) st.tab = 'all';
    const pages = all.filter((p) => (st.tab === 'all' || p.group_ids.includes(Number(st.tab)))
      && (!st.q || [p.name, p.input, p.facebook_page_id, p.username].some((x) => x && String(x).toLowerCase().includes(st.q.toLowerCase())))
      && (!st.status || (st.status === 'disabled' ? !p.enabled : p.enabled && p.status === st.status)));
    for (const id of [...st.sel]) if (!all.find((p) => p.id === id)) st.sel.delete(id);
    if (!st.add.startDate) st.add.startDate = cfg.crawl.defaultStartDate || '';
    if (!st.addInit) { st.add.crawlNow = cfg.crawl.initialOnAdd !== false; st.addInit = true; }

    return h('div', { class: 'page' },
      h('div', { class: 'head' },
        h('div', null, h('h1', null, 'Danh sách Page'), h('p', null, `${num(all.length)} Page · ${num(all.filter((p) => p.enabled).length)} đang theo dõi`)),
        h('div', { class: 'toolbar' },
          h('button', { class: 'btn lg', onclick: () => call('pages:saveCsvTemplate').then((p) => p && toast(`Đã lưu file mẫu: ${p}`)) }, 'Tải file CSV mẫu'),
          h('button', { class: 'btn lg', onclick: () => importCsv(App) }, 'Nhập CSV'),
          h('button', { class: 'btn primary lg', onclick: () => { st.showAdd = !st.showAdd; App.render(); } }, st.showAdd ? 'Đóng' : '+ Thêm Page'))
      ),
      st.showAdd ? addForm(groups, App) : null,
      h('section', { class: 'card flush' },
        h('div', { class: 'tabs', role: 'tablist', style: { padding: '8px 12px 0' } },
          [['all', 'Tất cả', all.length], ...groups.map((g) => [String(g.id), g.name, all.filter((p) => p.group_ids.includes(g.id)).length])].map(([id, label, n]) =>
            h('button', { type: 'button', role: 'tab', class: st.tab === id ? 'on' : '', 'aria-selected': st.tab === id ? 'true' : 'false', onclick: () => { st.tab = id; App.render(); } }, label, h('span', { class: 'count' }, n)))),
        h('div', { class: 'toolbar', style: { padding: '12px 16px', borderBottom: '1px solid var(--line)' } },
          h('input', { class: 'input', type: 'search', placeholder: 'Tìm theo tên, link, Page ID', value: st.q, style: { flex: '1 1 240px', width: 'auto' }, 'aria-label': 'Tìm Page', onchange: (e) => { st.q = e.target.value; App.render(); } }),
          h('select', { class: 'input', style: { width: 'auto' }, 'aria-label': 'Trạng thái', onchange: (e) => { st.status = e.target.value; App.render(); } },
            [['', 'Tất cả trạng thái'], ['active', 'Hoạt động'], ['pending', 'Chờ xử lý'], ['running', 'Đang thu thập'], ['error', 'Lỗi'], ['disabled', 'Tạm dừng']].map(([v, t]) => h('option', { value: v, selected: st.status === v }, t)))
        ),
        st.sel.size ? bulkBar(groups, App) : null,
        pages.length ? pages.map((p) => row(p, gById, App)) : h('div', { class: 'empty' }, all.length ? 'Không có Page phù hợp bộ lọc.' : 'Chưa có Page nào. Bấm "+ Thêm Page" hoặc "Nhập CSV".')
      )
    );
  }

  function addForm(groups, App) {
    const a = st.add;
    const idUrl = nextId(); const idDate = nextId();
    return h('section', { class: 'card hl', 'aria-label': 'Thêm Page' },
      h('h2', null, 'Thêm Page mới'),
      h('div', { class: 'toolbar', style: { alignItems: 'flex-end' } },
        h('div', { class: 'field', style: { flex: '1 1 320px' } }, h('label', { for: idUrl }, 'Dán link Page hoặc nhập Page ID'),
          h('input', { id: idUrl, class: 'input', value: a.input, placeholder: 'https://facebook.com/ten-page hoặc 1029384756', oninput: (e) => { a.input = e.target.value; } })),
        h('div', { class: 'field', style: { flex: '0 1 200px' } }, h('label', { for: idDate }, 'Lấy bài viết từ ngày'),
          h('input', { id: idDate, class: 'input', type: 'date', value: a.startDate, onchange: (e) => { a.startDate = e.target.value; } }))
      ),
      h('fieldset', { style: { margin: 0, padding: 0, border: 'none', display: 'flex', flexDirection: 'column', gap: '8px' } },
        h('legend', { class: 'lbl', style: { paddingBottom: '8px' } }, 'Chọn nhóm theo dõi (chọn được nhiều nhóm)'),
        h('div', { class: 'toolbar' }, groups.map((g) => h('label', { class: 'chip-check' + (a.groups.has(g.id) ? ' on' : '') },
          h('input', { type: 'checkbox', checked: a.groups.has(g.id), onchange: (e) => { if (e.target.checked) a.groups.add(g.id); else a.groups.delete(g.id); App.render(); } }), g.name)))
      ),
      h('div', { class: 'toolbar', style: { justifyContent: 'space-between' } },
        h('label', { class: 'check' }, h('input', { type: 'checkbox', checked: a.crawlNow, onchange: (e) => { a.crawlNow = e.target.checked; } }), 'Thu thập ban đầu ngay sau khi thêm'),
        h('button', { class: 'btn primary lg', onclick: async () => {
          const r = await call('pages:add', { input: a.input, groupIds: [...a.groups], startDate: a.startDate || null, crawlNow: a.crawlNow });
          toast(r.crawling ? 'Đã thêm Page và bắt đầu thu thập ban đầu.' : a.crawlNow ? 'Đã thêm Page. Chưa có Access Token Meta nên chưa thu thập — nhập token trong Cài đặt › Kết nối Meta.' : 'Đã thêm Page.');
          st.add = { input: '', startDate: a.startDate, groups: a.groups, crawlNow: a.crawlNow };
          App.render();
        } }, 'Thêm Page'))
    );
  }

  function bulkBar(groups, App) {
    const ids = [...st.sel];
    const move = h('select', { class: 'input', style: { width: 'auto', minHeight: '36px' }, 'aria-label': 'Chuyển hoặc thêm vào nhóm' },
      h('option', { value: '' }, 'Chọn nhóm…'), groups.map((g) => h('option', { value: g.id }, g.name)));
    return h('div', { class: 'bulk' },
      h('b', { style: { color: '#1E3A8A' } }, `Đã chọn ${ids.length} Page`),
      move,
      h('button', { class: 'btn sm', onclick: async () => { if (!move.value) return toast('Chọn nhóm trước.', true); await call('pages:addToGroup', ids, Number(move.value), false); toast('Đã thêm vào nhóm.'); App.render(); } }, 'Thêm vào nhóm'),
      h('button', { class: 'btn sm', onclick: async () => { if (!move.value) return toast('Chọn nhóm trước.', true); await call('pages:addToGroup', ids, Number(move.value), true); toast('Đã chuyển nhóm.'); App.render(); } }, 'Chuyển sang nhóm'),
      h('button', { class: 'btn sm primary', onclick: async () => { await call('crawl:run', 'manual', ids); toast(`Đã bắt đầu thu thập ${ids.length} Page.`); } }, 'Thu thập ngay'),
      h('button', { class: 'btn sm', onclick: async () => { await call('pages:setEnabled', ids, false); App.render(); } }, 'Tạm dừng theo dõi'),
      h('button', { class: 'btn sm', onclick: async () => { await call('pages:setEnabled', ids, true); App.render(); } }, 'Bật theo dõi'),
      h('button', { class: 'btn sm danger', onclick: () => deleteDialog(ids, `${ids.length} Page đã chọn`, App) }, 'Xoá khỏi danh sách'),
      h('button', { class: 'btn sm', onclick: () => { st.sel.clear(); App.render(); } }, 'Bỏ chọn')
    );
  }

  function row(p, gById, App) {
    const name = p.name || p.input;
    return h('div', { class: 'page-row' + (st.sel.has(p.id) ? ' sel' : '') },
      h('input', { type: 'checkbox', checked: st.sel.has(p.id), 'aria-label': `Chọn ${name}`, style: { width: '18px', height: '18px' }, onchange: (e) => { if (e.target.checked) st.sel.add(p.id); else st.sel.delete(p.id); App.render(); } }),
      h('span', { class: 'avatar' }, p.picture_url ? h('img', { src: p.picture_url, alt: '' }) : String(name).slice(0, 1).toUpperCase()),
      h('div', { class: 'page-main' },
        h('b', null, name),
        h('span', { class: 'muted small' }, p.link ? p.link.replace(/^https?:\/\/(www\.)?/, '') : p.input, p.facebook_page_id ? ` · ID ${p.facebook_page_id}` : ''),
        h('span', { class: 'small', style: { color: 'var(--ink2)' } }, `${num(p.post_count)} bài viết · ${p.status === 'running' ? 'Đang thu thập…' : ago(p.last_crawl_at)}${p.start_date ? ` · Lấy từ ${p.start_date.split('-').reverse().join('/')}` : ''}`),
        p.status === 'error' && p.last_error_message ? h('span', { class: 'small', style: { color: 'var(--err)' } }, p.last_error_message) : null
      ),
      h('div', { class: 'chips' },
        p.group_ids.map((id) => gById.get(id)).filter(Boolean).map((g) => h('span', { class: 'gchip', style: { borderColor: g.color, color: g.color } }, g.name)),
        h('button', { class: 'btn sm', style: { minHeight: '26px', padding: '0 8px', fontSize: '12px' }, onclick: () => groupsDialog(p, [...gById.values()], App) }, 'Sửa nhóm')),
      badgeForStatus(p.status, !!p.enabled),
      switchBtn(!!p.enabled, async (on) => { await call('pages:setEnabled', [p.id], on); App.render(); }, `Theo dõi ${name}`),
      h('div', { class: 'toolbar', style: { gap: '6px' } },
        h('button', { class: 'btn sm', onclick: async () => { await call('crawl:run', p.initial_done ? 'manual' : 'initial', [p.id]); toast(`Đang thu thập ${name}.`); App.render(); } }, 'Thu thập ngay'),
        h('button', { class: 'btn sm', onclick: async () => { await call('crawl:run', 'info', [p.id]); toast(`Đang cập nhật thông tin ${name}.`); } }, 'Cập nhật info'),
        h('button', { class: 'btn sm icon-btn danger', 'aria-label': `Xoá ${name}`, title: 'Xoá', onclick: () => deleteDialog([p.id], name, App, p.group_ids.length) }, UI.icon('trash', 16)))
    );
  }

  function groupsDialog(p, groups, App) {
    const chosen = new Set(p.group_ids);
    modal((close, rerender) => [
      h('h2', null, `Nhóm của "${p.name || p.input}"`),
      h('div', { class: 'toolbar' }, groups.map((g) => h('label', { class: 'chip-check' + (chosen.has(g.id) ? ' on' : '') },
        h('input', { type: 'checkbox', checked: chosen.has(g.id), onchange: (e) => { if (e.target.checked) chosen.add(g.id); else chosen.delete(g.id); rerender(); } }), g.name))),
      h('div', { class: 'toolbar', style: { justifyContent: 'flex-end' } },
        h('button', { class: 'btn', onclick: close }, 'Huỷ'),
        h('button', { class: 'btn primary', onclick: async () => { await call('pages:setGroups', p.id, [...chosen]); close(); App.render(); } }, 'Lưu'))
    ]);
  }

  function deleteDialog(ids, label, App, groupCount) {
    let keep = true;
    let confirmText = '';
    modal((close, rerender) => [
      h('h2', null, `Xoá "${label}" khỏi danh sách theo dõi?`),
      h('p', { class: 'note' }, groupCount ? `Page sẽ ngừng được thu thập và bị gỡ khỏi ${groupCount} nhóm.` : 'Page sẽ ngừng được thu thập và bị gỡ khỏi các nhóm.'),
      h('label', { class: 'opt' + (keep ? ' on' : '') }, h('input', { type: 'radio', name: 'keep', checked: keep, onchange: () => { keep = true; rerender(); } }),
        h('span', null, h('b', null, 'Giữ lại dữ liệu'), h('br'), h('span', { class: 'muted small' }, 'Bài viết, ảnh và lịch sử tương tác vẫn còn trong Dashboard và Google Sheet.'))),
      h('label', { class: 'opt danger' + (!keep ? ' on' : '') }, h('input', { type: 'radio', name: 'keep', checked: !keep, onchange: () => { keep = false; rerender(); } }),
        h('span', null, h('b', null, 'Xoá toàn bộ dữ liệu'), h('br'), h('span', { class: 'muted small' }, 'Xoá vĩnh viễn bài viết, ảnh trên máy, lịch sử tương tác. Ảnh trên Drive và dòng trên Sheet giữ nguyên.'))),
      !keep ? h('div', { class: 'field' }, h('label', { for: 'del-confirm' }, 'Gõ XOA để xác nhận'),
        h('input', { id: 'del-confirm', class: 'input', value: confirmText, oninput: (e) => { confirmText = e.target.value; } })) : null,
      h('div', { class: 'toolbar', style: { justifyContent: 'flex-end' } },
        h('button', { class: 'btn', onclick: close }, 'Huỷ'),
        h('button', { class: keep ? 'btn primary' : 'btn danger solid', onclick: async () => {
          if (!keep && confirmText.trim().toUpperCase() !== 'XOA') return toast('Gõ XOA để xác nhận xoá dữ liệu.', true);
          await call('pages:delete', ids, keep);
          ids.forEach((id) => st.sel.delete(id));
          close(); toast(keep ? 'Đã ngừng theo dõi, dữ liệu được giữ lại.' : 'Đã xoá Page và dữ liệu.'); App.render();
        } }, keep ? 'Ngừng theo dõi' : 'Xoá Page và dữ liệu'))
    ]);
  }

  async function importCsv(App) {
    let crawlNow = true;
    modal((close) => [
      h('h2', null, 'Nhập danh sách Page từ CSV'),
      h('p', { class: 'note' }, 'Cột 1: link hoặc Page ID · Cột 2: tên nhóm (nhiều nhóm cách nhau bởi |, nhóm chưa có sẽ được tạo) · Cột 3: lấy bài từ ngày (YYYY-MM-DD, tuỳ chọn). Bấm "Tải file CSV mẫu" để xem ví dụ.'),
      h('label', { class: 'check' }, h('input', { type: 'checkbox', checked: crawlNow, onchange: (e) => { crawlNow = e.target.checked; } }), 'Thu thập ban đầu ngay sau khi nhập'),
      h('div', { class: 'toolbar', style: { justifyContent: 'flex-end' } },
        h('button', { class: 'btn', onclick: close }, 'Huỷ'),
        h('button', { class: 'btn primary', onclick: async () => {
          const r = await call('pages:importCsv', { crawlNow });
          if (!r) return;
          close();
          toast(`Đã nhập ${r.added} Page${r.errors.length ? ` · ${r.errors.length} dòng bị bỏ qua` : ''}${crawlNow && r.added && !r.crawling ? ' · Chưa có Access Token Meta nên chưa thu thập' : ''}.`, !!r.errors.length && !r.added);
          if (r.errors.length) modal((c2) => [h('h2', null, 'Các dòng bị bỏ qua'), h('div', { style: { maxHeight: '320px', overflowY: 'auto' } }, r.errors.map((e) => h('div', { class: 'small', style: { padding: '4px 0' } }, e))), h('div', { class: 'toolbar', style: { justifyContent: 'flex-end' } }, h('button', { class: 'btn', onclick: c2 }, 'Đóng'))]);
          App.render();
        } }, 'Chọn file CSV…'))
    ]);
  }

  window.Views = window.Views || {};
  window.Views.pages = { live: true, render };
})();
