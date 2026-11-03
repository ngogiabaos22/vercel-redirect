/* PageHub renderer: talks to the main process through window.ph.call(name, ...args). */
const $ = s => document.querySelector(s);
const $$ = s => [...document.querySelectorAll(s)];
const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
const pad = n => String(n).padStart(2, '0');
const fmt = t => { if (!t) return '—'; const d = new Date(t); return `${pad(d.getDate())}/${pad(d.getMonth() + 1)} ${pad(d.getHours())}:${pad(d.getMinutes())}`; };
const nf = n => Number(n || 0).toLocaleString('vi-VN');
const COLORS = ['#3559E0', '#D2603A', '#1F9A8A', '#B0457A', '#7A5AF8', '#0E8C9E', '#6B8E23', '#A0522D'];
const colorOf = id => { let h = 0; for (const c of String(id)) h = (h * 31 + c.charCodeAt(0)) >>> 0; return COLORS[h % COLORS.length]; };
const ini = n => (String(n || '?').trim().split(/\s+/).pop() || '?')[0].toUpperCase();
const av = (id, name, sq) => `<span class="av" style="background:${colorOf(id)};${sq ? 'border-radius:9px' : ''}" title="${esc(name)}">${esc(ini(name))}</span>`;

let S = null; // public state from main
let toastTimer;
function toast(msg, err) {
  const t = $('#toast');
  t.textContent = msg; t.className = 'toast show' + (err ? ' err' : '');
  clearTimeout(toastTimer); toastTimer = setTimeout(() => (t.className = 'toast'), err ? 6000 : 3000);
}
async function call(name, ...args) {
  const r = await window.ph.call(name, ...args);
  if (!r.ok) throw new Error(r.error);
  return r.data;
}
async function act(fn, okMsg) {
  try { const v = await fn(); if (okMsg) toast(typeof okMsg === 'function' ? okMsg(v) : okMsg); return v; }
  catch (e) { toast(e.message, true); return undefined; }
}
const copy = (text, msg) => act(() => call('app:copy', text), msg || 'Đã copy');
const openUrl = url => call('app:open', url);
document.addEventListener('click', e => {
  const a = e.target.closest('a[data-href]');
  if (a) { e.preventDefault(); openUrl(a.dataset.href); }
  const g = e.target.closest('[data-go]');
  if (g) { e.preventDefault(); go(g.dataset.go); }
});

/* ---------- routing ---------- */
const TITLES = { overview: 'Tổng quan', compose: 'Soạn bài từ Sheet', queue: 'Hàng đợi đăng', nicks: 'Nick & Fanpage', connections: 'Cài đặt kết nối', log: 'Nhật ký' };
let view = 'overview';
function go(v) {
  view = TITLES[v] ? v : 'overview';
  $$('[data-view]').forEach(s => (s.hidden = s.dataset.view !== view));
  $$('#nav button').forEach(b => (b.dataset.v === view ? b.setAttribute('aria-current', 'page') : b.removeAttribute('aria-current')));
  $('#vt').textContent = TITLES[view];
  render();
  if (view === 'compose' && !C.loaded && S.settings.sheet.url) loadRows();
}
$$('#nav button').forEach(b => (b.onclick = () => go(b.dataset.v)));

async function refresh() {
  S = await call('state:get');
  const due = S.queue.filter(q => q.status === 'failed' || q.status === 'hold').length;
  $('#qCount').hidden = !due; $('#qCount').textContent = due;
  const st = (ok, label) => `<div><span class="dot" style="background:${ok === true ? '#4CC28C' : ok === false ? '#E05252' : '#8590AD'}"></span>${label}</div>`;
  $('#sideFoot').innerHTML = st(S.status.sheet && S.status.sheet.ok, 'Google Sheet') + st(S.status.fb && S.status.fb.ok, 'Facebook App') +
    st(S.settings.ai.provider === 'none' ? null : S.status.ai && S.status.ai.ok, 'AI viết caption') + `<div style="margin-top:6px">Phiên bản ${esc(S.version)}</div>`;
  render();
}
window.ph.onChange(() => refresh());

function render() {
  if (!S) return;
  ({ overview: renderOverview, compose: renderCompose, queue: renderQueue, nicks: renderNicks, connections: renderConn, log: renderLog })[view]();
}

const STATUS = {
  scheduled: ['wait', 'Chờ đăng'], posting: ['wait', 'Đang đăng…'], done: ['ok', 'Đã đăng'],
  failed: ['bad', 'Lỗi'], hold: ['bad', 'Chờ nick đăng nhập lại'], cancelled: ['neutral', 'Đã huỷ']
};
const chip = s => `<span class="chip ${STATUS[s][0]}">${STATUS[s][1]}</span>`;
const pageName = id => (S.pages.find(p => p.id === id) || {}).name || 'Page đã gỡ';
const accName = id => (S.accounts.find(a => a.id === id) || {}).name || '—';

