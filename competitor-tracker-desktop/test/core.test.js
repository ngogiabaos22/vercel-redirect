'use strict';
// Kiểm thử lõi (không cần Electron): chạy bằng `npm test` (Node 22+).
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { Store, normalizePageInput } = require('../src/main/db');
const { Secrets } = require('../src/main/secrets');
const { Engine } = require('../src/main/engine');
const { saveConfig } = require('../src/main/config');
const rules = require('../src/main/rules');
const { pagesFromCsv } = require('../src/main/csv');
const { normalizePost } = require('../src/main/meta');
const { dueTasks } = require('../src/main/scheduler');
const { rowFor } = require('../src/main/google');
const { dashboard } = require('../src/main/stats');

const plainCrypto = { available: false, encrypt: (s) => Buffer.from(s), decrypt: (b) => b.toString() };
function tmp() { return fs.mkdtempSync(path.join(os.tmpdir(), 'ct-')); }

// ---------- Meta API giả lập ----------
function graphPost(id, created, extra = {}) {
  return {
    id, message: `Bài ${id}`, created_time: created, permalink_url: `https://facebook.com/${id}`,
    full_picture: `https://img.test/${id}.jpg`,
    attachments: { data: [{ media_type: 'photo', media: { image: { src: `https://img.test/${id}.jpg` } } }] },
    reactions: { summary: { total_count: 100 } }, comments: { summary: { total_count: 10 } }, shares: { count: 5 },
    ...extra
  };
}
function mockFetch(state) {
  return async (url) => {
    const u = new URL(url);
    state.calls.push(u.pathname + u.search);
    const json = (body, status = 200) => ({ ok: status < 400, status, headers: { get: () => null }, json: async () => body });
    if (u.hostname === 'img.test') {
      if (state.expired && state.expired.has(u.pathname)) { state.expired.delete(u.pathname); return { ok: false, status: 403, headers: { get: () => null } }; }
      return { ok: true, status: 200, headers: { get: (k) => (k === 'content-type' ? 'image/jpeg' : null) }, arrayBuffer: async () => new Uint8Array([1, 2, 3]).buffer };
    }
    const p = u.pathname.replace(/^\/v\d+\.\d+\/?/, '');
    if (state.failAfter !== undefined && state.calls.filter((c) => c.includes('/posts')).length > state.failAfter && p.endsWith('/posts')) {
      return json({ error: { code: 4, message: 'Application request limit reached' } }, 400);
    }
    if (p === 'exampleA' || p === '111') return json({ id: '111', name: 'Page đối thủ A', link: 'https://facebook.com/exampleA', fan_count: 1000 });
    if (p === '111/posts') {
      const page = Number(u.searchParams.get('page') || 0);
      const since = Number(u.searchParams.get('since') || 0);
      const data = (state.pages[page] || []).filter((x) => !since || Date.parse(x.created_time) / 1000 >= since);
      const next = state.pages[page + 1] ? `https://graph.test/v21.0/111/posts?page=${page + 1}&since=${since || ''}&access_token=SECRET` : undefined;
      return json({ data, paging: next ? { next } : {} });
    }
    if (p === '' && u.searchParams.get('ids')) {
      const out = {};
      for (const id of u.searchParams.get('ids').split(',')) out[id] = graphPost(id, '2026-10-01T00:00:00+0000', { reactions: { summary: { total_count: 999 } } });
      return json(out);
    }
    return json({ error: { code: 100, message: 'Unknown path ' + p } }, 400);
  };
}
function setup(state) {
  const dir = tmp();
  const store = new Store(path.join(dir, 'db.sqlite'));
  const secrets = new Secrets(store, plainCrypto);
  secrets.set('metaToken', 'SECRET');
  const engine = new Engine({ store, secrets, dataDir: dir, fetchImpl: mockFetch(state), sleep: async () => {} });
  saveConfig(store, { meta: { baseUrl: 'https://graph.test' }, crawl: { defaultStartDate: '2026-01-01' } });
  return { dir, store, secrets, engine };
}
const now = Date.now();
const iso = (daysAgo) => new Date(now - daysAgo * 86400000).toISOString().replace('Z', '+0000');

