'use strict';
(function () {
  const { h, call, num, toast, modal } = UI;
  const st = { selected: null, editing: null };

  async function render(App) {
    const [groups, pages] = await Promise.all([call('groups:list'), call('pages:list', {})]);
    if (!groups.find((g) => g.id === st.selected)) st.selected = groups[0] ? groups[0].id : null;
    const sel = groups.find((g) => g.id === st.selected);
    const gById = new Map(groups.map((g) => [g.id, g]));
    const members = sel ? pages.filter((p) => p.group_ids.includes(sel.id)) : [];

    const card = (g) => {
      const editing = st.editing === g.id;
      let nameInput; let purposeInput;
      return h('div', { class: 'group-card' + (g.id === st.selected ? ' on' : '') },
        h('div', { class: 'toolbar', style: { flexWrap: 'nowrap' } },
          h('span', { class: 'swatch', style: { background: g.color } }),
          editing
            ? h('div', { style: { flex: 1, display: 'flex', flexDirection: 'column', gap: '6px' } },
              nameInput = h('input', { class: 'input', value: g.name, 'aria-label': 'Tên nhóm' }),
              purposeInput = h('input', { class: 'input', value: g.purpose || '', placeholder: 'Mục đích (VD: Đối thủ trực tiếp)', 'aria-label': 'Mục đích nhóm' }),
              h('div', { class: 'toolbar' },
                h('button', { class: 'btn primary sm', onclick: async () => { await call('groups:update', g.id, { name: nameInput.value, purpose: purposeInput.value }); st.editing = null; App.render(); } }, 'Lưu'),
                h('button', { class: 'btn sm', onclick: () => { st.editing = null; App.render(); } }, 'Huỷ')))
            : h('button', { class: 'group-title', onclick: () => { st.selected = g.id; App.render(); } },
              h('b', { style: { fontSize: '15px' } }, g.name), h('span', { class: 'muted small' }, g.purpose || 'Chưa có mô tả'))
        ),
        h('div', { class: 'toolbar', style: { justifyContent: 'space-between' } },
          h('span', { class: 'small' }, h('b', null, num(g.page_count)), ' Page · ', h('b', null, num(g.post_count)), ' bài viết'),
          h('span', { class: 'toolbar' },
            h('button', { class: 'btn sm', onclick: () => { st.editing = g.id; App.render(); } }, 'Đổi tên'),
            h('button', { class: 'btn sm danger', onclick: () => confirmDelete(g, App) }, 'Xoá'))
        )
      );
    };

    return h('div', { class: 'page' },
      h('div', { class: 'head' },
        h('div', null, h('h1', null, 'Nhóm đối thủ'), h('p', null, 'Một Page có thể thuộc nhiều nhóm. Xoá nhóm không xoá Page và dữ liệu bài viết. Mỗi nhóm là một tab trên Google Sheet.')),
        h('button', { class: 'btn primary lg', onclick: async () => { const id = await call('groups:create', {}); st.selected = id; st.editing = id; App.render(); } }, '+ Thêm nhóm')
      ),
      h('div', { class: 'row' },
        h('section', { class: 'grow', style: { display: 'flex', flexDirection: 'column', gap: '10px' }, 'aria-label': 'Danh sách nhóm' },
          groups.length ? groups.map(card) : h('div', { class: 'card empty' }, 'Chưa có nhóm nào.')),
        h('section', { class: 'card flush grow2', 'aria-label': 'Page trong nhóm' },
          sel ? [
            h('div', { class: 'card-head', style: { borderBottom: '1px solid var(--line)' } },
              h('div', null, h('h2', null, sel.name), h('span', { class: 'muted small' }, `${members.length} Page trong nhóm`)),
              h('button', { class: 'btn', onclick: () => assignDialog(sel, pages, App) }, 'Gán thêm Page')),
            members.length ? members.map((p) => h('div', { class: 'page-row' },
              h('span', { class: 'avatar' }, p.picture_url ? h('img', { src: p.picture_url, alt: '' }) : initials(p)),
              h('div', { class: 'page-main' }, h('b', null, p.name || p.input), h('span', { class: 'muted small' }, `${p.link || p.input} · ${num(p.post_count)} bài viết`)),
              h('div', { class: 'chips' }, p.group_ids.map((id) => gById.get(id)).filter(Boolean).map((g) => h('span', { class: 'gchip', style: { borderColor: g.color, color: g.color } }, g.name))),
              h('button', { class: 'btn sm', onclick: async () => { await call('pages:removeFromGroup', p.id, sel.id); App.render(); } }, 'Gỡ khỏi nhóm')
            )) : h('div', { class: 'empty' }, 'Nhóm chưa có Page nào. Bấm "Gán thêm Page" hoặc chọn nhóm khi thêm Page.'),
            h('p', { class: 'note', style: { padding: '14px 20px' } }, 'Gỡ Page khỏi nhóm không xoá dữ liệu; Page vẫn được theo dõi ở các nhóm khác.')
          ] : h('div', { class: 'empty' }, 'Tạo một nhóm để bắt đầu.')
        )
      )
    );
  }

  function initials(p) { return String(p.name || p.input || '?').replace(/^https?:\/\/(www\.)?facebook\.com\//, '').slice(0, 1).toUpperCase(); }

  function confirmDelete(g, App) {
    modal((close) => [
      h('h2', null, `Xoá nhóm "${g.name}"?`),
      h('p', { class: 'note' }, `${g.page_count} Page trong nhóm và toàn bộ bài viết vẫn được giữ nguyên. Chỉ quan hệ nhóm bị xoá. Tab trên Google Sheet không bị xoá tự động.`),
      h('div', { class: 'toolbar', style: { justifyContent: 'flex-end' } },
        h('button', { class: 'btn', onclick: close }, 'Huỷ'),
        h('button', { class: 'btn danger solid', onclick: async () => { await call('groups:delete', g.id); close(); toast(`Đã xoá nhóm "${g.name}". Page và bài viết vẫn được giữ.`); App.render(); } }, 'Xoá nhóm'))
    ]);
  }

  function assignDialog(group, pages, App) {
    const chosen = new Set();
    let q = '';
    modal((close, rerender) => {
      const list = pages.filter((p) => !p.group_ids.includes(group.id) && (!q || (p.name || p.input).toLowerCase().includes(q.toLowerCase())));
      const search = h('input', { class: 'input', type: 'search', placeholder: 'Tìm Page', value: q, 'aria-label': 'Tìm Page', oninput: (e) => { q = e.target.value; rerender(); search.focus(); } });
      setTimeout(() => { const el = document.querySelector('.modal input[type=search]'); if (el && q) { el.focus(); el.setSelectionRange(q.length, q.length); } });
      return [
        h('h2', null, `Gán Page vào "${group.name}"`),
        search,
        h('div', { style: { maxHeight: '320px', overflowY: 'auto', display: 'flex', flexDirection: 'column', gap: '4px' } },
          list.length ? list.map((p) => h('label', { class: 'check' }, h('input', { type: 'checkbox', checked: chosen.has(p.id), onchange: (e) => { if (e.target.checked) chosen.add(p.id); else chosen.delete(p.id); } }), p.name || p.input))
            : h('div', { class: 'empty' }, 'Không còn Page nào để gán.')),
        h('div', { class: 'toolbar', style: { justifyContent: 'flex-end' } },
          h('button', { class: 'btn', onclick: close }, 'Huỷ'),
          h('button', { class: 'btn primary', onclick: async () => { if (chosen.size) await call('pages:addToGroup', [...chosen], group.id, false); close(); App.render(); } }, 'Gán vào nhóm'))
      ];
    });
  }

  window.Views = window.Views || {};
  window.Views.groups = { live: false, render };
})();