/* ---------- overview ---------- */
function renderOverview() {
  const accs = S.accounts, via = accs.filter(a => !a.isMain);
  const today = new Date(); today.setHours(0, 0, 0, 0);
  const q = S.queue;
  const upcoming = q.filter(x => x.status === 'scheduled' || x.status === 'hold' || x.status === 'posting').sort((a, b) => a.when - b.when);
  const doneToday = q.filter(x => x.status === 'done' && x.postedAt >= today.getTime()).length;
  const bad = q.filter(x => x.status === 'failed' || x.status === 'hold');
  const conn = [S.status.sheet && S.status.sheet.ok, S.status.fb && S.status.fb.ok, S.settings.ai.provider === 'none' || (S.status.ai && S.status.ai.ok)];
  const needs = [];
  if (!conn[0]) needs.push('<a data-go="connections">Kết nối Google Sheet</a>');
  if (!conn[1]) needs.push('<a data-go="connections">Nhập Facebook App</a>');
  if (!accs.length) needs.push('<a data-go="nicks">Cho nick đầu tiên đăng nhập</a>');
  $('[data-view="overview"]').innerHTML = `
  ${needs.length ? `<div class="note warn"><b>Cần làm tiếp:</b> ${needs.join(' · ')}</div>` : ''}
  <div class="kpis">
    <div class="panel kpi"><div class="l">Tổng Fanpage</div><div class="v">${S.pages.length}</div><div class="d">${S.pages.filter(p => (accs.find(a => a.id === p.poster) || {}).status === 'ok').length} sẵn sàng đăng</div></div>
    <div class="panel kpi"><div class="l">Tổng tài khoản</div><div class="v">${accs.length}</div><div class="d">${accs.length - via.length} nick chính · ${via.length} via</div></div>
    <div class="panel kpi"><div class="l">Via (nick phụ)</div><div class="v">${via.length}</div><div class="d"><span style="color:var(--ok)">${via.filter(a => a.status === 'ok').length} hoạt động</span> · <span style="color:var(--bad)">${via.filter(a => a.status !== 'ok').length} cần đăng nhập lại</span></div></div>
    <div class="panel kpi"><div class="l">Bài hôm nay</div><div class="v">${doneToday}</div><div class="d">${upcoming.length} đang chờ · ${bad.length} cần xử lý</div></div>
  </div>
  <div class="g2">
    <div class="panel"><div class="ph"><h2>Sắp đăng</h2><button class="btn sm" data-go="queue">Xem hàng đợi</button></div>
      <div class="list">${upcoming.slice(0, 10).map(x => `<div class="li"><span class="mono small">${fmt(x.when)}</span><span class="x"><b>${esc(x.caption.split('\n')[0])}</b><span class="muted small">${esc(pageName(x.pageId))}${x.error ? ' · ' + esc(x.error) : ''}</span></span>${chip(x.status)}</div>`).join('') || '<div class="empty">Chưa có bài nào. Vào <a data-go="compose">Soạn bài từ Sheet</a> để lên lịch.</div>'}</div></div>
    <div class="panel"><h2>Kết nối</h2><div class="list" style="margin-top:8px">
      ${[['Google Sheet', S.status.sheet, S.status.sheet && S.status.sheet.ok ? `${esc(S.status.sheet.file)} · ${esc(S.status.sheet.sheet)}` : ''], ['Facebook App', S.status.fb, S.status.fb && S.status.fb.ok ? esc(S.status.fb.name) : ''], ['AI viết caption', S.settings.ai.provider === 'none' ? { none: true } : S.status.ai, S.settings.ai.provider]]
        .map(([n, s, d]) => `<div class="li" style="grid-template-columns:minmax(0,1fr) auto"><span><b style="font-weight:500">${n}</b><div class="muted small">${s && s.none ? 'Không dùng, bạn tự viết caption' : s && !s.ok ? esc(s.error) : d || 'Chưa kiểm tra'}</div></span>${s && s.none ? '<span class="chip neutral">Tắt</span>' : s && s.ok ? '<span class="chip ok">Đã kết nối</span>' : s ? '<span class="chip bad">Lỗi</span>' : '<span class="chip neutral">Chưa kết nối</span>'}</div>`).join('')}
    </div><div class="actions" style="margin-top:10px"><button class="btn sm" data-go="connections">Cài đặt kết nối</button></div></div>
  </div>`;
}

/* ---------- compose ---------- */
const C = { loaded: false, loading: false, rows: [], missing: [], src: 'all', use: 'open', row: null, ed: {}, imgs: {}, err: '' };
const edOf = r => C.ed[r.row] || (C.ed[r.row] = { cap: '', page: null, pick: [], whenMode: 'auto', when: '' });
const pageFor = r => edOf(r).page || S.srcMap[r.page] || '';
async function loadRows() {
  C.loading = true; C.err = ''; render();
  try {
    const j = await call('sheet:rows');
    C.rows = j.rows; C.missing = j.missing || []; C.loaded = true;
  } catch (e) { C.err = e.message; }
  C.loading = false; render();
}
async function loadImages(pageId, force) {
  const link = S.drive[pageId];
  if (!link) return;
  C.imgs[pageId] = { loading: true, files: [] }; render();
  try { C.imgs[pageId] = { files: await call('sheet:images', link, !!force) }; }
  catch (e) { C.imgs[pageId] = { error: e.message, files: [] }; }
  render();
}
const thumbs = {};
async function loadThumb(id) {
  if (thumbs[id]) return;
  thumbs[id] = 'loading';
  try { thumbs[id] = await call('sheet:thumb', id); } catch (e) { thumbs[id] = 'error'; }
  $$(`[data-thumb="${id}"]`).forEach(el => { if (thumbs[id].startsWith('data:')) el.style.backgroundImage = `url("${thumbs[id]}")`; });
}

