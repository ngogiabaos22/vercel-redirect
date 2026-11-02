'use strict';
// Lớp dữ liệu: SQLite tích hợp sẵn trong Node/Electron (node:sqlite), không cần cài thêm module native.
const { DatabaseSync } = require('node:sqlite');

const SCHEMA = `
CREATE TABLE IF NOT EXISTS page_groups (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  purpose TEXT DEFAULT '',
  color TEXT DEFAULT '#1D4ED8',
  -- điều kiện tải ảnh riêng: default | custom | all | none
  image_mode TEXT NOT NULL DEFAULT 'default',
  min_reactions INTEGER, min_comments INTEGER, min_shares INTEGER,
  sheet_enabled INTEGER NOT NULL DEFAULT 1,
  sheet_tab TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS facebook_pages (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  input TEXT NOT NULL,
  facebook_page_id TEXT UNIQUE,
  name TEXT, username TEXT, link TEXT, category TEXT, picture_url TEXT,
  fan_count INTEGER, followers_count INTEGER,
  enabled INTEGER NOT NULL DEFAULT 1,
  -- pending | active | running | error
  status TEXT NOT NULL DEFAULT 'pending',
  last_error_code TEXT, last_error_message TEXT,
  start_date TEXT,
  last_crawl_at TEXT, last_info_at TEXT,
  initial_done INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS page_group_members (
  page_id INTEGER NOT NULL REFERENCES facebook_pages(id) ON DELETE CASCADE,
  group_id INTEGER NOT NULL REFERENCES page_groups(id) ON DELETE CASCADE,
  PRIMARY KEY (page_id, group_id)
);

CREATE TABLE IF NOT EXISTS facebook_posts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  page_id INTEGER NOT NULL REFERENCES facebook_pages(id) ON DELETE CASCADE,
  facebook_post_id TEXT NOT NULL UNIQUE,
  message TEXT, permalink_url TEXT, post_type TEXT,
  created_time TEXT,
  full_picture TEXT,
  media_json TEXT DEFAULT '[]',
  reactions INTEGER, comments INTEGER, shares INTEGER,
  first_crawled_at TEXT NOT NULL DEFAULT (datetime('now')),
  last_crawled_at TEXT,
  -- '' | done | skipped | partial | error
  image_status TEXT NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS idx_posts_page_time ON facebook_posts(page_id, created_time DESC);

CREATE TABLE IF NOT EXISTS post_metrics (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  post_id INTEGER NOT NULL REFERENCES facebook_posts(id) ON DELETE CASCADE,
  collected_at TEXT NOT NULL DEFAULT (datetime('now')),
  reactions INTEGER, comments INTEGER, shares INTEGER
);
CREATE INDEX IF NOT EXISTS idx_metrics_post ON post_metrics(post_id, collected_at DESC);

CREATE TABLE IF NOT EXISTS content_assets (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  post_id INTEGER NOT NULL REFERENCES facebook_posts(id) ON DELETE CASCADE,
  idx INTEGER NOT NULL,
  source_url TEXT,
  local_path TEXT,
  size_bytes INTEGER,
  drive_file_id TEXT, drive_link TEXT,
  -- pending | downloaded | uploaded | error
  status TEXT NOT NULL DEFAULT 'pending',
  error TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now')),
  UNIQUE(post_id, idx)
);

CREATE TABLE IF NOT EXISTS sheet_rows (
  post_id INTEGER NOT NULL REFERENCES facebook_posts(id) ON DELETE CASCADE,
  tab TEXT NOT NULL,
  row_number INTEGER NOT NULL,
  synced_at TEXT NOT NULL DEFAULT (datetime('now')),
  PRIMARY KEY (post_id, tab)
);

CREATE TABLE IF NOT EXISTS crawl_jobs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  job_type TEXT NOT NULL,
  -- running | done | partial | failed | cancelled
  status TEXT NOT NULL DEFAULT 'running',
  trigger TEXT NOT NULL DEFAULT 'manual',
  total_pages INTEGER DEFAULT 0, ok_pages INTEGER DEFAULT 0, failed_pages INTEGER DEFAULT 0,
  new_posts INTEGER DEFAULT 0, updated_posts INTEGER DEFAULT 0, images INTEGER DEFAULT 0,
  started_at TEXT NOT NULL DEFAULT (datetime('now')),
  finished_at TEXT,
  message TEXT
);

CREATE TABLE IF NOT EXISTS crawl_job_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  job_id INTEGER NOT NULL REFERENCES crawl_jobs(id) ON DELETE CASCADE,
  page_id INTEGER NOT NULL REFERENCES facebook_pages(id) ON DELETE CASCADE,
  status TEXT NOT NULL DEFAULT 'pending',
  step TEXT, progress INTEGER DEFAULT 0,
  cursor TEXT,
  posts_fetched INTEGER DEFAULT 0,
  error_code TEXT, error_message TEXT,
  started_at TEXT, finished_at TEXT,
  UNIQUE(job_id, page_id)
);

CREATE TABLE IF NOT EXISTS settings (
  key TEXT PRIMARY KEY,
  value TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS app_logs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  level TEXT NOT NULL,
  source TEXT,
  message TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
`;

