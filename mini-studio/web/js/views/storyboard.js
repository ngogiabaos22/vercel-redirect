// Bước 2 – Storyboard: ảnh mỗi cảnh, vùng vẽ, gán câu thoại, xem thử
import { api, fileUrl, thumbUrl, watchJob } from '../api.js';
import { h, icon, modal, toast, debounce, fmtSec, PALETTE, pickFile, confirmBox } from '../ui.js';
import { S, markDirty, flush, reloadProject, refreshChrome, go } from '../app.js';

const st = { sid: null, sel: null, img: null, scale: 1, preview: null, data: null };

export async function renderStoryboard(view, p) {
  view.className = 'view fill';
  if (!p.scenes.length) {
    view.replaceChildren(h('div', { class: 'empty', style: { margin: 'auto' } }, icon('film'),
      h('h3', {}, 'Chưa có cảnh nào'), h('p', {}, 'Hãy viết kịch bản và bấm "Tách cảnh" trước.'),
      h('a', { class: 'btn accent', href: `#/p/${p.id}/script` }, 'Về bước Kịch bản')));
    return;
  }
  if (!p.scenes.find((s) => s.id === st.sid)) st.sid = p.scenes[0].id;
  stopPreview();
  const root = h('div', { class: 'sb' });
  view.replaceChildren(root);

  const onPaste = async (e) => {
    if (/INPUT|TEXTAREA/.test(document.activeElement?.tagName)) return;
    const item = [...(e.clipboardData?.items || [])].find((i) => i.type.startsWith('image/'));
    if (item) { e.preventDefault(); await uploadImage(p, item.getAsFile()); }
  };
  const onKey = (e) => {
    if (/INPUT|TEXTAREA|SELECT/.test(document.activeElement?.tagName)) return;
    if ((e.key === 'Delete' || e.key === 'Backspace') && st.sel) { e.preventDefault(); removeElement(p, st.sel); }
    if (e.key === ' ') { e.preventDefault(); st.preview ? stopPreview() : startPreview(p); }
  };
  window.addEventListener('paste', onPaste);
  window.addEventListener('keydown', onKey);
  S.cleanup.push(() => { window.removeEventListener('paste', onPaste); window.removeEventListener('keydown', onKey); stopPreview(); });
  draw(p);
}

const scene = (p) => p.scenes.find((s) => s.id === st.sid);
const elColor = (sc, el) => el.bg ? '#8C8577' : PALETTE[sc.elements.filter((e) => !e.bg).indexOf(el) % PALETTE.length];

function draw(p) {
  const root = document.querySelector('.sb');
  if (!root) return;
  const sc = scene(p);
  root.replaceChildren(sceneList(p), center(p, sc), side(p, sc));
  refreshTimeline(p);
}

// ── cột trái: danh sách cảnh ──
function sceneList(p) {
  return h('div', { class: 'sb-list' },
    h('div', { class: 'muted small' }, `${p.scenes.length} cảnh · sửa nội dung ở bước Kịch bản`),
    p.scenes.some((x) => !x.image) ? h('button', { class: 'btn sm accent', style: { width: '100%' }, onclick: () => imagesAll(p) }, icon('sparkle'), `Tạo ảnh ${p.scenes.filter((x) => !x.image).length} cảnh còn thiếu`) : null,
    p.scenes.map((s, i) => {
      const nEl = (s.elements || []).filter((e) => !e.bg).length;
      return h('div', { class: `sb-scene ${s.id === st.sid ? 'on' : ''}`, onclick: () => { st.sid = s.id; st.sel = null; stopPreview(); draw(p); } },
        h('div', { class: 'st' }, s.image ? h('img', { src: thumbUrl(p.id, s.image, 240), loading: 'lazy' }) : 'Chưa có ảnh'),
        h('div', { class: 'sm' }, h('b', {}, `${i + 1}. ${s.title}`),
          h('div', { class: 'row', style: { gap: '4px', flexWrap: 'wrap' } },
            s.image ? h('span', { class: 'tag green' }, `${nEl} vùng`) : h('span', { class: 'tag amber' }, 'cần ảnh'),
            h('span', { class: 'tag' }, `${s.lines.length} câu`))));
    }));
}

