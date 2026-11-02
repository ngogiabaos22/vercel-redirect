'use strict';
// Kết nối Google đơn giản: một đoạn Google Apps Script dán vào chính file Google Sheet của bạn.
// Không cần Google Cloud Console, không cần OAuth Client hay Service Account.
// Script tự tạo thư mục Drive để lưu ảnh và ghi dữ liệu vào các tab của Sheet.
const fs = require('node:fs');
const crypto = require('node:crypto');

const SCRIPT_VERSION = 1;

/** Mã Apps Script người dùng dán vào Sheet (Tiện ích mở rộng → Apps Script). KEY = khoá kết nối riêng của máy này. */
function scriptCode(key) {
  return `// Competitor Tracker — kết nối Google Sheets + Drive (phiên bản ${SCRIPT_VERSION})
// Dán toàn bộ đoạn này vào Apps Script của file Google Sheet, rồi Triển khai → Ứng dụng web.
// KHÔNG chia sẻ đoạn mã này: KEY bên dưới là khoá kết nối riêng của bạn.
const KEY = '${key}';
const FOLDER_NAME = 'Competitor Tracker - Ảnh';

function doPost(e) {
  try {
    const req = JSON.parse(e.postData.contents);
    if (req.key !== KEY) return out_({ ok: false, error: 'Sai khoá kết nối. Hãy sao chép lại mã từ app.' });
    const lock = LockService.getScriptLock();
    lock.waitLock(30000);
    try { return out_({ ok: true, data: handle_(req) }); } finally { lock.releaseLock(); }
  } catch (err) {
    return out_({ ok: false, error: String((err && err.message) || err) });
  }
}

function doGet() { return out_({ ok: true, data: { app: 'Competitor Tracker', version: ${SCRIPT_VERSION} } }); }

function out_(o) { return ContentService.createTextOutput(JSON.stringify(o)).setMimeType(ContentService.MimeType.JSON); }
function ss_() { return SpreadsheetApp.getActiveSpreadsheet(); }
// Chống chèn công thức: ô chữ bắt đầu bằng = + - @ được ghi dạng văn bản
function safe_(v) { return typeof v === 'string' && /^[=+\\-@]/.test(v) ? "'" + v : v; }

function root_() {
  const props = PropertiesService.getScriptProperties();
  const id = props.getProperty('FOLDER_ID');
  if (id) { try { const f = DriveApp.getFolderById(id); if (!f.isTrashed()) return f; } catch (e) {} }
  const f = DriveApp.createFolder(FOLDER_NAME);
  props.setProperty('FOLDER_ID', f.getId());
  return f;
}
function child_(parent, name) {
  const it = parent.getFoldersByName(name);
  return it.hasNext() ? it.next() : parent.createFolder(name);
}
function sheet_(title) {
  const sh = ss_().getSheetByName(title);
  if (!sh) throw new Error('Không có tab "' + title + '"');
  return sh;
}

function handle_(req) {
  switch (req.action) {
    case 'info': {
      const s = ss_(); const f = root_();
      return { version: ${SCRIPT_VERSION}, title: s.getName(), url: s.getUrl(), tabs: s.getSheets().map(function (x) { return x.getName(); }), folderName: f.getName(), folderUrl: f.getUrl() };
    }
    case 'ensureTab': {
      const s = ss_();
      if (!s.getSheetByName(req.title)) {
        const sh = s.insertSheet(req.title);
        sh.getRange(1, 1, 1, req.header.length).setValues([req.header]).setFontWeight('bold');
        sh.setFrozenRows(1);
      }
      return true;
    }
    case 'readPostIds': {
      const sh = ss_().getSheetByName(req.title);
      if (!sh || sh.getLastRow() < 2) return [];
      return sh.getRange(2, 11, sh.getLastRow() - 1, 1).getValues().map(function (r) { return String(r[0]); });
    }
    case 'append': {
      if (!req.rows.length) return 0;
      const sh = sheet_(req.title);
      sh.getRange(sh.getLastRow() + 1, 1, req.rows.length, req.rows[0].length).setValues(req.rows.map(function (r) { return r.map(safe_); }));
      return req.rows.length;
    }
    case 'update': {
      const sh = sheet_(req.title);
      req.data.forEach(function (d) { sh.getRange(d.row, d.col, 1, d.values.length).setValues([d.values.map(safe_)]); });
      return req.data.length;
    }
    case 'upload': {
      const folder = child_(child_(root_(), req.page), req.month);
      const blob = Utilities.newBlob(Utilities.base64Decode(req.data), req.mime, req.name);
      const file = folder.createFile(blob);
      return { id: file.getId(), url: file.getUrl() };
    }
  }
  throw new Error('Lệnh không hợp lệ: ' + req.action);
}
`;
}

