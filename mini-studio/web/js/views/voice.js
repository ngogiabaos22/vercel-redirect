// Bước 3 – Giọng đọc
import { api, fileUrl, watchJob } from '../api.js';
import { h, icon, toast, fmtSec, fmtMs, pickFile, confirmBox } from '../ui.js';
import { S, markDirty, flush, reloadProject, refreshChrome, refreshSystem } from '../app.js';
import { openSettings } from './settings.js';

const ENGINE_ICON = { piper: '🗣️', edge: '☁️', espeak: '🤖', record: '🎙️', estimate: '⏱️' };
let player = null;
let recorder = null;

export async function renderVoice(view, p) {
  const sys = S.system || (await refreshSystem());
  const engines = sys.tts;
  const left = h('div', { class: 'stack' });
  const right = h('div', {});
  view.replaceChildren(
    h('div', { class: 'page-h' }, h('div', {}, h('h1', {}, 'Giọng đọc'),
      h('p', {}, 'Độ dài giọng đọc quyết định nhịp vẽ: mỗi vùng được vẽ đúng lúc câu tương ứng được đọc.')),
      h('div', { class: 'spacer' }),
      h('a', { class: 'btn', href: `/api/projects/${p.id}/srt` }, icon('download'), 'Tải phụ đề .srt'),
      h('a', { class: 'btn primary', href: `#/p/${p.id}/export` }, 'Sang Xuất video', icon('arrow'))),
    h('div', { class: 'voice-grid' }, left, right));

  const drawLeft = () => {
    const v = p.voice;
    const eng = engines[v.engine] || engines.estimate;
    const voiceSel = eng.voices && eng.voices.length ? h('select', { class: 'input', onchange: (e) => { v.voice = e.target.value; markDirty(); } },
      eng.voices.map((x) => h('option', { value: x.id, selected: x.id === v.voice }, x.label))) : null;
    if (voiceSel && !eng.voices.find((x) => x.id === v.voice)) { v.voice = eng.voices[0].id; markDirty(); }
    const speedLbl = h('b', {}, `${(+v.speed).toFixed(2)}×`);
    const gapLbl = h('b', {}, `${v.gapMs} ms`);
    const busy = h('div', { class: 'stack', style: { display: 'none' } }, h('div', { class: 'progress' }, h('div')), h('div', { class: 'muted small' }));
    const gen = async (force) => {
      await flush();
      busy.style.display = 'flex';
      try {
        const job = await api.post(`/projects/${p.id}/tts`, { force });
        const j = await watchJob(job, (x) => { busy.firstChild.firstChild.style.width = (x.progress * 100) + '%'; busy.lastChild.textContent = x.message; });
        const np = await reloadProject(); Object.assign(p, np);
        toast(j.result.count ? `Đã tạo giọng cho ${j.result.count} câu` : 'Không có câu nào cần tạo', 'ok');
        refreshChrome(); drawRight();
      } catch (e) { toast(e.message, 'err'); }
      busy.style.display = 'none';
    };
    const auto = !['estimate', 'record'].includes(v.engine);
    left.replaceChildren(
      h('div', { class: 'card' },
        h('div', { class: 'card-h' }, h('h3', {}, 'Nguồn giọng')),
        h('div', { class: 'card-b stack', style: { gap: '8px' } }, Object.entries(engines).map(([k, e]) => h('div', {
          class: `engine ${v.engine === k ? 'on' : ''} ${e.available ? '' : 'na'}`,
          onclick: () => { v.engine = k; v.voice = (e.voices && e.voices[0] && e.voices[0].id) || ''; markDirty(); drawLeft(); },
        }, h('div', { class: 'ei' }, ENGINE_ICON[k] || '•'),
          h('div', { style: { flex: 1 } }, h('b', {}, e.label),
            h('span', {}, e.available ? (e.offline ? 'Chạy offline' : 'Miễn phí, cần mạng') : `Chưa cài: ${e.install || ''}`)),
          v.engine === k ? h('span', { class: 'tag accent' }, 'Đang dùng') : null)))),
      h('div', { class: 'card' },
        h('div', { class: 'card-h' }, h('h3', {}, 'Thiết lập')),
        h('div', { class: 'card-b stack' },
          v.engine === 'piper' && !(eng.voices || []).length ? h('div', { class: 'tag amber', style: { height: 'auto', padding: '8px 10px', whiteSpace: 'normal' } },
            'Chưa có giọng Piper nào. ', h('a', { href: '#', onclick: (e) => { e.preventDefault(); openSettings('voices'); } }, 'Tải giọng tiếng Việt (~60 MB)')) : null,
          voiceSel ? h('label', { class: 'field' }, 'Giọng', voiceSel) : null,
          auto ? h('label', { class: 'field' }, h('span', {}, 'Tốc độ đọc ', speedLbl),
            h('input', { type: 'range', min: .7, max: 1.4, step: .05, value: v.speed, oninput: (e) => { v.speed = +e.target.value; speedLbl.textContent = `${v.speed.toFixed(2)}×`; markDirty(); } })) : null,
          h('label', { class: 'field' }, h('span', {}, 'Nghỉ giữa các câu ', gapLbl),
            h('input', { type: 'range', min: 0, max: 1200, step: 50, value: v.gapMs, oninput: (e) => { v.gapMs = +e.target.value; gapLbl.textContent = `${v.gapMs} ms`; markDirty(); } })),
          auto ? h('div', { class: 'btn-row' },
            h('button', { class: 'btn accent', onclick: () => gen(false), disabled: !eng.available }, icon('sparkle'), 'Tạo giọng các câu còn thiếu'),
            h('button', { class: 'btn', onclick: async () => { if (await confirmBox('Tạo lại giọng cho TẤT CẢ câu (kể cả câu đã thu âm)?', 'Tạo lại')) gen(true); }, disabled: !eng.available }, icon('refresh'), 'Tạo lại tất cả'))
            : h('div', { class: 'muted small' }, v.engine === 'record'
              ? 'Bấm 🎙 ở từng câu để thu âm bằng micro, hoặc ⤒ để tải file ghi âm có sẵn (mp3, wav, m4a…). App tự cắt khoảng lặng đầu/cuối.'
              : 'Chế độ nháp: thời lượng mỗi câu được ước tính theo tốc độ đọc trong Cài đặt. Dùng để dựng storyboard trước khi có giọng.'),
          busy)),
    );
  };

  const drawRight = () => {
    const lines = p.scenes.flatMap((s) => s.lines);
    const voiced = lines.filter((l) => l.audio && l.audio.file).length;
    const total = p.scenes.reduce((a, s) => a + s.lines.reduce((b, l) => b + ((l.audio && l.audio.durationMs) || 0), 0), 0);
    right.replaceChildren(
      h('div', { class: 'row', style: { marginBottom: '12px' } },
        h('span', { class: `tag ${voiced === lines.length ? 'green' : 'amber'}` }, `${voiced}/${lines.length} câu có giọng`),
        h('span', { class: 'tag' }, `Tổng giọng ${fmtMs(total)}`)),
      ...p.scenes.map((sc, si) => h('div', { class: 'vscene' },
        h('div', { class: 'vscene-h' }, h('h3', {}, `${si + 1}. ${sc.title}`),
          h('button', { class: 'btn xs', onclick: () => playScene(p, sc) }, icon('play'), 'Nghe cả cảnh')),
        h('div', { class: 'card' }, sc.lines.map((ln, i) => lineRow(p, ln, i, drawRight))))),
    );
  };
  drawLeft(); drawRight();
  S.cleanup.push(() => { if (player) player.pause(); if (recorder && recorder.state === 'recording') recorder.stop(); });
}