// ── giữa: thanh công cụ + sân khấu + dòng thời gian ──
function center(p, sc) {
  const stage = h('div', { class: 'stage' });
  const tools = h('div', { class: 'sb-toolbar' },
    h('button', { class: 'btn sm', onclick: async () => { const f = await pickFile('image/*'); if (f) uploadImage(p, f); } }, icon('upload'), 'Tải ảnh'),
    h('button', { class: 'btn sm', title: 'Dán ảnh từ bộ nhớ tạm (Ctrl+V)', onclick: () => pasteFromClipboard(p) }, icon('clipboard'), 'Dán'),
    h('button', { class: 'btn sm', onclick: () => generateImage(p, sc) }, icon('sparkle'), 'Tạo AI'),
    h('button', { class: 'btn sm', title: 'Chép prompt tạo ảnh', onclick: () => copyPrompt(p, sc) }, icon('copy'), 'Prompt'),
    h('div', { class: 'sep' }),
    h('button', { class: 'btn sm', disabled: !sc.image, onclick: () => autoDetect(p, sc) }, icon('scan'), 'Tự tách'),
    h('button', { class: 'btn sm', disabled: !sc.image, onclick: () => addElement(p, sc) }, icon('square'), 'Vùng'),
    h('div', { style: { flex: 1 } }),
    h('button', { class: 'btn sm', disabled: !sc.image, onclick: () => renderPreview(p, sc) }, icon('film'), 'Render 480p'),
    h('button', { class: 'btn sm primary', id: 'btnPlay', disabled: !sc.image, onclick: () => (st.preview ? stopPreview() : startPreview(p)) }, icon('play'), 'Xem thử'),
  );
  const tl = h('div', { class: 'tl', id: 'tl' });
  if (!sc.image) stage.append(dropzone(p, sc));
  else mountCanvas(p, sc, stage);
  return h('div', { class: 'sb-center' }, tools, stage, tl);
}

function dropzone(p, sc) {
  const box = h('div', { class: 'prompt-box' }, '…');
  api.post(`/projects/${p.id}/scenes/${sc.id}/prompt`, {}).then((r) => { box.textContent = r.prompt; });
  const dz = h('div', { class: 'dropzone' },
    icon('image'),
    h('h3', {}, 'Thêm ảnh nét vẽ cho cảnh này'),
    h('div', { class: 'muted' }, 'Kéo thả ảnh vào đây, bấm ', h('kbd', {}, 'Ctrl'), '+', h('kbd', {}, 'V'), ' để dán, hoặc tạo bằng AI.'),
    h('div', { class: 'btn-row' },
      h('button', { class: 'btn primary', onclick: async () => { const f = await pickFile('image/*'); if (f) uploadImage(p, f); } }, icon('upload'), 'Chọn ảnh'),
      h('button', { class: 'btn', onclick: () => generateImage(p, sc) }, icon('sparkle'), 'Tạo bằng Stable Diffusion'),
      h('button', { class: 'btn', onclick: () => copyPrompt(p, sc) }, icon('copy'), 'Chép prompt cho ChatGPT / Gemini')),
    h('div', { class: 'muted small' }, 'Prompt gợi ý (đúng phong cách nét chì, nền giấy, không chữ):'), box);
  dz.addEventListener('dragover', (e) => { e.preventDefault(); dz.classList.add('over'); });
  dz.addEventListener('dragleave', () => dz.classList.remove('over'));
  dz.addEventListener('drop', (e) => {
    e.preventDefault(); dz.classList.remove('over');
    const f = [...e.dataTransfer.files].find((x) => x.type.startsWith('image/'));
    if (f) uploadImage(p, f);
  });
  return dz;
}