function newKey() { return crypto.randomBytes(18).toString('base64url'); }

function validScriptUrl(u) {
  return /^https:\/\/script\.google\.com\/(a\/[^/]+\/)?macros\/s\/[A-Za-z0-9_-]+\/exec\/?$/.test(String(u || '').trim());
}

/** Backend có cùng giao diện với lớp Google (google.js) để engine dùng chung. */
class AppsScript {
  constructor(url, key, fetchImpl) {
    if (!url) throw new Error('Chưa dán link Ứng dụng web Apps Script (Cài đặt › Google).');
    if (!validScriptUrl(url)) throw new Error('Link Apps Script không đúng. Link đúng có dạng https://script.google.com/macros/s/…/exec');
    if (!key) throw new Error('Chưa có khoá kết nối. Hãy bấm "Sao chép mã" trong Cài đặt › Google và dán lại vào Apps Script.');
    this.url = url.trim(); this.key = key; this.fetch = fetchImpl || globalThis.fetch;
  }

  async call(action, payload = {}) {
    let res;
    try {
      res = await this.fetch(this.url, {
        method: 'POST', redirect: 'follow',
        headers: { 'Content-Type': 'text/plain;charset=utf-8' },
        body: JSON.stringify({ key: this.key, action, ...payload })
      });
    } catch (e) {
      throw new Error(`Không gọi được Apps Script: ${e.message}`);
    }
    const text = await res.text();
    let body;
    try { body = JSON.parse(text); } catch {
      if (/<html/i.test(text)) throw new Error('Apps Script trả về trang web thay vì dữ liệu. Kiểm tra lại: Triển khai dạng "Ứng dụng web", "Người có quyền truy cập: Bất kỳ ai", và dán đúng link kết thúc bằng /exec.');
      throw new Error(`Apps Script trả về dữ liệu không hợp lệ (HTTP ${res.status}).`);
    }
    if (!body.ok) throw new Error(`Apps Script: ${body.error}`);
    return body.data;
  }

  async info() { return this.call('info'); }

  // ----- giao diện giống google.Google -----
  async checkFolder() { const i = await this.info(); return { id: 'apps-script', name: i.folderName, url: i.folderUrl }; }
  async spreadsheet() {
    const i = await this.info();
    return { properties: { title: i.title }, sheets: i.tabs.map((t) => ({ properties: { title: t } })) };
  }
  async ensureTab(_id, title, known) {
    if (known.has(title)) return;
    const { HEADER } = require('./google');
    await this.call('ensureTab', { title, header: HEADER });
    known.add(title);
  }
  async readPostIds(_id, title) {
    const ids = await this.call('readPostIds', { title });
    const map = new Map();
    ids.forEach((id, i) => { if (id) map.set(String(id), i + 2); });
    return map;
  }
  async appendRows(_id, title, rows) {
    for (let i = 0; i < rows.length; i += 500) await this.call('append', { title, rows: rows.slice(i, i + 500) });
  }
  async updateRanges(_id, data) {
    const byTab = new Map();
    for (const d of data) {
      const m = /^'((?:[^']|'')*)'!([A-Z]+)(\d+):[A-Z]+\d+$/.exec(d.range);
      if (!m) throw new Error(`Vùng ô không hợp lệ: ${d.range}`);
      const tab = m[1].replace(/''/g, "'");
      if (!byTab.has(tab)) byTab.set(tab, []);
      byTab.get(tab).push({ row: Number(m[3]), col: colNum(m[2]), values: d.values[0] });
    }
    for (const [title, list] of byTab) {
      for (let i = 0; i < list.length; i += 500) await this.call('update', { title, data: list.slice(i, i + 500) });
    }
  }
  /** Tải 1 ảnh lên thư mục Drive do script quản lý: <thư mục gốc>/<Page>/<YYYY-MM>/ */
  async uploadImage(localPath, { page, month, name, mime }) {
    const r = await this.call('upload', { page, month, name, mime, data: fs.readFileSync(localPath).toString('base64') });
    return { id: r.id, webViewLink: r.url };
  }
}

function colNum(letters) { return letters.split('').reduce((n, c) => n * 26 + (c.charCodeAt(0) - 64), 0); }

module.exports = { AppsScript, scriptCode, newKey, validScriptUrl, SCRIPT_VERSION };