const DEFAULT_GROUPS = [
  ['Nhóm 1', 'Đối thủ trực tiếp', '#1D4ED8'],
  ['Nhóm 2', 'Đối thủ cùng ngành', '#F97316'],
  ['Nhóm 3', 'Tham khảo Content', '#0D9488']
];

class Store {
  constructor(file) {
    this.db = new DatabaseSync(file);
    this.db.exec('PRAGMA journal_mode = WAL; PRAGMA foreign_keys = ON;');
    this.db.exec(SCHEMA);
    const n = this.db.prepare('SELECT COUNT(*) AS n FROM page_groups').get().n;
    const seeded = this.getSetting('seeded', false);
    if (!n && !seeded) {
      const ins = this.db.prepare('INSERT INTO page_groups(name, purpose, color) VALUES (?,?,?)');
      for (const g of DEFAULT_GROUPS) ins.run(...g);
    }
    this.setSetting('seeded', true);
  }

  close() { this.db.close(); }
  all(sql, ...p) { return this.db.prepare(sql).all(...p).map(plain); }
  get(sql, ...p) { const r = this.db.prepare(sql).get(...p); return r ? plain(r) : null; }
  run(sql, ...p) { return this.db.prepare(sql).run(...p); }
  tx(fn) {
    this.db.exec('BEGIN');
    try { const r = fn(); this.db.exec('COMMIT'); return r; } catch (e) { this.db.exec('ROLLBACK'); throw e; }
  }

  // ---------- settings ----------
  getSetting(key, fallback) {
    const r = this.db.prepare('SELECT value FROM settings WHERE key = ?').get(key);
    if (!r) return fallback;
    try { return JSON.parse(r.value); } catch { return fallback; }
  }
  setSetting(key, value) {
    this.run('INSERT INTO settings(key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value', key, JSON.stringify(value));
  }

  log(level, source, message) {
    this.run('INSERT INTO app_logs(level, source, message) VALUES (?,?,?)', level, source, String(message).slice(0, 2000));
  }

  // ---------- groups ----------
  listGroups() {
    return this.all(`SELECT g.*,
        (SELECT COUNT(*) FROM page_group_members m WHERE m.group_id = g.id) AS page_count,
        (SELECT COUNT(*) FROM facebook_posts p JOIN page_group_members m ON m.page_id = p.page_id WHERE m.group_id = g.id) AS post_count
      FROM page_groups g ORDER BY g.id`);
  }
  createGroup({ name, purpose = '', color = '#1D4ED8' }) {
    const r = this.run('INSERT INTO page_groups(name, purpose, color) VALUES (?,?,?)', requireText(name, 'Tên nhóm'), purpose, color);
    return Number(r.lastInsertRowid);
  }
  updateGroup(id, patch) {
    const allowed = ['name', 'purpose', 'color', 'image_mode', 'min_reactions', 'min_comments', 'min_shares', 'sheet_enabled'];
    for (const k of Object.keys(patch)) {
      if (!allowed.includes(k)) continue;
      let v = patch[k];
      if (k === 'name') v = requireText(v, 'Tên nhóm');
      if (k === 'sheet_enabled') v = v ? 1 : 0;
      if (k.startsWith('min_')) v = v === '' || v == null ? null : Math.max(0, parseInt(v, 10) || 0);
      this.run(`UPDATE page_groups SET ${k} = ? WHERE id = ?`, v, id);
    }
  }
  // Xoá nhóm chỉ xoá quan hệ, KHÔNG xoá Page hay bài viết.
  deleteGroup(id) {
    this.tx(() => {
      this.run('DELETE FROM page_group_members WHERE group_id = ?', id);
      this.run('DELETE FROM page_groups WHERE id = ?', id);
    });
  }