// ── trình sửa vùng trên canvas ──
function mountCanvas(p, sc, stage) {
  const wrap = h('div', { class: 'stage-inner' });
  const cv = h('canvas');
  const sub = h('div', { class: 'sub-preview', style: { display: 'none' } });
  wrap.append(cv, sub);
  stage.append(wrap);
  const img = new Image();
  img.src = fileUrl(p.id, sc.image);
  st.img = img;
  const fit = () => {
    if (!img.naturalWidth) return;
    const r = stage.getBoundingClientRect();
    const s = Math.min((r.width - 36) / img.naturalWidth, (r.height - 36) / img.naturalHeight);
    st.scale = Math.max(0.05, s);
    cv.width = Math.round(img.naturalWidth * st.scale);
    cv.height = Math.round(img.naturalHeight * st.scale);
    paint(sc, cv);
  };
  img.onload = fit;
  const ro = new ResizeObserver(fit);
  ro.observe(stage);
  S.cleanup.push(() => ro.disconnect());

  stage.addEventListener('dragover', (e) => e.preventDefault());
  stage.addEventListener('drop', (e) => {
    e.preventDefault();
    const f = [...e.dataTransfer.files].find((x) => x.type.startsWith('image/'));
    if (f) uploadImage(p, f);
  });

  let drag = null;
  const pos = (e) => { const r = cv.getBoundingClientRect(); return [(e.clientX - r.left) / st.scale, (e.clientY - r.top) / st.scale]; };
  const HS = 8 / Math.max(st.scale, 0.0001);
  cv.addEventListener('mousedown', (e) => {
    if (st.preview) return;
    const [x, y] = pos(e);
    const hs = 9 / st.scale;
    const selEl = sc.elements.find((el) => el.id === st.sel && !el.bg);
    if (selEl) {
      const r = selEl.region;
      const hx = [r.x, r.x + r.width / 2, r.x + r.width], hy = [r.y, r.y + r.height / 2, r.y + r.height];
      for (let i = 0; i < 3; i++) for (let j = 0; j < 3; j++) {
        if (i === 1 && j === 1) continue;
        if (Math.abs(x - hx[i]) < hs && Math.abs(y - hy[j]) < hs) { drag = { mode: 'resize', el: selEl, i, j, o: { ...r } }; return; }
      }
    }
    const hit = [...sc.elements].reverse().find((el) => !el.bg && x >= el.region.x && x <= el.region.x + el.region.width && y >= el.region.y && y <= el.region.y + el.region.height);
    if (hit) { st.sel = hit.id; drag = { mode: 'move', el: hit, sx: x, sy: y, o: { ...hit.region } }; paint(sc, cv); refreshSide(p); return; }
    drag = { mode: 'new', sx: x, sy: y, cur: null };
  });
  const onMove = (e) => {
    if (!drag) {
      if (st.preview) return;
      const [x, y] = pos(e);
      const over = sc.elements.some((el) => !el.bg && x >= el.region.x && x <= el.region.x + el.region.width && y >= el.region.y && y <= el.region.y + el.region.height);
      cv.style.cursor = over ? 'move' : 'crosshair';
      return;
    }
    const [x, y] = pos(e);
    const W = img.naturalWidth, H = img.naturalHeight;
    const clamp = (v, a, b) => Math.max(a, Math.min(b, v));
    if (drag.mode === 'move') {
      const r = drag.el.region;
      r.x = Math.round(clamp(drag.o.x + x - drag.sx, 0, W - r.width));
      r.y = Math.round(clamp(drag.o.y + y - drag.sy, 0, H - r.height));
    } else if (drag.mode === 'resize') {
      const r = drag.el.region, o = drag.o;
      let x0 = o.x, y0 = o.y, x1 = o.x + o.width, y1 = o.y + o.height;
      if (drag.i === 0) x0 = clamp(x, 0, x1 - 10); if (drag.i === 2) x1 = clamp(x, x0 + 10, W);
      if (drag.j === 0) y0 = clamp(y, 0, y1 - 10); if (drag.j === 2) y1 = clamp(y, y0 + 10, H);
      Object.assign(r, { x: Math.round(x0), y: Math.round(y0), width: Math.round(x1 - x0), height: Math.round(y1 - y0) });
    } else {
      drag.cur = { x: Math.min(drag.sx, x), y: Math.min(drag.sy, y), width: Math.abs(x - drag.sx), height: Math.abs(y - drag.sy) };
    }
    paint(sc, cv, drag.mode === 'new' ? drag.cur : null);
  };
  const onUp = () => {
    if (!drag) return;
    if (drag.mode === 'new' && drag.cur && drag.cur.width > 14 && drag.cur.height > 14) {
      const r = drag.cur;
      const el = { id: 'e' + Math.random().toString(16).slice(2, 10), label: `Vùng ${sc.elements.filter((e) => !e.bg).length + 1}`,
        region: { x: Math.round(r.x), y: Math.round(r.y), width: Math.round(r.width), height: Math.round(r.height) }, lineIds: [], protectedRegions: [] };
      sc.elements.push(el); st.sel = el.id; changed(p);
    } else if (drag.mode !== 'new') changed(p);
    else { st.sel = null; refreshSide(p); }
    drag = null; paint(sc, cv);
  };
  window.addEventListener('mousemove', onMove);
  window.addEventListener('mouseup', onUp);
  S.cleanup.push(() => { window.removeEventListener('mousemove', onMove); window.removeEventListener('mouseup', onUp); });
  void HS;
}

