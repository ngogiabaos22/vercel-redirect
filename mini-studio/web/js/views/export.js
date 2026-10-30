// Bước 4 – Xuất video
import { api, fileUrl, watchJob } from '../api.js';
import { h, icon, toast, seg, toggle, fmtMs, pickFile, timeAgo, confirmBox, modal } from '../ui.js';
import { S, markDirty, flush, reloadProject, refreshChrome } from '../app.js';

const SUB_STYLES = {
  box: { background: 'rgba(20,20,24,.8)', color: '#fff' },
  paper: { background: '#FFFAEE', color: '#282828', border: '1.5px solid #3c3c3c' },
  outline: { background: 'transparent', color: '#fff', textShadow: '-2px -2px 0 #000,2px -2px 0 #000,-2px 2px 0 #000,2px 2px 0 #000' },
};

export async function renderExport(view, p) {
  const e = p.export;
  const left = h('div', { class: 'card' });
  const right = h('div', { class: 'stack' });
  view.replaceChildren(
    h('div', { class: 'page-h' }, h('div', {}, h('h1', {}, 'Xuất video'),
      h('p', {}, 'Bàn tay vẽ từng vùng theo lời đọc, kèm phụ đề và nhạc nền. Cảnh không đổi sẽ được dùng lại, không render lại.'))),
    h('div', { class: 'export-grid' }, left, right));

  const set = (fn) => { fn(); markDirty(); };
  const subDemo = h('div', { class: 'sub-demo' });
  const drawDemo = () => {
    const s = e.subtitles;
    const pos = s.position === 'top' ? { top: '10px' } : s.position === 'middle' ? { top: '34px' } : { bottom: '10px' };
    subDemo.replaceChildren(s.enabled ? h('div', { class: 's', style: { ...SUB_STYLES[s.style], ...pos, fontSize: `${13 * s.size / 100}px` } }, 'Phụ đề hiển thị như thế này') : h('div', { class: 'muted small', style: { padding: '36px', textAlign: 'center' } }, 'Không hiện phụ đề'));
  };
  drawDemo();

  const music = h('div', { class: 'stack', style: { gap: '8px' } });
  const drawMusic = async () => {
    const lib = (await api.get('/music')).files;
    music.replaceChildren(
      h('div', { class: 'row' },
        h('span', { class: `tag ${e.bgm.file ? 'blue' : ''}` }, icon('music'), e.bgm.file ? e.bgm.file.split('/').pop() : 'Không có nhạc nền'),
        e.bgm.file ? h('button', { class: 'btn xs ghost', onclick: () => { set(() => { e.bgm.file = ''; }); drawMusic(); } }, 'Bỏ') : null),
      h('div', { class: 'btn-row' },
        h('button', {
          class: 'btn sm', onclick: async () => {
            const f = await pickFile('audio/*'); if (!f) return;
            await flush();
            const np = await api.upload(`/projects/${p.id}/bgm`, f, f.name, { 'X-Library': '1' });
            e.bgm.file = np.export.bgm.file; drawMusic(); toast('Đã thêm nhạc nền (và lưu vào thư viện)', 'ok');
          },
        }, icon('upload'), 'Tải nhạc lên'),
        lib.length ? h('select', {
          class: 'input', style: { width: 'auto', height: '30px', padding: '0 8px' }, onchange: async (ev) => {
            if (!ev.target.value) return; await flush();
            const np = await api.post(`/projects/${p.id}/bgm/library`, { name: ev.target.value });
            e.bgm.file = np.export.bgm.file; drawMusic();
          },
        }, h('option', { value: '' }, `Thư viện (${lib.length})`), lib.map((x) => h('option', { value: x }, x))) : null),
      h('div', { class: 'muted small' }, 'Nguồn nhạc miễn phí bản quyền: YouTube Audio Library, Pixabay Music, Free Music Archive. Đặt sẵn file vào thư mục data/music để dùng lại.'));
  };
  drawMusic();
  const volLbl = h('b', {}, `${e.bgm.volume}%`);

  left.replaceChildren(
    h('div', { class: 'opt-group' }, h('h4', {}, icon('film'), 'Hình ảnh'),
      h('div', { class: 'opt-row' }, h('span', {}, 'Chất lượng'), seg([['480', '480p nháp'], ['720', '720p HD'], ['1080', '1080p Full HD']], String(e.quality), (v) => set(() => { e.quality = v; }))),
      h('div', { class: 'opt-row' }, h('span', {}, 'Khung hình/giây'), seg([[24, '24'], [30, '30'], [60, '60 mượt']], +e.fps, (v) => set(() => { e.fps = v; }))),
      h('div', { class: 'opt-row' }, h('span', {}, 'Đường bút'), seg([['grid', 'Lưới (ổn định)'], ['skeleton', 'Bám nét (tự nhiên)']], e.inkPath, (v) => set(() => { e.inkPath = v; }))),
      h('div', { class: 'opt-row' }, h('span', {}, 'Kiểu tô màu'), seg([['contour-wipe', 'Quét theo viền'], ['brush', 'Cọ theo nét']], e.colorFill, (v) => set(() => { e.colorFill = v; }))),
      h('div', { class: 'opt-row' }, h('span', {}, 'Hiện bàn tay'), h('div', { class: 'row' }, toggle(e.hand, (v) => set(() => { e.hand = v; })), h('span', { class: 'muted small' }, 'Đổi kiểu tay trong Cài đặt')))),
    h('div', { class: 'opt-group' }, h('h4', {}, icon('text'), 'Phụ đề (chèn vào hình)'),
      h('div', { class: 'opt-row' }, h('span', {}, 'Bật phụ đề'), toggle(e.subtitles.enabled, (v) => { set(() => { e.subtitles.enabled = v; }); drawDemo(); })),
      h('div', { class: 'opt-row' }, h('span', {}, 'Kiểu'), seg([['box', 'Hộp tối'], ['paper', 'Giấy'], ['outline', 'Viền chữ']], e.subtitles.style, (v) => { set(() => { e.subtitles.style = v; }); drawDemo(); })),
      h('div', { class: 'opt-row' }, h('span', {}, 'Vị trí'), seg([['bottom', 'Dưới'], ['middle', 'Giữa'], ['top', 'Trên']], e.subtitles.position, (v) => { set(() => { e.subtitles.position = v; }); drawDemo(); })),
      h('div', { class: 'opt-row' }, h('span', {}, 'Cỡ chữ'), h('input', { type: 'range', min: 60, max: 160, step: 5, value: e.subtitles.size, oninput: (ev) => { e.subtitles.size = +ev.target.value; markDirty(); drawDemo(); } })),
      subDemo),
    h('div', { class: 'opt-group' }, h('h4', {}, icon('music'), 'Âm thanh'),
      music,
      h('div', { class: 'opt-row', style: { marginTop: '10px' } }, h('span', {}, h('span', {}, 'Âm lượng nhạc '), volLbl),
        h('input', { type: 'range', min: 0, max: 60, value: e.bgm.volume, oninput: (ev) => { e.bgm.volume = +ev.target.value; volLbl.textContent = `${e.bgm.volume}%`; markDirty(); } })),
      h('div', { class: 'opt-row' }, h('span', {}, 'Tự hạ nhạc khi đọc'), toggle(e.bgm.duck, (v) => set(() => { e.bgm.duck = v; })))),
    h('div', { class: 'opt-group' }, h('h4', {}, 'Nhịp cảnh'),
      h('div', { class: 'grid2' },
        h('label', { class: 'field' }, 'Chờ trước câu đầu (ms)', h('input', { class: 'input', type: 'number', value: e.leadMs, min: 0, step: 100, oninput: (ev) => { e.leadMs = +ev.target.value; markDirty(); } })),
        h('label', { class: 'field' }, 'Dừng sau câu cuối (ms)', h('input', { class: 'input', type: 'number', value: e.tailMs, min: 500, step: 100, oninput: (ev) => { e.tailMs = +ev.target.value; markDirty(); } })))),
  );

  // ── cột phải: kiểm tra + nút xuất + kết quả ──
  const lines = p.scenes.flatMap((s) => s.lines);
  const noImg = p.scenes.filter((s) => !s.image);
  const voiced = lines.filter((l) => l.audio && l.audio.file).length;
  const unassigned = p.scenes.reduce((a, s) => a + s.lines.filter((l) => !(s.elements || []).some((e2) => (e2.lineIds || []).includes(l.id))).length, 0);
  const ff = !!(S.system && S.system.ffmpeg);
  const ci = (kind) => h('span', { class: `ci ${kind}` }, icon(kind === 'ok' ? 'check' : kind === 'bad' ? 'x' : 'alert'));
  const tl = await api.get(`/projects/${p.id}/timeline`);
  const canRender = ff && p.scenes.length && !noImg.length;
  const prog = h('div', { class: 'progress' }, h('div'));
  const msg = h('div', { class: 'small muted' });
  const log = h('div', { class: 'log', style: { display: 'none' } });
  const running = h('div', { class: 'stack', style: { display: 'none' } }, prog, msg, log);
  const results = h('div', { class: 'stack' });
  const btn = h('button', { class: 'btn accent lg', style: { width: '100%' }, disabled: !canRender, onclick: () => start() }, icon('film'), 'Xuất video');

  const start = async (job) => {
    await flush();
    btn.disabled = true; running.style.display = 'flex'; log.style.display = 'block';
    try {
      job = job || await api.post(`/projects/${p.id}/render`);
      await watchJob(job, (j) => {
        prog.firstChild.style.width = (j.progress * 100) + '%';
        msg.textContent = `${Math.round(j.progress * 100)}% · ${j.message}`;
        log.textContent = j.log.join('\n'); log.scrollTop = log.scrollHeight;
      });
      Object.assign(p, await reloadProject());
      toast('Xuất video xong!', 'ok'); refreshChrome(); drawResults(true);
    } catch (err) { toast(err.message, 'err'); msg.textContent = 'Lỗi: ' + err.message; }
    btn.disabled = !canRender;
  };

  const drawResults = (autoplay = false) => {
    const rs = p.renders || [];
    results.replaceChildren(...(rs.length ? rs.map((r, i) => i === 0
      ? h('div', { class: 'card' },
        h('video', { class: 'player', src: fileUrl(p.id, r.file), controls: true, autoplay, style: { borderRadius: '12px 12px 0 0' } }),
        h('div', { class: 'card-b stack', style: { gap: '10px' } },
          h('div', { class: 'row' }, h('b', {}, r.file.split('/').pop()), h('div', { style: { flex: 1 } }), h('span', { class: 'tag' }, `${r.w}×${r.h}`), h('span', { class: 'tag' }, fmtMs(r.durationMs)), h('span', { class: 'tag' }, `${(r.size / 1048576).toFixed(1)} MB`)),
          h('div', { class: 'btn-row' },
            h('a', { class: 'btn sm primary', href: fileUrl(p.id, r.file) + '?download=1' }, icon('download'), 'MP4'),
            h('a', { class: 'btn sm', href: fileUrl(p.id, r.srt) + '?download=1' }, 'Phụ đề .srt'),
            h('button', { class: 'btn sm', onclick: () => showDesc(p, r) }, 'Mô tả & chương'),
            r.thumb ? h('a', { class: 'btn sm', href: fileUrl(p.id, r.thumb) + '?download=1' }, icon('image'), 'Ảnh bìa') : null,
            h('button', { class: 'btn sm ghost', onclick: () => api.post(`/projects/${p.id}/open`, { sub: 'exports' }).then((x) => toast('Mở thư mục: ' + x.path)) }, icon('folder'), 'Mở thư mục'))))
      : h('div', { class: 'render-item' },
        h('video', { src: fileUrl(p.id, r.file) + '#t=1', preload: 'metadata' }),
        h('div', { style: { flex: 1, minWidth: 0 } }, h('div', { class: 'small', style: { fontWeight: 700, overflow: 'hidden', textOverflow: 'ellipsis' } }, r.file.split('/').pop()),
          h('div', { class: 'muted small' }, `${timeAgo(r.created)} · ${fmtMs(r.durationMs)} · ${r.quality}p`)),
        h('a', { class: 'btn xs', href: fileUrl(p.id, r.file) + '?download=1' }, icon('download')),
        h('button', { class: 'btn xs ghost danger', onclick: async () => { if (await confirmBox('Xoá bản xuất này?', 'Xoá', true)) { Object.assign(p, await api.del(`/projects/${p.id}/renders/${i}`)); drawResults(); } } }, icon('trash'))))
      : [h('div', { class: 'card empty' }, icon('film'), h('h3', {}, 'Chưa có video nào'), 'Bấm "Xuất video" để tạo bản đầu tiên.')]));
  };

  right.replaceChildren(
    h('div', { class: 'card' },
      h('div', { class: 'card-h' }, h('h3', {}, 'Sẵn sàng xuất?'), h('div', { style: { flex: 1 } }), h('span', { class: 'tag' }, `≈ ${fmtMs(tl.totalMs)}`)),
      h('div', { class: 'card-b' },
        h('div', { class: 'check' }, ci(p.scenes.length ? 'ok' : 'bad'), `${p.scenes.length} cảnh, ${lines.length} câu thoại`),
        h('div', { class: 'check' }, ci(noImg.length ? 'bad' : 'ok'), noImg.length ? `${noImg.length} cảnh chưa có ảnh` : 'Tất cả cảnh đã có ảnh'),
        h('div', { class: 'check' }, ci(voiced === lines.length ? 'ok' : 'warn'), voiced === lines.length ? 'Tất cả câu đã có giọng đọc' : `${lines.length - voiced} câu chưa có giọng (sẽ im lặng theo thời lượng ước tính)`),
        h('div', { class: 'check' }, ci(unassigned ? 'warn' : 'ok'), unassigned ? `${unassigned} câu chưa gán vùng vẽ (không vẽ gì khi đọc)` : 'Mọi câu đều có vùng vẽ'),
        h('div', { class: 'check' }, ci(ff ? 'ok' : 'bad'), ff ? 'ffmpeg sẵn sàng' : 'Thiếu ffmpeg: pip install imageio-ffmpeg'),
        h('div', { class: 'divider' }),
        btn, h('div', { style: { height: '10px' } }), running)),
    h('div', { class: 'row' }, h('h3', { style: { margin: '6px 0 0', fontSize: '15px' } }, 'Bản đã xuất'), h('div', { style: { flex: 1 } }),
      h('button', { class: 'btn sm', onclick: () => makeThumb(p) }, icon('image'), 'Tạo ảnh bìa')),
    results,
  );
  drawResults();
  const { jobs } = await api.get(`/projects/${p.id}/jobs`);
  const active = jobs.find((j) => j.kind === 'render');
  if (active) start(active);
}

