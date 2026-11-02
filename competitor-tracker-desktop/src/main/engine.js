'use strict';
// Bộ máy thu thập: chạy job theo từng Page (có checkpoint), tải ảnh theo điều kiện, đồng bộ Drive và Google Sheets.
const fs = require('node:fs');
const path = require('node:path');
const { MetaClient, MetaError } = require('./meta');
const rules = require('./rules');
const google = require('./google');
const { AppsScript } = require('./appsscript');
const { loadConfig } = require('./config');

const JOB_LABELS = {
  initial: 'Thu thập ban đầu', daily: 'Tự động hằng ngày', manual: 'Thu thập thủ công', retry: 'Chạy lại Page lỗi',
  info: 'Cập nhật Page info', images: 'Tải ảnh', sheets: 'Đồng bộ Google Sheet'
};

class Engine {
  /**
   * @param {{store:import('./db').Store, secrets:import('./secrets').Secrets, dataDir:string, emit?:Function,
   *          fetchImpl?:Function, metaFactory?:Function, googleFactory?:Function, sleep?:Function}} deps
   */
  constructor(deps) {
    this.store = deps.store;
    this.secrets = deps.secrets;
    this.dataDir = deps.dataDir;
    this.emit = deps.emit || (() => {});
    this.fetch = deps.fetchImpl || globalThis.fetch;
    this.metaFactory = deps.metaFactory || ((opts) => new MetaClient(opts));
    this.googleFactory = deps.googleFactory || ((cfg) => (cfg.drive.authType === 'apps_script'
      ? new AppsScript(cfg.drive.scriptUrl, this.secrets.get('appsScriptKey'), this.fetch)
      : new google.Google(google.createAuth(cfg, this.secrets))));
    this.sleep = deps.sleep;
    this.locks = new Set(); // khoá theo Page: không chạy trùng 2 job cho cùng 1 Page
    this.cancelled = new Set();
    this.busy = { images: false, sheets: false };
  }

  config() { return loadConfig(this.store); }
  imagesDir(cfg = this.config()) { return cfg.images.folder || path.join(this.dataDir, 'images'); }

  meta(cfg = this.config()) {
    return this.metaFactory({
      token: this.secrets.get('metaToken'), version: cfg.meta.version, baseUrl: cfg.meta.baseUrl,
      fetchImpl: this.fetch, maxRetries: cfg.crawl.retries, sleep: this.sleep
    });
  }

  // ======================= CRAWL =======================
  /**
   * @param {'initial'|'daily'|'manual'|'retry'|'info'} type
   * @param {number[]|null} pageIds  null = tất cả Page đang bật theo dõi
   */
  async runCrawl(type, pageIds = null, trigger = 'manual') {
    const cfg = this.config();
    if (!this.secrets.get('metaToken')) throw new Error('Chưa nhập Access Token Meta — vào Cài đặt › Kết nối Meta để nhập trước khi thu thập.');
    const ids = pageIds && pageIds.length ? pageIds : this.store.all('SELECT id FROM facebook_pages WHERE enabled = 1 ORDER BY id').map((r) => r.id);
    if (!ids.length) throw new Error('Không có Page nào để thu thập.');
    const jobId = this.store.createJob(type, trigger, ids);
    this.store.log('info', 'crawler', `Bắt đầu ${JOB_LABELS[type]} · ${ids.length} Page`);
    this.emit('job', { jobId });
    const client = this.meta(cfg);
    const totals = { ok: 0, failed: 0, newPosts: 0, updated: 0 };
    const queue = [...ids];
    const worker = async () => {
      while (queue.length) {
        if (this.cancelled.has(jobId)) return;
        const pageId = queue.shift();
        const r = await this.crawlPage(jobId, pageId, type, client, cfg);
        if (r.ok) totals.ok++; else totals.failed++;
        totals.newPosts += r.newPosts || 0;
        totals.updated += r.updated || 0;
        this.store.updateJob(jobId, { ok_pages: totals.ok, failed_pages: totals.failed, new_posts: totals.newPosts, updated_posts: totals.updated });
        this.emit('job', { jobId });
      }
    };
    await Promise.all(Array.from({ length: Math.min(cfg.crawl.workers, ids.length) }, worker));
    const cancelled = this.cancelled.delete(jobId);
    if (cancelled) this.store.run(`UPDATE crawl_job_items SET status = 'cancelled' WHERE job_id = ? AND status = 'pending'`, jobId);
    const status = cancelled ? 'cancelled' : totals.failed === 0 ? 'done' : totals.ok === 0 ? 'failed' : 'partial';
    this.store.updateJob(jobId, { status, finished_at: nowSql() });
    this.store.log(status === 'done' ? 'info' : 'warn', 'crawler', `${JOB_LABELS[type]} kết thúc: ${totals.ok} OK, ${totals.failed} lỗi, ${totals.newPosts} bài mới`);
    this.emit('job', { jobId, finished: true });
    return { jobId, status, ...totals };
  }

