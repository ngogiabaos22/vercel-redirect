'use strict';
(function () {
  const { h, call, num, dt, dur, toast, jobBadge } = UI;
  const st = { openJob: null };

  async function render(App) {
    const [running, jobs, errors] = await Promise.all([call('jobs:running'), call('jobs:list'), call('jobs:errors')]);
    const labels = (App.state.info && App.state.info.jobLabels) || {};
    const items = st.openJob ? await call('jobs:items', st.openJob) : [];

    return h('div', { class: 'page' },
      h('div', { class: 'head' },
        h('div', null, h('h1', null, 'Lịch sử thu thập'), h('p', null, 'Tiến độ và lỗi riêng cho từng Page · Tự động chạy theo lịch trong Cài đặt')),
        h('div', { class: 'toolbar' },
          h('button', { class: 'btn lg', onclick: async () => { await call('images:run'); toast('Đã bắt đầu tải ảnh theo điều kiện.'); App.render(); } }, 'Tải ảnh ngay'),
          h('button', { class: 'btn lg', onclick: async () => { await call('sheets:run'); toast('Đã bắt đầu đồng bộ Google Sheet.'); App.render(); } }, 'Đồng bộ Sheet ngay'),
          h('button', { class: 'btn primary lg', onclick: async () => { await call('crawl:run', 'manual', null); toast('Đã bắt đầu thu thập tất cả Page.'); App.render(); } }, 'Thu thập thủ công'))
      ),
      running.map((j) => h('section', { class: 'card hl' },
        h('div', { class: 'toolbar', style: { justifyContent: 'space-between' } },
          h('div', null, h('h2', null, `Đang chạy · ${labels[j.job_type] || j.job_type}`),
            h('span', { class: 'muted small' }, `Bắt đầu ${dt(j.started_at)} · ${j.total_pages ? `${j.ok_pages + j.failed_pages}/${j.total_pages} Page · ${num(j.new_posts)} bài mới` : (j.message || 'Đang xử lý…')}`)),
          j.total_pages ? h('button', { class: 'btn danger', onclick: async () => { await call('crawl:cancel', j.id); toast('Đang huỷ — các Page đang chạy sẽ dừng sau trang hiện tại.'); } }, 'Huỷ') : null),
        j.items.slice(0, 50).map((it) => h('div', { class: 'toolbar', style: { flexWrap: 'nowrap' } },
          h('span', { style: { flex: '0 0 200px', fontWeight: 600, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' } }, it.page_name || it.page_input),
          h('div', { style: { flex: 1, display: 'flex', flexDirection: 'column', gap: '4px', minWidth: 0 } },
            h('div', { class: 'bar' }, h('div', { class: it.status === 'error' ? 'err' : '', style: { width: `${it.status === 'done' ? 100 : it.progress || 0}%` } })),
            h('span', { class: 'muted small' }, it.status === 'error' ? `${it.error_code}: ${it.error_message}` : it.status === 'pending' ? 'Đang chờ' : it.step || '')),
          h('span', { style: { width: '48px', textAlign: 'right', fontWeight: 600 } }, `${it.status === 'done' ? 100 : it.progress || 0}%`)))
      )),
      h('section', { class: 'card flush' },
        h('div', { class: 'card-head' }, h('h2', null, 'Các lần thu thập')),
        jobs.length ? h('div', { class: 'tbl-wrap' }, h('table', null,
          h('thead', null, h('tr', null, h('th', null, 'Thời gian'), h('th', null, 'Loại'), h('th', null, 'Kích hoạt'), h('th', null, 'Trạng thái'), h('th', { class: 'num' }, 'Page OK / lỗi'), h('th', { class: 'num' }, 'Bài mới'), h('th', { class: 'num' }, 'Bài cập nhật'), h('th', null, 'Ghi chú'), h('th', null, 'Thời lượng'), h('th', null, ''))),
          h('tbody', null, jobs.map((j) => [
            h('tr', null,
              h('td', { style: { whiteSpace: 'nowrap' } }, dt(j.started_at)),
              h('td', null, labels[j.job_type] || j.job_type),
              h('td', { class: 'muted small' }, { schedule: 'Theo lịch', manual: 'Thủ công', add: 'Khi thêm Page', import: 'Nhập CSV', tray: 'Khay hệ thống' }[j.trigger] || j.trigger),
              h('td', null, jobBadge(j.status)),
              h('td', { class: 'num' }, j.total_pages ? `${num(j.ok_pages)} / ${num(j.failed_pages)}` : '—'),
              h('td', { class: 'num' }, j.total_pages ? num(j.new_posts) : '—'),
              h('td', { class: 'num' }, j.total_pages ? num(j.updated_posts) : '—'),
              h('td', { class: 'small', style: { maxWidth: '280px' } }, j.message || ''),
              h('td', { class: 'muted', style: { whiteSpace: 'nowrap' } }, dur(j.started_at, j.finished_at)),
              h('td', { style: { whiteSpace: 'nowrap' } },
                j.total_pages ? h('button', { class: 'btn sm', onclick: () => { st.openJob = st.openJob === j.id ? null : j.id; App.render(); } }, st.openJob === j.id ? 'Ẩn' : 'Chi tiết') : null,
                j.failed_pages && j.status !== 'running' ? h('button', { class: 'btn sm', style: { marginLeft: '6px' }, onclick: async () => { await call('crawl:retryFailed', j.id); toast('Đang chạy lại các Page lỗi từ checkpoint.'); App.render(); } }, 'Chạy lại Page lỗi') : null)
            ),
            st.openJob === j.id ? h('tr', null, h('td', { colspan: 10, style: { background: 'var(--bg)' } }, itemsTable(items))) : null
          ]))
        )) : h('div', { class: 'empty' }, 'Chưa có lần thu thập nào.')
      ),
      h('section', { class: 'card flush' },
        h('div', { class: 'card-head' }, h('h2', null, 'Lỗi theo Page · lần thu thập gần nhất')),
        errors.length ? h('div', { class: 'tbl-wrap' }, h('table', null,
          h('thead', null, h('tr', null, h('th', null, 'Page'), h('th', null, 'Bước lỗi'), h('th', null, 'Mã lỗi'), h('th', null, 'Mô tả'), h('th', null, 'Tiếp tục từ'), h('th', null, ''))),
          h('tbody', null, errors.map((e) => h('tr', null,
            h('td', { style: { fontWeight: 600 } }, e.page_name), h('td', null, e.step || ''), h('td', null, h('span', { class: 'code' }, e.error_code || 'ERROR')),
            h('td', { class: 'small' }, e.error_message || ''), h('td', { class: 'muted small' }, e.cursor ? 'Checkpoint đã lưu' : '—'),
            h('td', null, h('button', { class: 'btn sm', onclick: async () => { await call('crawl:run', 'retry', [e.page_id]); toast(`Đang chạy lại ${e.page_name}.`); App.render(); } }, e.cursor ? 'Chạy tiếp' : 'Chạy lại'))
          )))
        )) : h('div', { class: 'empty' }, 'Không có lỗi trong lần thu thập gần nhất.')
      )
    );
  }

  function itemsTable(items) {
    return h('table', null,
      h('thead', null, h('tr', null, h('th', null, 'Page'), h('th', null, 'Trạng thái'), h('th', { class: 'num' }, 'Bài đã lấy'), h('th', null, 'Bước'), h('th', null, 'Lỗi'))),
      h('tbody', null, items.map((i) => h('tr', null,
        h('td', null, i.page_name || i.page_input), h('td', null, itemBadge(i.status)),
        h('td', { class: 'num' }, num(i.posts_fetched)), h('td', { class: 'small' }, i.step || ''), h('td', { class: 'small' }, i.error_message ? `${i.error_code}: ${i.error_message}` : '')))));
  }

  function itemBadge(s) {
    const [c, t] = { done: ['b-ok', 'Xong'], error: ['b-err', 'Lỗi'], running: ['b-run', 'Đang chạy'], cancelled: ['b-off', 'Đã huỷ'], skipped: ['b-off', 'Bỏ qua'], pending: ['b-off', 'Đang chờ'] }[s] || ['b-off', s];
    return h('span', { class: `badge ${c}` }, t);
  }

  window.Views = window.Views || {};
  window.Views.history = { live: true, render };
})();