test('chuẩn hoá link Page', () => {
  assert.equal(normalizePageInput('https://www.facebook.com/exampleA/'), 'exampleA');
  assert.equal(normalizePageInput('facebook.com/profile.php?id=12345'), '12345');
  assert.equal(normalizePageInput('1029384756'), '1029384756');
  assert.equal(normalizePageInput('https://facebook.com/pages/Ten-Page/998877'), '998877');
  assert.throws(() => normalizePageInput('https://google.com/abc def'));
});

test('khởi tạo tạo sẵn Nhóm 1–3, xoá nhóm không xoá Page', () => {
  const { store } = setup({ calls: [], pages: [] });
  const groups = store.listGroups();
  assert.deepEqual(groups.map((g) => g.name), ['Nhóm 1', 'Nhóm 2', 'Nhóm 3']);
  const id = store.addPage({ input: 'https://facebook.com/exampleA', groupIds: [groups[0].id, groups[1].id] });
  assert.deepEqual(store.listPages()[0].group_ids.sort(), [groups[0].id, groups[1].id].sort());
  assert.throws(() => store.addPage({ input: 'facebook.com/exampleA' }), /đã có/);
  store.deleteGroup(groups[0].id);
  assert.equal(store.listPages().length, 1);
  assert.deepEqual(store.listPages()[0].group_ids, [groups[1].id]);
  assert.ok(store.getPage(id));
});

test('điều kiện tải ảnh: ô trống = 0, AND/OR, thiếu chỉ số, nhiều nhóm', () => {
  const g = { minReactions: '', minComments: null, minShares: undefined, mode: 'and', naPolicy: 'ignore' };
  assert.equal(rules.shouldDownload({ reactions: 0, comments: 0, shares: 0 }, [], g), true);
  const c = { ...g, minReactions: 500, minComments: 50 };
  assert.equal(rules.shouldDownload({ reactions: 600, comments: 60, shares: 0 }, [], c), true);
  assert.equal(rules.shouldDownload({ reactions: 600, comments: 10, shares: 0 }, [], c), false);
  assert.equal(rules.shouldDownload({ reactions: 600, comments: 10, shares: 0 }, [], { ...c, mode: 'or' }), true);
  assert.equal(rules.shouldDownload({ reactions: 600, comments: null, shares: 0 }, [], c), true); // bỏ qua chỉ số thiếu
  assert.equal(rules.shouldDownload({ reactions: 600, comments: null, shares: 0 }, [], { ...c, naPolicy: 'fail' }), false);
  const groups = [{ image_mode: 'none' }, { image_mode: 'custom', min_reactions: 100 }];
  assert.equal(rules.shouldDownload({ reactions: 150, comments: 0, shares: 0 }, groups, c), true);
  assert.equal(rules.shouldDownload({ reactions: 50, comments: 0, shares: 0 }, groups, c), false);
  assert.equal(rules.shouldDownload({ reactions: 1, comments: 0, shares: 0 }, [{ image_mode: 'all' }], c), true);
});

test('chuẩn hoá bài viết: thiếu số liệu → null, không gán 0', () => {
  const p = normalizePost({ id: '1', created_time: '2026-10-01T00:00:00+0000' });
  assert.equal(p.reactions, null); assert.equal(p.comments, null); assert.equal(p.shares, null);
  const q = normalizePost({ id: '2', reactions: { summary: { total_count: 3 } } });
  assert.equal(q.shares, 0); // Graph API bỏ trường shares khi = 0
});

test('đọc CSV danh sách Page', () => {
  const rows = pagesFromCsv('﻿Link hoặc Page ID,Nhóm,Ngày\nhttps://facebook.com/a,"Nhóm 1|Nhóm 3",2026-09-01\n123,,\n');
  assert.equal(rows.length, 2);
  assert.deepEqual(rows[0].groups, ['Nhóm 1', 'Nhóm 3']);
  assert.equal(rows[0].startDate, '2026-09-01');
  assert.equal(rows[1].input, '123');
});

