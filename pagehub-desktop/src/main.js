const { app, BrowserWindow, ipcMain, shell, Tray, Menu, nativeImage, clipboard } = require('electron');
const path = require('path');
const http = require('http');
const crypto = require('crypto');
const store = require('./lib/store');
const fb = require('./lib/facebook');
const sheet = require('./lib/sheet');
const ai = require('./lib/ai');
const scheduler = require('./lib/scheduler');
const { similarity } = require('./lib/similarity');

let win = null;
let tray = null;
let quitting = false;

if (process.env.PAGEHUB_DATA_DIR) app.setPath('userData', process.env.PAGEHUB_DATA_DIR);
if (!app.requestSingleInstanceLock()) app.quit();
app.on('second-instance', () => showWindow());

function showWindow() {
  if (!win) return createWindow();
  if (win.isMinimized()) win.restore();
  win.show();
  win.focus();
}

function notify() {
  if (win && !win.isDestroyed()) win.webContents.send('state-changed');
}

function createWindow() {
  win = new BrowserWindow({
    width: 1320, height: 860, minWidth: 980, minHeight: 640,
    title: 'PageHub', backgroundColor: '#F4F6FA',
    icon: path.join(__dirname, 'assets', 'tray.png'),
    webPreferences: { preload: path.join(__dirname, 'preload.js'), contextIsolation: true, nodeIntegration: false, sandbox: true }
  });
  win.removeMenu();
  win.loadFile(path.join(__dirname, 'renderer', 'index.html'));
  win.webContents.setWindowOpenHandler(({ url }) => {
    if (/^https?:\/\//.test(url)) shell.openExternal(url);
    return { action: 'deny' };
  });
  win.webContents.on('will-navigate', (e, url) => {
    if (!url.startsWith('file:')) { e.preventDefault(); if (/^https?:\/\//.test(url)) shell.openExternal(url); }
  });
  win.on('close', e => {
    if (!quitting && store.get().settings.app.closeToTray) {
      e.preventDefault();
      win.hide();
      if (tray && !store.get().trayHintShown) {
        tray.displayBalloon && tray.displayBalloon({ title: 'PageHub vẫn đang chạy', content: 'App chạy nền ở khay hệ thống để đăng bài đúng giờ. Bấm chuột phải vào biểu tượng để thoát.' });
        store.get().trayHintShown = true; store.save();
      }
    }
  });
  win.on('closed', () => { win = null; });
}

function createTray() {
  tray = new Tray(nativeImage.createFromPath(path.join(__dirname, 'assets', 'tray.png')));
  tray.setToolTip('PageHub – đăng bài tự động');
  tray.setContextMenu(Menu.buildFromTemplate([
    { label: 'Mở PageHub', click: showWindow },
    { label: 'Đăng các bài đến giờ ngay', click: () => scheduler.tick() },
    { type: 'separator' },
    { label: 'Thoát (dừng đăng bài)', click: () => { quitting = true; app.quit(); } }
  ]));
  tray.on('click', showWindow);
}

/* ---------- Facebook sign-in through a local callback ---------- */
let servers = [];
let pending = null; // { state, at }

function htmlPage(title, body, ok) {
  return `<!doctype html><meta charset="utf-8"><title>${title}</title><body style="font-family:Segoe UI,system-ui,sans-serif;background:#F4F6FA;display:grid;place-items:center;height:100vh;margin:0"><div style="background:#fff;border:1px solid #E1E6EF;border-radius:14px;padding:28px 32px;max-width:520px"><h2 style="margin:0 0 8px;color:${ok ? '#17845A' : '#C23B3B'}">${title}</h2><p style="color:#333;line-height:1.5">${body}</p><p style="color:#5E6A80">Bạn có thể đóng tab này và quay lại PageHub.</p></div>`;
}

async function handleCallback(u) {
  if (!pending || u.searchParams.get('state') !== pending.state) throw new Error('Phiên đăng nhập không khớp. Bấm "Thêm nick" trong PageHub để thử lại.');
  pending = null;
  if (u.searchParams.get('error')) throw new Error(u.searchParams.get('error_description') || 'Bạn đã huỷ đăng nhập');
  const { token, expiresIn } = await fb.exchangeCode(u.searchParams.get('code'));
  const user = await fb.me(token);
  const list = await fb.pages(token);
  const d = store.get();
  let acc = d.accounts.find(a => a.id === user.id);
  if (!acc) { acc = { id: user.id, name: user.name, isMain: d.accounts.length === 0 }; d.accounts.push(acc); }
  Object.assign(acc, { name: user.name, token: store.enc(token), expiresAt: expiresIn ? Date.now() + expiresIn * 1000 : null, status: 'ok', error: '', connectedAt: Date.now(), pages: list.map(p => p.id) });
  for (const p of list) {
    const page = d.pages[p.id] || (d.pages[p.id] = { id: p.id, name: p.name, tokens: {}, poster: user.id, strict: true });
    page.name = p.name;
    page.tokens[user.id] = store.enc(p.access_token);
    if (!page.poster || !page.tokens[page.poster]) page.poster = user.id;
  }
  // Posts waiting for this nick can go again.
  d.queue.forEach(q => { if (q.status === 'hold' && d.pages[q.pageId] && d.pages[q.pageId].tokens[user.id]) { q.status = 'scheduled'; q.when = Math.max(q.when, Date.now()); q.error = ''; } });
  store.log(`${user.name} đã đăng nhập, quản trị ${list.length} Page`);
  store.save();
  notify();
  return { name: user.name, count: list.length };
}

function startServer(port) {
  if (servers.length) return Promise.resolve();
  const handler = async (req, res) => {
    const u = new URL(req.url, `http://localhost:${port}`);
    if (u.pathname !== '/facebook/callback') { res.writeHead(404); return res.end(); }
    res.writeHead(200, { 'content-type': 'text/html; charset=utf-8' });
    try {
      const r = await handleCallback(u);
      res.end(htmlPage('Đã kết nối', `Nick <b>${r.name}</b> đã kết nối với <b>${r.count}</b> Fanpage.`, true));
      showWindow();
    } catch (e) {
      store.log('Đăng nhập Facebook lỗi: ' + e.message, 'error'); notify();
      res.end(htmlPage('Chưa kết nối được', String(e.message).replace(/</g, '&lt;'), false));
    }
  };
  const listen = host => new Promise(resolve => {
    const s = http.createServer(handler);
    s.once('error', () => resolve());
    s.listen(port, host, () => { servers.push(s); resolve(); });
  });
  return Promise.all([listen('127.0.0.1'), listen('::1')]).then(() => {
    if (!servers.length) throw new Error(`Cổng ${port} đang bị phần mềm khác dùng. Đổi cổng trong Cài đặt kết nối › Facebook App.`);
  });
}

function stopServer() { servers.forEach(s => s.close()); servers = []; }

/* ---------- state for the window (no secrets) ---------- */
function publicState() {
  const d = store.get();
  const s = d.settings;
  return {
    version: app.getVersion(),
    settings: {
      sheet: { url: s.sheet.url, sheetName: s.sheet.sheetName, keyHint: s.sheet.key.slice(0, 4) },
      fb: { appId: s.fb.appId, hasSecret: !!s.fb.secret, version: s.fb.version, port: s.fb.port, redirect: fb.redirectUri() },
      ai: { provider: s.ai.provider, hasKey: !!s.ai.key, model: s.ai.model, prompt: s.ai.prompt, simMax: s.ai.simMax, defaults: Object.fromEntries(Object.entries(ai.PROVIDERS).map(([k, v]) => [k, v.model])) },
      rules: s.rules,
      app: s.app
    },
    status: d.status,
    srcMap: d.srcMap,
    drive: d.drive,
    accounts: d.accounts.map(a => ({ id: a.id, name: a.name, isMain: !!a.isMain, status: a.status, error: a.error, expiresAt: a.expiresAt, connectedAt: a.connectedAt, pages: a.pages || [] })),
    pages: Object.values(d.pages).map(p => ({ id: p.id, name: p.name, poster: p.poster, strict: p.strict, nicks: Object.keys(p.tokens) })),
    queue: d.queue.slice(-400),
    log: d.log.slice(0, 200),
    loginPending: !!pending
  };
}

/* ---------- IPC ---------- */
function handle(name, fn) {
  ipcMain.handle(name, async (_e, ...args) => {
    try { return { ok: true, data: await fn(...args) }; } catch (e) { return { ok: false, error: e.message || String(e) }; }
  });
}

function registerIpc() {
  handle('state:get', () => publicState());

  handle('settings:save', patch => {
    const s = store.get().settings;
    if (patch.sheet) Object.assign(s.sheet, { url: (patch.sheet.url ?? s.sheet.url).trim(), sheetName: (patch.sheet.sheetName ?? s.sheet.sheetName).trim() });
    if (patch.fb) {
      if (patch.fb.appId !== undefined) s.fb.appId = String(patch.fb.appId).trim();
      if (patch.fb.secret) s.fb.secret = store.enc(String(patch.fb.secret).trim());
      if (patch.fb.version) s.fb.version = String(patch.fb.version).trim();
      if (patch.fb.port) { s.fb.port = Math.max(1024, Math.min(65535, Number(patch.fb.port) || 5175)); stopServer(); }
    }
    if (patch.ai) {
      if (patch.ai.provider) s.ai.provider = patch.ai.provider;
      if (patch.ai.key) s.ai.key = store.enc(String(patch.ai.key).trim());
      if (patch.ai.model !== undefined) s.ai.model = String(patch.ai.model).trim();
      if (patch.ai.prompt !== undefined) s.ai.prompt = String(patch.ai.prompt) || store.DEFAULT_PROMPT;
      if (patch.ai.simMax) s.ai.simMax = Number(patch.ai.simMax);
    }
    if (patch.rules) Object.assign(s.rules, patch.rules);
    if (patch.app) {
      Object.assign(s.app, patch.app);
      app.setLoginItemSettings({ openAtLogin: !!s.app.openAtLogin, args: ['--hidden'] });
    }
    store.save();
    return publicState();
  });

  handle('maps:save', ({ srcMap, drive }) => {
    const d = store.get();
    if (srcMap) d.srcMap = srcMap;
    if (drive) d.drive = drive;
    store.save();
    return publicState();
  });

  handle('sheet:code', () => sheet.code());
  handle('sheet:newKey', () => { store.get().settings.sheet.key = crypto.randomBytes(18).toString('base64url'); store.get().status.sheet = null; store.save(); return sheet.code(); });
  handle('sheet:test', async () => {
    const d = store.get();
    try {
      const j = await sheet.call({ action: 'ping' });
      d.status.sheet = { ok: true, at: Date.now(), file: j.file, sheet: j.sheet, sheets: j.sheets, missing: j.missing };
    } catch (e) {
      d.status.sheet = { ok: false, at: Date.now(), error: e.message };
    }
    store.save();
    return d.status.sheet;
  });
  handle('sheet:rows', () => sheet.rows());
  handle('sheet:images', (link, force) => sheet.images(link, force));
  handle('sheet:thumb', id => sheet.thumb(id));

  handle('fb:test', async () => {
    const d = store.get();
    try { const r = await fb.testApp(); d.status.fb = { ok: true, at: Date.now(), name: r.name }; }
    catch (e) { d.status.fb = { ok: false, at: Date.now(), error: e.message }; }
    store.save();
    return d.status.fb;
  });
  handle('fb:login', async () => {
    const s = store.get().settings.fb;
    if (!s.appId || !s.secret) throw new Error('Nhập App ID và App Secret trong Cài đặt kết nối › Facebook App trước');
    await startServer(s.port || 5175);
    pending = { state: crypto.randomBytes(12).toString('hex'), at: Date.now() };
    const url = fb.loginUrl(pending.state);
    return { url };
  });
  handle('account:update', (id, patch) => {
    const a = store.get().accounts.find(x => x.id === id);
    if (!a) throw new Error('Không tìm thấy nick');
    if (patch.isMain !== undefined) a.isMain = !!patch.isMain;
    store.save();
    return publicState();
  });
  handle('account:remove', id => {
    const d = store.get();
    const mains = Object.values(d.pages).filter(p => p.poster === id && Object.keys(p.tokens).length > 1);
    d.accounts = d.accounts.filter(a => a.id !== id);
    for (const p of Object.values(d.pages)) {
      delete p.tokens[id];
      if (p.poster === id) p.poster = Object.keys(p.tokens)[0] || null;
      if (!Object.keys(p.tokens).length) delete d.pages[p.id];
    }
    store.log(`Đã gỡ nick khỏi PageHub${mains.length ? `, chuyển người đăng của ${mains.length} Page sang nick khác` : ''}`);
    store.save();
    return publicState();
  });
  handle('page:update', (id, patch) => {
    const p = store.get().pages[id];
    if (!p) throw new Error('Không tìm thấy Page');
    if (patch.poster && p.tokens[patch.poster]) p.poster = patch.poster;
    if (patch.strict !== undefined) p.strict = !!patch.strict;
    store.save();
    return publicState();
  });

  handle('ai:rewrite', args => ai.rewrite(args));
  handle('ai:test', async () => {
    const d = store.get();
    try { const t = await ai.complete('Trả lời đúng một từ.', 'Viết chữ: OK'); d.status.ai = { ok: true, at: Date.now(), sample: t.slice(0, 40) }; }
    catch (e) { d.status.ai = { ok: false, at: Date.now(), error: e.message }; }
    store.save();
    return d.status.ai;
  });

  handle('queue:add', async item => {
    const d = store.get();
    const page = d.pages[item.pageId];
    if (!page) throw new Error('Chọn Fanpage nhận bài');
    const caption = String(item.caption || '').trim();
    if (!caption) throw new Error('Chưa có caption');
    const sim = Math.round(similarity(caption, item.original || '') * 100);
    if (item.original && sim >= d.settings.ai.simMax) throw new Error(`Caption còn giống bài gốc ${sim}% (tối đa ${d.settings.ai.simMax}%). Hãy viết lại.`);
    if (!item.images || !item.images.length) throw new Error('Chọn ít nhất một ảnh từ thư mục Drive của Fanpage');
    const own = await sheet.images(d.drive[item.pageId]);
    const ownIds = new Set(own.map(f => f.id));
    if (item.images.some(id => !ownIds.has(id))) throw new Error('Chỉ dùng được ảnh trong thư mục Drive của chính Fanpage này');
    let when = item.when ? Number(item.when) : scheduler.nextSlot(item.pageId);
    if (!when) throw new Error('Hết chỗ trong lịch 30 ngày tới. Tăng số bài/ngày hoặc nới khung giờ.');
    if (when < Date.now() - 60 * 1000) throw new Error('Giờ đăng đã qua');
    const q = {
      id: crypto.randomUUID(), pageId: item.pageId, caption, images: item.images.slice(0, 10),
      when, status: 'scheduled', row: item.row || null, source: item.source || '', createdAt: Date.now(), attempts: 0
    };
    d.queue.push(q);
    store.log(`Lên lịch ${scheduler.fmt(when)} trên ${page.name}`);
    store.save();
    if (q.row) await sheet.setStatus(q.row, `Đã lên lịch · ${scheduler.fmt(when)} · ${page.name}`).catch(e => store.log(`Không ghi được trạng thái dòng ${q.row}: ${e.message}`, 'warn'));
    notify();
    return q;
  });
  handle('queue:action', async (id, action) => {
    const d = store.get();
    const q = d.queue.find(x => x.id === id);
    if (!q) throw new Error('Không tìm thấy bài');
    if (action === 'cancel' && ['scheduled', 'hold', 'failed'].includes(q.status)) {
      q.status = 'cancelled';
      if (q.row) await sheet.setStatus(q.row, '').catch(() => {});
    } else if (action === 'retry' && ['failed', 'hold'].includes(q.status)) {
      Object.assign(q, { status: 'scheduled', when: Date.now(), attempts: 0, error: '' });
      setTimeout(() => scheduler.tick(), 500);
    } else if (action === 'now' && q.status === 'scheduled') {
      q.when = Date.now();
      setTimeout(() => scheduler.tick(), 300);
    } else if (action === 'delete' && ['cancelled', 'done', 'failed'].includes(q.status)) {
      d.queue = d.queue.filter(x => x !== q);
    }
    store.save();
    notify();
    return publicState();
  });

  handle('app:open', url => { if (/^https?:\/\//.test(url)) shell.openExternal(url); });
  handle('app:copy', text => { clipboard.writeText(String(text)); });
  handle('app:dataDir', () => app.getPath('userData'));
}

app.whenReady().then(() => {
  store.get();
  registerIpc();
  createTray();
  if (!process.argv.includes('--hidden')) createWindow();
  scheduler.start(notify);
});

app.on('before-quit', () => { quitting = true; store.flush(); });
app.on('window-all-closed', () => { /* keep running in the tray */ });