  cancel(jobId) { this.cancelled.add(Number(jobId)); }

  async crawlPage(jobId, pageId, type, client, cfg) {
    const page = this.store.getPage(pageId);
    if (!page) return { ok: false };
    if (this.locks.has(pageId)) {
      this.store.updateJobItem(jobId, pageId, { status: 'skipped', error_code: 'LOCKED', error_message: 'Page đang được thu thập ở tác vụ khác', finished_at: nowSql() });
      return { ok: true };
    }
    this.locks.add(pageId);
    const item = (patch) => { this.store.updateJobItem(jobId, pageId, patch); this.emit('job', { jobId }); };
    item({ status: 'running', started_at: nowSql(), step: 'Lấy thông tin Page', progress: 5 });
    this.store.setPageStatus(pageId, 'running');
    let newPosts = 0;
    let updated = 0;
    try {
      // 1. Thông tin Page: lần đầu, hoặc khi người dùng yêu cầu
      let fbId = page.facebook_page_id;
      if (!fbId || type === 'info') {
        const info = await client.pageInfo(fbId || page.input);
        this.store.savePageInfo(pageId, info);
        fbId = info.id;
      }
      if (type === 'info') {
        item({ status: 'done', step: 'Hoàn thành', progress: 100, finished_at: nowSql() });
        this.store.setPageStatus(pageId, page.initial_done ? 'active' : 'pending');
        return { ok: true };
      }

      // 2. Bài viết mới (lần đầu: từ ngày bắt đầu; hằng ngày: từ bài mới nhất đã có)
      const startDate = page.start_date || cfg.crawl.defaultStartDate;
      const latest = this.store.latestPostTime(pageId);
      let since = startDate ? new Date(startDate + 'T00:00:00+07:00').toISOString() : undefined;
      if (latest && page.initial_done) {
        const overlap = new Date(Date.parse(latest) - 3600000).toISOString(); // chồng lấn 1 giờ để không sót bài
        if (!since || overlap > since) since = overlap;
      }
      const resume = this.store.lastCheckpoint(pageId);
      const fetchedIds = [];
      item({ step: resume ? 'Chạy tiếp từ checkpoint' : 'Lấy bài viết', progress: 15 });
      await client.fetchPosts(fbId, {
        since,
        resumeUrl: resume ? resume.cursor : null,
        shouldStop: () => this.cancelled.has(jobId),
        onPage: async (posts, next) => {
          this.store.tx(() => {
            for (const p of posts) {
              const r = this.store.upsertPost(pageId, p);
              fetchedIds.push(p.facebook_post_id);
              if (r.isNew) newPosts++; else updated++;
            }
          });
          // checkpoint: lưu cursor sau mỗi trang xử lý thành công
          const cur = this.store.getJobItem(jobId, pageId);
          item({ cursor: stripToken(next), posts_fetched: (cur.posts_fetched || 0) + posts.length, step: `Lấy bài viết · ${fetchedIds.length} bài`, progress: Math.min(60, 15 + fetchedIds.length) });
        }
      });
      if (this.cancelled.has(jobId)) {
        item({ status: 'cancelled', step: 'Đã huỷ', finished_at: nowSql() });
        this.store.setPageStatus(pageId, page.initial_done ? 'active' : 'pending');
        return { ok: false, newPosts, updated };
      }

      // 3. Cập nhật tương tác N bài cũ gần nhất (lưu snapshot mới, không ghi đè lịch sử)
      const limit = Number(cfg.crawl.oldPostsLimit) || 0;
      if (limit > 0 && page.initial_done) {
        item({ step: `Cập nhật ${limit} bài cũ`, progress: 75 });
        const old = this.store.recentPosts(pageId, limit, fetchedIds);
        if (old.length) {
          const fresh = await client.postsByIds(old.map((o) => o.facebook_post_id));
          const byId = new Map(old.map((o) => [o.facebook_post_id, o]));
          for (const f of fresh) {
            const o = byId.get(f.facebook_post_id);
            if (!o) continue;
            this.store.updatePostMetrics(o.id, f);
            if (f.media.length) this.store.run('UPDATE facebook_posts SET media_json = ?, full_picture = COALESCE(?, full_picture) WHERE id = ?', JSON.stringify(f.media), f.full_picture, o.id);
            updated++;
          }
        }
      }

      this.store.run(`UPDATE facebook_pages SET initial_done = 1, last_crawl_at = datetime('now') WHERE id = ?`, pageId);
      this.store.setPageStatus(pageId, 'active');
      // đã chạy xong → xoá checkpoint cũ của Page này
      this.store.run('UPDATE crawl_job_items SET cursor = NULL WHERE page_id = ?', pageId);
      item({ status: 'done', step: 'Hoàn thành', progress: 100, cursor: null, finished_at: nowSql() });
      return { ok: true, newPosts, updated };
    } catch (e) {
      const code = e instanceof MetaError ? e.label : 'ERROR';
      const msg = explainError(e);
      item({ status: 'error', error_code: code, error_message: msg, finished_at: nowSql() });
      this.store.setPageStatus(pageId, 'error', code, msg);
      this.store.log('error', 'crawler', `${page.name || page.input}: ${code} ${msg}`);
      return { ok: false, newPosts, updated };
    } finally {
      this.locks.delete(pageId);
    }
  }

