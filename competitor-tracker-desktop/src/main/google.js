'use strict';
// Google Drive + Google Sheets qua REST API, xác thực bằng Service Account (khuyên dùng với Shared Drive)
// hoặc OAuth tài khoản Google (đăng nhập qua trình duyệt).
const http = require('node:http');
const fs = require('node:fs');

const SCOPES = ['https://www.googleapis.com/auth/drive', 'https://www.googleapis.com/auth/spreadsheets'];
const DRIVE = 'https://www.googleapis.com/drive/v3';
const UPLOAD = 'https://www.googleapis.com/upload/drive/v3/files';
const SHEETS = 'https://sheets.googleapis.com/v4/spreadsheets';

const HEADER = ['Ngày đăng', 'Page', 'Link bài viết', 'Caption', 'Link ảnh', 'Reaction', 'Comment', 'Share', 'Trạng thái sử dụng', 'Ghi chú', 'Post ID (không sửa)'];
const NA = 'Không khả dụng';

function lib() { return require('google-auth-library'); }

/** Tạo client đã xác thực từ cấu hình + bí mật. */
function createAuth(cfg, secrets) {
  const { JWT, OAuth2Client } = lib();
  if (cfg.drive.authType === 'oauth') {
    const refresh = secrets.get('googleRefreshToken');
    if (!cfg.drive.oauthClientId || !secrets.get('googleOAuthSecret')) throw new Error('Chưa nhập OAuth Client ID / Client Secret của Google.');
    if (!refresh) throw new Error('Chưa đăng nhập tài khoản Google. Bấm "Đăng nhập Google" trong Cài đặt › Google.');
    const c = new OAuth2Client(cfg.drive.oauthClientId, secrets.get('googleOAuthSecret'));
    c.setCredentials({ refresh_token: refresh });
    return c;
  }
  const raw = secrets.get('googleServiceAccount');
  if (!raw) throw new Error('Chưa nhập file khoá Service Account (JSON) của Google.');
  let key;
  try { key = JSON.parse(raw); } catch { throw new Error('File khoá Service Account không phải JSON hợp lệ.'); }
  return new JWT({ email: key.client_email, key: key.private_key, scopes: SCOPES });
}

/** Đăng nhập OAuth qua trình duyệt (loopback 127.0.0.1). Trả về { refreshToken, email }. */
async function oauthLogin(clientId, clientSecret, openUrl) {
  const { OAuth2Client } = lib();
  const server = http.createServer();
  await new Promise((r) => server.listen(0, '127.0.0.1', r));
  const port = server.address().port;
  const redirect = `http://127.0.0.1:${port}`;
  const client = new OAuth2Client(clientId, clientSecret, redirect);
  const url = client.generateAuthUrl({ access_type: 'offline', prompt: 'consent', scope: [...SCOPES, 'openid', 'email'] });
  const code = await new Promise((resolve, reject) => {
    const timer = setTimeout(() => { server.close(); reject(new Error('Hết thời gian chờ đăng nhập Google (5 phút).')); }, 300000);
    server.on('request', (req, res) => {
      const u = new URL(req.url, redirect);
      const c = u.searchParams.get('code');
      const err = u.searchParams.get('error');
      res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
      res.end(`<html><body style="font-family:sans-serif;padding:40px"><h2>${c ? 'Đăng nhập thành công' : 'Đăng nhập thất bại'}</h2><p>Bạn có thể đóng tab này và quay lại Competitor Tracker.</p></body></html>`);
      clearTimeout(timer);
      server.close();
      if (c) resolve(c); else reject(new Error(`Google từ chối đăng nhập: ${err || 'không rõ lý do'}`));
    });
    openUrl(url);
  });
  const { tokens } = await client.getToken(code);
  if (!tokens.refresh_token) throw new Error('Google không trả về refresh token. Hãy thử đăng nhập lại.');
  let email = '';
  try {
    client.setCredentials(tokens);
    const info = await client.getTokenInfo(tokens.access_token);
    email = info.email || '';
  } catch { /* không bắt buộc */ }
  return { refreshToken: tokens.refresh_token, email };
}

class Google {
  constructor(auth) { this.auth = auth; }

  async req(opts) {
    try {
      const res = await this.auth.request({ retry: true, ...opts });
      return res.data;
    } catch (e) {
      const msg = e.response && e.response.data && e.response.data.error ? (e.response.data.error.message || JSON.stringify(e.response.data.error)) : e.message;
      const err = new Error(`Google API: ${msg}`);
      err.status = e.response && e.response.status;
      throw err;
    }
  }