function paint(sc, cv, ghost) {
  const ctx = cv.getContext('2d');
  const img = st.img;
  if (!img || !img.naturalWidth) return;
  const s = st.scale;
  ctx.clearRect(0, 0, cv.width, cv.height);
  ctx.drawImage(img, 0, 0, cv.width, cv.height);
  let n = 0;
  sc.elements.forEach((el) => {
    const c = elColor(sc, el);
    const r = el.region;
    const on = el.id === st.sel;
    if (el.bg) {
      if (!on) return;
      ctx.setLineDash([8, 6]); ctx.strokeStyle = c; ctx.lineWidth = 2;
      ctx.strokeRect(3, 3, cv.width - 6, cv.height - 6); ctx.setLineDash([]);
      return;
    }
    n += 1;
    const x = r.x * s, y = r.y * s, w = r.width * s, hh = r.height * s;
    ctx.fillStyle = c + (on ? '22' : '12');
    ctx.fillRect(x, y, w, hh);
    ctx.strokeStyle = c; ctx.lineWidth = on ? 2.5 : 1.6;
    ctx.strokeRect(x + .5, y + .5, w, hh);
    const seq = sc.elements.indexOf(el) + 1;
    const label = `${seq}`;
    ctx.font = '700 12px system-ui, sans-serif';
    ctx.fillStyle = c;
    ctx.fillRect(x, y, ctx.measureText(label).width + 12, 20);
    ctx.fillStyle = '#fff'; ctx.fillText(label, x + 6, y + 14);
    if (on) {
      ctx.fillStyle = '#fff'; ctx.strokeStyle = c; ctx.lineWidth = 1.5;
      for (const hx of [x, x + w / 2, x + w]) for (const hy of [y, y + hh / 2, y + hh]) {
        if (hx === x + w / 2 && hy === y + hh / 2) continue;
        ctx.fillRect(hx - 4, hy - 4, 8, 8); ctx.strokeRect(hx - 4, hy - 4, 8, 8);
      }
    }
  });
  if (ghost) {
    ctx.setLineDash([6, 4]); ctx.strokeStyle = '#23211D'; ctx.lineWidth = 1.5;
    ctx.strokeRect(ghost.x * s, ghost.y * s, ghost.width * s, ghost.height * s); ctx.setLineDash([]);
  }
}

function repaint(p) {
  const cv = document.querySelector('.stage canvas');
  if (cv) paint(scene(p), cv);
}

const changed = (p) => { markDirty(); refreshSide(p); refreshTimeline(p); refreshList(p); };
function refreshList(p) {
  const old = document.querySelector('.sb-list');
  if (old) old.replaceWith(sceneList(p));
}

