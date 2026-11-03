// Local data file in %APPDATA%/PageHub. Secrets (App Secret, AI key, Facebook tokens)
// are encrypted with Electron safeStorage, which uses Windows DPAPI for the current user.
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const { app, safeStorage } = require('electron');

const DEFAULT_PROMPT =
  'Bạn viết bài cho Fanpage "{page}". Dựa trên Ý TƯỞNG của bài dưới đây, viết một bài mới bằng tiếng Việt. ' +
  'Không chép câu chữ, không nhắc tên Page hay thương hiệu khác. Giọng thân thiện, 3–5 dòng, tối đa 2 emoji, ' +
  'kết bằng một câu mời nhắn tin. Chỉ trả về nội dung bài viết, không giải thích.';

function defaults() {
  return {
    settings: {
      sheet: { url: '', key: crypto.randomBytes(18).toString('base64url'), sheetName: '' },
      fb: { appId: '', secret: '', version: 'v23.0', port: 5175 },
      ai: { provider: 'none', key: '', model: '', prompt: DEFAULT_PROMPT, simMax: 40 },
      rules: { ws: '19:00', we: '21:30', max: 3, gap: 60 },
      app: { closeToTray: true, openAtLogin: false }
    },
    status: { sheet: null, fb: null, ai: null },
    srcMap: {},   // page name in the sheet -> your Fanpage id
    drive: {},    // your Fanpage id -> Drive folder link with your own images
    accounts: [], // Facebook nicks that signed in through the app
    pages: {},    // Fanpage id -> { id, name, tokens: { accountId: enc }, poster, strict }
    queue: [],
    log: []
  };
}

let data = null;
let file = null;
let timer = null;

function merge(base, extra) {
  if (!extra || typeof extra !== 'object' || Array.isArray(extra)) return extra === undefined ? base : extra;
  const out = { ...base };
  for (const k of Object.keys(extra)) {
    out[k] = base && typeof base[k] === 'object' && !Array.isArray(base[k]) && base[k] !== null ? merge(base[k], extra[k]) : extra[k];
  }
  return out;
}

function load() {
  file = path.join(app.getPath('userData'), 'pagehub-data.json');
  try {
    data = merge(defaults(), JSON.parse(fs.readFileSync(file, 'utf8')));
  } catch (e) {
    data = defaults();
  }
  return data;
}

function get() { return data || load(); }

function flush() {
  if (!file) return;
  const tmp = file + '.tmp';
  fs.mkdirSync(path.dirname(file), { recursive: true });
  fs.writeFileSync(tmp, JSON.stringify(data, null, 2));
  fs.renameSync(tmp, file);
}

function save() {
  clearTimeout(timer);
  timer = setTimeout(flush, 150);
}

function enc(s) {
  if (!s) return '';
  if (safeStorage.isEncryptionAvailable()) return 'enc:' + safeStorage.encryptString(s).toString('base64');
  return 'raw:' + Buffer.from(s, 'utf8').toString('base64');
}

function dec(s) {
  if (!s) return '';
  try {
    if (s.startsWith('enc:')) return safeStorage.decryptString(Buffer.from(s.slice(4), 'base64'));
    if (s.startsWith('raw:')) return Buffer.from(s.slice(4), 'base64').toString('utf8');
  } catch (e) {
    return '';
  }
  return s;
}

function log(msg, level = 'info') {
  const d = get();
  d.log.unshift({ t: Date.now(), msg: String(msg), level });
  d.log = d.log.slice(0, 500);
  save();
}

module.exports = { get, save, flush, enc, dec, log, DEFAULT_PROMPT };
