'use strict';
// Meta Graph API client. Chỉ dùng API chính thức (cần quyền Page Public Content Access cho Page không do bạn quản trị).

const POST_FIELDS = [
  'id', 'message', 'created_time', 'permalink_url', 'full_picture', 'status_type',
  'attachments{media_type,type,url,media,subattachments.limit(20){media_type,type,url,media}}',
  'shares',
  'reactions.summary(total_count).limit(0)',
  'comments.summary(total_count).limit(0)'
].join(',');
const METRIC_FIELDS = 'id,full_picture,attachments{media_type,type,url,media,subattachments.limit(20){media_type,type,url,media}},shares,reactions.summary(total_count).limit(0),comments.summary(total_count).limit(0)';
const PAGE_FIELDS = 'id,name,username,link,category,fan_count,followers_count,picture{url}';

const RATE_LIMIT_CODES = new Set([4, 17, 32, 613, 80001, 80004]);

class MetaError extends Error {
  constructor(message, { code, subcode, status, type } = {}) {
    super(message);
    this.code = code; this.subcode = subcode; this.status = status; this.type = type;
  }
  get isRateLimit() { return RATE_LIMIT_CODES.has(Number(this.code)); }
  get isAuth() { return Number(this.code) === 190; }
  get isPermission() { return [10, 200, 803].includes(Number(this.code)) || (Number(this.code) >= 200 && Number(this.code) <= 299); }
  get label() {
    if (this.isRateLimit) return `RATE_LIMIT · ${this.code}`;
    if (this.isAuth) return `TOKEN · ${this.code}`;
    if (this.isPermission) return `PERMISSION · ${this.code}`;
    return this.code ? `META · ${this.code}` : 'NETWORK';
  }
}

class MetaClient {
  /**
   * @param {{token:string, version?:string, baseUrl?:string, fetchImpl?:Function, onCall?:Function, maxRetries?:number, sleep?:Function}} opts
   */
  constructor(opts) {
    this.token = opts.token;
    this.version = (opts.version || 'v21.0').replace(/^\/?/, '');
    this.baseUrl = (opts.baseUrl || 'https://graph.facebook.com').replace(/\/$/, '');
    this.fetch = opts.fetchImpl || globalThis.fetch;
    this.onCall = opts.onCall || (() => {});
    this.maxRetries = opts.maxRetries ?? 3;
    this.sleep = opts.sleep || ((ms) => new Promise((r) => setTimeout(r, ms)));
    this.lastUsage = null;
  }

  url(path, params = {}) {
    const u = new URL(`${this.baseUrl}/${this.version}/${String(path).replace(/^\//, '')}`);
    for (const [k, v] of Object.entries(params)) if (v !== undefined && v !== null && v !== '') u.searchParams.set(k, v);
    return u;
  }

  async request(urlOrPath, params) {
    if (!this.token) throw new MetaError('Chưa nhập Access Token Meta trong Cài đặt › Kết nối Meta.', { code: 190 });
    const u = typeof urlOrPath === 'string' && /^https?:/.test(urlOrPath) ? new URL(urlOrPath) : this.url(urlOrPath, params);
    u.searchParams.set('access_token', this.token);
    let attempt = 0;
    for (;;) {
      const t0 = Date.now();
      let res, body;
      try {
        res = await this.fetch(u.toString());
        body = await res.json().catch(() => ({}));
      } catch (e) {
        if (attempt < this.maxRetries) { attempt++; await this.sleep(1000 * 2 ** attempt); continue; }
        throw new MetaError(`Lỗi mạng khi gọi Meta API: ${e.message}`);
      }
      const usage = headerJson(res, 'x-app-usage') || headerJson(res, 'x-business-use-case-usage');
      if (usage) this.lastUsage = usage;
      this.onCall({ endpoint: u.pathname, status: res.status, ms: Date.now() - t0, usage });
      if (res.ok && !body.error) return body;
      const e = body.error || {};
      const err = new MetaError(e.message || `HTTP ${res.status}`, { code: e.code, subcode: e.error_subcode, status: res.status, type: e.type });
      const transient = err.isRateLimit || res.status >= 500 || e.is_transient;
      if (transient && attempt < this.maxRetries) {
        attempt++;
        // rate limit: chờ lâu hơn (30s, 60s, 120s); lỗi tạm thời: 2s, 4s, 8s
        await this.sleep((err.isRateLimit ? 30000 : 1000) * 2 ** (attempt - 1));
        continue;
      }
      throw err;
    }
  }