  // ======================= IMAGES =======================
  candidatePosts(cfg) {
    const since = new Date(Date.now() - cfg.images.withinDays * 86400000).toISOString();
    return this.store.all(`SELECT p.*, pg.name AS page_name, pg.input AS page_input FROM facebook_posts p JOIN facebook_pages pg ON pg.id = p.page_id
      WHERE pg.enabled = 1 AND p.created_time >= ? AND p.image_status IN ('', 'skipped', 'partial', 'error')
      ORDER BY p.created_time DESC`, since);
  }

  /** Xem trước: bao nhiêu bài trong khoảng đang xét đạt điều kiện (dùng cấu hình truyền vào chưa lưu). */
  previewRules(imagesCfg) {
    const cfg = this.config();
    const imgCfg = { ...cfg.images, ...(imagesCfg || {}) };
    const since = new Date(Date.now() - imgCfg.withinDays * 86400000).toISOString();
    const posts = this.store.all('SELECT p.* FROM facebook_posts p JOIN facebook_pages pg ON pg.id = p.page_id WHERE pg.enabled = 1 AND p.created_time >= ?', since);
    const cache = new Map();
    let match = 0; let images = 0;
    for (const p of posts) {
      if (!cache.has(p.page_id)) cache.set(p.page_id, this.store.pageGroups(p.page_id));
      if (rules.shouldDownload(p, cache.get(p.page_id), imgCfg)) {
        match++;
        images += Math.min(JSON.parse(p.media_json || '[]').length, imgCfg.maxPerPost);
      }
    }
    return { total: posts.length, match, images, summary: rules.describe(imgCfg) };
  }