function renderCompose() {
  const V = $('[data-view="compose"]');
  if (!S.settings.sheet.url) { V.innerHTML = `<div class="panel"><div class="empty">Chưa kết nối Google Sheet. Vào <a data-go="connections">Cài đặt kết nối</a> và làm 4 bước kết nối.</div></div>`; return; }
  const srcs = [...new Set(C.rows.map(r => r.page).filter(Boolean))];
  const L = C.rows.filter(r => (C.src === 'all' || r.page === C.src) && (C.use === 'all' || (C.use === 'open' ? !r.status : !!r.status)))
    .sort((a, b) => (b.reaction + b.comment + b.share) - (a.reaction + a.comment + a.share));
  if (!L.some(r => r.row === C.row)) C.row = L[0] ? L[0].row : null;
  const r = C.rows.find(x => x.row === C.row);
  V.innerHTML = `
  <div class="panel row between" style="padding:12px 16px"><div><b>${esc(S.status.sheet && S.status.sheet.file || 'Google Sheet')}</b> <span class="muted">${esc(S.status.sheet && S.status.sheet.sheet || '')}</span>
    <div class="muted small">${C.loading ? 'Đang đọc Sheet…' : C.loaded ? `${C.rows.length} dòng` : ''}${C.missing.length ? ` · <span style="color:var(--bad)">Thiếu cột: ${C.missing.map(esc).join(', ')}</span>` : ''}</div></div>
    <button class="btn sm" id="reload"${C.loading ? ' disabled' : ''}>Đọc lại Sheet</button></div>
  ${C.err ? `<div class="note warn">${esc(C.err)}</div>` : ''}
  <div class="note">Mỗi dòng là bài của Page khác, chỉ dùng làm ý tưởng. Bài đăng lên Fanpage của bạn cần <b>caption mới</b> (giống bài gốc dưới ${S.settings.ai.simMax}%) và <b>ảnh trong thư mục Drive của Fanpage đó</b>. Ảnh ở cột “Link ảnh (Drive)” không được đăng lại.</div>
  <div class="filters"><select class="inp" id="fSrc"><option value="all">Mọi Page trong Sheet</option>${srcs.map(s => `<option value="${esc(s)}"${C.src === s ? ' selected' : ''}>${esc(s)} → ${S.srcMap[s] ? esc(pageName(S.srcMap[s])) : 'chưa gán'}</option>`).join('')}</select>
    <select class="inp" id="fUse"><option value="open"${C.use === 'open' ? ' selected' : ''}>Chưa dùng</option><option value="used"${C.use === 'used' ? ' selected' : ''}>Đã dùng</option><option value="all"${C.use === 'all' ? ' selected' : ''}>Tất cả</option></select>
    <span class="muted small">${L.length} dòng · sắp theo tổng tương tác</span></div>
  <div class="lib">
    <div class="panel" style="padding:0"><div class="tablewrap"><table class="rowt"><thead><tr><th>Ngày</th><th>Page → Fanpage</th><th>Caption gốc</th><th class="r">❤ · 💬 · ↗</th><th>Trạng thái sử dụng</th></tr></thead><tbody>
      ${L.map(x => { const pid = pageFor(x); return `<tr data-row="${x.row}" class="${x.row === C.row ? 'on' : ''}"><td class="small mono">${esc(String(x.date).split(' ')[0])}<div class="muted">dòng ${x.row}</div></td>
        <td class="small"><b style="font-weight:500">${esc(x.page)}</b><div>${pid ? '→ ' + esc(pageName(pid)) : '<span style="color:var(--warn)">→ chưa gán</span>'}</div></td>
        <td><div class="clamp">${esc(x.caption)}</div>${edOf(x).cap ? '<span class="chip wait">Đã viết bài mới</span>' : ''}</td>
        <td class="r small num">${nf(x.reaction)} · ${nf(x.comment)} · ${nf(x.share)}</td>
        <td><span class="chip ${x.status ? 'ok' : 'neutral'}">${esc(x.status ? String(x.status).slice(0, 40) : 'Chưa dùng')}</span></td></tr>`; }).join('') || `<tr><td colspan="5" class="empty">${C.loading ? 'Đang tải…' : 'Không có dòng nào.'}</td></tr>`}
    </tbody></table></div></div>
    <div class="panel ed" id="ed"></div>
  </div>`;
  $('#reload').onclick = loadRows;
  $('#fSrc').onchange = e => { C.src = e.target.value; render(); };
  $('#fUse').onchange = e => { C.use = e.target.value; render(); };
  $$('[data-row]').forEach(tr => (tr.onclick = () => { C.row = +tr.dataset.row; render(); }));
  renderEditor(r);
}

