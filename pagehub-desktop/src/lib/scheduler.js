// Posts queued items when they are due. Runs every 30 s while the app is open (or in the tray).
const store = require('./store');
const fb = require('./facebook');
const sheet = require('./sheet');

let notify = () => {};
let busy = false;
let timer = null;

const pad = n => String(n).padStart(2, '0');
const fmt = t => { const d = new Date(t); return `${pad(d.getDate())}/${pad(d.getMonth() + 1)} ${pad(d.getHours())}:${pad(d.getMinutes())}`; };
const toMin = s => { const [h, m] = String(s).split(':').map(Number); return h * 60 + (m || 0); };

function account(id) { return store.get().accounts.find(a => a.id === id); }

// Next free slot for a Page inside the posting window, respecting max/day and the minimum gap.
function nextSlot(pageId, fromTime) {
  const { ws, we, max, gap } = store.get().settings.rules;
  const t0 = toMin(ws), t1 = Math.max(toMin(we), t0 + 1);
  const earliest = (fromTime || Date.now()) + 5 * 60 * 1000;
  const items = store.get().queue.filter(q => q.pageId === pageId && q.status !== 'cancelled' && q.status !== 'failed');
  for (let day = 0; day < 30; day++) {
    const d = new Date(earliest); d.setHours(0, 0, 0, 0); d.setDate(d.getDate() + day);
    const dayStart = d.getTime(), dayEnd = dayStart + 864e5;
    const used = items.filter(q => q.when >= dayStart && q.when < dayEnd).map(q => (q.when - dayStart) / 6e4);
    if (used.length >= max) continue;
    for (let k = 0; k < 80; k++) {
      const m = t0 + Math.floor(Math.random() * (t1 - t0));
      const when = dayStart + m * 6e4;
      if (when < earliest) continue;
      if (used.every(u => Math.abs(u - m) >= gap)) return when;
    }
  }
  return null;
}

// Pick the nick used for this post: the Page's chosen nick, or another connected nick unless the Page is strict.
function pickPoster(page) {
  const ok = id => { const a = account(id); return a && a.status === 'ok' && page.tokens[id]; };
  if (ok(page.poster)) return page.poster;
  if (page.strict) return null;
  return Object.keys(page.tokens).find(ok) || null;
}

async function postOne(q) {
  const d = store.get();
  const page = d.pages[q.pageId];
  if (!page) { q.status = 'failed'; q.error = 'Fanpage không còn trong danh sách'; return; }
  const accId = pickPoster(page);
  if (!accId) {
    q.status = 'hold';
    q.error = page.strict ? 'Nick đăng của Page cần đăng nhập lại' : 'Không có nick nào đăng được cho Page này';
    return;
  }
  const token = store.dec(page.tokens[accId]);
  q.status = 'posting'; q.accountId = accId; store.save(); notify();
  try {
    const ids = [];
    for (const fid of q.images) {
      const img = await sheet.image(fid);
      ids.push(await fb.uploadPhoto(q.pageId, token, Buffer.from(img.base64, 'base64'), img.mime, img.name));
    }
    const post = await fb.createPost(q.pageId, token, q.caption, ids);
    q.status = 'done'; q.postId = post.id; q.postedAt = Date.now(); q.error = '';
    q.link = `https://www.facebook.com/${post.id}`;
    store.log(`Đã đăng lên ${page.name} bằng ${account(accId).name}`);
    if (q.row) {
      await sheet.setStatus(q.row, `Đã đăng · ${fmt(q.postedAt)} · ${page.name} · ${q.link}`)
        .catch(e => store.log(`Không ghi được trạng thái dòng ${q.row}: ${e.message}`, 'warn'));
    }
  } catch (e) {
    q.attempts = (q.attempts || 0) + 1;
    q.error = e.message;
    if (fb.isAuthError(e)) {
      const a = account(accId);
      if (a) { a.status = 'expired'; a.error = e.message; }
      q.status = page.strict ? 'hold' : 'scheduled';
      q.when = Date.now() + 60 * 1000;
      store.log(`${a ? a.name : 'Nick'} cần đăng nhập lại: ${e.message}`, 'error');
    } else if (q.attempts < 3) {
      q.status = 'scheduled';
      q.when = Date.now() + 5 * 60 * 1000;
      store.log(`Đăng lỗi (${page.name}), thử lại sau 5 phút: ${e.message}`, 'warn');
    } else {
      q.status = 'failed';
      store.log(`Đăng thất bại sau 3 lần (${page.name}): ${e.message}`, 'error');
    }
  }
}

async function tick() {
  if (busy) return;
  busy = true;
  try {
    const due = store.get().queue.filter(q => q.status === 'scheduled' && q.when <= Date.now()).sort((a, b) => a.when - b.when);
    for (const q of due) {
      await postOne(q);
      store.save(); notify();
    }
  } finally {
    busy = false;
  }
}

function start(onChange) {
  notify = onChange || notify;
  // Anything left "posting" from a crash goes back to the queue.
  store.get().queue.forEach(q => { if (q.status === 'posting') q.status = 'scheduled'; });
  clearInterval(timer);
  timer = setInterval(tick, 30 * 1000);
  setTimeout(tick, 3000);
}

module.exports = { start, tick, nextSlot, postOne, fmt };