  async runImages(trigger = 'manual') {
    if (this.busy.images) throw new Error('Đang tải ảnh, vui lòng chờ lượt hiện tại kết thúc.');
    this.busy.images = true;
    const cfg = this.config();
    const jobId = this.store.createJob('images', trigger, []);
    this.emit('job', { jobId });
    let downloaded = 0; let failed = 0; let skipped = 0; let uploaded = 0;
    try {
      const dir = this.imagesDir(cfg);
      fs.mkdirSync(dir, { recursive: true });
      const todayCount = this.store.get(`SELECT COUNT(*) AS n FROM content_assets WHERE local_path IS NOT NULL AND date(created_at) = date('now')`).n;
      let budget = Math.max(0, cfg.images.maxPerDay - todayCount);
      const wantDrive = cfg.images.storage !== 'local';
      let drive = null;
      if (wantDrive) {
        try { drive = await this.driveContext(cfg); } catch (e) {
          this.store.log('warn', 'drive', `Bỏ qua đồng bộ Drive: ${e.message}`);
          if (cfg.images.storage === 'drive') throw e;
        }
      }
      const groupCache = new Map();
      const posts = this.candidatePosts(cfg);
      let client = null;
      for (const post of posts) {
        if (budget <= 0) break;
        if (!groupCache.has(post.page_id)) groupCache.set(post.page_id, this.store.pageGroups(post.page_id));
        if (!rules.shouldDownload(post, groupCache.get(post.page_id), cfg.images)) {
          this.store.run(`UPDATE facebook_posts SET image_status = 'skipped' WHERE id = ?`, post.id);
          skipped++;
          continue;
        }
        let media = JSON.parse(post.media_json || '[]').slice(0, cfg.images.maxPerPost);
        if (!media.length) { this.store.run(`UPDATE facebook_posts SET image_status = 'done' WHERE id = ?`, post.id); continue; }
        let refreshed = false;
        let postOk = true;
        for (let i = 0; i < media.length && budget > 0; i++) {
          const existing = this.store.get('SELECT * FROM content_assets WHERE post_id = ? AND idx = ?', post.id, i);
          if (existing && ['downloaded', 'uploaded'].includes(existing.status)) continue;
          let res = await this.download(media[i].src, dir, post, i);
          if (!res.ok && res.expired && !refreshed) {
            // link ảnh Facebook hết hạn → lấy lại link mới rồi thử lại
            refreshed = true;
            try {
              client = client || this.meta(cfg);
              const [fresh] = await client.postsByIds([post.facebook_post_id]);
              if (fresh && fresh.media.length) {
                media = fresh.media.slice(0, cfg.images.maxPerPost);
                this.store.run('UPDATE facebook_posts SET media_json = ? WHERE id = ?', JSON.stringify(fresh.media), post.id);
                if (media[i]) res = await this.download(media[i].src, dir, post, i);
              }
            } catch (e) { res = { ok: false, error: explainError(e) }; }
          }
          if (!res.ok) {
            this.store.upsertAsset(post.id, i, { source_url: media[i] ? media[i].src : null, status: 'error', error: res.error });
            failed++; postOk = false;
            continue;
          }
          budget--; downloaded++;
          this.store.upsertAsset(post.id, i, { source_url: media[i].src, local_path: res.path, size_bytes: res.size, status: 'downloaded', error: null });
          if (drive) {
            const up = await this.uploadAsset(drive, post, i, res.path, cfg);
            if (up) uploaded++; else postOk = false;
          }
        }
        this.store.run('UPDATE facebook_posts SET image_status = ? WHERE id = ?', postOk ? 'done' : 'partial', post.id);
        this.store.updateJob(jobId, { images: downloaded, message: `${downloaded} ảnh tải · ${uploaded} lên Drive · ${failed} lỗi · ${skipped} bài chưa đạt điều kiện` });
        this.emit('job', { jobId });
      }
      // Ảnh đã tải trước đó nhưng chưa lên Drive (VD Drive lỗi hôm trước)
      if (drive) {
        const pending = this.store.all(`SELECT a.*, p.facebook_post_id, p.created_time, p.page_id, pg.name AS page_name, pg.input AS page_input FROM content_assets a
          JOIN facebook_posts p ON p.id = a.post_id JOIN facebook_pages pg ON pg.id = p.page_id WHERE a.status = 'downloaded' AND a.local_path IS NOT NULL`);
        for (const a of pending) {
          if (await this.uploadAsset(drive, { id: a.post_id, facebook_post_id: a.facebook_post_id, created_time: a.created_time, page_name: a.page_name, page_input: a.page_input }, a.idx, a.local_path, cfg)) uploaded++;
        }
      }
      this.applyRetention(cfg);
      const status = failed ? (downloaded ? 'partial' : 'failed') : 'done';
      this.store.updateJob(jobId, { status, images: downloaded, finished_at: nowSql(), message: `${downloaded} ảnh tải · ${uploaded} lên Drive · ${failed} lỗi · ${skipped} bài chưa đạt điều kiện` });
      return { jobId, downloaded, uploaded, failed, skipped };
    } catch (e) {
      this.store.updateJob(jobId, { status: 'failed', finished_at: nowSql(), message: explainError(e) });
      throw e;
    } finally {
      this.busy.images = false;
      this.emit('job', { jobId, finished: true });
    }
  }