function renderEditor(r) {
  const E = $('#ed');
  if (!r) { E.innerHTML = '<div class="empty">Chọn một dòng ở bảng bên trái.</div>'; return; }
  const e = edOf(r), pid = pageFor(r), lim = S.settings.ai.simMax;
  const page = S.pages.find(p => p.id === pid);
  const imgs = pid ? C.imgs[pid] : null;
  if (pid && S.drive[pid] && !imgs) loadImages(pid);
  const simPct = Math.round(window.similarity(e.cap, r.caption) * 100);
  const col = !e.cap ? 'var(--muted)' : simPct >= lim ? 'var(--bad)' : simPct >= lim * 0.6 ? 'var(--warn)' : 'var(--ok)';
  E.innerHTML = `
    <div class="row between"><h2>Dòng ${r.row}</h2>${r.link ? `<a data-href="${esc(r.link)}" class="small">Xem bài gốc ↗</a>` : ''}</div>
    <div class="src"><b>${esc(r.page)}</b>${esc(r.caption)}</div>
    <label class="field"><span class="flabel">Đăng lên Fanpage</span><select class="inp" id="ePg"><option value="">Chọn Fanpage…</option>${S.pages.map(p => `<option value="${p.id}"${p.id === pid ? ' selected' : ''}>${esc(p.name)}</option>`).join('')}</select></label>
    <div class="field"><div class="row between"><span class="flabel">Caption của bạn</span><button class="btn sm" id="eAI"${S.settings.ai.provider === 'none' ? ' title="Chưa chọn AI trong Cài đặt kết nối"' : ''}>✨ AI viết bài mới</button></div>
      <textarea class="inp" id="eCap" placeholder="Viết theo giọng Fanpage của bạn">${esc(e.cap)}</textarea>
      <div class="row between small"><span>Độ giống bài gốc (tối đa ${lim}%)</span><b id="simV" style="color:${col}">${e.cap ? simPct + '%' : '—'}</b></div>
      <div class="simbar"><span id="simB" style="width:${e.cap ? Math.max(3, simPct) : 0}%;background:${col}"></span></div></div>
    <div class="field"><div class="row between"><span class="flabel">Ảnh của bạn${page ? ' · ' + esc(page.name) : ''}</span>${pid && S.drive[pid] ? '<button class="btn sm" id="eImgR">Đọc lại thư mục</button>' : ''}</div>
      ${!pid ? '<div class="empty">Chọn Fanpage trước.</div>' : !S.drive[pid] ? `<div class="empty">Fanpage này chưa có thư mục ảnh. Thêm link thư mục Drive trong <a data-go="connections">Cài đặt kết nối</a>.</div>` :
        !imgs || imgs.loading ? '<div class="empty">Đang đọc thư mục ảnh…</div>' : imgs.error ? `<div class="empty" style="color:var(--bad)">${esc(imgs.error)}</div>` : !imgs.files.length ? '<div class="empty">Thư mục không có ảnh.</div>' :
        `<div class="thumbs">${imgs.files.map(f => { const i = e.pick.indexOf(f.id); return `<label class="th${i >= 0 ? ' on' : ''}"><input type="checkbox" data-pick="${esc(f.id)}"${i >= 0 ? ' checked' : ''}><span class="img" data-thumb="${esc(f.id)}"></span>${i >= 0 ? `<span class="ord">${i + 1}</span>` : ''}<span class="fn">${esc(f.name)}</span></label>`; }).join('')}</div><span class="muted small">Thứ tự chọn là thứ tự ảnh trong bài, tối đa 10 ảnh.</span>`}</div>
    <div class="field"><span class="flabel">Giờ đăng</span><div class="row"><label class="switch"><input type="radio" name="wm" value="auto"${e.whenMode === 'auto' ? ' checked' : ''}>Ngẫu nhiên ${esc(S.settings.rules.ws)}–${esc(S.settings.rules.we)}</label>
      <label class="switch"><input type="radio" name="wm" value="fixed"${e.whenMode === 'fixed' ? ' checked' : ''}>Chọn giờ</label><input class="inp" type="datetime-local" id="eWhen" value="${esc(e.when)}"${e.whenMode === 'fixed' ? '' : ' disabled'}></div></div>
    <div class="muted small">Người đăng: ${page ? `<b>${esc(accName(page.poster))}</b>${page.strict ? ' (chỉ đăng bằng nick này)' : ''}` : '—'}</div>
    <div class="actions"><button class="btn primary" id="eGo"${r.status ? ' disabled' : ''}>${r.status ? 'Dòng này đã dùng' : 'Lên lịch'}</button></div>`;
  $('#ePg').onchange = ev => { e.page = ev.target.value || null; e.pick = []; render(); };
  $('#eCap').oninput = ev => {
    e.cap = ev.target.value;
    const v = Math.round(window.similarity(e.cap, r.caption) * 100), c = !e.cap ? 'var(--muted)' : v >= lim ? 'var(--bad)' : v >= lim * 0.6 ? 'var(--warn)' : 'var(--ok)';
    $('#simV').textContent = e.cap ? v + '%' : '—'; $('#simV').style.color = c; $('#simB').style.width = (e.cap ? Math.max(3, v) : 0) + '%'; $('#simB').style.background = c;
  };
  $('#eAI').onclick = async () => {
    if (S.settings.ai.provider === 'none') { toast('Chưa chọn AI. Vào Cài đặt kết nối › AI viết caption', true); return; }
    const b = $('#eAI'); b.disabled = true; b.textContent = 'Đang viết…';
    const res = await act(() => call('ai:rewrite', { caption: r.caption, pageName: page ? page.name : '' }), v => `${v.provider} đã viết xong · giống ${v.sim}%`);
    if (res) e.cap = res.text;
    render();
  };
  if ($('#eImgR')) $('#eImgR').onclick = () => loadImages(pid, true);
  $$('[data-pick]').forEach(cb => (cb.onchange = () => {
    const id = cb.dataset.pick;
    if (cb.checked) { if (e.pick.length >= 10) { toast('Tối đa 10 ảnh', true); cb.checked = false; return; } e.pick.push(id); } else e.pick = e.pick.filter(x => x !== id);
    render();
  }));
  $$('[data-thumb]').forEach(el => { const id = el.dataset.thumb; if (thumbs[id] && thumbs[id].startsWith('data:')) el.style.backgroundImage = `url("${thumbs[id]}")`; else loadThumb(id); });
  $$('input[name="wm"]').forEach(x => (x.onchange = () => { e.whenMode = x.value; render(); }));
  $('#eWhen').onchange = ev => { e.when = ev.target.value; };
  $('#eGo').onclick = async () => {
    const when = e.whenMode === 'fixed' && e.when ? new Date(e.when).getTime() : null;
    if (e.whenMode === 'fixed' && !when) { toast('Chọn ngày giờ đăng', true); return; }
    $('#eGo').disabled = true;
    const q = await act(() => call('queue:add', { pageId: pid, caption: e.cap, original: r.caption, images: e.pick, when, row: r.row, source: r.page }), v => `Đã lên lịch ${fmt(v.when)} trên ${pageName(v.pageId)}`);
    if (q) { r.status = `Đã lên lịch · ${fmt(q.when)} · ${pageName(q.pageId)}`; delete C.ed[r.row]; }
    render();
  };
}