// ── cột phải: thông tin cảnh, vùng vẽ, câu thoại ──
function side(p, sc) {
  const box = h('div', { class: 'sb-side' });
  fillSide(p, sc, box);
  return box;
}
function refreshSide(p) {
  const box = document.querySelector('.sb-side');
  if (box) fillSide(p, scene(p), box);
}
function fillSide(p, sc, box) {
  const lineOwner = {};
  sc.elements.forEach((el) => (el.lineIds || []).forEach((id) => { lineOwner[id] = lineOwner[id] || el; }));
  let dragId = null;
  const els = sc.elements.map((el, idx) => {
    const c = elColor(sc, el);
    const on = el.id === st.sel;
    const item = h('div', {
      class: `el ${on ? 'on' : ''}`, draggable: true,
      ondragstart: () => { dragId = el.id; item.classList.add('dragging'); },
      ondragend: () => item.classList.remove('dragging'),
      ondragover: (e) => { e.preventDefault(); item.classList.add('over'); },
      ondragleave: () => item.classList.remove('over'),
      ondrop: (e) => {
        e.preventDefault(); item.classList.remove('over');
        const from = sc.elements.findIndex((x) => x.id === dragId);
        if (from < 0 || from === idx) return;
        const [m] = sc.elements.splice(from, 1);
        sc.elements.splice(sc.elements.indexOf(el) + (from < idx ? 1 : 0), 0, m);
        changed(p); repaint(p);
      },
    },
    h('div', { class: 'eh', onclick: (e) => { if (e.target.tagName === 'INPUT') return; st.sel = on ? null : el.id; refreshSide(p); repaint(p); } },
      h('span', { class: 'grip' }, icon('grip')),
      h('span', { class: 'sw', style: { background: c } }, idx + 1),
      h('input', { class: 'lb', value: el.label || '', oninput: (e) => { el.label = e.target.value; markDirty(); }, onfocus: () => { st.sel = el.id; repaint(p); } }),
      h('button', { class: 'btn ghost xs', title: 'Xoá vùng', onclick: (e) => { e.stopPropagation(); removeElement(p, el.id); } }, icon('trash'))),
    on ? h('div', { class: 'eb' },
      h('div', { class: 'muted small' }, el.bg ? 'Vùng nền: vẽ các nét nằm ngoài mọi vùng khác (đường đất, chân trời…). Gán câu để vẽ cùng lúc đọc câu đó.' : 'Vẽ vùng này khi đọc câu:'),
      sc.lines.map((ln, i) => {
        const owner = lineOwner[ln.id];
        const checked = (el.lineIds || []).includes(ln.id);
        return h('label', { class: `lchk ${owner && owner !== el && !checked ? 'taken' : ''}` },
          h('input', {
            type: 'checkbox', checked, onchange: (e) => {
              el.lineIds = el.lineIds || [];
              if (e.target.checked) el.lineIds.push(ln.id); else el.lineIds = el.lineIds.filter((x) => x !== ln.id);
              el.lineIds.sort((a, b) => sc.lines.findIndex((l) => l.id === a) - sc.lines.findIndex((l) => l.id === b));
              changed(p);
            },
          }), h('span', {}, `${i + 1}. ${ln.text}`));
      })) : null);
    return item;
  });

  const unassigned = sc.lines.filter((l) => !lineOwner[l.id]).length;
  box.replaceChildren(
    h('section', {},
      h('h4', {}, 'Cảnh'),
      h('div', { class: 'stack' },
        h('label', { class: 'field' }, 'Tên cảnh', h('input', { class: 'input', value: sc.title, oninput: (e) => { sc.title = e.target.value; markDirty(); } })),
        h('label', { class: 'field' }, h('span', {}, 'Mô tả hình ', h('span', { class: 'hint' }, '(dùng để tạo prompt ảnh)')),
          h('textarea', { class: 'input', rows: 2, value: sc.imageNote || '', oninput: (e) => { sc.imageNote = e.target.value; markDirty(); } })),
        sc.image ? h('div', { class: 'btn-row' },
          h('span', { class: 'muted small' }, `${sc.canvas?.width}×${sc.canvas?.height}px`),
          h('span', { id: 'cov', class: 'small' }),
          h('div', { style: { flex: 1 } }),
          h('button', {
            class: 'btn xs danger', onclick: async () => {
              if (!(await confirmBox('Gỡ ảnh và các vùng của cảnh này?', 'Gỡ', true))) return;
              await flush(); await api.del(`/projects/${p.id}/scenes/${sc.id}/image`); await reloadProject(); draw(S.project);
            },
          }, 'Gỡ ảnh')) : null)),
    sc.image ? h('section', {},
      h('h4', {}, 'Thứ tự vẽ', h('span', { class: 'spacer' }), h('span', { class: 'tag' }, `${sc.elements.length} vùng`)),
      h('div', { class: 'muted small', style: { marginBottom: '8px' } }, 'Kéo để đổi thứ tự. Bấm vào vùng để gán câu thoại. Vẽ khung mới bằng cách kéo chuột trên ảnh.'),
      h('div', { class: 'els' }, els)) : null,
    h('section', {},
      h('h4', {}, 'Lời thoại', h('span', { class: 'spacer' }),
        unassigned && sc.image ? h('span', { class: 'tag amber' }, `${unassigned} câu chưa gán`) : null),
      h('div', { class: 'lines-mini' }, sc.lines.map((ln, i) => {
        const o = lineOwner[ln.id];
        return h('div', { class: 'lm' }, h('span', { class: 'dot', style: { background: o ? elColor(sc, o) : '' } }), h('span', {}, `${i + 1}. ${ln.text}`));
      }))),
  );
  updateCoverage(p);
}

const updateCoverage = debounce(async (p) => {
  const sc = scene(p);
  const el = document.getElementById('cov');
  if (!el || !sc.image) return;
  await flush();
  const { coverage } = await api.post(`/projects/${p.id}/scenes/${sc.id}/coverage`, { elements: sc.elements });
  const pct = Math.round(coverage * 100);
  el.replaceChildren(h('span', { class: `tag ${pct >= 97 ? 'green' : pct >= 85 ? 'amber' : 'red'}`, title: 'Tỉ lệ nét vẽ nằm trong các vùng. Phần ngoài vùng chỉ hiện ở cuối cảnh.' }, `Phủ nét ${pct}%`));
}, 500);