  async download(url, dir, post, idx) {
    if (!url) return { ok: false, error: 'Không có link ảnh' };
    try {
      const res = await this.fetch(url);
      if (res.status === 403 || res.status === 410 || res.status === 404) return { ok: false, expired: true, error: `Link ảnh hết hạn (HTTP ${res.status})` };
      if (!res.ok) return { ok: false, error: `HTTP ${res.status}` };
      const type = (res.headers.get('content-type') || '').toLowerCase();
      const ext = type.includes('png') ? '.png' : type.includes('webp') ? '.webp' : type.includes('gif') ? '.gif' : '.jpg';
      const buf = Buffer.from(await res.arrayBuffer());
      const month = (post.created_time || new Date().toISOString()).slice(0, 7);
      const folder = path.join(dir, slug(post.page_name || post.page_input || String(post.page_id)), month);
      fs.mkdirSync(folder, { recursive: true });
      const file = path.join(folder, `${safeFile(post.facebook_post_id)}_${idx + 1}${ext}`);
      fs.writeFileSync(file, buf);
      return { ok: true, path: file, size: buf.length };
    } catch (e) {
      return { ok: false, error: `Lỗi tải ảnh: ${e.message}` };
    }
  }

  async driveContext(cfg) {
    if (cfg.drive.authType !== 'apps_script' && !cfg.drive.rootFolderId) throw new Error('Chưa nhập ID thư mục Google Drive gốc.');
    const g = this.googleFactory(cfg);
    return { g, root: cfg.drive.rootFolderId, folders: this.store.getSetting('driveFolders', {}) };
  }

  async uploadAsset(drive, post, idx, localPath, cfg) {
    try {
      const pageName = post.page_name || post.page_input || 'Page';
      const month = (post.created_time || new Date().toISOString()).slice(0, 7);
      const ext0 = path.extname(localPath).toLowerCase();
      const mime0 = ext0 === '.png' ? 'image/png' : ext0 === '.webp' ? 'image/webp' : ext0 === '.gif' ? 'image/gif' : 'image/jpeg';
      if (drive.g.uploadImage) {
        // Apps Script: script tự quản lý thư mục <gốc>/<Page>/<tháng>
        const f = await drive.g.uploadImage(localPath, { page: pageName, month, name: path.basename(localPath), mime: mime0 });
        return this.finishUpload(post, idx, localPath, f, cfg);
      }
      const key1 = `${drive.root}/${pageName}`;
      if (!drive.folders[key1]) drive.folders[key1] = await drive.g.ensureFolder(pageName, drive.root);
      const key2 = `${key1}/${month}`;
      if (!drive.folders[key2]) drive.folders[key2] = await drive.g.ensureFolder(month, drive.folders[key1]);
      this.store.setSetting('driveFolders', drive.folders);
      const f = await drive.g.uploadFile(localPath, path.basename(localPath), drive.folders[key2], mime0);
      return this.finishUpload(post, idx, localPath, f, cfg);
    } catch (e) {
      this.store.upsertAsset(post.id, idx, { error: `Drive: ${e.message}` });
      this.store.log('error', 'drive', e.message);
      return false;
    }
  }

