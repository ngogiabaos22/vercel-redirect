'use strict';
// Chạy thật đoạn mã Apps Script trong môi trường giả lập Google Sheets / Drive, nối với app qua fetch giả.
const test = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { scriptCode, AppsScript, validScriptUrl } = require('../src/main/appsscript');
const { Store } = require('../src/main/db');
const { Secrets } = require('../src/main/secrets');
const { Engine } = require('../src/main/engine');
const { saveConfig } = require('../src/main/config');

const URL_OK = 'https://script.google.com/macros/s/AKfycbx_TEST-123/exec';

function fakeGoogle() {
  let nextId = 1;
  const folders = new Map();
  const files = [];
  const mkFolder = (name, parent) => {
    const f = {
      id: `F${nextId++}`, name, parent, children: [],
      getId() { return this.id; }, getName() { return this.name; }, getUrl() { return `https://drive.google.com/drive/folders/${this.id}`; }, isTrashed() { return false; },
      getFoldersByName(n) { const list = this.children.filter((c) => c.name === n); let i = 0; return { hasNext: () => i < list.length, next: () => list[i++] }; },
      createFolder(n) { const c = mkFolder(n, this); this.children.push(c); return c; },
      createFile(blob) { const file = { id: `D${nextId++}`, folder: this, blob, getId() { return this.id; }, getUrl() { return `https://drive.google.com/file/d/${this.id}/view`; } }; files.push(file); return file; }
    };
    folders.set(f.id, f);
    return f;
  };
  const sheets = [];
  const mkSheet = (name) => {
    const grid = [];
    const sh = {
      name, grid,
      getName() { return name; },
      getLastRow() { return grid.length; },
      setFrozenRows() {},
      getRange(r, c, nr, nc) {
        return {
          setValues(vals) { for (let i = 0; i < nr; i++) { grid[r - 1 + i] = grid[r - 1 + i] || []; for (let j = 0; j < nc; j++) grid[r - 1 + i][c - 1 + j] = vals[i][j]; } return this; },
          getValues() { const out = []; for (let i = 0; i < nr; i++) { const row = []; for (let j = 0; j < nc; j++) row.push((grid[r - 1 + i] || [])[c - 1 + j] ?? ''); out.push(row); } return out; },
          setFontWeight() { return this; }
        };
      }
    };
    sheets.push(sh);
    return sh;
  };
  mkSheet('Trang tính1');
  const props = {};
  const ctx = {
    ContentService: { MimeType: { JSON: 'json' }, createTextOutput: (s) => ({ text: s, setMimeType() { return this; } }) },
    LockService: { getScriptLock: () => ({ waitLock() {}, releaseLock() {} }) },
    PropertiesService: { getScriptProperties: () => ({ getProperty: (k) => props[k] || null, setProperty: (k, v) => { props[k] = v; } }) },
    DriveApp: { createFolder: (n) => mkFolder(n, null), getFolderById: (id) => { const f = folders.get(id); if (!f) throw new Error('not found'); return f; } },
    Utilities: { base64Decode: (s) => Buffer.from(s, 'base64'), newBlob: (bytes, mime, name) => ({ bytes, mime, name }) },
    SpreadsheetApp: {
      getActiveSpreadsheet: () => ({
        getName: () => 'Thống kê đối thủ', getUrl: () => 'https://docs.google.com/spreadsheets/d/XYZ/edit',
        getSheets: () => sheets, getSheetByName: (n) => sheets.find((s) => s.name === n) || null, insertSheet: (n) => mkSheet(n)
      })
    }
  };
  return { ctx, sheets, files, folders };
}

function wire(key) {
  const g = fakeGoogle();
  vm.createContext(g.ctx);
  vm.runInContext(scriptCode(key), g.ctx);
  const fetchImpl = async (url, opts) => {
    if (url.startsWith('https://img.test/')) {
      return { ok: true, status: 200, headers: { get: () => 'image/jpeg' }, arrayBuffer: async () => new Uint8Array([9, 9, 9]).buffer };
    }
    assert.equal(url, URL_OK);
    const out = g.ctx.doPost({ postData: { contents: opts.body } });
    return { status: 200, text: async () => out.text };
  };
  return { g, fetchImpl };
}