function removeElement(p, id) {
  const sc = scene(p);
  sc.elements = sc.elements.filter((e) => e.id !== id);
  if (st.sel === id) st.sel = null;
  changed(p); repaint(p);
}
function addElement(p, sc) {
  const c = sc.canvas;
  const el = { id: 'e' + Math.random().toString(16).slice(2, 10), label: `Vùng ${sc.elements.length + 1}`,
    region: { x: Math.round(c.width * .35), y: Math.round(c.height * .3), width: Math.round(c.width * .3), height: Math.round(c.height * .4) }, lineIds: [], protectedRegions: [] };
  sc.elements.push(el); st.sel = el.id; changed(p); repaint(p);
}

// ── dòng thời gian ──
const refreshTimeline = debounce(async (p) => {
  const box = document.getElementById('tl');
  const sc = scene(p);
  if (!box || !sc) return;
  await flush();
  let d;
  try { d = await api.get(`/projects/${p.id}/scenes/${sc.id}/audio`); } catch (e) { return; }
  st.data = d;
  const total = d.annotation ? d.annotation.sceneDurationMs : d.timeline.durationMs;
  const pct = (ms) => (100 * ms / total) + '%';
  const voiced = d.timeline.lines.filter((l) => l.hasAudio).length;
  box.replaceChildren(
    h('div', { class: 'tl-head' }, h('b', {}, 'Dòng thời gian'), `${fmtSec(total)}`,
      voiced < d.timeline.lines.length ? h('span', { class: 'tag amber' }, `${d.timeline.lines.length - voiced} câu đang dùng thời lượng ước tính`) : h('span', { class: 'tag green' }, 'Đã có giọng đọc'),
      h('div', { style: { flex: 1 } }), h('span', {}, 'Phím ', h('kbd', {}, 'Space'), ' để xem thử')),
    h('div', { class: 'tl-row', id: 'tlLines' }, d.timeline.lines.map((l, i) => h('div', {
      class: 'blk line', title: l.text, style: { left: pct(l.startMs), width: pct(l.durMs), opacity: l.hasAudio ? 1 : .55 },
    }, `${i + 1}. ${l.text}`)), h('div', { class: 'tl-cursor', style: { left: 0, display: 'none' } })),
    d.annotation ? h('div', { class: 'tl-row' }, d.annotation.elements.map((e) => {
      const el = sc.elements.find((x) => x.id === e.id);
      return h('div', { class: 'blk', title: e.label, style: { left: pct(e.reveal.startMs), width: pct(e.reveal.durationMs), background: el ? elColor(sc, el) : '#888' } },
        `${e.sequence}. ${e.label}`);
    })) : null);
}, 450);