test('thu thập: phân trang, chống trùng, snapshot lịch sử, cập nhật bài cũ', async () => {
  const state = { calls: [], pages: [[graphPost('111_1', iso(1)), graphPost('111_2', iso(2))], [graphPost('111_3', iso(3))]] };
  const { store, engine } = setup(state);
  const pageId = store.addPage({ input: 'https://facebook.com/exampleA', groupIds: [1] });
  const r1 = await engine.runCrawl('initial', [pageId]);
  assert.equal(r1.status, 'done');
  assert.equal(store.get('SELECT COUNT(*) AS n FROM facebook_posts').n, 3);
  assert.equal(store.getPage(pageId).facebook_page_id, '111');
  // token không bị lưu vào checkpoint
  assert.equal(store.get(`SELECT COUNT(*) AS n FROM crawl_job_items WHERE cursor LIKE '%SECRET%'`).n, 0);
  // chạy lại: không trùng bài, thêm snapshot mới, cập nhật bài cũ
  const r2 = await engine.runCrawl('daily', [pageId]);
  assert.equal(r2.status, 'done');
  assert.equal(store.get('SELECT COUNT(*) AS n FROM facebook_posts').n, 3);
  assert.ok(store.get('SELECT COUNT(*) AS n FROM post_metrics').n > 3);
  assert.ok(state.calls.some((c) => c.includes('ids=')), 'phải gọi cập nhật bài cũ');
});

test('rate limit giữa chừng: lưu checkpoint rồi chạy tiếp từ đó', async () => {
  const state = { calls: [], failAfter: 1, pages: [[graphPost('111_1', iso(1))], [graphPost('111_2', iso(2))], [graphPost('111_3', iso(3))]] };
  const { store, engine } = setup(state);
  const pageId = store.addPage({ input: 'exampleA' });
  const r1 = await engine.runCrawl('initial', [pageId]);
  assert.equal(r1.status, 'failed');
  assert.equal(store.getPage(pageId).status, 'error');
  assert.match(store.getPage(pageId).last_error_code, /RATE_LIMIT/);
  assert.equal(store.get('SELECT COUNT(*) AS n FROM facebook_posts').n, 1);
  const cp = store.lastCheckpoint(pageId);
  assert.ok(cp && cp.cursor.includes('page=1'));
  delete state.failAfter;
  const r2 = await engine.runCrawl('retry', [pageId]);
  assert.equal(r2.status, 'done');
  assert.equal(store.get('SELECT COUNT(*) AS n FROM facebook_posts').n, 3);
  assert.equal(store.lastCheckpoint(pageId), null);
});

test('tải ảnh theo điều kiện, link hết hạn thì lấy lại', async () => {
  const state = { calls: [], expired: new Set(['/111_1.jpg']), pages: [[
    graphPost('111_1', iso(0)),
    graphPost('111_2', iso(0), { reactions: { summary: { total_count: 5 } } })
  ]] };
  const { store, engine, dir } = setup(state);
  saveConfig(store, { images: { minReactions: 50, storage: 'local' } });
  const pageId = store.addPage({ input: 'exampleA', groupIds: [2] });
  await engine.runCrawl('initial', [pageId]);
  const preview = engine.previewRules();
  assert.equal(preview.match, 1);
  const r = await engine.runImages();
  assert.equal(r.downloaded, 1);
  assert.equal(r.skipped, 1);
  const asset = store.get('SELECT * FROM content_assets');
  assert.ok(asset.local_path.startsWith(dir));
  assert.ok(fs.existsSync(asset.local_path));
  assert.ok(state.calls.some((c) => c.includes('ids=111_1')), 'phải lấy lại link ảnh mới');
});

test('dòng Google Sheet ghi "Không khả dụng" thay vì 0', () => {
  const row = rowFor({ created_time: '2026-11-01T12:30:00.000Z', page_name: 'A', permalink_url: 'u', message: 'cap', reactions: null, comments: 3, shares: 0 },
    [{ drive_link: 'https://drive/x', local_path: '/a.jpg' }], 'drive');
  assert.equal(row[0], '01/11/2026 19:30');
  assert.equal(row[4], 'https://drive/x');
  assert.equal(row[5], 'Không khả dụng');
  assert.equal(row[7], 0);
});

