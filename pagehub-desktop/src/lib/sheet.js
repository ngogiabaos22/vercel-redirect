// Client for the owner's Apps Script web app (src/assets/Code.gs).
const fs = require('fs');
const path = require('path');
const store = require('./store');

const imageCache = new Map(); // folder link -> { at, files }
const thumbCache = new Map(); // file id -> data URL

async function call(params) {
  const s = store.get().settings.sheet;
  if (!s.url) throw new Error('Chưa nhập URL ứng dụng web Apps Script (Cài đặt kết nối › Google Sheet)');
  const u = new URL(s.url);
  for (const [k, v] of Object.entries({ ...params, key: s.key })) {
    if (v !== undefined && v !== null && v !== '') u.searchParams.set(k, String(v));
  }
  let res;
  try {
    res = await fetch(u, { redirect: 'follow' });
  } catch (e) {
    throw new Error('Không gọi được Apps Script: ' + e.message);
  }
  const text = await res.text();
  let json;
  try {
    json = JSON.parse(text);
  } catch (e) {
    throw new Error('Apps Script không trả về dữ liệu. Kiểm tra URL, và lúc triển khai đã chọn "Người có quyền truy cập: Bất kỳ ai".');
  }
  if (!json.ok) throw new Error(json.error || 'Apps Script báo lỗi');
  return json;
}

function code() {
  const tpl = fs.readFileSync(path.join(__dirname, '..', 'assets', 'Code.gs'), 'utf8');
  return tpl.replace('__KEY__', store.get().settings.sheet.key);
}

async function rows() {
  const name = store.get().settings.sheet.sheetName;
  return call({ action: 'rows', sheet: name });
}

async function images(link, force) {
  if (!link) return [];
  const hit = imageCache.get(link);
  if (!force && hit && Date.now() - hit.at < 5 * 60 * 1000) return hit.files;
  const j = await call({ action: 'images', link });
  imageCache.set(link, { at: Date.now(), files: j.files });
  return j.files;
}

async function image(id) {
  return call({ action: 'image', id });
}

async function thumb(id) {
  if (thumbCache.has(id)) return thumbCache.get(id);
  const j = await image(id);
  const url = `data:${j.mime};base64,${j.base64}`;
  if (thumbCache.size > 80) thumbCache.delete(thumbCache.keys().next().value);
  thumbCache.set(id, url);
  return url;
}

async function setStatus(row, status) {
  const name = store.get().settings.sheet.sheetName;
  return call({ action: 'setStatus', sheet: name, row, status });
}

module.exports = { call, code, rows, images, image, thumb, setStatus };