// ── xem thử (mô phỏng nhanh trong trình duyệt, không cần render) ──
async function startPreview(p) {
  const sc = scene(p);
  const cv = document.querySelector('.stage canvas');
  if (!cv || !st.img) return;
  await flush();
  const d = await api.get(`/projects/${p.id}/scenes/${sc.id}/audio`);
  const ann = d.annotation;
  const total = ann.sceneDurationMs;
  const audio = d.hasAudio ? new Audio(fileUrl(p.id, d.file, d.v)) : null;
  const t0 = performance.now();
  const ctx = cv.getContext('2d');
  const tmp = document.createElement('canvas');
  tmp.width = cv.width; tmp.height = cv.height;
  const tctx = tmp.getContext('2d');
  const s = st.scale;
  // màu nền lấy từ góc ảnh để vùng chưa vẽ trùng màu giấy của ảnh
  const probe = document.createElement('canvas'); probe.width = probe.height = 4;
  probe.getContext('2d').drawImage(st.img, 0, 0, 8, 8, 0, 0, 4, 4);
  const [pr, pg, pb] = probe.getContext('2d').getImageData(0, 0, 1, 1).data;
  const paper = `rgb(${pr},${pg},${pb})`;
  const sub = document.querySelector('.sub-preview');
  const cursor = document.querySelector('#tlLines .tl-cursor');
  const btn = document.getElementById('btnPlay');
  if (btn) btn.replaceChildren(icon('stop'), 'Dừng');
  st.preview = { audio, raf: 0 };
  if (audio) audio.play().catch(() => {});
  const frame = () => {
    if (!st.preview) return;
    const t = audio && !audio.paused ? audio.currentTime * 1000 : performance.now() - t0;
    ctx.fillStyle = paper; ctx.fillRect(0, 0, cv.width, cv.height);
    const els = ann.elements;
    const last = els.length ? Math.max(...els.map((e) => e.reveal.startMs + e.reveal.durationMs)) : 0;
    if (t >= last + 300) ctx.drawImage(st.img, 0, 0, cv.width, cv.height);
    else els.forEach((e, i) => {
      const k = (t - e.reveal.startMs) / e.reveal.durationMs;
      if (k <= 0) return;
      const r = e.region;
      tctx.clearRect(0, 0, tmp.width, tmp.height);
      tctx.save();
      tctx.beginPath();
      tctx.rect(r.x * s, r.y * s, r.width * s * Math.min(1, k), r.height * s);
      tctx.clip();
      tctx.drawImage(st.img, 0, 0, tmp.width, tmp.height);
      tctx.restore();
      els.slice(i + 1).forEach((l) => tctx.clearRect(l.region.x * s, l.region.y * s, l.region.width * s, l.region.height * s));
      ctx.drawImage(tmp, 0, 0);
      if (k < 1) { // vẽ "đầu bút"
        ctx.fillStyle = '#E8590C';
        ctx.beginPath(); ctx.arc((r.x + r.width * k) * s, (r.y + r.height * (0.5 + 0.35 * Math.sin(t / 90))) * s, 5, 0, 7); ctx.fill();
      }
    });
    const line = d.timeline.lines.find((l) => t >= l.startMs && t < l.startMs + l.durMs);
    sub.style.display = line ? 'block' : 'none';
    if (line) sub.textContent = line.text;
    if (cursor) { cursor.style.display = 'block'; cursor.style.left = (100 * t / total) + '%'; }
    if (t > total) return stopPreview();
    st.preview.raf = requestAnimationFrame(frame);
  };
  frame();
}
function stopPreview() {
  if (!st.preview) return;
  cancelAnimationFrame(st.preview.raf);
  if (st.preview.audio) st.preview.audio.pause();
  st.preview = null;
  const sub = document.querySelector('.sub-preview'); if (sub) sub.style.display = 'none';
  const cursor = document.querySelector('#tlLines .tl-cursor'); if (cursor) cursor.style.display = 'none';
  const btn = document.getElementById('btnPlay'); if (btn) btn.replaceChildren(icon('play'), 'Xem thử');
  if (S.project) repaint(S.project);
}