  // ---------- pages ----------
  listPages({ groupId, status, q } = {}) {
    const where = [];
    const params = [];
    if (groupId) { where.push('p.id IN (SELECT page_id FROM page_group_members WHERE group_id = ?)'); params.push(groupId); }
    if (status === 'disabled') where.push('p.enabled = 0');
    else if (status) { where.push('p.enabled = 1 AND p.status = ?'); params.push(status); }
    if (q) { where.push('(p.name LIKE ? OR p.input LIKE ? OR p.facebook_page_id LIKE ? OR p.username LIKE ?)'); const l = `%${q}%`; params.push(l, l, l, l); }
    const rows = this.all(`SELECT p.*,
        (SELECT COUNT(*) FROM facebook_posts x WHERE x.page_id = p.id) AS post_count,
        (SELECT group_concat(group_id) FROM page_group_members m WHERE m.page_id = p.id) AS group_ids
      FROM facebook_pages p ${where.length ? 'WHERE ' + where.join(' AND ') : ''}
      ORDER BY p.id DESC`, ...params);
    return rows.map((r) => ({ ...r, group_ids: r.group_ids ? String(r.group_ids).split(',').map(Number) : [] }));
  }
  getPage(id) { return this.get('SELECT * FROM facebook_pages WHERE id = ?', id); }
  pageGroups(pageId) { return this.all('SELECT g.* FROM page_groups g JOIN page_group_members m ON m.group_id = g.id WHERE m.page_id = ?', pageId); }

  addPage({ input, groupIds = [], startDate = null }) {
    const raw = requireText(input, 'Link hoặc Page ID');
    const key = normalizePageInput(raw);
    const dup = this.get('SELECT id FROM facebook_pages WHERE input = ? OR facebook_page_id = ? OR lower(username) = lower(?)', key, key, key);
    if (dup) throw new Error(`Page "${raw}" đã có trong danh sách.`);
    return this.tx(() => {
      const r = this.run('INSERT INTO facebook_pages(input, start_date) VALUES (?, ?)', key, startDate || null);
      const id = Number(r.lastInsertRowid);
      this.setPageGroups(id, groupIds);
      return id;
    });
  }
  setPageGroups(pageId, groupIds) {
    this.run('DELETE FROM page_group_members WHERE page_id = ?', pageId);
    const ins = this.db.prepare('INSERT OR IGNORE INTO page_group_members(page_id, group_id) VALUES (?, ?)');
    for (const g of groupIds) ins.run(pageId, Number(g));
  }
  addPagesToGroup(pageIds, groupId, move = false) {
    this.tx(() => {
      for (const id of pageIds) {
        if (move) this.run('DELETE FROM page_group_members WHERE page_id = ?', id);
        this.run('INSERT OR IGNORE INTO page_group_members(page_id, group_id) VALUES (?, ?)', id, groupId);
      }
    });
  }
  removePageFromGroup(pageId, groupId) {
    this.run('DELETE FROM page_group_members WHERE page_id = ? AND group_id = ?', pageId, groupId);
  }
  setPageEnabled(id, enabled) { this.run('UPDATE facebook_pages SET enabled = ? WHERE id = ?', enabled ? 1 : 0, id); }
  updatePage(id, patch) {
    const allowed = ['start_date'];
    for (const k of Object.keys(patch)) if (allowed.includes(k)) this.run(`UPDATE facebook_pages SET ${k} = ? WHERE id = ?`, patch[k] || null, id);
  }
  // keepData = true: chỉ ngừng theo dõi (enabled=0, gỡ khỏi nhóm). false: xoá Page + bài + ảnh (DB).
  deletePage(id, keepData) {
    if (keepData) {
      this.tx(() => {
        this.run('UPDATE facebook_pages SET enabled = 0 WHERE id = ?', id);
        this.run('DELETE FROM page_group_members WHERE page_id = ?', id);
      });
      return [];
    }
    const files = this.all('SELECT a.local_path FROM content_assets a JOIN facebook_posts p ON p.id = a.post_id WHERE p.page_id = ? AND a.local_path IS NOT NULL', id).map((r) => r.local_path);
    this.run('DELETE FROM facebook_pages WHERE id = ?', id);
    return files;
  }
  savePageInfo(id, info) {
    this.run(`UPDATE facebook_pages SET facebook_page_id = ?, name = ?, username = ?, link = ?, category = ?, picture_url = ?,
      fan_count = ?, followers_count = ?, last_info_at = datetime('now') WHERE id = ?`,
    info.id, info.name ?? null, info.username ?? null, info.link ?? null, info.category ?? null,
    info.picture?.data?.url ?? null, numOrNull(info.fan_count), numOrNull(info.followers_count), id);
  }
  setPageStatus(id, status, code = null, message = null) {
    this.run('UPDATE facebook_pages SET status = ?, last_error_code = ?, last_error_message = ? WHERE id = ?', status, code, message, id);
  }

