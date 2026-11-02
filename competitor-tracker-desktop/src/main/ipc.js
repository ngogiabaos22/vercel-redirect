'use strict';
// Các lệnh giao diện gọi xuống tiến trình chính. Giao diện không bao giờ nhận token/khoá thật.
const fs = require('node:fs');
const { loadConfig, saveConfig } = require('./config');
const { pagesFromCsv, TEMPLATE } = require('./csv');
const { dashboard } = require('./stats');
const { nextRunText } = require('./scheduler');
const { MetaError } = require('./meta');
const google = require('./google');
const appsScript = require('./appsscript');
const { explainError, JOB_LABELS } = require('./engine');

const GROUP_COLORS = ['#1D4ED8', '#F97316', '#0D9488', '#7C3AED', '#DB2777', '#475569', '#CA8A04', '#0891B2'];

function sheetIdFrom(v) {
  const s = String(v || '').trim();
  const m = s.match(/\/spreadsheets\/d\/([A-Za-z0-9_-]+)/);
  return m ? m[1] : s;
}
function folderIdFrom(v) {
  const s = String(v || '').trim();
  const m = s.match(/\/folders\/([A-Za-z0-9_-]+)/) || s.match(/[?&]id=([A-Za-z0-9_-]+)/);
  return m ? m[1] : s;
}