/* ---------- queue ---------- */
let qFilter = 'open';
function renderQueue() {
  const L = S.queue.filter(q => qFilter === 'all' || (qFilter === 'open' ? ['scheduled', 'posting', 'hold', 'failed'].includes(q.status) : q.status === qFilter))
    .sort((a, b) => (qFilter === 'done' ? b.postedAt - a.postedAt : a.when - b.when));
  $('[data-view="queue"]').innerHTML = `
  <div class="filters"><select class="inp" id="qf">${[['open', 'Đang chờ và cần xử lý'], ['done', 'Đã đăng'], ['cancelled', 'Đã huỷ'], ['all', 'Tất cả']].map(([k, n]) => `<option value="${k}"${qFilter === k ? ' selected' : ''}>${n}</option>`).join('')}</select>
    <span class="muted small">App phải đang mở hoặc chạy ở khay hệ thống thì bài mới được đăng đúng giờ.</span></div>
  <div class="panel" style="padding:0"><div class="tablewrap"><table><thead><tr><th>Giờ đăng</th><th>Fanpage</th><th>Caption</th><th>Ảnh</th><th>Trạng thái</th><th></th></tr></thead><tbody>
  ${L.map(q => `<tr><td class="mono small">${fmt(q.status === 'done' ? q.postedAt : q.when)}</td><td>${esc(pageName(q.pageId))}<div class="muted small">${q.accountId ? 'Người đăng: ' + esc(accName(q.accountId)) : ''}</div></td>
    <td><div class="clamp">${esc(q.caption)}</div>${q.error ? `<div class="small" style="color:var(--bad)">${esc(q.error)}</div>` : ''}${q.source ? `<div class="muted small">Ý tưởng: ${esc(q.source)}${q.row ? ' · dòng ' + q.row : ''}</div>` : ''}</td>
    <td class="num">${q.images.length}</td><td>${chip(q.status)}</td>
    <td class="r"><div class="row" style="justify-content:flex-end;gap:4px">${q.status === 'scheduled' ? `<button class="btn sm" data-qa="now" data-id="${q.id}">Đăng ngay</button>` : ''}${['failed', 'hold'].includes(q.status) ? `<button class="btn sm" data-qa="retry" data-id="${q.id}">Thử lại</button>` : ''}${q.link ? `<a class="btn sm" data-href="${esc(q.link)}">Xem bài</a>` : ''}${['scheduled', 'hold', 'failed'].includes(q.status) ? `<button class="btn sm ghost" data-qa="cancel" data-id="${q.id}">Huỷ</button>` : ''}${['cancelled', 'done', 'failed'].includes(q.status) ? `<button class="btn sm ghost" data-qa="delete" data-id="${q.id}">Xoá</button>` : ''}</div></td></tr>`).join('') || '<tr><td colspan="6" class="empty">Không có bài nào.</td></tr>'}
  </tbody></table></div></div>`;
  $('#qf').onchange = e => { qFilter = e.target.value; render(); };
  $$('[data-qa]').forEach(b => (b.onclick = () => act(() => call('queue:action', b.dataset.id, b.dataset.qa), { now: 'Đang đăng…', retry: 'Đã đưa lại vào hàng đợi', cancel: 'Đã huỷ', delete: 'Đã xoá' }[b.dataset.qa])));
}

/* ---------- nicks & pages ---------- */
let loginUrl = '';
function renderNicks() {
  const accStatus = a => a.status === 'ok' ? (a.expiresAt && a.expiresAt - Date.now() < 7 * 864e5 ? `<span class="chip warn">Đăng nhập lại trước ${fmt(a.expiresAt).split(' ')[0]}</span>` : '<span class="chip ok">Hoạt động</span>') : '<span class="chip bad">Cần đăng nhập lại</span>';
  $('[data-view="nicks"]').innerHTML = `
  <div class="panel"><div class="ph"><h2>Thêm nick (mỗi nick đăng nhập 1 lần)</h2></div>
    <ol class="steps">
      <li><div><b>Thêm nick làm Tester của App</b><p class="muted">developers.facebook.com › App của bạn › App Roles › Roles › Add Testers. Nick vào <a data-href="https://developers.facebook.com/requests/">developers.facebook.com/requests</a> để chấp nhận.</p></div></li>
      <li><div><b>Đăng nhập nick qua PageHub</b><p class="muted">Bấm “Mở trang đăng nhập”. Muốn thêm nick khác, hãy <b>copy link và mở bằng cửa sổ ẩn danh</b> (Ctrl+Shift+N) để không dính nick đang đăng nhập trên trình duyệt. Chọn đủ các Page của nick khi Facebook hỏi.</p>
        <div class="row"><button class="btn primary" id="login">Mở trang đăng nhập</button><button class="btn" id="loginCopy">Copy link đăng nhập (ẩn danh)</button>${S.loginPending ? '<span class="chip wait">Đang chờ bạn đăng nhập…</span>' : ''}</div></div></li>
    </ol></div>
  <div class="acc">${S.accounts.map(a => `<div class="card"><div class="row" style="flex-wrap:nowrap">${av(a.id, a.name)}<div style="flex:1;min-width:0"><b>${esc(a.name)}</b><div class="muted small">${a.isMain ? 'Nick chính' : 'Via (nick phụ)'} · ID ${esc(a.id)}</div></div>${accStatus(a)}</div>
    ${a.error ? `<div class="small" style="color:var(--bad)">${esc(a.error)}</div>` : ''}
    <div class="small">Quản trị: ${a.pages.map(id => esc(pageName(id))).join(', ') || '—'}</div>
    <div class="row"><label class="switch"><input type="checkbox" data-main="${a.id}"${a.isMain ? ' checked' : ''}>Nick chính</label><span style="flex:1"></span><button class="btn sm" data-relogin="${a.id}">Đăng nhập lại</button><button class="btn sm ghost" data-rm="${a.id}">Gỡ</button></div></div>`).join('') || '<div class="panel empty">Chưa có nick nào. Làm 2 bước ở trên để thêm nick đầu tiên.</div>'}</div>
  <div class="panel" style="padding:0"><div class="ph" style="padding:16px 18px 0"><h2>Fanpage (${S.pages.length})</h2><span class="muted small">Page tự hiện khi nick quản trị nó đăng nhập</span></div>
    <div class="tablewrap"><table><thead><tr><th>Fanpage</th><th>Người đăng (hiện ở dòng “Người đăng”)</th><th>Chỉ đăng bằng nick này</th><th>Thư mục ảnh</th><th>Lấy ý tưởng từ</th></tr></thead><tbody>
    ${S.pages.map(p => `<tr><td><div class="row" style="flex-wrap:nowrap">${av(p.id, p.name, true)}<span><b style="font-weight:500">${esc(p.name)}</b><div class="muted small mono">${esc(p.id)}</div></span></div></td>
      <td><select class="inp" data-poster="${p.id}">${p.nicks.map(id => `<option value="${id}"${id === p.poster ? ' selected' : ''}>${esc(accName(id))}</option>`).join('')}</select></td>
      <td><label class="switch"><input type="checkbox" data-strict="${p.id}"${p.strict ? ' checked' : ''}>${p.strict ? 'Bật' : 'Tắt, dùng nick khác nếu lỗi'}</label></td>
      <td>${S.drive[p.id] ? '<span class="chip ok">Đã có</span>' : '<a data-go="connections" class="chip warn" style="text-decoration:none">Chưa có</a>'}</td>
      <td class="small">${Object.entries(S.srcMap).filter(([, v]) => v === p.id).map(([k]) => esc(k)).join(', ') || '<span class="muted">—</span>'}</td></tr>`).join('') || '<tr><td colspan="5" class="empty">Chưa có Fanpage.</td></tr>'}
    </tbody></table></div></div>`;
  const getUrl = async () => { const r = await act(() => call('fb:login')); if (r) { loginUrl = r.url; await refresh(); } return r; };
  $('#login').onclick = async () => { const r = await getUrl(); if (r) openUrl(r.url); };
  $('#loginCopy').onclick = async () => { const r = await getUrl(); if (r) copy(r.url, 'Đã copy link. Mở cửa sổ ẩn danh rồi dán vào'); };
  $$('[data-relogin]').forEach(b => (b.onclick = async () => { const r = await getUrl(); if (r) { copy(r.url, 'Đã copy link đăng nhập. Mở cửa sổ ẩn danh, đăng nhập đúng nick này'); } }));
  $$('[data-main]').forEach(c => (c.onchange = () => act(() => call('account:update', c.dataset.main, { isMain: c.checked }))));
  $$('[data-rm]').forEach(b => (b.onclick = () => {
    if (b.dataset.sure !== '1') { b.dataset.sure = '1'; b.textContent = 'Bấm lần nữa để gỡ'; b.style.color = 'var(--bad)'; return; }
    act(() => call('account:remove', b.dataset.rm), 'Đã gỡ nick. Nhớ gỡ cả vai trò Tester trong App');
  }));
  $$('[data-poster]').forEach(s => (s.onchange = () => act(() => call('page:update', s.dataset.poster, { poster: s.value }), 'Đã đổi người đăng')));
  $$('[data-strict]').forEach(c => (c.onchange = () => act(() => call('page:update', c.dataset.strict, { strict: c.checked }))));
}