  finishUpload(post, idx, localPath, f, cfg) {
    const patch = { drive_file_id: f.id, drive_link: f.webViewLink || `https://drive.google.com/file/d/${f.id}/view`, status: 'uploaded', error: null };
    if (cfg.images.storage === 'drive') {
      try { fs.unlinkSync(localPath); } catch { /* đã xoá */ }
      patch.local_path = null;
    }
    this.store.upsertAsset(post.id, idx, patch);
    return true;
  }

  sheetsReady(cfg) { return cfg.drive.authType === 'apps_script' ? !!cfg.drive.scriptUrl : !!cfg.sheets.spreadsheetId; }

  applyRetention(cfg) {
    const months = Number(cfg.images.retentionMonths) || 0;
    if (!months) return 0;
    const old = this.store.all(`SELECT * FROM content_assets WHERE local_path IS NOT NULL AND created_at < datetime('now', ?)`, `-${months} months`);
    for (const a of old) {
      try { fs.unlinkSync(a.local_path); } catch { /* bỏ qua */ }
      this.store.run('UPDATE content_assets SET local_path = NULL WHERE id = ?', a.id);
    }
    if (old.length) this.store.log('info', 'images', `Đã xoá ${old.length} ảnh trên máy cũ hơn ${months} tháng`);
    return old.length;
  }

  // ======================= GOOGLE SHEETS =======================
  async runSheets(trigger = 'manual') {
    if (this.busy.sheets) throw new Error('Đang đồng bộ Google Sheet.');
    const cfg = this.config();
    if (!this.sheetsReady(cfg)) throw new Error(cfg.drive.authType === 'apps_script' ? 'Chưa kết nối Google (Cài đặt › Google).' : 'Chưa nhập ID / link file Google Sheet.');
    this.busy.sheets = true;
    const jobId = this.store.createJob('sheets', trigger, []);
    this.emit('job', { jobId });
    let appended = 0; let updatedRows = 0;
    try {
      const g = this.googleFactory(cfg);
      const ss = await g.spreadsheet(cfg.sheets.spreadsheetId);
      const known = new Set((ss.sheets || []).map((s) => s.properties.title));
      const tabs = this.store.all('SELECT * FROM page_groups WHERE sheet_enabled = 1 ORDER BY id').map((gr) => ({ title: google.safeTabName(gr.name), groupId: gr.id }));
      if (cfg.sheets.summaryTab) tabs.push({ title: 'Tổng hợp', groupId: null });
      for (const tab of tabs) {
        await g.ensureTab(cfg.sheets.spreadsheetId, tab.title, known);
        const posts = this.store.all(`SELECT p.*, pg.name AS page_name,
            (SELECT synced_at FROM sheet_rows s WHERE s.post_id = p.id AND s.tab = ?) AS synced_at,
            (SELECT MAX(updated_at) FROM content_assets a WHERE a.post_id = p.id) AS asset_at
          FROM facebook_posts p JOIN facebook_pages pg ON pg.id = p.page_id
          WHERE ${tab.groupId ? 'p.page_id IN (SELECT page_id FROM page_group_members WHERE group_id = ?)' : '1 = 1'}
          ${cfg.sheets.onlyMatched ? "AND p.image_status IN ('done','partial')" : ''}
          ORDER BY p.created_time ASC`, ...(tab.groupId ? [tab.title, tab.groupId] : [tab.title]));
        const present = await g.readPostIds(cfg.sheets.spreadsheetId, tab.title);
        const toAppend = [];
        const updates = [];
        const mark = [];
        for (const p of posts) {
          const assets = this.store.listAssetsForPost(p.id);
          const row = google.rowFor(p, assets, cfg.sheets.linkType);
          const rowNo = present.get(String(p.facebook_post_id));
          if (rowNo) {
            const changed = !p.synced_at || (p.last_crawled_at && p.last_crawled_at > p.synced_at) || (p.asset_at && p.asset_at > p.synced_at);
            if (cfg.sheets.writeMode === 'update' && changed) {
              // chỉ ghi E..H (link ảnh + tương tác); cột I, J người dùng tự điền không bị ghi đè
              updates.push({ range: google.a1(tab.title, `E${rowNo}:H${rowNo}`), values: [row.slice(4, 8)] });
              mark.push(p.id);
            }
          } else if (!p.synced_at) {
            toAppend.push([...row, '', '', p.facebook_post_id]);
            mark.push(p.id);
          }
        }
        await g.appendRows(cfg.sheets.spreadsheetId, tab.title, toAppend);
        await g.updateRanges(cfg.sheets.spreadsheetId, updates);
        appended += toAppend.length; updatedRows += updates.length;
        this.store.tx(() => {
          for (const id of mark) {
            this.store.run(`INSERT INTO sheet_rows(post_id, tab, row_number, synced_at) VALUES (?,?,0, datetime('now'))
              ON CONFLICT(post_id, tab) DO UPDATE SET synced_at = datetime('now')`, id, tab.title);
          }
        });
        this.store.updateJob(jobId, { message: `${appended} dòng mới · ${updatedRows} dòng cập nhật` });
        this.emit('job', { jobId });
      }
      this.store.setSetting('lastSheetSync', { at: new Date().toISOString(), appended, updated: updatedRows });
      this.store.updateJob(jobId, { status: 'done', finished_at: nowSql(), message: `${appended} dòng mới · ${updatedRows} dòng cập nhật` });
      return { jobId, appended, updated: updatedRows };
    } catch (e) {
      this.store.updateJob(jobId, { status: 'failed', finished_at: nowSql(), message: explainError(e) });
      this.store.log('error', 'sheets', e.message);
      throw e;
    } finally {
      this.busy.sheets = false;
      this.emit('job', { jobId, finished: true });
    }
  }