function registerIpc({ ipcMain, dialog, shell, clipboard, app, store, secrets, engine, getWin, applyAppSettings }) {
  const handle = (channel, fn) => ipcMain.handle(channel, async (_e, ...args) => {
    try { return { ok: true, data: await fn(...args) }; } catch (e) { return { ok: false, error: explainError(e) }; }
  });
  const background = (promise, label) => {
    promise.catch((e) => store.log('error', label, explainError(e)));
    return { started: true };
  };

  // ---------- app ----------
  handle('app:info', () => {
    const cfg = loadConfig(store);
    return {
      version: app.getVersion(), dataDir: app.getPath('userData'), imagesDir: engine.imagesDir(cfg),
      nextRun: nextRunText(cfg), secrets: secrets.status(), jobLabels: JOB_LABELS,
      lastSheetSync: store.getSetting('lastSheetSync', null)
    };
  });
  handle('shell:openExternal', (url) => { if (/^https?:\/\//i.test(url)) shell.openExternal(url); });
  handle('shell:openPath', (p) => { if (p && fs.existsSync(p)) return shell.openPath(p); throw new Error('Không tìm thấy đường dẫn.'); });
  handle('dialog:pickFolder', async () => {
    const r = await dialog.showOpenDialog(getWin(), { properties: ['openDirectory', 'createDirectory'] });
    return r.canceled ? null : r.filePaths[0];
  });

  // ---------- groups ----------
  handle('groups:list', () => store.listGroups());
  handle('groups:create', (g) => {
    const n = store.listGroups().length;
    return store.createGroup({ name: g && g.name ? g.name : `Nhóm ${n + 1}`, purpose: (g && g.purpose) || '', color: GROUP_COLORS[n % GROUP_COLORS.length] });
  });
  handle('groups:update', (id, patch) => store.updateGroup(id, patch));
  handle('groups:delete', (id) => store.deleteGroup(id));

  // ---------- pages ----------
  handle('pages:list', (f) => store.listPages(f || {}));
  handle('pages:add', ({ input, groupIds, startDate, crawlNow }) => {
    const id = store.addPage({ input, groupIds, startDate });
    const crawling = !!crawlNow && secrets.has('metaToken');
    if (crawling) background(engine.runCrawl('initial', [id], 'add'), 'crawler');
    return { id, crawling };
  });
  handle('pages:importCsv', async ({ crawlNow }) => {
    const r = await dialog.showOpenDialog(getWin(), { filters: [{ name: 'CSV', extensions: ['csv', 'txt'] }], properties: ['openFile'] });
    if (r.canceled) return null;
    const rows = pagesFromCsv(fs.readFileSync(r.filePaths[0], 'utf8'));
    const groups = store.listGroups();
    const byName = new Map(groups.map((g) => [g.name.toLowerCase(), g.id]));
    const added = []; const errors = [];
    for (const row of rows) {
      try {
        const gids = row.groups.map((name) => {
          const k = name.toLowerCase();
          if (!byName.has(k)) byName.set(k, store.createGroup({ name, color: GROUP_COLORS[byName.size % GROUP_COLORS.length] }));
          return byName.get(k);
        });
        added.push(store.addPage({ input: row.input, groupIds: gids, startDate: row.startDate }));
      } catch (e) { errors.push(`${row.input}: ${e.message}`); }
    }
    const crawling = !!crawlNow && added.length > 0 && secrets.has('metaToken');
    if (crawling) background(engine.runCrawl('initial', added, 'import'), 'crawler');
    return { added: added.length, errors, crawling };
  });
  handle('pages:saveCsvTemplate', async () => {
    const r = await dialog.showSaveDialog(getWin(), { defaultPath: 'mau-danh-sach-page.csv', filters: [{ name: 'CSV', extensions: ['csv'] }] });
    if (r.canceled || !r.filePath) return null;
    fs.writeFileSync(r.filePath, TEMPLATE, 'utf8');
    return r.filePath;
  });
  handle('pages:setGroups', (id, groupIds) => store.setPageGroups(id, groupIds));
  handle('pages:addToGroup', (ids, groupId, move) => store.addPagesToGroup(ids, groupId, !!move));
  handle('pages:removeFromGroup', (id, groupId) => store.removePageFromGroup(id, groupId));
  handle('pages:setEnabled', (ids, enabled) => { for (const id of [].concat(ids)) store.setPageEnabled(id, enabled); });
  handle('pages:update', (id, patch) => store.updatePage(id, patch));
  handle('pages:delete', (ids, keepData) => {
    for (const id of [].concat(ids)) {
      const files = store.deletePage(id, keepData);
      for (const f of files) { try { fs.unlinkSync(f); } catch { /* đã xoá */ } }
    }
  });
  handle('pages:stats', (id) => store.get(`SELECT COUNT(*) AS posts,
      (SELECT COUNT(*) FROM content_assets a JOIN facebook_posts p ON p.id = a.post_id WHERE p.page_id = ? AND a.status IN ('downloaded','uploaded')) AS images
      FROM facebook_posts WHERE page_id = ?`, id, id));

  // ---------- jobs ----------
  handle('crawl:run', (type, pageIds) => {
    const ids = pageIds && pageIds.length ? pageIds : store.all('SELECT id FROM facebook_pages WHERE enabled = 1').map((r) => r.id);
    if (!ids.length) throw new Error('Không có Page nào đang bật theo dõi.');
    if (!secrets.has('metaToken')) throw new Error('Chưa nhập Access Token Meta — vào Cài đặt › Kết nối Meta để nhập trước khi thu thập.');
    return background(engine.runCrawl(type, ids, 'manual'), 'crawler');
  });
  handle('crawl:retryFailed', (jobId) => {
    const ids = store.all(`SELECT page_id FROM crawl_job_items WHERE job_id = ? AND status = 'error'`, jobId).map((r) => r.page_id);
    if (!ids.length) throw new Error('Không có Page lỗi trong tác vụ này.');
    if (!secrets.has('metaToken')) throw new Error('Chưa nhập Access Token Meta.');
    return background(engine.runCrawl('retry', ids, 'manual'), 'crawler');
  });
  handle('crawl:cancel', (jobId) => engine.cancel(jobId));
  handle('images:run', () => background(engine.runImages('manual'), 'images'));
  handle('images:preview', (cfg) => engine.previewRules(cfg));
  handle('sheets:run', () => background(engine.runSheets('manual'), 'sheets'));
  handle('jobs:list', () => store.listJobs(40));
  handle('jobs:items', (jobId) => store.jobItems(jobId));
  handle('jobs:running', () => {
    const jobs = store.all(`SELECT * FROM crawl_jobs WHERE status = 'running' ORDER BY id DESC`);
    return jobs.map((j) => ({ ...j, items: j.total_pages ? store.jobItems(j.id) : [] }));
  });
  handle('jobs:errors', () => store.all(`SELECT i.*, COALESCE(p.name, p.input) AS page_name, j.job_type, j.started_at AS job_started
      FROM crawl_job_items i JOIN facebook_pages p ON p.id = i.page_id JOIN crawl_jobs j ON j.id = i.job_id
      WHERE i.status = 'error' AND j.id = (SELECT MAX(id) FROM crawl_jobs WHERE job_type IN ('daily','manual','initial','retry'))
      ORDER BY i.id`));
  handle('logs:list', () => store.all('SELECT * FROM app_logs ORDER BY id DESC LIMIT 200'));

  // ---------- dashboard ----------
  handle('stats:dashboard', (f) => dashboard(store, f || {}));

  // ---------- config & secrets ----------
  handle('config:get', () => loadConfig(store));
  handle('config:save', (patch) => {
    if (patch.sheets && 'spreadsheetId' in patch.sheets) patch.sheets.spreadsheetId = sheetIdFrom(patch.sheets.spreadsheetId);
    if (patch.drive && 'rootFolderId' in patch.drive) patch.drive.rootFolderId = folderIdFrom(patch.drive.rootFolderId);
    const next = saveConfig(store, patch);
    applyAppSettings();
    return next;
  });
  handle('secrets:set', (name, value) => { secrets.set(name, value); return secrets.status(); });
  handle('secrets:status', () => secrets.status());
  handle('google:pickServiceAccount', async () => {
    const r = await dialog.showOpenDialog(getWin(), { filters: [{ name: 'JSON', extensions: ['json'] }], properties: ['openFile'] });
    if (r.canceled) return null;
    const raw = fs.readFileSync(r.filePaths[0], 'utf8');
    let key;
    try { key = JSON.parse(raw); } catch { throw new Error('File không phải JSON hợp lệ.'); }
    if (key.type !== 'service_account' || !key.client_email || !key.private_key) throw new Error('Đây không phải file khoá Service Account của Google Cloud.');
    secrets.set('googleServiceAccount', raw);
    saveConfig(store, { drive: { accountEmail: key.client_email } });
    return key.client_email;
  });
  handle('google:oauthLogin', async () => {
    const cfg = loadConfig(store);
    const r = await google.oauthLogin(cfg.drive.oauthClientId, secrets.get('googleOAuthSecret'), (url) => shell.openExternal(url));
    secrets.set('googleRefreshToken', r.refreshToken);
    saveConfig(store, { drive: { accountEmail: r.email } });
    return r.email;
  });
  // ---------- Google (cách đơn giản: Apps Script) ----------
  handle('clipboard:write', (text) => { clipboard.writeText(String(text)); });
  handle('google:scriptCode', () => {
    if (!secrets.has('appsScriptKey')) secrets.set('appsScriptKey', appsScript.newKey());
    return appsScript.scriptCode(secrets.get('appsScriptKey'));
  });
  handle('google:connectScript', async (url) => {
    const u = String(url || '').trim();
    if (!appsScript.validScriptUrl(u)) throw new Error('Link chưa đúng. Hãy dán "URL ứng dụng web" dạng https://script.google.com/macros/s/…/exec');
    if (!secrets.has('appsScriptKey')) throw new Error('Hãy bấm "Sao chép mã" và dán mã vào Apps Script trước.');
    const info = await new appsScript.AppsScript(u, secrets.get('appsScriptKey'), engine.fetch).info();
    const cfg = loadConfig(store);
    saveConfig(store, { drive: { authType: 'apps_script', scriptUrl: u }, sheets: { enabled: true }, images: { storage: cfg.images.storage === 'local' ? 'local_drive' : cfg.images.storage } });
    store.setSetting('appsScriptInfo', { ...info, connectedAt: new Date().toISOString() });
    return { ...info, outdated: (info.version || 0) < appsScript.SCRIPT_VERSION };
  });
  handle('google:disconnectScript', () => {
    saveConfig(store, { drive: { scriptUrl: '' } });
    store.setSetting('appsScriptInfo', null);
  });
  handle('google:scriptInfo', () => store.getSetting('appsScriptInfo', null));

  handle('google:test', async () => {
    const cfg = loadConfig(store);
    if (cfg.drive.authType === 'apps_script') {
      const info = await new appsScript.AppsScript(cfg.drive.scriptUrl, secrets.get('appsScriptKey'), engine.fetch).info();
      store.setSetting('appsScriptInfo', { ...info, connectedAt: new Date().toISOString() });
      return { drive: `OK · Thư mục "${info.folderName}"`, sheets: `OK · "${info.title}" · ${info.tabs.length} tab` };
    }
    const g = new google.Google(google.createAuth(cfg, secrets));
    const out = {};
    if (cfg.drive.rootFolderId) {
      try { const f = await g.checkFolder(cfg.drive.rootFolderId); out.drive = `OK · Thư mục "${f.name}"${f.driveId ? ' (Shared Drive)' : ''}`; } catch (e) { out.drive = `Lỗi: ${e.message}`; }
    } else out.drive = 'Chưa nhập thư mục Drive';
    if (cfg.sheets.spreadsheetId) {
      try { const s = await g.spreadsheet(cfg.sheets.spreadsheetId); out.sheets = `OK · "${s.properties.title}" · ${s.sheets.length} tab`; } catch (e) { out.sheets = `Lỗi: ${e.message}`; }
    } else out.sheets = 'Chưa nhập file Google Sheet';
    return out;
  });
  handle('meta:test', async (input) => {
    const client = engine.meta();
    const target = String(input || '').trim();
    if (!target) throw new Error('Nhập link hoặc ID một Page đối thủ để kiểm tra.');
    const { normalizePageInput } = require('./db');
    try {
      const info = await client.pageInfo(normalizePageInput(target));
      let sample = null;
      await client.fetchPosts(info.id, { maxPages: 1, onPage: async (posts) => { sample = posts; } });
      const p = sample && sample[0];
      return {
        page: `${info.name} (ID ${info.id})`,
        posts: sample ? `${sample.length} bài ở trang đầu` : 'Không lấy được bài viết',
        fields: p ? {
          caption: p.message !== null, reactions: p.reactions !== null, comments: p.comments !== null, shares: p.shares !== null, images: p.media.length > 0
        } : null,
        usage: client.lastUsage
      };
    } catch (e) {
      if (e instanceof MetaError) throw new Error(`${e.label}: ${explainError(e)}`);
      throw e;
    }
  });
}

module.exports = { registerIpc, sheetIdFrom, folderIdFrom };