/* ---------- connections ---------- */
let codeText = '', codeOpen = false;
const PROVIDERS = { none: 'Không dùng AI (tự viết caption)', gemini: 'Google Gemini · có gói miễn phí', groq: 'Groq · có gói miễn phí', openrouter: 'OpenRouter · có model miễn phí', openai: 'OpenAI · trả phí', claude: 'Claude (Anthropic) · trả phí' };
const stChip = (s, none) => none ? '<span class="chip neutral">Tắt</span>' : !s ? '<span class="chip neutral">Chưa kiểm tra</span>' : s.ok ? '<span class="chip ok">Đã kết nối</span>' : '<span class="chip bad">Lỗi</span>';
async function renderConn() {
  const s = S.settings, sh = S.status.sheet;
  const srcNames = [...new Set([...Object.keys(S.srcMap), ...C.rows.map(r => r.page).filter(Boolean)])];
  $('[data-view="connections"]').innerHTML = `
  <section class="panel" id="cx-sheet"><div class="ph"><h2>1. Google Sheet</h2>${stChip(sh)}</div>
    <ol class="steps">
      <li><div><b>Tạo một file Google Sheet mới (hoặc mở file có sẵn)</b><p class="muted">Dòng 1 cần đủ các cột: <code>Ngày đăng</code> <code>Page</code> <code>Link bài viết</code> <code>Caption</code> <code>Link ảnh (Drive)</code> <code>Reaction</code> <code>Comment</code> <code>Share</code> <code>Trạng thái sử dụng</code>.</p>
        <div class="row"><a class="btn sm" data-href="https://sheets.new">Tạo Google Sheet mới ↗</a><button class="btn sm" id="copyHead">Copy dòng tiêu đề</button></div></div></li>
      <li><div><b>Mở Apps Script và dán mã kết nối</b><p class="muted">Trong Google Sheet: <b>Tiện ích mở rộng → Apps Script</b>. Xoá hết mã có sẵn, dán mã vừa sao chép, rồi bấm Lưu (Ctrl + S).</p>
        <div class="row"><button class="btn sm primary" id="copyCode">Sao chép mã</button><button class="btn sm" id="viewCode">${codeOpen ? 'Ẩn mã' : 'Xem mã'}</button><span class="muted small">Khoá kết nối: <span class="mono">${esc(s.sheet.keyHint)}••••</span></span><button class="btn sm ghost" id="newKey">Tạo khoá mới</button></div>
        <pre class="code"${codeOpen ? '' : ' hidden'}>${esc(codeText)}</pre></div></li>
      <li><div><b>Triển khai thành Ứng dụng web</b><p class="muted">Bấm <b>Triển khai → Tùy chọn triển khai mới →</b> biểu tượng bánh răng → <b>Ứng dụng web</b>. Đặt “Thực thi với tư cách: <b>Tôi</b>” và “Người có quyền truy cập: <b>Bất kỳ ai</b>” → Triển khai. Khi Google hỏi quyền: chọn tài khoản → <b>Nâng cao</b> → <b>Đi tới … (không an toàn)</b> → <b>Cho phép</b>. Đây là mã của chính bạn nên cảnh báo này là bình thường.</p></div></li>
      <li><div><b>Dán “URL ứng dụng web” vào đây</b>
        <div class="row" style="flex-wrap:nowrap;margin-top:6px"><input class="inp mono" id="sUrl" style="flex:1" value="${esc(s.sheet.url)}" placeholder="https://script.google.com/macros/s/…/exec"><input class="inp" id="sName" style="width:170px" value="${esc(s.sheet.sheetName)}" placeholder="Tên sheet (để trống = sheet đầu)"><button class="btn primary" id="sConnect">Kết nối</button></div>
        <p class="muted small">Link được bảo vệ bằng khoá riêng nằm trong mã. Không chia sẻ mã và link này. Nếu tạo khoá mới, dán lại mã và triển khai lại (Quản lý triển khai → Chỉnh sửa → Phiên bản mới).</p>
        ${sh ? (sh.ok ? `<div class="note">✓ Đọc được <b>${esc(sh.file)}</b> · sheet <b>${esc(sh.sheet)}</b>${sh.missing && sh.missing.length ? ` · <span style="color:var(--bad)">Thiếu cột: ${sh.missing.map(esc).join(', ')}</span>` : ' · đủ 9 cột'}</div>` : `<div class="note warn">${esc(sh.error)}</div>`) : ''}</div></li>
    </ol></section>

  <section class="panel"><div class="ph"><h2>2. Page trong Sheet → Fanpage nhận ý tưởng, và thư mục ảnh của bạn</h2><button class="btn sm primary" id="mapSave">Lưu</button></div>
    <div class="cgrid">
      <div class="cf"><span>Page ở cột “Page” → đăng lên Fanpage</span>${srcNames.length ? `<table><tbody>${srcNames.map(n => `<tr><td>${esc(n)}</td><td><select class="inp" data-src="${esc(n)}"><option value="">Không dùng</option>${S.pages.map(p => `<option value="${p.id}"${S.srcMap[n] === p.id ? ' selected' : ''}>${esc(p.name)}</option>`).join('')}</select></td></tr>`).join('')}</tbody></table>` : '<span class="muted small">Đọc Sheet ở mục Soạn bài để hiện danh sách Page.</span>'}</div>
      <div class="cf"><span>Thư mục Drive chứa ảnh của chính Fanpage</span>${S.pages.length ? `<table><tbody>${S.pages.map(p => `<tr><td>${esc(p.name)}</td><td><input class="inp" data-drive="${p.id}" value="${esc(S.drive[p.id] || '')}" placeholder="https://drive.google.com/drive/folders/…" style="width:100%"></td></tr>`).join('')}</tbody></table>` : '<span class="muted small">Cho nick đăng nhập để hiện danh sách Fanpage.</span>'}
        <span class="muted small">Thư mục phải nằm trong tài khoản Google đang chạy Apps Script (hoặc được chia sẻ cho tài khoản đó).</span></div>
    </div></section>

  <section class="panel"><div class="ph"><h2>3. Facebook App</h2>${stChip(S.status.fb)}</div>
    <div class="cgrid">
      <label class="cf"><span>App ID</span><input class="inp" id="fId" value="${esc(s.fb.appId)}" placeholder="Dãy số trong Cài đặt › Thông tin cơ bản"></label>
      <label class="cf"><span>App Secret ${s.fb.hasSecret ? '(đã lưu, nhập lại để đổi)' : ''}</span><input class="inp" id="fSec" type="password" placeholder="${s.fb.hasSecret ? '••••••••' : 'App Secret'}" autocomplete="off"></label>
      <label class="cf"><span>Phiên bản Graph API</span><input class="inp mono" id="fVer" value="${esc(s.fb.version)}"></label>
      <label class="cf"><span>Cổng đăng nhập trên máy</span><input class="inp" id="fPort" type="number" value="${esc(s.fb.port)}"></label>
      <div class="cf" style="grid-column:1/-1"><span>Redirect URL (App ở chế độ Development tự cho phép localhost; nếu Facebook báo lỗi URL, dán vào Facebook Login › Settings › Valid OAuth Redirect URIs)</span><div class="row" style="flex-wrap:nowrap"><input class="inp mono" readonly value="${esc(s.fb.redirect)}" style="flex:1"><button class="btn sm" id="copyRedir">Copy</button></div></div>
    </div>
    <p class="muted small">Quyền cần dùng: <code>pages_show_list</code> <code>pages_manage_posts</code> <code>pages_read_engagement</code>. ${S.status.fb && !S.status.fb.ok ? `<span style="color:var(--bad)">${esc(S.status.fb.error)}</span>` : ''}</p>
    <div class="actions"><a class="btn" data-href="https://developers.facebook.com/apps/">Mở Meta for Developers ↗</a><button class="btn primary" id="fSave">Lưu &amp; kiểm tra</button></div></section>

  <section class="panel"><div class="ph"><h2>4. AI viết caption</h2>${stChip(S.status.ai, s.ai.provider === 'none')}</div>
    <div class="cgrid">
      <label class="cf"><span>Nhà cung cấp</span><select class="inp" id="aP">${Object.entries(PROVIDERS).map(([k, n]) => `<option value="${k}"${s.ai.provider === k ? ' selected' : ''}>${n}</option>`).join('')}</select></label>
      <label class="cf"><span>API key ${s.ai.hasKey ? '(đã lưu, nhập lại để đổi)' : ''}</span><input class="inp" id="aKey" type="password" placeholder="${s.ai.hasKey ? '••••••••' : 'Dán API key'}" autocomplete="off"></label>
      <label class="cf"><span>Model (để trống = mặc định)</span><input class="inp mono" id="aModel" value="${esc(s.ai.model)}" placeholder="${esc(s.ai.defaults[s.ai.provider] || '')}"></label>
      <label class="cf"><span>Độ giống bài gốc tối đa</span><select class="inp" id="aSim">${[30, 40, 50].map(v => `<option value="${v}"${s.ai.simMax === v ? ' selected' : ''}>${v}%</option>`).join('')}</select></label>
      <label class="cf" style="grid-column:1/-1"><span>Hướng dẫn cho AI ({page} = tên Fanpage)</span><textarea class="inp" id="aPrompt" style="min-height:90px">${esc(s.ai.prompt)}</textarea></label>
    </div>
    <p class="muted small">Lấy key miễn phí: Gemini tại <a data-href="https://aistudio.google.com/apikey">aistudio.google.com</a>, Groq tại <a data-href="https://console.groq.com/keys">console.groq.com</a>, OpenRouter tại <a data-href="https://openrouter.ai/keys">openrouter.ai</a>. Gói miễn phí có giới hạn số lần gọi mỗi ngày. ${S.status.ai && !S.status.ai.ok ? `<span style="color:var(--bad)">${esc(S.status.ai.error)}</span>` : ''}</p>
    <div class="actions"><button class="btn primary" id="aSave">Lưu &amp; kiểm tra</button></div></section>

  <section class="panel"><div class="ph"><h2>5. Lịch đăng và ứng dụng</h2></div>
    <div class="cgrid">
      <label class="cf"><span>Khung giờ đăng ngẫu nhiên</span><span class="row" style="flex-wrap:nowrap"><input class="inp" type="time" id="rWs" value="${esc(s.rules.ws)}">–<input class="inp" type="time" id="rWe" value="${esc(s.rules.we)}"></span></label>
      <label class="cf"><span>Tối đa bài/ngày mỗi Fanpage · cách nhau ít nhất (phút)</span><span class="row" style="flex-wrap:nowrap"><input class="inp" type="number" min="1" max="20" id="rMax" value="${esc(s.rules.max)}" style="width:90px"><input class="inp" type="number" min="15" step="15" id="rGap" value="${esc(s.rules.gap)}" style="width:100px"></span></label>
      <label class="switch"><input type="checkbox" id="oTray"${s.app.closeToTray ? ' checked' : ''}>Bấm X thì thu nhỏ xuống khay hệ thống (vẫn đăng bài)</label>
      <label class="switch"><input type="checkbox" id="oLogin"${s.app.openAtLogin ? ' checked' : ''}>Tự chạy khi bật máy</label>
    </div>
    <div class="actions"><button class="btn primary" id="rSave">Lưu</button></div></section>`;

  if (!codeText) { codeText = await call('sheet:code').catch(() => ''); if (codeOpen) render(); }
  $('#copyHead').onclick = () => copy(['Ngày đăng', 'Page', 'Link bài viết', 'Caption', 'Link ảnh (Drive)', 'Reaction', 'Comment', 'Share', 'Trạng thái sử dụng'].join('\t'), 'Đã copy, dán vào ô A1');
  $('#copyCode').onclick = async () => { codeText = await call('sheet:code'); copy(codeText, 'Đã sao chép mã. Dán vào Apps Script rồi bấm Lưu'); };
  $('#viewCode').onclick = async () => { codeText = await call('sheet:code'); codeOpen = !codeOpen; render(); };
  $('#newKey').onclick = async () => {
    const b = $('#newKey');
    if (b.dataset.sure !== '1') { b.dataset.sure = '1'; b.textContent = 'Bấm lần nữa: mã cũ sẽ ngừng hoạt động'; b.style.color = 'var(--bad)'; return; }
    codeText = await call('sheet:newKey'); await refresh(); toast('Đã tạo khoá mới. Dán lại mã vào Apps Script và triển khai phiên bản mới');
  };
  $('#sConnect').onclick = async () => {
    const url = $('#sUrl').value.trim();
    if (!/^https:\/\/script\.google\.com\/macros\/s\/[\w-]+\/exec$/.test(url)) { toast('URL chưa đúng. Dạng đúng: https://script.google.com/macros/s/…/exec', true); return; }
    $('#sConnect').disabled = true; $('#sConnect').textContent = 'Đang kết nối…';
    await act(() => call('settings:save', { sheet: { url, sheetName: $('#sName').value } }));
    const r = await act(() => call('sheet:test'));
    await refresh();
    if (r && r.ok) { toast(`Đã kết nối ${r.file}`); C.loaded = false; } else if (r) toast(r.error, true);
  };
  $('#mapSave').onclick = () => {
    const srcMap = {}; $$('[data-src]').forEach(x => { srcMap[x.dataset.src] = x.value; });
    const drive = {}; $$('[data-drive]').forEach(x => { if (x.value.trim()) drive[x.dataset.drive] = x.value.trim(); });
    Object.keys(C.imgs).forEach(k => delete C.imgs[k]);
    act(() => call('maps:save', { srcMap: Object.keys(srcMap).length ? srcMap : S.srcMap, drive }).then(refresh), 'Đã lưu');
  };
  $('#copyRedir').onclick = () => copy(s.fb.redirect);
  $('#fSave').onclick = async () => {
    await act(() => call('settings:save', { fb: { appId: $('#fId').value, secret: $('#fSec').value, version: $('#fVer').value, port: $('#fPort').value } }));
    const r = await act(() => call('fb:test')); await refresh();
    if (r) r.ok ? toast(`Đã kết nối App “${r.name}”`) : toast(r.error, true);
  };
  $('#aP').onchange = () => { $('#aModel').placeholder = s.ai.defaults[$('#aP').value] || ''; };
  $('#aSave').onclick = async () => {
    await act(() => call('settings:save', { ai: { provider: $('#aP').value, key: $('#aKey').value, model: $('#aModel').value, prompt: $('#aPrompt').value, simMax: $('#aSim').value } }));
    if ($('#aP').value === 'none') { await refresh(); toast('Đã tắt AI'); return; }
    const r = await act(() => call('ai:test')); await refresh();
    if (r) r.ok ? toast('AI trả lời được') : toast(r.error, true);
  };
  $('#rSave').onclick = () => act(() => call('settings:save', {
    rules: { ws: $('#rWs').value || s.rules.ws, we: $('#rWe').value || s.rules.we, max: Math.max(1, +$('#rMax').value || 3), gap: Math.max(15, +$('#rGap').value || 60) },
    app: { closeToTray: $('#oTray').checked, openAtLogin: $('#oLogin').checked }
  }).then(refresh), 'Đã lưu');
}

/* ---------- log ---------- */
function renderLog() {
  const lv = { info: ['neutral', 'Thông tin'], warn: ['warn', 'Cảnh báo'], error: ['bad', 'Lỗi'] };
  $('[data-view="log"]').innerHTML = `<div class="panel"><div class="loglist">${S.log.map(l => `<div><span class="mono small">${fmt(l.t)}</span><span class="chip ${lv[l.level][0]}">${lv[l.level][1]}</span><span>${esc(l.msg)}</span></div>`).join('') || '<div class="empty">Chưa có hoạt động.</div>'}</div></div>`;
}

refresh().then(() => go(S.status.sheet && S.status.sheet.ok ? 'overview' : 'connections'));
