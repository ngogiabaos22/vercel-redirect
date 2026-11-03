// Graph API calls. Each nick signs in once; the page tokens derived from that nick's
// long-lived user token post as the Page and show the nick on the admin-only "Người đăng" line.
const store = require('./store');

const SCOPES = ['pages_show_list', 'pages_manage_posts', 'pages_read_engagement'];

class FbError extends Error {
  constructor(err) {
    super(err && err.message ? err.message : 'Facebook báo lỗi');
    this.code = err && err.code;
    this.subcode = err && err.error_subcode;
  }
}

// PAGEHUB_GRAPH_BASE lets tests point the app at a local mock instead of graph.facebook.com.
const base = () => `${process.env.PAGEHUB_GRAPH_BASE || 'https://graph.facebook.com'}/${store.get().settings.fb.version || 'v23.0'}`;

async function readJson(res) {
  const j = await res.json().catch(() => ({}));
  if (!res.ok || j.error) throw new FbError(j.error || { message: `HTTP ${res.status}` });
  return j;
}

async function get(pathname, params) {
  const u = new URL(base() + pathname);
  for (const [k, v] of Object.entries(params)) u.searchParams.set(k, v);
  return readJson(await fetch(u));
}

async function postForm(pathname, form) {
  return readJson(await fetch(base() + pathname, { method: 'POST', body: form }));
}

function redirectUri() {
  return `http://localhost:${store.get().settings.fb.port || 5175}/facebook/callback`;
}

function loginUrl(state) {
  const s = store.get().settings.fb;
  const u = new URL(`https://www.facebook.com/${s.version || 'v23.0'}/dialog/oauth`);
  u.searchParams.set('client_id', s.appId);
  u.searchParams.set('redirect_uri', redirectUri());
  u.searchParams.set('state', state);
  u.searchParams.set('response_type', 'code');
  u.searchParams.set('scope', SCOPES.join(','));
  return u.toString();
}

function appCreds() {
  const s = store.get().settings.fb;
  const secret = store.dec(s.secret);
  if (!s.appId || !secret) throw new Error('Nhập App ID và App Secret trong Cài đặt kết nối › Facebook App');
  return { appId: s.appId, secret };
}

async function testApp() {
  const { appId, secret } = appCreds();
  const j = await get(`/${appId}`, { fields: 'id,name', access_token: `${appId}|${secret}` });
  return { id: j.id, name: j.name };
}

async function exchangeCode(code) {
  const { appId, secret } = appCreds();
  const short = await get('/oauth/access_token', { client_id: appId, client_secret: secret, redirect_uri: redirectUri(), code });
  const long = await get('/oauth/access_token', { grant_type: 'fb_exchange_token', client_id: appId, client_secret: secret, fb_exchange_token: short.access_token });
  return { token: long.access_token, expiresIn: long.expires_in || 0 };
}

async function me(token) {
  return get('/me', { fields: 'id,name', access_token: token });
}

async function pages(token) {
  const out = [];
  let next = `${base()}/me/accounts?fields=id,name,access_token,tasks&limit=100&access_token=${encodeURIComponent(token)}`;
  while (next) {
    const j = await readJson(await fetch(next));
    out.push(...(j.data || []));
    next = j.paging && j.paging.next;
  }
  return out.filter(p => !p.tasks || p.tasks.includes('CREATE_CONTENT') || p.tasks.includes('MANAGE'));
}

async function uploadPhoto(pageId, pageToken, buffer, mime, name) {
  const form = new FormData();
  form.append('source', new Blob([buffer], { type: mime || 'image/jpeg' }), name || 'image.jpg');
  form.append('published', 'false');
  form.append('access_token', pageToken);
  const j = await postForm(`/${pageId}/photos`, form);
  return j.id;
}

async function createPost(pageId, pageToken, message, mediaIds) {
  const form = new URLSearchParams();
  form.set('message', message);
  mediaIds.forEach((id, i) => form.set(`attached_media[${i}]`, JSON.stringify({ media_fbid: id })));
  form.set('access_token', pageToken);
  return postForm(`/${pageId}/feed`, form);
}

// 190 = token invalid/expired, 102 = session, 10/200 = permission missing.
const isAuthError = e => e instanceof FbError && [190, 102, 10, 200].includes(Number(e.code));

module.exports = { FbError, loginUrl, redirectUri, testApp, exchangeCode, me, pages, uploadPhoto, createPost, isAuthError };
