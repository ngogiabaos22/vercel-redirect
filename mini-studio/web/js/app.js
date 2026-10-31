// Bộ khung ứng dụng: trạng thái, điều hướng, tự lưu
import { api } from './api.js';
import { h, icon, toast, modal, debounce, timeAgo } from './ui.js';
import { renderHome } from './views/home.js';
import { renderScript } from './views/script.js';
import { renderStoryboard } from './views/storyboard.js';
import { renderVoice } from './views/voice.js';
import { renderExport } from './views/export.js';
import { openSettings } from './views/settings.js';

export const S = { project: null, system: null, settings: null, saveState: 'saved', cleanup: [] };

const STEPS = [
  { key: 'script', n: 1, label: 'Kịch bản', render: renderScript },
  { key: 'storyboard', n: 2, label: 'Storyboard', render: renderStoryboard },
  { key: 'voice', n: 3, label: 'Giọng đọc', render: renderVoice },
  { key: 'export', n: 4, label: 'Xuất video', render: renderExport },
];

// ── lưu ──
let saveTimer = null;
let savePromise = Promise.resolve();
export function markDirty() {
  setSave('saving');
  clearTimeout(saveTimer);
  saveTimer = setTimeout(flush, 700);
}
export async function flush() {
  clearTimeout(saveTimer);
  if (!S.project || S.saveState !== 'saving') return savePromise;
  const p = S.project;
  savePromise = api.put(`/projects/${p.id}`, p).then((res) => {
    // chỉ nhận lại các trường máy chủ quản lý để không ghi đè thứ người dùng đang gõ
    p.renders = res.renders; p.updated = res.updated;
    setSave('saved');
  }).catch((e) => { setSave('error'); toast('Không lưu được: ' + e.message, 'err'); });
  return savePromise;
}
export async function reloadProject() {
  await flush();
  S.project = await api.get(`/projects/${S.project.id}`);
  return S.project;
}
export function setProject(p) { S.project = p; }
function setSave(st) {
  S.saveState = st;
  const el = document.querySelector('.save-state');
  if (el) {
    el.className = 'save-state ' + st;
    el.textContent = st === 'saving' ? 'Đang lưu…' : st === 'error' ? 'Lỗi lưu' : 'Đã lưu';
  }
}
window.addEventListener('beforeunload', (e) => { if (S.saveState === 'saving') { flush(); e.preventDefault(); } });

// ── tiến độ từng bước ──
export function stepStatus(p) {
  const lines = p.scenes.flatMap((s) => s.lines);
  return {
    script: p.scenes.length > 0,
    storyboard: p.scenes.length > 0 && p.scenes.every((s) => s.image),
    voice: lines.length > 0 && lines.every((l) => l.audio && l.audio.file),
    export: (p.renders || []).length > 0,
  };
}

function renderRail(active) {
  const nav = document.getElementById('steps');
  const p = S.project;
  const st = p ? stepStatus(p) : {};
  nav.replaceChildren(
    h('a', { class: `step ${!p ? 'active' : ''}`, href: '#/' },
      h('div', { class: 'num' }, icon('folder')), 'Dự án'),
    h('div', { class: 'step-sep' }),
    ...STEPS.map((s) => h('a', {
      class: `step ${active === s.key ? 'active' : ''} ${st[s.key] && active !== s.key ? 'done' : ''} ${!p ? 'disabled' : ''}`,
      href: p ? `#/p/${p.id}/${s.key}` : '#/',
    }, h('div', { class: 'num' }, st[s.key] && active !== s.key ? icon('check') : String(s.n)), s.label)),
  );
}

function renderTopbar(active) {
  const bar = document.getElementById('topbar');
  const p = S.project;
  if (!p) {
    bar.replaceChildren(h('div', { class: 'title' }, 'Dự án của bạn'), h('div', { class: 'spacer' }), sysBadge());
    return;
  }
  const step = STEPS.find((s) => s.key === active);
  const name = h('input', {
    class: 'title', value: p.name, title: 'Đổi tên dự án',
    oninput: (e) => { p.name = e.target.value; markDirty(); },
  });
  bar.replaceChildren(
    h('a', { class: 'btn ghost sm', href: '#/' }, '← Dự án'),
    name,
    h('span', { class: 'tag' }, p.aspect),
    h('span', { class: 'muted small' }, step ? `Bước ${step.n}/4 · ${step.label}` : ''),
    h('div', { class: 'spacer' }),
    h('span', { class: 'save-state ' + S.saveState }, 'Đã lưu'),
    h('button', { class: 'btn sm', onclick: openVersions, title: 'Lịch sử phiên bản' }, icon('history'), 'Phiên bản'),
    sysBadge(),
  );
  setSave(S.saveState);
}