// ── ảnh ──
async function uploadImage(p, file) {
  const sc = scene(p);
  const t = toast('Đang tải ảnh và tự tách vùng…');
  await flush();
  try {
    const np = await api.upload(`/projects/${p.id}/scenes/${sc.id}/image`, file, file.name || 'paste.png');
    Object.assign(S.project, np);
    st.sel = null;
    refreshChrome();
    draw(S.project);
    toast(`Đã thêm ảnh, tìm thấy ${scene(S.project).elements.filter((e) => !e.bg).length} vùng`, 'ok');
  } catch (e) { toast(e.message, 'err'); }
  void t;
}
async function pasteFromClipboard(p) {
  try {
    const items = await navigator.clipboard.read();
    for (const it of items) {
      const type = it.types.find((x) => x.startsWith('image/'));
      if (type) return uploadImage(p, await it.getType(type));
    }
    toast('Bộ nhớ tạm không có ảnh', 'err');
  } catch (e) { toast('Hãy bấm Ctrl+V trên trang này để dán ảnh', 'err'); }
}
async function copyPrompt(p, sc) {
  await flush();
  const r = await api.post(`/projects/${p.id}/scenes/${sc.id}/prompt`, {});
  try { await navigator.clipboard.writeText(r.prompt); toast('Đã chép prompt. Dán vào công cụ tạo ảnh bạn dùng, tải ảnh về rồi thả vào đây.', 'ok'); }
  catch (e) { modal({ title: 'Prompt tạo ảnh', body: h('div', { class: 'modal-b' }, h('textarea', { class: 'input', rows: 6, value: r.prompt })) }); }
}
async function autoDetect(p, sc) {
  const nReal = sc.elements.filter((e) => !e.bg).length;
  if (nReal && !(await confirmBox('Tách lại vùng tự động? Các vùng hiện tại sẽ bị thay thế.', 'Tách lại'))) return;
  await flush();
  const r = await api.post(`/projects/${p.id}/scenes/${sc.id}/detect`, { max: 6 });
  sc.elements = r.elements; st.sel = null;
  changed(p); repaint(p);
  toast(`Tìm thấy ${r.elements.filter((e) => !e.bg).length} vùng (phủ ${Math.round(r.coverage * 100)}% nét vẽ)`, 'ok');
}
async function imagesAll(p) {
  await flush();
  const prog = h('div', { class: 'progress' }, h('div'));
  const msg = h('div', { class: 'muted small' }, 'Đang bắt đầu…');
  const log = h('div', { class: 'log' });
  const m = modal({ title: 'Tạo ảnh cho mọi cảnh còn thiếu', wide: true, body: h('div', { class: 'modal-b stack' }, prog, msg, log) });
  try {
    const job = await api.post(`/projects/${p.id}/images/auto`, {});
    await watchJob(job, (j) => { prog.firstChild.style.width = (j.progress * 100) + '%'; msg.textContent = j.message; log.textContent = j.log.join('\n'); log.scrollTop = log.scrollHeight; });
    toast('Đã tạo xong ảnh', 'ok'); m.close();
  } catch (e) { msg.textContent = 'Lỗi: ' + e.message; toast(e.message, 'err'); }
  await reloadProject(); refreshChrome(); draw(S.project);
}
async function generateImage(p, sc) {
  await flush();
  const r = await api.post(`/projects/${p.id}/scenes/${sc.id}/prompt`, {});
  const ta = h('textarea', { class: 'input', rows: 6, value: r.prompt });
  const status = h('div', { class: 'muted small' });
  const provs = S.system?.imageProviders || [];
  const prog = h('div', { class: 'progress', style: { display: 'none' } }, h('div'));
  const go1 = h('button', {
    class: 'btn accent', onclick: async () => {
      go1.disabled = true; prog.style.display = 'block';
      try {
        const job = await api.post(`/projects/${p.id}/scenes/${sc.id}/generate`, { prompt: ta.value });
        await watchJob(job, (j) => { prog.firstChild.style.width = (j.progress * 100) + '%'; status.textContent = j.message; });
        m.close(); await reloadProject(); draw(S.project); toast('Đã tạo ảnh', 'ok');
      } catch (e) { toast(e.message, 'err'); go1.disabled = false; }
    },
  }, icon('sparkle'), 'Tạo ảnh');
  const m = modal({
    title: 'Tạo ảnh bằng AI',
    body: h('div', { class: 'modal-b stack' },
      h('div', { class: 'svc' }, h('span', { class: `d ${provs.length ? 'on' : ''}` }),
        provs.length ? `Nguồn (thử lần lượt): ${provs.join(' → ')}` : 'Chưa bật nguồn ảnh nào (Cài đặt → Nguồn ảnh)'),
      h('label', { class: 'field' }, 'Prompt (tiếng Anh cho kết quả tốt nhất)', ta),
      h('button', {
        class: 'btn sm', style: { alignSelf: 'flex-start' }, onclick: async () => {
          status.textContent = 'Đang nhờ AI viết prompt…';
          try { ta.value = (await api.post(`/projects/${p.id}/scenes/${sc.id}/prompt`, { useAI: true })).prompt; status.textContent = ''; }
          catch (e) { status.textContent = e.message; }
        },
      }, icon('wand'), 'Nhờ AI viết lại prompt tiếng Anh'),
      prog, status,
      h('div', { class: 'muted small' }, 'Thêm/đổi nguồn ảnh (Pollinations, Cloudflare, Gemini, Hugging Face, OpenAI, SD trên máy…) ở Cài đặt → Nguồn ảnh.')),
    footer: [h('button', { class: 'btn', onclick: () => m.close() }, 'Đóng'), go1],
  });
}
async function renderPreview(p, sc) {
  await flush();
  const prog = h('div', { class: 'progress' }, h('div'));
  const msg = h('div', { class: 'muted small' }, 'Đang chuẩn bị…');
  const body = h('div', { class: 'modal-b stack' }, prog, msg);
  modal({ title: `Render thử: ${sc.title} (480p)`, body, wide: true });
  try {
    const job = await api.post(`/projects/${p.id}/scenes/${sc.id}/preview`);
    const j = await watchJob(job, (x) => { prog.firstChild.style.width = (x.progress * 100) + '%'; msg.textContent = x.message; });
    body.replaceChildren(h('video', { class: 'player', src: fileUrl(p.id, j.result.file, j.result.v), controls: true, autoplay: true }),
      h('div', { class: 'muted small' }, 'Bản nháp 480p để kiểm tra nhịp vẽ. Bản chính xuất ở bước 4.'));
  } catch (e) { msg.textContent = 'Lỗi: ' + e.message; }
}
export { go };