test('đồng bộ Sheet: mỗi nhóm 1 tab, không ghi trùng, chỉ cập nhật E–H', async () => {
  const state = { calls: [], pages: [[graphPost('111_1', iso(1))]] };
  const { store, engine } = setup(state);
  const tabs = new Map();
  const fakeSheets = {
    spreadsheet: async () => ({ properties: { title: 'T' }, sheets: [] }),
    ensureTab: async (_id, title, known) => { if (!tabs.has(title)) tabs.set(title, [['header']]); known.add(title); },
    readPostIds: async (_id, title) => { const m = new Map(); tabs.get(title).forEach((r, i) => { if (i > 0) m.set(String(r[10]), i + 1); }); return m; },
    appendRows: async (_id, title, rows) => { tabs.get(title).push(...rows); },
    updateRanges: async (_id, data) => { state.updates = (state.updates || 0) + data.length; for (const d of data) assert.match(d.range, /!E\d+:H\d+$/); }
  };
  engine.googleFactory = () => fakeSheets;
  saveConfig(store, { sheets: { enabled: true, spreadsheetId: 'abc' } });
  const pageId = store.addPage({ input: 'exampleA', groupIds: [1, 3] });
  await engine.runCrawl('initial', [pageId]);
  const r1 = await engine.runSheets();
  assert.equal(r1.appended, 3); // Nhóm 1 + Nhóm 3 + Tổng hợp
  assert.deepEqual([...tabs.keys()].sort(), ['Nhóm 1', 'Nhóm 2', 'Nhóm 3', 'Tổng hợp'].sort());
  assert.equal(tabs.get('Nhóm 2').length, 1);
  const r2 = await engine.runSheets();
  assert.equal(r2.appended, 0);
  assert.equal(r2.updated, 0); // không có gì thay đổi
  await new Promise((res) => setTimeout(res, 1100));
  await engine.runCrawl('daily', [pageId]);
  const r3 = await engine.runSheets();
  assert.equal(r3.appended, 0);
  assert.ok(r3.updated >= 1);
});

test('lịch chạy theo giờ Việt Nam và chạy bù', () => {
  const cfg = { crawl: { enabled: true, time: '02:00', catchUp: true }, images: { enabled: true, time: '03:00' }, sheets: { enabled: false, schedule: 'after_images', time: '04:00' } };
  const at = (hhmm) => new Date(`2026-11-02T${hhmm}:00+07:00`);
  assert.deepEqual(dueTasks(cfg, {}, at('01:59')).due, []);
  assert.deepEqual(dueTasks(cfg, {}, at('02:00')).due, ['crawl']);
  assert.deepEqual(dueTasks(cfg, {}, at('09:15')).due, ['crawl', 'images']);
  assert.deepEqual(dueTasks(cfg, { crawl: '2026-11-02', images: '2026-11-02' }, at('09:15')).due, []);
  assert.deepEqual(dueTasks({ ...cfg, crawl: { ...cfg.crawl, catchUp: false } }, {}, at('09:15')).due, []);
});

test('Dashboard đọc đúng dữ liệu đã lưu', async () => {
  const state = { calls: [], pages: [[graphPost('111_1', iso(1)), graphPost('111_2', iso(2), { reactions: undefined, comments: undefined, shares: undefined })]] };
  const { store, engine } = setup(state);
  const pageId = store.addPage({ input: 'exampleA', groupIds: [1] });
  await engine.runCrawl('initial', [pageId]);
  const d = dashboard(store, { days: 7 });
  assert.equal(d.kpi.pages, 1);
  assert.equal(d.kpi.posts, 2);
  assert.equal(d.byGroup[0].reactions, 100); // bài thiếu số liệu không bị tính là 0 vào tổng
  assert.equal(d.byGroup[1].reactions, null);
  assert.equal(d.topPosts.length, 2);
});

test('bí mật không lưu dạng chữ thường khi có mã hoá hệ điều hành', () => {
  const dir = tmp();
  const store = new Store(path.join(dir, 'db.sqlite'));
  const xor = (b) => Buffer.from([...b].map((x) => x ^ 0x5a));
  const s = new Secrets(store, { available: true, encrypt: (str) => xor(Buffer.from(str)), decrypt: (b) => xor(b).toString() });
  s.set('metaToken', 'EAAB-token');
  assert.equal(s.get('metaToken'), 'EAAB-token');
  const raw = store.get(`SELECT value FROM settings WHERE key = 'secret:metaToken'`).value;
  assert.ok(!raw.includes('EAAB'));
  assert.deepEqual(s.status().metaToken, true);
});
