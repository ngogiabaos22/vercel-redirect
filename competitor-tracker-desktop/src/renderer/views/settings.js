'use strict';
(function () {
  const { h, call, toast, switchBtn, nextId, num } = UI;
  const st = { tab: 'general', draft: null, metaTest: null, googleTest: null, preview: null, testInput: '' };
  const TABS = [['general', 'Chung'], ['rules', 'Điều kiện tải ảnh'], ['google', 'Google Sheets & Drive'], ['meta', 'Kết nối Meta']];

  async function render(App) {
    const [cfg, info, groups, scriptInfo] = await Promise.all([call('config:get'), call('app:info'), call('groups:list'), call('google:scriptInfo')]);
    if (!st.draft) st.draft = JSON.parse(JSON.stringify(cfg));
    const d = st.draft;
    const body = { general: general, rules: rulesTab, google: googleTab, meta: metaTab }[st.tab](d, info, groups, App, scriptInfo);
    return h('div', { class: 'page' },
      h('div', { class: 'head' }, h('div', null, h('h1', null, 'Cài đặt'), h('p', null, 'Lịch thu thập, điều kiện tải ảnh, Google Drive / Sheets và kết nối Meta Graph API'))),
      h('nav', { class: 'tabs', 'aria-label': 'Mục cài đặt' }, TABS.map(([id, label]) => h('button', { type: 'button', class: st.tab === id ? 'on' : '', 'aria-current': st.tab === id ? 'page' : null, onclick: () => { st.tab = id; st.draft = null; App.render(); } }, label))),
      body,
      h('div', { class: 'toolbar', style: { justifyContent: 'flex-end' } },
        h('button', { class: 'btn lg', onclick: () => { st.draft = null; App.render(); } }, 'Huỷ thay đổi'),
        h('button', { class: 'btn primary lg', onclick: () => save(App) }, 'Lưu cấu hình'))
    );
  }

  async function save(App, quiet) {
    const d = st.draft;
    const patch = {
      meta: d.meta, crawl: { ...d.crawl, oldPostsLimit: int(d.crawl.oldPostsLimit), workers: int(d.crawl.workers), retries: int(d.crawl.retries) },
      images: { ...d.images, withinDays: int(d.images.withinDays), maxPerDay: int(d.images.maxPerDay), maxPerPost: int(d.images.maxPerPost), retentionMonths: int(d.images.retentionMonths) || 0,
        minReactions: nullable(d.images.minReactions), minComments: nullable(d.images.minComments), minShares: nullable(d.images.minShares) },
      drive: d.drive, sheets: d.sheets, app: d.app
    };
    st.draft = await call('config:save', patch);
    if (!quiet) toast('Đã lưu cấu hình.');
    App.refreshInfo();
    App.render();
  }
  const int = (v) => parseInt(v, 10);
  const nullable = (v) => (v === '' || v === null || v === undefined ? null : Math.max(0, parseInt(v, 10) || 0));

  // ---------- helpers ----------
  function inp(obj, key, attrs = {}, after) {
    return h('input', { class: 'input', value: obj[key] ?? '', ...attrs, oninput: (e) => { obj[key] = e.target.value; if (after) after(); } });
  }
  function fld(label, control) { const id = nextId(); control.id = id; return h('div', { class: 'field' }, h('label', { for: id }, label), control); }
  function sel(obj, key, options, after) {
    return h('select', { class: 'input', onchange: (e) => { obj[key] = e.target.value; if (after) after(); } }, options.map(([v, t]) => h('option', { value: v, selected: String(obj[key]) === String(v) }, t)));
  }
  function toggleRow(label, sub, obj, key, App) {
    return h('div', { class: 'toolbar', style: { justifyContent: 'space-between', flexWrap: 'nowrap' } },
      h('div', null, h('div', { style: { fontWeight: 600 } }, label), sub ? h('div', { class: 'muted small' }, sub) : null),
      switchBtn(!!obj[key], (on) => { obj[key] = on; App.render(); }, label));
  }
  function radio(name, obj, key, value, title, sub, App) {
    const on = obj[key] === value;
    return h('label', { class: 'opt' + (on ? ' on' : '') }, h('input', { type: 'radio', name, checked: on, onchange: () => { obj[key] = value; App.render(); } }),
      h('span', null, h('b', null, title), sub ? [h('br'), h('span', { class: 'muted small' }, sub)] : null));
  }

  // ---------- Chung ----------
  function general(d, info, groups, App) {
    return h('div', { class: 'row' },
      h('section', { class: 'card grow2' },
        h('h2', null, 'Lịch thu thập tự động'),
        toggleRow('Tự động thu thập hằng ngày', 'Kiểm tra bài viết mới và cập nhật tương tác bài cũ', d.crawl, 'enabled', App),
        h('div', { class: 'grid' },
          fld('Giờ chạy hằng ngày (giờ Việt Nam)', inp(d.crawl, 'time', { type: 'time' })),
          fld('Số bài cũ cập nhật / Page', inp(d.crawl, 'oldPostsLimit', { type: 'number', min: 0, max: 100 })),
          fld('Số Page chạy song song', inp(d.crawl, 'workers', { type: 'number', min: 1, max: 20 })),
          fld('Số lần thử lại khi lỗi', inp(d.crawl, 'retries', { type: 'number', min: 0, max: 10 }))),
        h('label', { class: 'check' }, h('input', { type: 'checkbox', checked: d.crawl.catchUp, onchange: (e) => { d.crawl.catchUp = e.target.checked; } }), 'Chạy bù trong ngày nếu máy tắt / app đóng đúng giờ chạy'),
        h('label', { class: 'check' }, h('input', { type: 'checkbox', checked: d.crawl.initialOnAdd, onchange: (e) => { d.crawl.initialOnAdd = e.target.checked; } }), 'Mặc định thu thập ban đầu ngay khi thêm Page'),
        h('div', { class: 'callout info' }, 'App cần đang mở (hoặc chạy ẩn dưới khay hệ thống) để lịch tự động hoạt động. Bật "Khởi động cùng máy tính" bên cạnh để không bỏ lỡ lịch.')
      ),
      h('div', { class: 'grow', style: { display: 'flex', flexDirection: 'column', gap: '16px' } },
        h('section', { class: 'card' },
          h('h2', null, 'Lưu trữ ảnh'),
          radio('store', d.images, 'storage', 'local', 'Chỉ trên máy tính', 'Ảnh lưu trong thư mục bên dưới', App),
          radio('store', d.images, 'storage', 'local_drive', 'Máy tính + Google Drive', 'Lưu trên máy trước, đồng bộ Drive sau (khuyên dùng)', App),
          radio('store', d.images, 'storage', 'drive', 'Chỉ Google Drive', 'Xoá bản trên máy sau khi lên Drive thành công', App),
          h('div', { class: 'field' }, h('span', { class: 'lbl' }, 'Thư mục lưu ảnh trên máy'),
            h('div', { class: 'toolbar', style: { flexWrap: 'nowrap' } },
              h('input', { class: 'input', value: d.images.folder || info.imagesDir, readonly: true, 'aria-label': 'Thư mục lưu ảnh' }),
              h('button', { class: 'btn', onclick: async () => { const p = await call('dialog:pickFolder'); if (p) { d.images.folder = p; App.render(); } } }, 'Chọn…'),
              h('button', { class: 'btn', onclick: () => call('shell:openPath', d.images.folder || info.imagesDir).catch(() => {}) }, 'Mở'))),
          fld('Tự xoá ảnh trên máy sau (tháng, 0 = không xoá)', inp(d.images, 'retentionMonths', { type: 'number', min: 0, max: 120 }))
        ),
        h('section', { class: 'card' },
          h('h2', null, 'Ứng dụng'),
          toggleRow('Chạy ẩn dưới khay hệ thống khi đóng cửa sổ', 'Để lịch thu thập vẫn chạy', d.app, 'minimizeToTray', App),
          toggleRow('Khởi động cùng máy tính', 'Windows / macOS', d.app, 'launchAtLogin', App),
          h('dl', { class: 'kv' }, h('dt', null, 'Phiên bản'), h('dd', null, info.version), h('dt', null, 'Dữ liệu'), h('dd', null, info.dataDir)),
          h('button', { class: 'btn', onclick: () => call('shell:openPath', info.dataDir) }, 'Mở thư mục dữ liệu')
        )
      )
    );
  }

  // ---------- Điều kiện tải ảnh ----------
  let previewTimer = null;
  function schedulePreview(App) {
    clearTimeout(previewTimer);
    previewTimer = setTimeout(async () => {
      const i = st.draft.images;
      st.preview = await call('images:preview', { ...i, withinDays: int(i.withinDays) || 1, maxPerPost: int(i.maxPerPost) || 1 });
      const box = document.getElementById('rules-preview');
      if (box) box.replaceChildren(...previewNodes());
    }, 300);
    void App;
  }
  function previewNodes() {
    const p = st.preview;
    if (!p) return [h('span', { class: 'muted' }, 'Đang tính…')];
    return [
      h('div', { class: 'callout info' }, p.summary),
      h('div', null, h('b', { style: { fontSize: '30px' } }, num(p.match)), h('span', { class: 'muted' }, ` / ${num(p.total)} bài trong khoảng đang xét đạt điều kiện`)),
      h('div', { class: 'bar' }, h('div', { style: { width: (p.total ? p.match / p.total * 100 : 0) + '%' } })),
      h('span', { class: 'small' }, `Ước tính ${num(p.images)} ảnh sẽ được tải`)
    ];
  }

  function rulesTab(d, info, groups, App) {
    const i = d.images;
    const upd = () => schedulePreview(App);
    schedulePreview(App);
    return h('div', { class: 'row' },
      h('div', { class: 'grow2', style: { display: 'flex', flexDirection: 'column', gap: '16px' } },
        h('section', { class: 'card' },
          h('h2', null, 'Phạm vi lấy bài viết'),
          h('p', { class: 'note' }, 'Mặc định cho Page mới. Có thể đặt riêng cho từng Page khi thêm Page.'),
          h('div', { class: 'grid' }, fld('Lấy bài viết từ ngày', inp(d.crawl, 'defaultStartDate', { type: 'date' }))),
          h('div', { class: 'callout warn' }, 'Meta API có thể không trả về bài quá cũ. Ngày bắt đầu chỉ áp dụng cho lần thu thập ban đầu của Page.')
        ),
        h('section', { class: 'card' },
          h('div', { class: 'toolbar', style: { justifyContent: 'space-between' } },
            h('div', null, h('h2', null, 'Điều kiện tương tác để tải ảnh'), h('p', { class: 'note' }, 'Ô để trống được tính là 0 (không giới hạn).')),
            h('div', { class: 'seg', role: 'group', 'aria-label': 'Cách kết hợp điều kiện' },
              h('button', { type: 'button', class: i.mode === 'and' ? 'on' : '', 'aria-pressed': i.mode === 'and' ? 'true' : 'false', onclick: () => { i.mode = 'and'; App.render(); } }, 'Thoả TẤT CẢ'),
              h('button', { type: 'button', class: i.mode === 'or' ? 'on' : '', 'aria-pressed': i.mode === 'or' ? 'true' : 'false', onclick: () => { i.mode = 'or'; App.render(); } }, 'Thoả MỘT trong các'))),
          h('div', { class: 'grid' },
            fld('Reaction ≥', inp(i, 'minReactions', { type: 'number', min: 0, placeholder: '0' }, upd)),
            fld('Comment ≥', inp(i, 'minComments', { type: 'number', min: 0, placeholder: '0' }, upd)),
            fld('Share ≥', inp(i, 'minShares', { type: 'number', min: 0, placeholder: '0' }, upd))),
          h('fieldset', { style: { border: 'none', margin: 0, padding: 0, display: 'flex', flexDirection: 'column', gap: '4px' } },
            h('legend', { class: 'lbl', style: { paddingBottom: '6px' } }, 'Khi API không trả về một chỉ số (Không khả dụng)'),
            h('label', { class: 'check' }, h('input', { type: 'radio', name: 'na', checked: i.naPolicy !== 'fail', onchange: () => { i.naPolicy = 'ignore'; upd(); } }), 'Bỏ qua điều kiện đó, xét các chỉ số còn lại'),
            h('label', { class: 'check' }, h('input', { type: 'radio', name: 'na', checked: i.naPolicy === 'fail', onchange: () => { i.naPolicy = 'fail'; upd(); } }), 'Coi là không đạt, không tải ảnh'))
        ),
        h('section', { class: 'card flush' },
          h('div', { class: 'card-head' }, h('div', null, h('h2', null, 'Điều kiện riêng theo nhóm'), h('p', { class: 'note' }, 'Lưu ngay khi thay đổi. Page thuộc nhiều nhóm: tải nếu đạt điều kiện của ít nhất một nhóm.'))),
          h('div', { class: 'tbl-wrap' }, h('table', null,
            h('thead', null, h('tr', null, h('th', null, 'Nhóm'), h('th', null, 'Áp dụng'), h('th', null, 'Reaction ≥'), h('th', null, 'Comment ≥'), h('th', null, 'Share ≥'))),
            h('tbody', null, groups.map((g) => {
              const custom = g.image_mode === 'custom';
              const save = (patch) => call('groups:update', g.id, patch).then(() => { if ('image_mode' in patch) App.render(); else schedulePreview(App); });
              const numIn = (key, label) => h('input', { class: 'input', type: 'number', min: 0, style: { width: '100px' }, disabled: !custom, value: g[key] ?? '', placeholder: '0', 'aria-label': `${label} ${g.name}`, onchange: (e) => save({ [key]: e.target.value }) });
              return h('tr', null,
                h('td', { style: { fontWeight: 600 } }, g.name),
                h('td', null, h('select', { class: 'input', style: { width: 'auto' }, 'aria-label': `Áp dụng cho ${g.name}`, onchange: (e) => save({ image_mode: e.target.value }) },
                  [['default', 'Dùng mặc định'], ['custom', 'Điều kiện riêng'], ['all', 'Tải tất cả ảnh'], ['none', 'Không tải ảnh']].map(([v, t]) => h('option', { value: v, selected: g.image_mode === v }, t)))),
                h('td', null, numIn('min_reactions', 'Reaction tối thiểu')), h('td', null, numIn('min_comments', 'Comment tối thiểu')), h('td', null, numIn('min_shares', 'Share tối thiểu')));
            }))))
        )
      ),
      h('div', { class: 'grow', style: { display: 'flex', flexDirection: 'column', gap: '16px' } },
        h('section', { class: 'card' },
          h('h2', null, 'Lịch tải ảnh theo ngày'),
          toggleRow('Tự động tải ảnh hằng ngày', null, i, 'enabled', App),
          h('div', { class: 'grid' },
            fld('Giờ chạy tải ảnh', inp(i, 'time', { type: 'time' })),
            fld('Xét bài đăng trong (ngày)', inp(i, 'withinDays', { type: 'number', min: 1, max: 365 }, upd)),
            fld('Tối đa ảnh / ngày', inp(i, 'maxPerDay', { type: 'number', min: 1 })),
            fld('Tối đa ảnh / bài', inp(i, 'maxPerPost', { type: 'number', min: 1, max: 100 }, upd))),
          h('p', { class: 'note' }, 'Nên đặt sau giờ thu thập để dùng số tương tác mới nhất. Bài chưa đạt hôm nay sẽ được xét lại các ngày sau trong khoảng đã chọn.')
        ),
        h('section', { class: 'card' }, h('h2', null, 'Xem trước với dữ liệu hiện có'), h('div', { id: 'rules-preview', style: { display: 'flex', flexDirection: 'column', gap: '10px' } }, previewNodes()))
      )
    );
  }

  // ---------- Google ----------
  function googleTab(d, info, groups, App, scriptInfo) {
    const simple = d.drive.authType === 'apps_script';
    const connected = simple && d.drive.scriptUrl && scriptInfo;
    const step = (n, title, ...body) => h('div', { style: { display: 'flex', gap: '12px', alignItems: 'flex-start' } },
      h('span', { style: { flex: 'none', width: '28px', height: '28px', borderRadius: '999px', background: 'var(--blue-soft)', color: 'var(--blue-d)', fontWeight: 700, display: 'flex', alignItems: 'center', justifyContent: 'center' } }, String(n)),
      h('div', { style: { flex: 1, minWidth: 0, display: 'flex', flexDirection: 'column', gap: '8px' } }, h('b', null, title), ...body));
    const urlIn = h('input', { class: 'input', placeholder: 'https://script.google.com/macros/s/…/exec', value: st.scriptUrl ?? d.drive.scriptUrl ?? '', 'aria-label': 'URL ứng dụng web', oninput: (e) => { st.scriptUrl = e.target.value; } });

    const simpleCard = h('section', { class: 'card' },
      h('div', { class: 'toolbar', style: { justifyContent: 'space-between' } },
        h('h2', null, 'Kết nối Google Sheets + Drive'),
        connected ? h('span', { class: 'badge b-ok' }, 'Đã kết nối') : h('span', { class: 'badge b-off' }, 'Chưa kết nối')),
      connected ? [
        h('div', { class: 'callout ok' }, 'App đã kết nối với file Google Sheet của bạn. Ảnh sẽ được lưu vào thư mục Drive do app tự tạo.'),
        h('dl', { class: 'kv' },
          h('dt', null, 'Google Sheet'), h('dd', null, h('a', { href: scriptInfo.url, target: '_blank' }, scriptInfo.title + ' ↗')),
          h('dt', null, 'Thư mục ảnh'), h('dd', null, h('a', { href: scriptInfo.folderUrl, target: '_blank' }, scriptInfo.folderName + ' ↗'))),
        h('div', { class: 'toolbar' },
          h('button', { class: 'btn', onclick: async () => { st.googleTest = await call('google:test'); toast('Kết nối Google hoạt động bình thường.'); App.render(); } }, 'Kiểm tra lại'),
          h('button', { class: 'btn danger', onclick: async () => { await call('google:disconnectScript'); st.draft = null; st.scriptUrl = ''; App.render(); } }, 'Ngắt kết nối'))
      ] : [
        h('p', { class: 'note' }, 'Không cần Google Cloud. Chỉ cần một file Google Sheet và copy – dán, khoảng 3 phút. Làm một lần duy nhất.'),
        step(1, 'Tạo một file Google Sheet mới (hoặc mở file có sẵn)',
          h('div', null, h('button', { class: 'btn', onclick: () => call('shell:openExternal', 'https://sheets.new') }, 'Tạo Google Sheet mới ↗'))),
        step(2, 'Mở Apps Script và dán mã kết nối',
          h('span', { class: 'small muted' }, 'Trong Google Sheet: menu Tiện ích mở rộng → Apps Script. Xoá hết đoạn mã có sẵn, dán mã vừa sao chép, rồi bấm biểu tượng Lưu (hoặc Ctrl + S).'),
          h('div', null, h('button', { class: 'btn primary', onclick: async () => { const code = await call('google:scriptCode'); await call('clipboard:write', code); toast('Đã sao chép mã. Dán vào Apps Script rồi bấm Lưu.'); } }, 'Sao chép mã'))),
        step(3, 'Triển khai thành Ứng dụng web',
          h('span', { class: 'small muted' }, 'Bấm Triển khai → Tùy chọn triển khai mới → biểu tượng bánh răng → chọn Ứng dụng web. Đặt "Thực thi với tư cách: Tôi" và "Người có quyền truy cập: Bất kỳ ai" → Triển khai.'),
          h('span', { class: 'small muted' }, 'Google sẽ hỏi cấp quyền: chọn tài khoản → Nâng cao → Đi tới … (không an toàn) → Cho phép. Đây là mã của chính bạn nên Google hiện cảnh báo như vậy là bình thường.')),
        step(4, 'Dán "URL ứng dụng web" vào đây',
          h('div', { class: 'toolbar', style: { flexWrap: 'nowrap' } }, urlIn,
            h('button', { class: 'btn primary', onclick: async () => {
              const r = await call('google:connectScript', urlIn.value);
              st.draft = null; st.scriptUrl = undefined;
              toast(`Đã kết nối: ${r.title}`);
              if (r.outdated) toast('Mã Apps Script đã cũ — bấm Sao chép mã và dán lại để cập nhật.', true);
              App.render();
            } }, 'Kết nối')))
      ],
      h('p', { class: 'note' }, 'Link ứng dụng web được bảo vệ bằng khoá kết nối riêng nằm trong mã. Không chia sẻ mã và link này cho người khác.'),
      h('button', { class: 'btn sm', style: { alignSelf: 'flex-start' }, onclick: () => { d.drive.authType = 'service_account'; App.render(); } }, 'Cách khác (nâng cao): Service Account / OAuth')
    );

    const sa = d.drive.authType === 'service_account';
    const advancedCard = h('section', { class: 'card' },
      h('div', { class: 'toolbar', style: { justifyContent: 'space-between' } },
        h('h2', null, 'Kết nối nâng cao (Google Cloud)'),
        h('button', { class: 'btn sm', onclick: () => { d.drive.authType = 'apps_script'; App.render(); } }, '← Quay lại cách đơn giản')),
      radio('auth', d.drive, 'authType', 'service_account', 'Service Account', 'Dùng file khoá JSON. Ảnh phải lưu vào Shared Drive; chia sẻ thư mục và file Sheet cho email của Service Account.', App),
      radio('auth', d.drive, 'authType', 'oauth', 'Đăng nhập tài khoản Google', 'Dùng OAuth Client (loại Desktop app) tạo trong Google Cloud Console.', App),
      sa ? h('div', { class: 'toolbar' },
        h('button', { class: 'btn', onclick: async () => { const email = await call('google:pickServiceAccount'); if (email) { d.drive.accountEmail = email; toast(`Đã lưu khoá Service Account: ${email}`); App.render(); } } }, info.secrets.googleServiceAccount ? 'Thay file khoá JSON…' : 'Chọn file khoá JSON…'),
        h('span', { class: 'small ' + (info.secrets.googleServiceAccount ? '' : 'muted') }, info.secrets.googleServiceAccount ? `Đã lưu · ${d.drive.accountEmail || ''}` : 'Chưa có khoá'))
        : [
          fld('OAuth Client ID', inp(d.drive, 'oauthClientId', { placeholder: 'xxxx.apps.googleusercontent.com' })),
          secretField('OAuth Client Secret', 'googleOAuthSecret', info, App),
          h('div', { class: 'toolbar' },
            h('button', { class: 'btn', onclick: async () => { await save(App, true); toast('Đang mở trình duyệt để đăng nhập Google…'); const email = await call('google:oauthLogin'); toast(`Đã đăng nhập Google: ${email || 'OK'}`); App.render(); } }, 'Đăng nhập Google'),
            h('span', { class: 'small' }, info.secrets.googleRefreshToken ? `Đã đăng nhập · ${d.drive.accountEmail || ''}` : 'Chưa đăng nhập'))
        ],
      fld('Link hoặc ID thư mục Google Drive gốc để lưu ảnh', inp(d.drive, 'rootFolderId', { placeholder: 'https://drive.google.com/drive/folders/…' })),
      fld('Link hoặc ID file Google Sheet', inp(d.sheets, 'spreadsheetId', { placeholder: 'https://docs.google.com/spreadsheets/d/…' })),
      h('div', { class: 'toolbar' }, h('button', { class: 'btn', onclick: async () => { await save(App, true); st.googleTest = await call('google:test'); App.render(); } }, 'Kiểm tra kết nối Google'))
    );

    return h('div', { class: 'row' },
      h('div', { class: 'grow', style: { display: 'flex', flexDirection: 'column', gap: '16px' } },
        simple ? simpleCard : advancedCard,
        st.googleTest ? h('section', { class: 'card' }, h('h3', null, 'Kết quả kiểm tra'), h('dl', { class: 'kv' }, h('dt', null, 'Drive'), h('dd', null, st.googleTest.drive), h('dt', null, 'Sheets'), h('dd', null, st.googleTest.sheets))) : null
      ),
      h('div', { class: 'grow', style: { display: 'flex', flexDirection: 'column', gap: '16px' } },
        h('section', { class: 'card' },
          h('h2', null, 'Ghi dữ liệu ra Google Sheet'),
          toggleRow('Đồng bộ dữ liệu ra Google Sheet', 'Mỗi nhóm là 1 tab', d.sheets, 'enabled', App),
          h('div', { class: 'grid' },
            fld('Lịch đồng bộ', sel(d.sheets, 'schedule', [['after_images', 'Sau khi tải ảnh xong mỗi ngày'], ['time', 'Mỗi ngày vào giờ cố định'], ['manual', 'Chỉ khi bấm Đồng bộ ngay']], () => App.render())),
            d.sheets.schedule === 'time' ? fld('Giờ đồng bộ', inp(d.sheets, 'time', { type: 'time' })) : null,
            fld('Link ảnh ghi vào Sheet', sel(d.sheets, 'linkType', [['drive', 'Link Google Drive (ưu tiên)'], ['local', 'Đường dẫn trên máy'], ['both', 'Cả hai']]))),
          h('fieldset', { style: { border: 'none', margin: 0, padding: 0, display: 'flex', flexDirection: 'column', gap: '4px' } },
            h('legend', { class: 'lbl', style: { paddingBottom: '6px' } }, 'Ghi dữ liệu'),
            h('label', { class: 'check' }, h('input', { type: 'radio', name: 'wm', checked: d.sheets.writeMode !== 'append', onchange: () => { d.sheets.writeMode = 'update'; } }), 'Thêm dòng mới, cập nhật link ảnh và tương tác của dòng cũ'),
            h('label', { class: 'check' }, h('input', { type: 'radio', name: 'wm', checked: d.sheets.writeMode === 'append', onchange: () => { d.sheets.writeMode = 'append'; } }), 'Chỉ thêm dòng mới, không sửa dòng cũ')),
          h('label', { class: 'check' }, h('input', { type: 'checkbox', checked: d.sheets.summaryTab, onchange: (e) => { d.sheets.summaryTab = e.target.checked; } }), 'Thêm tab "Tổng hợp" chứa tất cả bài'),
          h('label', { class: 'check' }, h('input', { type: 'checkbox', checked: d.sheets.onlyMatched, onchange: (e) => { d.sheets.onlyMatched = e.target.checked; } }), 'Chỉ ghi bài đã đạt điều kiện tải ảnh'),
          h('p', { class: 'note' }, 'Cột: Ngày đăng · Page · Link bài viết · Caption · Link ảnh · Reaction · Comment · Share · Trạng thái sử dụng · Ghi chú · Post ID. Hai cột "Trạng thái sử dụng" và "Ghi chú" để bạn tự điền, app không ghi đè. Không sửa cột Post ID.'),
          info.lastSheetSync ? h('span', { class: 'small muted' }, `Đồng bộ gần nhất: ${UI.dt(info.lastSheetSync.at)} · ${info.lastSheetSync.appended} dòng mới · ${info.lastSheetSync.updated} dòng cập nhật`) : null,
          h('button', { class: 'btn', onclick: async () => { await save(App, true); await call('sheets:run'); toast('Đã bắt đầu đồng bộ Google Sheet — xem tiến độ ở Lịch sử thu thập.'); } }, 'Đồng bộ ngay')
        ),
        h('section', { class: 'card' },
          h('h2', null, 'Tab theo nhóm'),
          groups.map((g) => h('div', { class: 'toolbar', style: { flexWrap: 'nowrap', padding: '8px 10px', border: '1px solid var(--line)', borderRadius: '8px' } },
            h('span', { class: 'swatch', style: { background: g.color } }),
            h('div', { style: { flex: 1 } }, h('b', null, g.name), h('div', { class: 'muted small' }, `Tab "${g.name}" · ${num(g.post_count)} bài`)),
            switchBtn(!!g.sheet_enabled, async (on) => { await call('groups:update', g.id, { sheet_enabled: on }); App.render(); }, `Đồng bộ nhóm ${g.name}`)))
        )
      )
    );
  }

  function secretField(label, name, info, App) {
    const id = nextId();
    const input = h('input', { id, class: 'input', type: 'password', autocomplete: 'off', placeholder: info.secrets[name] ? '•••••••• (đã lưu — nhập để thay)' : 'Chưa nhập' });
    return h('div', { class: 'field' }, h('label', { for: id }, label),
      h('div', { class: 'toolbar', style: { flexWrap: 'nowrap' } }, input,
        h('button', { class: 'btn', onclick: async () => { if (!input.value.trim()) return toast('Nhập giá trị trước khi lưu.', true); await call('secrets:set', name, input.value.trim()); toast(`Đã lưu ${label} (mã hoá trên máy).`); App.refreshInfo(); App.render(); } }, 'Lưu'),
        info.secrets[name] ? h('button', { class: 'btn danger', onclick: async () => { await call('secrets:set', name, ''); toast(`Đã xoá ${label}.`); App.render(); } }, 'Xoá') : null));
  }

  // ---------- Meta ----------
  function metaTab(d, info, groups, App) {
    const t = st.metaTest;
    const yn = (v) => (v ? 'Có' : h('span', { class: 'na' }, 'Không khả dụng'));
    const testIn = h('input', { class: 'input', placeholder: 'https://facebook.com/ten-page-doi-thu hoặc Page ID', value: st.testInput, 'aria-label': 'Page để kiểm tra', oninput: (e) => { st.testInput = e.target.value; } });
    return h('div', { class: 'row' },
      h('section', { class: 'card grow' },
        h('h2', null, 'Meta Graph API'),
        h('div', { class: 'grid' },
          fld('App ID', inp(d.meta, 'appId', { placeholder: 'META_APP_ID' })),
          fld('Phiên bản API', inp(d.meta, 'version', { placeholder: 'v21.0' }))),
        secretField('Access Token (System User / long-lived)', 'metaToken', info, App),
        h('p', { class: 'note' }, 'Token được mã hoá bằng cơ chế bảo mật của hệ điều hành và chỉ lưu trên máy này. Để lấy dữ liệu Page đối thủ, app Meta cần được duyệt quyền Page Public Content Access (PPCA).'),
        h('div', { class: 'callout warn' }, 'Chỉ dùng Meta Graph API chính thức. Không dùng cookie cá nhân, tài khoản giả lập hay scraping.')
      ),
      h('section', { class: 'card grow' },
        h('h2', null, 'Kiểm tra với 1 Page mẫu (PoC)'),
        h('p', { class: 'note' }, 'Kiểm tra token, quyền truy cập và những trường dữ liệu API trả về cho một Page đối thủ.'),
        h('div', { class: 'toolbar', style: { flexWrap: 'nowrap' } }, testIn,
          h('button', { class: 'btn primary', onclick: async () => {
            await save(App, true);
            st.metaTest = { loading: true }; App.render();
            try { st.metaTest = await call('meta:test', st.testInput); } catch (e) { st.metaTest = { error: e.message }; }
            App.render();
          } }, 'Kiểm tra')),
        t && t.loading ? h('span', { class: 'muted' }, 'Đang kiểm tra…') : null,
        t && t.error ? h('div', { class: 'callout err' }, t.error) : null,
        t && t.page ? h('dl', { class: 'kv' },
          h('dt', null, 'Page'), h('dd', null, t.page),
          h('dt', null, 'Bài viết'), h('dd', null, t.posts),
          t.fields ? [h('dt', null, 'Caption'), h('dd', null, yn(t.fields.caption)), h('dt', null, 'Reactions'), h('dd', null, yn(t.fields.reactions)),
            h('dt', null, 'Comments'), h('dd', null, yn(t.fields.comments)), h('dt', null, 'Shares'), h('dd', null, yn(t.fields.shares)), h('dt', null, 'Ảnh'), h('dd', null, yn(t.fields.images))] : null,
          t.usage ? [h('dt', null, 'API usage'), h('dd', null, JSON.stringify(t.usage))] : null) : null
      )
    );
  }

  window.Views = window.Views || {};
  window.Views.settings = { live: false, render };
})();