  // ---------- posts ----------
  // UPSERT bài viết + luôn thêm 1 snapshot metrics mới (không ghi đè lịch sử).
  upsertPost(pageId, p) {
    const existing = this.get('SELECT id FROM facebook_posts WHERE facebook_post_id = ?', p.facebook_post_id);
    let postId;
    if (existing) {
      postId = existing.id;
      this.run(`UPDATE facebook_posts SET message = COALESCE(?, message), permalink_url = COALESCE(?, permalink_url), post_type = COALESCE(?, post_type),
        full_picture = COALESCE(?, full_picture), media_json = ?, reactions = ?, comments = ?, shares = ?, last_crawled_at = datetime('now') WHERE id = ?`,
      p.message ?? null, p.permalink_url ?? null, p.post_type ?? null, p.full_picture ?? null, JSON.stringify(p.media || []),
      p.reactions, p.comments, p.shares, postId);
    } else {
      const r = this.run(`INSERT INTO facebook_posts(page_id, facebook_post_id, message, permalink_url, post_type, created_time, full_picture, media_json,
        reactions, comments, shares, last_crawled_at) VALUES (?,?,?,?,?,?,?,?,?,?,?, datetime('now'))`,
      pageId, p.facebook_post_id, p.message ?? null, p.permalink_url ?? null, p.post_type ?? null, p.created_time ?? null,
      p.full_picture ?? null, JSON.stringify(p.media || []), p.reactions, p.comments, p.shares);
      postId = Number(r.lastInsertRowid);
    }
    this.addSnapshot(postId, p);
    return { postId, isNew: !existing };
  }
  addSnapshot(postId, m) {
    this.run('INSERT INTO post_metrics(post_id, reactions, comments, shares) VALUES (?,?,?,?)', postId, m.reactions, m.comments, m.shares);
  }
  updatePostMetrics(postId, m) {
    this.run(`UPDATE facebook_posts SET reactions = ?, comments = ?, shares = ?, last_crawled_at = datetime('now') WHERE id = ?`, m.reactions, m.comments, m.shares, postId);
    this.addSnapshot(postId, m);
  }
  latestPostTime(pageId) {
    const r = this.get('SELECT MAX(created_time) AS t FROM facebook_posts WHERE page_id = ?', pageId);
    return r && r.t;
  }
  recentPosts(pageId, limit, excludeIds = []) {
    const rows = this.all('SELECT * FROM facebook_posts WHERE page_id = ? ORDER BY created_time DESC LIMIT ?', pageId, limit + excludeIds.length);
    return rows.filter((r) => !excludeIds.includes(r.facebook_post_id)).slice(0, limit);
  }

  // ---------- assets ----------
  listAssetsForPost(postId) { return this.all('SELECT * FROM content_assets WHERE post_id = ? ORDER BY idx', postId); }
  upsertAsset(postId, idx, patch) {
    this.run('INSERT OR IGNORE INTO content_assets(post_id, idx) VALUES (?, ?)', postId, idx);
    const keys = Object.keys(patch);
    if (keys.length) this.run(`UPDATE content_assets SET ${keys.map((k) => `${k} = ?`).join(', ')}, updated_at = datetime('now') WHERE post_id = ? AND idx = ?`, ...keys.map((k) => patch[k]), postId, idx);
    return this.get('SELECT * FROM content_assets WHERE post_id = ? AND idx = ?', postId, idx);
  }