  // ---------- Drive ----------
  async checkFolder(folderId) {
    return this.req({ url: `${DRIVE}/files/${encodeURIComponent(folderId)}`, params: { fields: 'id,name,mimeType,driveId', supportsAllDrives: true } });
  }
  async ensureFolder(name, parentId) {
    const q = `name = '${String(name).replace(/\\/g, '\\\\').replace(/'/g, "\\'")}' and mimeType = 'application/vnd.google-apps.folder' and '${parentId}' in parents and trashed = false`;
    const found = await this.req({ url: `${DRIVE}/files`, params: { q, fields: 'files(id,name)', supportsAllDrives: true, includeItemsFromAllDrives: true, corpora: 'allDrives' } });
    if (found.files && found.files.length) return found.files[0].id;
    const created = await this.req({ url: `${DRIVE}/files`, method: 'POST', params: { supportsAllDrives: true, fields: 'id' }, data: { name, mimeType: 'application/vnd.google-apps.folder', parents: [parentId] } });
    return created.id;
  }
  async uploadFile(localPath, name, parentId, mimeType = 'image/jpeg') {
    const boundary = 'ct' + Date.now().toString(16);
    const meta = JSON.stringify({ name, parents: [parentId] });
    const body = Buffer.concat([
      Buffer.from(`--${boundary}\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n${meta}\r\n--${boundary}\r\nContent-Type: ${mimeType}\r\n\r\n`),
      fs.readFileSync(localPath),
      Buffer.from(`\r\n--${boundary}--`)
    ]);
    return this.req({
      url: UPLOAD, method: 'POST',
      params: { uploadType: 'multipart', supportsAllDrives: true, fields: 'id,webViewLink' },
      headers: { 'Content-Type': `multipart/related; boundary=${boundary}` },
      data: body
    });
  }

  // ---------- Sheets ----------
  async spreadsheet(id) {
    return this.req({ url: `${SHEETS}/${id}`, params: { fields: 'properties.title,sheets.properties' } });
  }
  async ensureTab(spreadsheetId, title, known) {
    if (known.has(title)) return;
    await this.req({ url: `${SHEETS}/${spreadsheetId}:batchUpdate`, method: 'POST', data: { requests: [{ addSheet: { properties: { title, gridProperties: { frozenRowCount: 1 } } } }] } });
    await this.req({ url: `${SHEETS}/${spreadsheetId}/values/${encodeURIComponent(a1(title, 'A1:K1'))}`, method: 'PUT', params: { valueInputOption: 'RAW' }, data: { values: [HEADER] } });
    known.add(title);
  }
  async readPostIds(spreadsheetId, title) {
    const r = await this.req({ url: `${SHEETS}/${spreadsheetId}/values/${encodeURIComponent(a1(title, 'K:K'))}` });
    const map = new Map();
    (r.values || []).forEach((row, i) => { if (i > 0 && row[0]) map.set(String(row[0]), i + 1); });
    return map;
  }
  async appendRows(spreadsheetId, title, rows) {
    if (!rows.length) return;
    await this.req({
      url: `${SHEETS}/${spreadsheetId}/values/${encodeURIComponent(a1(title, 'A:K'))}:append`, method: 'POST',
      params: { valueInputOption: 'RAW', insertDataOption: 'INSERT_ROWS' }, data: { values: rows }
    });
  }
  async updateRanges(spreadsheetId, data) {
    for (let i = 0; i < data.length; i += 500) {
      await this.req({ url: `${SHEETS}/${spreadsheetId}/values:batchUpdate`, method: 'POST', data: { valueInputOption: 'RAW', data: data.slice(i, i + 500) } });
    }
  }
}

function a1(tab, range) { return `'${String(tab).replace(/'/g, "''")}'!${range}`; }

function fmtDate(iso) {
  if (!iso) return '';
  const p = new Intl.DateTimeFormat('en-GB', { timeZone: 'Asia/Ho_Chi_Minh', day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false })
    .formatToParts(new Date(iso)).reduce((o, x) => { o[x.type] = x.value; return o; }, {});
  return `${p.day}/${p.month}/${p.year} ${p.hour === '24' ? '00' : p.hour}:${p.minute}`;
}
const val = (n) => (n === null || n === undefined ? NA : n);

/** Dòng A..H cho một bài (I, J do người dùng tự điền — không ghi đè; K = Post ID để đối chiếu). */
function rowFor(post, assets, linkType) {
  const drive = assets.filter((a) => a.drive_link).map((a) => a.drive_link);
  const local = assets.filter((a) => a.local_path).map((a) => a.local_path);
  let links = [];
  if (linkType === 'local') links = local;
  else if (linkType === 'both') links = [...drive, ...local];
  else links = drive.length ? drive : local;
  return [fmtDate(post.created_time), post.page_name || '', post.permalink_url || '', post.message || '', links.join('\n'), val(post.reactions), val(post.comments), val(post.shares)];
}

function safeTabName(name) {
  const s = String(name).replace(/[\[\]\*\?\/\\:]/g, ' ').trim().slice(0, 95);
  return s || 'Nhóm';
}

module.exports = { createAuth, oauthLogin, Google, rowFor, safeTabName, HEADER, a1, fmtDate };