function sysBadge() {
  const s = S.system;
  if (!s) return h('span');
  const ok = !!s.ffmpeg;
  return h('button', { class: `tag ${ok ? 'green' : 'red'}`, style: { cursor: 'pointer', border: 0 }, onclick: () => openSettings('system') },
    ok ? '● Sẵn sàng' : '● Thiếu ffmpeg');
}

async function openVersions() {
  const p = S.project;
  await flush();
  const list = h('div', { class: 'stack' }, h('div', { class: 'muted' }, 'Đang tải…'));
  const load = async () => {
    const { versions } = await api.get(`/projects/${p.id}/versions`);
    list.replaceChildren(...(versions.length ? versions.map((v) => h('div', { class: 'kv' },
      h('span', {}, v.replace(/^(\d{4})(\d{2})(\d{2})-(\d{2})(\d{2})(\d{2})-?/, '$3/$2/$1 $4:$5 ').replace(/-/g, ' ')),
      h('button', {
        class: 'btn xs', onclick: async () => {
          const np = await api.post(`/projects/${p.id}/versions/${v}/restore`);
          S.project = np; m.close(); toast('Đã khôi phục phiên bản', 'ok'); route();
        },
      }, 'Khôi phục'))) : [h('div', { class: 'muted' }, 'Chưa có phiên bản nào. App tự lưu phiên bản mỗi khi bạn bấm "Tách cảnh".')]));
  };
  const m = modal({
    title: 'Lịch sử phiên bản',
    body: h('div', { class: 'modal-b' }, list),
    footer: [h('button', {
      class: 'btn primary', onclick: async () => {
        await api.post(`/projects/${p.id}/versions`, { label: 'thu-cong' }); toast('Đã lưu phiên bản', 'ok'); load();
      },
    }, 'Lưu phiên bản hiện tại')],
  });
  load();
}

// ── điều hướng ──
export function go(hash) { location.hash = hash; }

async function route() {
  S.cleanup.forEach((f) => { try { f(); } catch (e) { /* bỏ qua */ } });
  S.cleanup = [];
  await flush();
  const view = document.getElementById('view');
  view.className = 'view';
  const m = location.hash.match(/^#\/p\/([a-z0-9-]+)\/?(\w+)?/);
  if (!m) {
    S.project = null;
    renderRail(); renderTopbar();
    return renderHome(view);
  }
  const [, pid, stepKey = 'script'] = m;
  try {
    if (!S.project || S.project.id !== pid) S.project = await api.get(`/projects/${pid}`);
  } catch (e) {
    toast('Không mở được dự án: ' + e.message, 'err'); return go('#/');
  }
  const step = STEPS.find((s) => s.key === stepKey) || STEPS[0];
  renderRail(step.key); renderTopbar(step.key);
  view.replaceChildren();
  await step.render(view, S.project);
}
export function refreshChrome() {
  const m = location.hash.match(/^#\/p\/[a-z0-9-]+\/?(\w+)?/);
  renderRail(m ? (m[1] || 'script') : undefined);
}

async function boot() {
  document.getElementById('btnSettings').onclick = () => openSettings();
  // báo máy chủ "giao diện còn mở"; đóng cửa sổ thì báo "bye" để app tự tắt (đang render thì đợi xong)
  setInterval(() => fetch('/api/ping').catch(() => {}), 20000);
  const bye = () => navigator.sendBeacon('/api/bye');
  window.addEventListener('pagehide', bye);
  window.addEventListener('beforeunload', bye);
  window.addEventListener('hashchange', route);
  try {
    [S.system, S.settings] = await Promise.all([api.get('/system'), api.get('/settings')]);
  } catch (e) { toast('Không kết nối được máy chủ Mini Studio', 'err'); }
  route();
}
export async function refreshSystem() {
  [S.system, S.settings] = await Promise.all([api.get('/system'), api.get('/settings')]);
  return S.system;
}
export { timeAgo };
boot();