  // ======================= PIPELINE =======================
  async runDailyCrawl(trigger = 'schedule') {
    return this.runCrawl('daily', null, trigger);
  }
  async runDailyImages(trigger = 'schedule') {
    const cfg = this.config();
    let r = null;
    if (cfg.images.enabled) r = await this.runImages(trigger);
    if (cfg.sheets.enabled && cfg.sheets.schedule === 'after_images' && this.sheetsReady(cfg)) {
      try { await this.runSheets(trigger); } catch (e) { this.store.log('error', 'sheets', e.message); }
    }
    return r;
  }
}

function explainError(e) {
  if (e instanceof MetaError) {
    if (e.isAuth) return 'Access token không hợp lệ hoặc đã hết hạn — cần cấp token mới trong Cài đặt › Kết nối Meta.';
    if (e.isPermission) return `Không có quyền truy cập dữ liệu Page (cần PPCA hoặc Page bị giới hạn): ${e.message}`;
    if (e.isRateLimit) return 'Vượt giới hạn gọi API của Meta — đã lưu checkpoint, sẽ chạy tiếp ở lần sau.';
    return e.message;
  }
  return e && e.message ? e.message : String(e);
}
// Không lưu access token vào database: bỏ tham số access_token khỏi link phân trang trước khi lưu checkpoint.
function stripToken(url) {
  if (!url) return null;
  try { const u = new URL(url); u.searchParams.delete('access_token'); return u.toString(); } catch { return null; }
}
function nowSql() { return new Date().toISOString().replace('T', ' ').slice(0, 19); }
function slug(s) { return String(s).normalize('NFKD').replace(/[̀-ͯ]/g, '').replace(/đ/g, 'd').replace(/Đ/g, 'D').replace(/[^A-Za-z0-9._-]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 80) || 'page'; }
function safeFile(s) { return String(s).replace(/[^A-Za-z0-9_-]/g, '_'); }

module.exports = { Engine, JOB_LABELS, explainError, slug };
