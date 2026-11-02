'use strict';
(function () {
  const { h, s, call, metric, compact, num, dt, field, nextId, jobBadge } = UI;
  const COLORS = ['#1D4ED8', '#F97316', '#0D9488', '#7C3AED', '#DB2777'];
  const TYPE_LABEL = { photo: 'Ảnh', video: 'Video', link: 'Liên kết', status: 'Văn bản' };
  const TYPE_COLOR = { photo: '#1D4ED8', video: '#60A5FA', link: '#F97316', status: '#CBD5E1' };
  const filters = { days: 7, groupId: '', pageId: '' };

  async function render(App) {
    const [data, groups, pages] = await Promise.all([
      call('stats:dashboard', { days: filters.days, groupId: filters.groupId ? Number(filters.groupId) : null, pageId: filters.pageId ? Number(filters.pageId) : null }),
      call('groups:list'), call('pages:list', {})
    ]);
    const k = data.kpi;
    const sel = (opts, value, onChange) => h('select', { class: 'input', onchange: (e) => onChange(e.target.value) },
      opts.map(([v, t]) => h('option', { value: v, selected: String(v) === String(value) }, t)));
    const setF = (key) => (v) => { filters[key] = key === 'days' ? Number(v) : v; App.render(); };

    return h('div', { class: 'page' },
      h('div', { class: 'head' },
        h('div', null, h('h1', null, 'Dashboard'), h('p', null, `Tổng hợp dữ liệu Page đối thủ · Lần chạy tiếp: ${App.state.info ? App.state.info.nextRun : ''}`)),
        h('button', { class: 'btn primary lg', onclick: async () => { await call('crawl:run', 'manual', null); UI.toast('Đã bắt đầu thu thập tất cả Page đang theo dõi.'); App.go('history'); } }, 'Thu thập thủ công')
      ),
      h('section', { class: 'card', 'aria-label': 'Bộ lọc' },
        h('div', { class: 'grid' },
          field('Thời gian', sel([[7, '7 ngày qua'], [30, '30 ngày qua'], [90, '90 ngày qua'], [365, '12 tháng qua']], filters.days, setF('days')), nextId()),
          field('Nhóm', sel([['', 'Tất cả nhóm'], ...groups.map((g) => [g.id, g.name])], filters.groupId, setF('groupId')), nextId()),
          field('Page', sel([['', 'Tất cả Page'], ...pages.map((p) => [p.id, p.name || p.input])], filters.pageId, setF('pageId')), nextId()),
          h('div', null, h('button', { class: 'btn', onclick: () => { Object.assign(filters, { days: 7, groupId: '', pageId: '' }); App.render(); } }, 'Đặt lại'))
        )
      ),
      h('section', { class: 'kpis', 'aria-label': 'Chỉ số chính' },
        kpi('Tổng Page', num(k.pages), `${num(k.enabledPages)} đang theo dõi`),
        kpi('Tổng bài viết', num(k.posts), `Trong ${filters.days} ngày đã chọn`),
        kpi('Tổng ảnh đã lưu', num(k.images), `${num(k.imagesOnDrive)} ảnh đã lên Google Drive`),
        kpi('Bài mới hôm nay', num(k.today), data.lastJob ? `Lần thu thập gần nhất ${dt(data.lastJob.started_at)}` : 'Chưa thu thập lần nào')
      ),
      !k.pages ? h('div', { class: 'callout info' }, 'Chưa có Page nào. Vào "Danh sách Page" để thêm Page đối thủ, và nhập kết nối Meta trong "Cài đặt".') : null,
      h('div', { class: 'row' },
        h('section', { class: 'card grow2' },
          h('div', { class: 'toolbar', style: { justifyContent: 'space-between' } }, h('h2', null, 'Số bài đăng theo ngày của từng Page'),
            h('div', { class: 'legend' }, data.perDay.map((p, i) => h('span', null, h('span', { class: 'dot', style: { background: COLORS[i] } }), p.name)))),
          lineChart(data.days, data.perDay)
        ),
        h('section', { class: 'card grow' }, h('h2', null, 'Tỷ lệ loại bài viết'), typeRatio(data.types))
      ),
      h('div', { class: 'row' },
        h('section', { class: 'card grow' }, h('h2', null, 'Tương tác theo nhóm'),
          h('div', { class: 'tbl-wrap' }, h('table', null,
            h('thead', null, h('tr', null, h('th', null, 'Nhóm'), h('th', { class: 'num' }, 'Bài'), h('th', { class: 'num' }, 'Reactions'), h('th', { class: 'num' }, 'Comments'), h('th', { class: 'num' }, 'Shares'))),
            h('tbody', null, data.byGroup.map((g) => h('tr', null,
              h('td', null, h('span', { style: { display: 'inline-flex', gap: '8px', alignItems: 'center' } }, h('span', { class: 'dot', style: { background: g.color } }), g.name)),
              h('td', { class: 'num' }, num(g.posts)),
              h('td', { class: 'num' }, g.posts ? metric(g.reactions) : '—'), h('td', { class: 'num' }, g.posts ? metric(g.comments) : '—'), h('td', { class: 'num' }, g.posts ? metric(g.shares) : '—')
            )))
          )),
          h('p', { class: 'note' }, 'Page thuộc nhiều nhóm được tính trong từng nhóm, nên tổng các nhóm có thể lớn hơn tổng chung.')
        ),
        h('section', { class: 'card grow' }, h('h2', null, 'Xu hướng tương tác (reactions + comments + shares)'), trendBars(data.trend))
      ),
      h('div', { class: 'row' },
        h('section', { class: 'card grow2' },
          h('h2', null, 'Top bài viết tương tác cao'),
          data.topPosts.length ? h('div', { class: 'tbl-wrap' }, h('table', null,
            h('thead', null, h('tr', null, h('th', null, 'Bài viết'), h('th', null, 'Page'), h('th', null, 'Ngày đăng'), h('th', { class: 'num' }, 'Reactions'), h('th', { class: 'num' }, 'Comments'), h('th', { class: 'num' }, 'Shares'))),
            h('tbody', null, data.topPosts.map((p) => h('tr', null,
              h('td', { class: 'ellipsis' }, p.permalink_url ? h('a', { href: p.permalink_url, target: '_blank' }, p.message || '(Không có caption)') : (p.message || '(Không có caption)')),
              h('td', { style: { whiteSpace: 'nowrap' } }, p.page_name), h('td', { style: { whiteSpace: 'nowrap' } }, dt(p.created_time)),
              h('td', { class: 'num' }, metric(p.reactions)), h('td', { class: 'num' }, metric(p.comments)), h('td', { class: 'num' }, metric(p.shares))
            )))
          )) : h('div', { class: 'empty' }, 'Chưa có bài viết trong khoảng thời gian này.')
        ),
        h('section', { class: 'card grow' }, h('h2', null, 'Top Page đăng nhiều bài nhất'),
          data.topPages.length ? data.topPages.map((p) => h('div', { class: 'hbar' },
            h('div', { class: 't' }, h('span', null, p.name), h('b', null, `${num(p.n)} bài`)),
            h('div', { class: 'bar' }, h('div', { style: { width: Math.round(p.n / data.topPages[0].n * 100) + '%' } }))
          )) : h('div', { class: 'empty' }, 'Chưa có dữ liệu.'),
          data.lastJob ? h('div', { class: 'toolbar', style: { borderTop: '1px solid var(--line2)', paddingTop: '12px' } },
            h('span', { class: 'muted small' }, `Lần thu thập gần nhất: ${dt(data.lastJob.started_at)}`), jobBadge(data.lastJob.status)) : null
        )
      )
    );
  }

  function kpi(label, value, sub) { return h('div', { class: 'kpi' }, h('span', null, label), h('b', null, value), h('small', null, sub)); }

  function lineChart(days, series) {
    if (!series.length) return h('div', { class: 'empty' }, 'Chưa có bài viết trong khoảng thời gian này.');
    const W = 640; const H = 220; const L = 36; const R = 10; const T = 16; const B = 30;
    const max = Math.max(1, ...series.flatMap((x) => x.values));
    const step = days.length > 1 ? (W - L - R) / (days.length - 1) : 0;
    const x = (i) => L + i * step;
    const y = (v) => T + (H - T - B) * (1 - v / max);
    const grid = [0, 0.5, 1].map((f) => {
      const v = Math.round(max * f);
      return [s('line', { x1: L, x2: W - R, y1: y(v), y2: y(v), stroke: '#E2E8F0' }), s('text', { x: 4, y: y(v) + 4, 'font-size': 11, fill: '#64748B' }, String(v))];
    });
    const every = Math.ceil(days.length / 7);
    const labels = days.map((d, i) => (i % every === 0 || i === days.length - 1 ? s('text', { x: x(i) - 14, y: H - 8, 'font-size': 11, fill: '#64748B' }, d.slice(8, 10) + '/' + d.slice(5, 7)) : null));
    const lines = series.map((sr, si) => s('polyline', { fill: 'none', stroke: COLORS[si], 'stroke-width': 2.5, 'stroke-linejoin': 'round', points: sr.values.map((v, i) => `${x(i)},${y(v)}`).join(' ') }));
    return s('svg', { viewBox: `0 0 ${W} ${H}`, class: 'chart', role: 'img', 'aria-label': 'Biểu đồ số bài đăng theo ngày' }, grid, labels, lines);
  }

  function typeRatio(types) {
    const total = types.reduce((a, t) => a + t.n, 0);
    if (!total) return h('div', { class: 'empty' }, 'Chưa có dữ liệu.');
    const order = ['photo', 'video', 'link', 'status'];
    const rows = order.map((t) => ({ t, n: (types.find((x) => x.t === t) || { n: 0 }).n }));
    return h('div', { style: { display: 'flex', flexDirection: 'column', gap: '12px' } },
      h('div', { class: 'bar', style: { height: '14px' } }, rows.map((r) => h('div', { style: { width: (r.n / total * 100) + '%', background: TYPE_COLOR[r.t] } }))),
      rows.map((r) => h('div', { class: 'hbar' }, h('div', { class: 't' },
        h('span', { style: { display: 'inline-flex', gap: '8px', alignItems: 'center' } }, h('span', { class: 'swatch', style: { background: TYPE_COLOR[r.t] } }), TYPE_LABEL[r.t]),
        h('b', null, `${Math.round(r.n / total * 100)}% · ${num(r.n)}`))))
    );
  }

  function trendBars(trend) {
    const vals = trend.map((t) => t.total).filter((v) => v !== null);
    if (!vals.length) return h('div', { class: 'empty' }, 'Không khả dụng — chưa có số liệu tương tác.');
    const max = Math.max(1, ...vals);
    const every = Math.ceil(trend.length / 10);
    return h('div', null,
      h('div', { class: 'bars' }, trend.map((t) => h('div', { class: 'col', title: t.total === null ? 'Không khả dụng' : num(t.total) },
        h('small', null, t.total === null ? '—' : compact(t.total)),
        h('i', { style: { height: (t.total === null ? 0 : Math.max(2, t.total / max * 85)) + '%' } })))),
      h('div', { class: 'xlabels' }, trend.map((t, i) => h('span', null, i % every === 0 ? t.d.slice(8, 10) + '/' + t.d.slice(5, 7) : '')))
    );
  }

  window.Views = window.Views || {};
  window.Views.dashboard = { live: true, render };
})();