  // ---------- jobs ----------
  createJob(type, trigger, pageIds) {
    return this.tx(() => {
      const r = this.run('INSERT INTO crawl_jobs(job_type, trigger, total_pages) VALUES (?,?,?)', type, trigger, pageIds.length);
      const jobId = Number(r.lastInsertRowid);
      const ins = this.db.prepare('INSERT INTO crawl_job_items(job_id, page_id) VALUES (?, ?)');
      for (const id of pageIds) ins.run(jobId, id);
      return jobId;
    });
  }
  updateJob(id, patch) {
    const keys = Object.keys(patch);
    if (keys.length) this.run(`UPDATE crawl_jobs SET ${keys.map((k) => `${k} = ?`).join(', ')} WHERE id = ?`, ...keys.map((k) => patch[k]), id);
  }
  updateJobItem(jobId, pageId, patch) {
    const keys = Object.keys(patch);
    if (keys.length) this.run(`UPDATE crawl_job_items SET ${keys.map((k) => `${k} = ?`).join(', ')} WHERE job_id = ? AND page_id = ?`, ...keys.map((k) => patch[k]), jobId, pageId);
  }
  getJobItem(jobId, pageId) { return this.get('SELECT * FROM crawl_job_items WHERE job_id = ? AND page_id = ?', jobId, pageId); }
  // checkpoint của lần chạy trước bị gián đoạn cho Page này (để chạy tiếp)
  lastCheckpoint(pageId) {
    return this.get(`SELECT i.cursor FROM crawl_job_items i JOIN crawl_jobs j ON j.id = i.job_id
      WHERE i.page_id = ? AND i.cursor IS NOT NULL AND i.status IN ('error','cancelled','running') ORDER BY i.id DESC LIMIT 1`, pageId);
  }
  listJobs(limit = 30) { return this.all('SELECT * FROM crawl_jobs ORDER BY id DESC LIMIT ?', limit); }
  jobItems(jobId) {
    return this.all(`SELECT i.*, p.name AS page_name, p.input AS page_input FROM crawl_job_items i JOIN facebook_pages p ON p.id = i.page_id
      WHERE i.job_id = ? ORDER BY i.id`, jobId);
  }
  // Job bị dừng do tắt app giữa chừng → đánh dấu để chạy tiếp
  markInterruptedJobs() {
    this.run(`UPDATE crawl_job_items SET status = 'error', error_code = 'INTERRUPTED', error_message = 'Ứng dụng bị đóng khi đang chạy — sẽ chạy tiếp từ checkpoint' WHERE status IN ('running','pending') AND job_id IN (SELECT id FROM crawl_jobs WHERE status = 'running')`);
    this.run(`UPDATE crawl_jobs SET status = 'partial', finished_at = datetime('now'), message = 'Bị gián đoạn' WHERE status = 'running'`);
    this.run(`UPDATE facebook_pages SET status = 'error', last_error_code = 'INTERRUPTED' WHERE status = 'running'`);
  }
}

function plain(r) { return Object.assign({}, r); }
function numOrNull(v) { return v === undefined || v === null || v === '' ? null : Number(v); }
function requireText(v, label) {
  const s = String(v ?? '').trim();
  if (!s) throw new Error(`${label} không được để trống.`);
  return s;
}

// Chuẩn hoá link/ID Page: trả về username hoặc ID số.
function normalizePageInput(raw) {
  let s = String(raw).trim();
  if (/^\d+$/.test(s)) return s;
  if (!/^https?:\/\//i.test(s) && /facebook\.com|fb\.com/i.test(s)) s = 'https://' + s;
  try {
    const u = new URL(s);
    if (!/(^|\.)facebook\.com$|(^|\.)fb\.com$/i.test(u.hostname)) throw new Error();
    const id = u.searchParams.get('id');
    if (id && /^\d+$/.test(id)) return id;
    const parts = u.pathname.split('/').filter(Boolean);
    if (parts[0] === 'pages' && parts.length >= 2) return /^\d+$/.test(parts[parts.length - 1]) ? parts[parts.length - 1] : parts[1];
    if (parts[0] && parts[0] !== 'profile.php') return decodeURIComponent(parts[0]);
  } catch { /* rơi xuống lỗi bên dưới */ }
  if (/^[A-Za-z0-9.\-_]+$/.test(s)) return s;
  throw new Error(`Không nhận dạng được Page từ "${raw}". Hãy dán link dạng facebook.com/ten-page hoặc nhập Page ID.`);
}

module.exports = { Store, normalizePageInput };