  async pageInfo(idOrUsername) {
    return this.request(encodeURIComponent(idOrUsername), { fields: PAGE_FIELDS });
  }

  /**
   * Lấy bài viết theo trang, gọi onPage(posts, nextCursorUrl) sau mỗi trang để lưu checkpoint.
   * @returns {Promise<number>} số bài đã lấy
   */
  async fetchPosts(pageId, { since, until, resumeUrl, onPage, shouldStop, maxPages = 2000 }) {
    let next = resumeUrl || null;
    let first = !resumeUrl;
    let total = 0;
    let pages = 0;
    while (first || next) {
      if (shouldStop && shouldStop()) break;
      const body = first
        ? await this.request(`${encodeURIComponent(pageId)}/posts`, { fields: POST_FIELDS, limit: 50, since: toUnix(since), until: toUnix(until) })
        : await this.request(next);
      first = false;
      const posts = (body.data || []).map(normalizePost);
      next = body.paging && body.paging.next ? body.paging.next : null;
      total += posts.length;
      pages++;
      await onPage(posts, next);
      if (pages >= maxPages) break; // giới hạn an toàn, không chạy vô hạn
    }
    return total;
  }

  async postsByIds(ids) {
    const out = [];
    for (let i = 0; i < ids.length; i += 50) {
      const chunk = ids.slice(i, i + 50);
      const body = await this.request('', { ids: chunk.join(','), fields: METRIC_FIELDS });
      for (const id of chunk) if (body[id]) out.push(normalizePost(body[id]));
    }
    return out;
  }
}

function headerJson(res, name) {
  try { const v = res.headers && res.headers.get(name); return v ? JSON.parse(v) : null; } catch { return null; }
}
function toUnix(d) {
  if (!d) return undefined;
  const t = typeof d === 'number' ? d : Date.parse(d);
  return Number.isFinite(t) ? Math.floor(t / 1000) : undefined;
}

// Chuẩn hoá bài viết. Trường API không trả về → null ("Không khả dụng"), không gán 0.
function normalizePost(p) {
  const media = [];
  const pushMedia = (a) => {
    if (!a) return;
    const src = a.media && a.media.image && a.media.image.src;
    if (src) media.push({ type: (a.media_type || a.type || 'photo').toLowerCase(), src, url: a.url || null });
  };
  const atts = (p.attachments && p.attachments.data) || [];
  for (const a of atts) {
    const subs = a.subattachments && a.subattachments.data;
    if (subs && subs.length) subs.forEach(pushMedia); else pushMedia(a);
  }
  if (!media.length && p.full_picture) media.push({ type: 'photo', src: p.full_picture, url: null });
  const first = atts[0] || {};
  const mt = String(first.media_type || first.type || '').toLowerCase();
  let postType = 'status';
  if (/video|reel/.test(mt) || /video/.test(p.status_type || '')) postType = 'video';
  else if (/photo|album|image/.test(mt) || (!mt && p.full_picture)) postType = 'photo';
  else if (/share|link/.test(mt) || p.status_type === 'shared_story') postType = 'link';
  const reactions = summaryCount(p.reactions);
  const comments = summaryCount(p.comments);
  // Graph API bỏ hẳn trường "shares" khi bài có 0 lượt chia sẻ. Chỉ coi là 0 khi API có trả số liệu tương tác khác;
  // nếu không có số liệu nào → null (Không khả dụng).
  let shares = null;
  if (p.shares && typeof p.shares.count === 'number') shares = p.shares.count;
  else if (reactions !== null || comments !== null) shares = 0;
  return {
    facebook_post_id: p.id,
    message: p.message ?? null,
    created_time: p.created_time ? new Date(p.created_time).toISOString() : null,
    permalink_url: p.permalink_url ?? null,
    full_picture: p.full_picture ?? null,
    post_type: postType,
    media,
    reactions,
    comments,
    shares
  };
}
function summaryCount(x) {
  return x && x.summary && typeof x.summary.total_count === 'number' ? x.summary.total_count : null;
}

module.exports = { MetaClient, MetaError, normalizePost };
