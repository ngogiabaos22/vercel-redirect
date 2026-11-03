/**
 * PageHub – kết nối Google Sheet
 * Dán toàn bộ mã này vào: Google Sheet → Tiện ích mở rộng → Apps Script → Lưu.
 * Sau đó: Triển khai → Tùy chọn triển khai mới → Ứng dụng web
 *   Thực thi với tư cách: Tôi · Người có quyền truy cập: Bất kỳ ai
 * Không chia sẻ mã này và URL ứng dụng web cho người khác: KEY bên dưới là khoá kết nối.
 */
const KEY = '__KEY__';
const SHEET_NAME = '';            // để trống = dùng sheet đầu tiên
const COLS = {
  date: 'Ngày đăng',
  page: 'Page',
  link: 'Link bài viết',
  caption: 'Caption',
  images: 'Link ảnh (Drive)',
  reaction: 'Reaction',
  comment: 'Comment',
  share: 'Share',
  status: 'Trạng thái sử dụng'
};
const MAX_IMAGE_BYTES = 8 * 1024 * 1024;

function doGet(e) { return handle_((e && e.parameter) || {}); }

function doPost(e) {
  let p = {};
  try { p = JSON.parse((e && e.postData && e.postData.contents) || '{}'); } catch (err) {}
  return handle_(p);
}

function handle_(p) {
  try {
    if (String(p.key || '') !== KEY) return out_({ ok: false, error: 'Sai khoá kết nối' });
    switch (p.action) {
      case 'ping':      return out_(ping_());
      case 'rows':      return out_(readRows_(p.sheet));
      case 'setStatus': return out_(setStatus_(p.sheet, Number(p.row), String(p.status || '')));
      case 'images':    return out_({ ok: true, files: listImages_(p.link) });
      case 'image':     return out_(imageData_(String(p.id || '')));
      default:          return out_({ ok: false, error: 'Thiếu hoặc sai action' });
    }
  } catch (err) {
    return out_({ ok: false, error: String(err && err.message || err) });
  }
}

function ping_() {
  const ss = SpreadsheetApp.getActive();
  const sh = sheet_();
  const head = headers_(sh);
  return {
    ok: true,
    file: ss.getName(),
    sheet: sh.getName(),
    sheets: ss.getSheets().map(s => s.getName()),
    missing: Object.values(COLS).filter(c => head.indexOf(c) < 0)
  };
}

function sheet_(name) {
  const ss = SpreadsheetApp.getActive();
  const n = name || SHEET_NAME;
  const sh = n ? ss.getSheetByName(n) : ss.getSheets()[0];
  if (!sh) throw new Error('Không tìm thấy sheet "' + n + '"');
  return sh;
}

function headers_(sh) {
  const last = Math.max(1, sh.getLastColumn());
  return sh.getRange(1, 1, 1, last).getDisplayValues()[0].map(h => String(h).trim());
}

function readRows_(name) {
  const sh = sheet_(name);
  const values = sh.getDataRange().getDisplayValues();
  const head = values[0].map(h => String(h).trim());
  const idx = {};
  Object.keys(COLS).forEach(k => { idx[k] = head.indexOf(COLS[k]); });
  const missing = Object.keys(COLS).filter(k => idx[k] < 0).map(k => COLS[k]);
  const num = v => Number(String(v).replace(/[^\d]/g, '')) || 0;
  const rows = [];
  for (let i = 1; i < values.length; i++) {
    const r = values[i];
    const get = k => (idx[k] >= 0 ? String(r[idx[k]]).trim() : '');
    if (!get('caption') && !get('link')) continue;
    rows.push({
      row: i + 1,
      date: get('date'),
      page: get('page'),
      link: get('link'),
      caption: get('caption'),
      images: get('images'),
      reaction: num(get('reaction')),
      comment: num(get('comment')),
      share: num(get('share')),
      status: get('status')
    });
  }
  return { ok: true, sheet: sh.getName(), missing: missing, rows: rows };
}

function setStatus_(name, row, status) {
  const sh = sheet_(name);
  const col = headers_(sh).indexOf(COLS.status) + 1;
  if (col < 1) throw new Error('Thiếu cột "' + COLS.status + '"');
  if (!(row >= 2 && row <= sh.getLastRow())) throw new Error('Số dòng không hợp lệ: ' + row);
  sh.getRange(row, col).setValue(status);
  return { ok: true, row: row, status: status };
}

/** Nhận link thư mục hoặc link ảnh Drive (nhiều link cách nhau bằng dấu phẩy hoặc xuống dòng). */
function listImages_(links) {
  const ids = String(links || '').split(/[\s,;]+/).map(idFromLink_).filter(Boolean);
  const files = [];
  ids.forEach(id => {
    let folder = null;
    try { folder = DriveApp.getFolderById(id); } catch (err) { folder = null; }
    if (folder) {
      const it = folder.getFiles();
      const inFolder = [];
      while (it.hasNext()) {
        const f = it.next();
        if (String(f.getMimeType()).indexOf('image/') === 0) inFolder.push(fileInfo_(f));
      }
      inFolder.sort((a, b) => a.name.localeCompare(b.name, 'vi', { numeric: true }));
      files.push.apply(files, inFolder);
    } else {
      try { files.push(fileInfo_(DriveApp.getFileById(id))); } catch (err) {}
    }
  });
  return files;
}

function fileInfo_(f) {
  return { id: f.getId(), name: f.getName(), mime: f.getMimeType(), size: f.getSize() };
}

function idFromLink_(s) {
  const m = String(s).match(/[-\w]{25,}/);
  return m ? m[0] : '';
}

function imageData_(id) {
  if (!id) throw new Error('Thiếu id ảnh');
  const f = DriveApp.getFileById(id);
  if (f.getSize() > MAX_IMAGE_BYTES) return { ok: false, error: 'Ảnh "' + f.getName() + '" lớn hơn 8MB' };
  const blob = f.getBlob();
  return { ok: true, name: f.getName(), mime: blob.getContentType(), base64: Utilities.base64Encode(blob.getBytes()) };
}

function out_(obj) {
  return ContentService.createTextOutput(JSON.stringify(obj)).setMimeType(ContentService.MimeType.JSON);
}