async function showDesc(p, r) {
  const text = await (await fetch(fileUrl(p.id, r.desc) + '?t=' + Date.now())).text();
  const ta = h('textarea', { class: 'input', rows: 14, value: text });
  modal({
    title: 'Mô tả video & mốc chương (YouTube)', wide: true,
    body: h('div', { class: 'modal-b' }, ta),
    footer: [h('button', { class: 'btn primary', onclick: async () => { await navigator.clipboard.writeText(ta.value); toast('Đã chép', 'ok'); } }, icon('copy'), 'Chép')],
  });
}

async function makeThumb(p) {
  const st = { title: p.name, scene: 0 };
  const img = h('img', { style: { width: '100%', borderRadius: '10px', border: '1px solid var(--line)', display: 'none' } });
  const gen = async () => {
    const r = await api.post(`/projects/${p.id}/thumbnail`, st);
    img.src = fileUrl(p.id, r.file, Date.now()); img.style.display = 'block'; dl.href = fileUrl(p.id, r.file) + '?download=1'; dl.style.display = '';
  };
  const dl = h('a', { class: 'btn primary', style: { display: 'none' } }, icon('download'), 'Tải ảnh bìa');
  modal({
    title: 'Tạo ảnh bìa', wide: true,
    body: h('div', { class: 'modal-b stack' },
      h('div', { class: 'grid2' },
        h('label', { class: 'field' }, 'Tiêu đề trên ảnh', h('input', { class: 'input', value: st.title, oninput: (e) => { st.title = e.target.value; } })),
        h('label', { class: 'field' }, 'Ảnh nền từ cảnh', h('select', { class: 'input', onchange: (e) => { st.scene = +e.target.value; } },
          p.scenes.map((s, i) => h('option', { value: i }, `${i + 1}. ${s.title}`))))),
      img),
    footer: [h('button', { class: 'btn', onclick: gen }, icon('refresh'), 'Tạo / cập nhật'), dl],
  });
  gen();
}