function lineRow(p, ln, i, redraw) {
  const a = ln.audio;
  const has = a && a.file;
  const row = h('div', { class: 'vline', id: 'ln-' + ln.id },
    h('span', { class: 'ix' }, i + 1),
    h('div', { class: 'tx' }, ln.text, has && a.engine ? h('span', { class: 'muted small' }, `  · ${a.engine === 'record' ? 'thu âm' : a.engine}`) : null),
    h('div', { class: `du ${has ? '' : 'est'}` }, has ? fmtSec(a.durationMs) : 'chưa có'),
    h('div', { class: 'btn-row', style: { gap: '4px' } },
      h('button', { class: 'btn ghost xs', title: 'Nghe', disabled: !has, onclick: () => play(fileUrl(p.id, a.file), row) }, icon('play')),
      h('button', {
        class: 'btn ghost xs', title: 'Tạo lại câu này', disabled: ['estimate', 'record'].includes(p.voice.engine), onclick: async () => {
          await flush();
          row.querySelector('.du').replaceChildren(h('span', { class: 'wave' }, h('i'), h('i'), h('i'), h('i')));
          try {
            await watchJob(await api.post(`/projects/${p.id}/tts`, { lineIds: [ln.id], force: true }));
            Object.assign(p, await reloadProject()); redraw();
          } catch (e) { toast(e.message, 'err'); redraw(); }
        },
      }, icon('refresh')),
      h('button', { class: 'btn ghost xs', title: 'Thu âm', onclick: () => record(p, ln, row, redraw) }, icon('mic')),
      h('button', {
        class: 'btn ghost xs', title: 'Tải file âm thanh', onclick: async () => {
          const f = await pickFile('audio/*,video/*'); if (!f) return;
          await flush();
          try { Object.assign(p, await api.upload(`/projects/${p.id}/lines/${ln.id}/audio`, f)); redraw(); refreshChrome(); } catch (e) { toast(e.message, 'err'); }
        },
      }, icon('upload')),
      h('button', {
        class: 'btn ghost xs danger', title: 'Xoá giọng', disabled: !has, onclick: async () => {
          await flush(); Object.assign(p, await api.del(`/projects/${p.id}/lines/${ln.id}/audio`)); redraw(); refreshChrome();
        },
      }, icon('trash'))));
  return row;
}

