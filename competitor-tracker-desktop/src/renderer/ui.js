'use strict';
/* Tiện ích giao diện: dựng DOM an toàn (textContent, không innerHTML), định dạng số/ngày, toast, hộp thoại. */
(function () {
  const SVG_NS = 'http://www.w3.org/2000/svg';

  function h(tag, attrs, ...children) {
    const el = document.createElement(tag);
    applyAttrs(el, attrs);
    append(el, children);
    return el;
  }
  function s(tag, attrs, ...children) {
    const el = document.createElementNS(SVG_NS, tag);
    for (const [k, v] of Object.entries(attrs || {})) if (v !== undefined && v !== null && v !== false) el.setAttribute(k, v);
    append(el, children);
    return el;
  }
  function applyAttrs(el, attrs) {
    for (const [k, v] of Object.entries(attrs || {})) {
      if (v === undefined || v === null || v === false) continue;
      if (k === 'class') el.className = v;
      else if (k === 'style' && typeof v === 'object') Object.assign(el.style, v);
      else if (k.startsWith('on') && typeof v === 'function') el.addEventListener(k.slice(2).toLowerCase(), v);
      else if (k === 'value') el.value = v;
      else if (k === 'checked') el.checked = !!v;
      else if (k === 'disabled') el.disabled = !!v;
      else if (k === 'selected') el.selected = !!v;
      else el.setAttribute(k, v === true ? '' : v);
    }
  }
  function append(el, children) {
    for (const c of children.flat(Infinity)) {
      if (c === null || c === undefined || c === false) continue;
      el.appendChild(c instanceof Node ? c : document.createTextNode(String(c)));
    }
  }

  const ICONS = {
    dashboard: 'M3 3h7v9H3zM14 3h7v5h-7zM14 12h7v9h-7zM3 16h7v5H3z',
    groups: 'M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z',
    pages: 'M4 22V4a1 1 0 0 1 1-1h12l-2 4 2 4H5',
    history: 'M3 12a9 9 0 1 0 3-6.7L3 8M3 3v5h5M12 7v5l3 2',
    settings: 'M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6',
    trash: 'M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6',
    external: 'M14 3h7v7M10 14 21 3M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5',
    folder: 'M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z'
  };
  function icon(name, size = 18) {
    return s('svg', { width: size, height: size, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2, 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'aria-hidden': 'true' }, s('path', { d: ICONS[name] }));
  }

  const nf = new Intl.NumberFormat('vi-VN');
  function num(v) { return v === null || v === undefined ? null : nf.format(v); }
  /** Ô số liệu: null → "Không khả dụng" (không gán 0). */
  function metric(v) { return v === null || v === undefined ? h('span', { class: 'na' }, 'Không khả dụng') : nf.format(v); }
  function compact(v) {
    if (v === null || v === undefined) return '—';
    if (v >= 1e6) return (v / 1e6).toLocaleString('vi-VN', { maximumFractionDigits: 1 }) + ' tr';
    if (v >= 1e3) return (v / 1e3).toLocaleString('vi-VN', { maximumFractionDigits: 1 }) + 'K';
    return nf.format(v);
  }
  // DB lưu giờ UTC dạng "YYYY-MM-DD HH:MM:SS" hoặc ISO
  function toDate(v) { if (!v) return null; const t = /T/.test(v) ? v : v.replace(' ', 'T') + 'Z'; const d = new Date(t); return isNaN(d) ? null : d; }
  function dt(v) {
    const d = toDate(v);
    if (!d) return '—';
    const p = new Intl.DateTimeFormat('en-GB', { timeZone: 'Asia/Ho_Chi_Minh', day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false })
      .formatToParts(d).reduce((o, x) => { o[x.type] = x.value; return o; }, {});
    const thisYear = String(new Date().getFullYear()) === p.year;
    return `${p.day}/${p.month}${thisYear ? '' : '/' + p.year} ${p.hour === '24' ? '00' : p.hour}:${p.minute}`;
  }
  function dur(a, b) {
    const s1 = toDate(a); const s2 = b ? toDate(b) : new Date();
    if (!s1 || !s2) return '—';
    const sec = Math.max(0, Math.round((s2 - s1) / 1000));
    if (sec < 60) return `${sec} giây`;
    if (sec < 3600) return `${Math.round(sec / 60)} phút`;
    return `${Math.floor(sec / 3600)} giờ ${Math.round((sec % 3600) / 60)} phút`;
  }
  function ago(v) {
    const d = toDate(v);
    if (!d) return 'Chưa thu thập';
    const diff = Date.now() - d;
    if (diff < 3600000) return `Thu thập ${Math.max(1, Math.round(diff / 60000))} phút trước`;
    const today = new Date(); today.setHours(0, 0, 0, 0);
    if (d >= today) return 'Thu thập hôm nay';
    return `Thu thập ${dt(v)}`;
  }

  function toast(msg, isErr) {
    const el = h('div', { class: 'toast' + (isErr ? ' err' : '') }, msg);
    document.getElementById('toasts').appendChild(el);
    setTimeout(() => el.remove(), isErr ? 8000 : 4000);
  }

  /** Gọi API, tự báo lỗi bằng toast. */
  async function call(channel, ...args) {
    try { return await window.api.call(channel, ...args); } catch (e) { toast(e.message, true); throw e; }
  }

  function modal(build) {
    const overlay = h('div', { class: 'overlay' });
    const close = () => overlay.remove();
    const box = h('div', { class: 'modal', role: 'dialog', 'aria-modal': 'true' });
    overlay.appendChild(box);
    overlay.addEventListener('click', (e) => { if (e.target === overlay) close(); });
    overlay.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
    const render = () => { box.replaceChildren(); append(box, [].concat(build(close, render))); };
    render();
    document.body.appendChild(overlay);
    const f = box.querySelector('input, button, select');
    if (f) f.focus();
    return close;
  }

  function switchBtn(on, onChange, label) {
    return h('button', { type: 'button', class: 'switch' + (on ? ' on' : ''), 'aria-pressed': on ? 'true' : 'false', 'aria-label': label, onclick: () => onChange(!on) });
  }

  function badgeForStatus(st, enabled = true) {
    if (!enabled) return h('span', { class: 'badge b-off' }, 'Tạm dừng');
    return {
      active: h('span', { class: 'badge b-ok' }, 'Hoạt động'),
      pending: h('span', { class: 'badge b-warn' }, 'Chờ xử lý'),
      running: h('span', { class: 'badge b-run' }, 'Đang thu thập'),
      error: h('span', { class: 'badge b-err' }, 'Lỗi')
    }[st] || h('span', { class: 'badge b-off' }, st);
  }
  function jobBadge(st) {
    const map = { running: ['b-run', 'Đang chạy'], done: ['b-ok', 'Hoàn thành'], partial: ['b-warn', 'Một phần'], failed: ['b-err', 'Lỗi'], cancelled: ['b-off', 'Đã huỷ'] };
    const [c, t] = map[st] || ['b-off', st];
    return h('span', { class: `badge ${c}` }, t);
  }

  function field(label, control, id) {
    if (id) control.id = id;
    return h('div', { class: 'field' }, h('label', { for: id }, label), control);
  }

  let uid = 0;
  const nextId = (p = 'f') => `${p}-${++uid}`;

  window.UI = { h, s, icon, num, metric, compact, dt, dur, ago, toDate, toast, call, modal, switchBtn, badgeForStatus, jobBadge, field, nextId };
})();