test('kiểm tra link Apps Script', () => {
  assert.ok(validScriptUrl(URL_OK));
  assert.ok(validScriptUrl('https://script.google.com/a/congty.vn/macros/s/abc_DEF/exec'));
  assert.ok(!validScriptUrl('https://script.google.com/macros/s/abc/dev'));
  assert.ok(!validScriptUrl('https://evil.com/macros/s/abc/exec'));
});

test('mã Apps Script: sai khoá bị từ chối, info tạo thư mục ảnh', async () => {
  const { fetchImpl, g } = wire('KEY123');
  await assert.rejects(new AppsScript(URL_OK, 'SAI', fetchImpl).info(), /Sai khoá/);
  const info = await new AppsScript(URL_OK, 'KEY123', fetchImpl).info();
  assert.equal(info.title, 'Thống kê đối thủ');
  assert.equal(info.folderName, 'Competitor Tracker - Ảnh');
  await new AppsScript(URL_OK, 'KEY123', fetchImpl).info();
  assert.equal([...g.folders.values()].filter((f) => !f.parent).length, 1, 'không tạo trùng thư mục gốc');
});

test('app + Apps Script: tải ảnh lên Drive và ghi Sheet theo nhóm', async () => {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'ct-as-'));
  const store = new Store(path.join(dir, 'db.sqlite'));
  const secrets = new Secrets(store, { available: false });
  secrets.set('appsScriptKey', 'KEY123');
  const { g, fetchImpl } = wire('KEY123');
  const engine = new Engine({ store, secrets, dataDir: dir, fetchImpl, sleep: async () => {} });
  saveConfig(store, { drive: { authType: 'apps_script', scriptUrl: URL_OK }, sheets: { enabled: true }, images: { storage: 'local_drive' } });

  const pageId = store.addPage({ input: 'exampleA', groupIds: [1, 3] });
  store.savePageInfo(pageId, { id: '111', name: 'Page đối thủ A' });
  const created = new Date().toISOString();
  store.upsertPost(pageId, { facebook_post_id: '111_1', message: '=HYPERLINK("x") khuyến mãi', permalink_url: 'https://facebook.com/111_1', created_time: created,
    media: [{ type: 'photo', src: 'https://img.test/a.jpg' }], reactions: 10, comments: null, shares: 2 });

  const img = await engine.runImages();
  assert.equal(img.downloaded, 1);
  assert.equal(img.uploaded, 1);
  assert.equal(g.files.length, 1);
  const file = g.files[0];
  assert.equal(file.folder.name, created.slice(0, 7));
  assert.equal(file.folder.parent.name, 'Page đối thủ A');

  const r1 = await engine.runSheets();
  assert.equal(r1.appended, 3); // Nhóm 1, Nhóm 3, Tổng hợp
  const tab = g.sheets.find((s) => s.name === 'Nhóm 1');
  assert.equal(tab.grid[0][0], 'Ngày đăng');
  const row = tab.grid[1];
  assert.equal(row[1], 'Page đối thủ A');
  assert.match(row[3], /^'=HYPERLINK/, 'chống chèn công thức');
  assert.equal(row[4], `https://drive.google.com/file/d/${file.id}/view`);
  assert.equal(row[6], 'Không khả dụng');
  assert.equal(row[10], '111_1');
  assert.ok(!g.sheets.find((s) => s.name === 'Nhóm 2').grid[1], 'Nhóm 2 không có bài');

  // người dùng tự điền cột I, sắp xếp lại không ảnh hưởng; cập nhật chỉ ghi E–H
  tab.grid[1][8] = 'Đã dùng';
  await new Promise((r) => setTimeout(r, 1100));
  store.updatePostMetrics(store.get('SELECT id FROM facebook_posts').id, { reactions: 50, comments: 4, shares: 3 });
  const r2 = await engine.runSheets();
  assert.equal(r2.appended, 0);
  assert.ok(r2.updated >= 1);
  assert.equal(tab.grid[1][5], 50);
  assert.equal(tab.grid[1][8], 'Đã dùng');
});