function play(url, row) {
  if (player) { player.pause(); document.querySelectorAll('.vline.playing').forEach((x) => x.classList.remove('playing')); }
  player = new Audio(url + '?t=' + Date.now());
  row && row.classList.add('playing');
  player.onended = () => row && row.classList.remove('playing');
  player.play();
}
async function playScene(p, sc) {
  await flush();
  const d = await api.get(`/projects/${p.id}/scenes/${sc.id}/audio`);
  if (!d.hasAudio) return toast('Cảnh này chưa có giọng đọc', 'err');
  play(fileUrl(p.id, d.file, d.v));
}

async function record(p, ln, row, redraw) {
  if (recorder && recorder.state === 'recording') { recorder.stop(); return; }
  let stream;
  try { stream = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true } }); }
  catch (e) { return toast('Không mở được micro: ' + e.message, 'err'); }
  const chunks = [];
  recorder = new MediaRecorder(stream);
  recorder.ondataavailable = (e) => chunks.push(e.data);
  recorder.onstop = async () => {
    stream.getTracks().forEach((t) => t.stop());
    row.classList.remove('rec');
    const blob = new Blob(chunks, { type: recorder.mimeType || 'audio/webm' });
    await flush();
    try { Object.assign(p, await api.upload(`/projects/${p.id}/lines/${ln.id}/audio`, blob, 'rec.webm')); toast('Đã lưu bản thu', 'ok'); refreshChrome(); }
    catch (e) { toast(e.message, 'err'); }
    redraw();
  };
  recorder.start();
  row.classList.add('rec');
  row.querySelector('.du').replaceChildren(h('span', { class: 'wave' }, h('i'), h('i'), h('i'), h('i')));
  toast('Đang thu… bấm 🎙 lần nữa để dừng');
}
