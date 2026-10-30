// Tự động hoá: tạo video 1 chạm, hàng loạt nhiều kịch bản, preset
import { api, watchJob } from './api.js';
import { h, icon, modal, toast, debounce, fmtMs, seg } from './ui.js';
import { go, flush, reloadProject, refreshChrome, S } from './app.js';

const PIPE_STEPS = [[0.03, 'Tách cảnh'], [0.40, 'Tạo ảnh'], [0.55, 'Giọng đọc'], [1.0, 'Vẽ & ghép video']];

// Cửa sổ theo dõi luồng 1 chạm. Trả về job khi xong (ném lỗi nếu hỏng).
export function pipelinePanel(job, title = 'Đang tạo video') {
  const steps = h('div', { class: 'pipe-steps' });
  const bar = h('div', { class: 'progress' }, h('div'));
  const msg = h('div', { class: 'small muted' }, 'Đang bắt đầu…');
  const log = h('div', { class: 'log' });
  const drawSteps = (p, state) => steps.replaceChildren(...PIPE_STEPS.map(([end, label], i) => {
    const start = i ? PIPE_STEPS[i - 1][0] : 0;
    const st = state === 'done' || p >= end ? 'ok' : p >= start ? (state === 'error' ? 'bad' : 'run') : '';
    return h('div', { class: `pipe-step ${st}` }, h('span', { class: 'n' }, st === 'ok' ? icon('check') : String(i + 1)), label);
  }));
  drawSteps(0);
  const cancel = h('button', { class: 'btn', onclick: () => api.post(`/jobs/${job.id}/cancel`).then(() => toast('Đang huỷ…')) }, 'Huỷ');
  const hide = h('button', { class: 'btn primary', onclick: () => m.close() }, 'Chạy nền');
  const m = modal({
    title, wide: true,
    body: h('div', { class: 'modal-b stack' }, steps, bar, msg, log,
      h('div', { class: 'muted small' }, 'Có thể đóng cửa sổ này, video vẫn tiếp tục chạy nền. Bật Telegram trong Cài đặt để nhận thông báo khi xong.')),
    footer: [cancel, hide],
  });
  return watchJob(job, (j) => {
    bar.firstChild.style.width = (j.progress * 100) + '%';
    msg.textContent = `${Math.round(j.progress * 100)}% · ${j.message}`;
    log.textContent = j.log.join('\n'); log.scrollTop = log.scrollHeight;
    drawSteps(j.progress, j.state);
  }).then((j) => {
    cancel.remove(); hide.textContent = 'Xem video'; hide.onclick = () => { m.close(); go(`#/p/${j.project}/export`); };
    toast('Đã tạo xong video!', 'ok');
    return j;
  }).catch((e) => {
    msg.textContent = 'Lỗi: ' + e.message; cancel.remove(); hide.textContent = 'Đóng'; drawSteps(1, 'error');
    throw e;
  });
}

// Chạy trọn luồng cho dự án đang mở (dùng kịch bản hiện tại)
export async function autorunProject(p, script) {
  await flush();
  const job = await api.post(`/projects/${p.id}/autorun`, script !== undefined ? { script } : {});
  try {
    await pipelinePanel(job, `Tạo video 1 chạm · ${p.name}`);
  } catch (e) { toast(e.message, 'err'); }
  if (S.project && S.project.id === p.id) { Object.assign(p, await reloadProject()); refreshChrome(); }
}

async function presetSelect(st) {
  const { presets, default: def } = await api.get('/presets');
  st.preset = def || '';
  if (!presets.length) return null;
  return h('label', { class: 'field' }, 'Preset cài đặt', h('select', { class: 'input', onchange: (e) => { st.preset = e.target.value; } },
    h('option', { value: '' }, '(Mặc định của app)'), presets.map((n) => h('option', { value: n, selected: n === def }, n))));
}

const ASPECTS = [['9:16', 'Dọc 9:16 · TikTok'], ['16:9', 'Ngang 16:9 · YouTube'], ['1:1', 'Vuông 1:1'], ['4:5', '4:5 · Facebook']];

// Trang chủ: dán kịch bản → bấm một nút là ra video
export async function quickCreate() {
  const st = { name: '', aspect: '9:16', script: '', preset: '' };
  const stats = h('div', { class: 'row small muted', style: { gap: '14px' } }, 'Dán kịch bản để xem ước tính');
  const preview = debounce(async () => {
    if (!st.script.trim()) return stats.replaceChildren('Dán kịch bản để xem ước tính');
    const { scenes } = await api.post('/parse', { script: st.script });
    const words = st.script.split(/\s+/).filter(Boolean).length;
    const spm = (S.settings && S.settings.readingSpeed) || 230;
    stats.replaceChildren(h('span', {}, h('b', {}, words), ' từ'), h('span', {}, 'ước tính ', h('b', {}, fmtMs(words * 60000 / spm))),
      h('span', {}, h('b', {}, scenes.length), ' cảnh'), h('span', {}, h('b', {}, scenes.length), ' ảnh cần tạo'));
  }, 300);
  const ta = h('textarea', {
    class: 'input', rows: 12, placeholder: 'Dán kịch bản văn xuôi vào đây – app tự chia cảnh, tự viết prompt ảnh, tự tạo ảnh, giọng đọc và xuất video.\n\nMuốn tự chia cảnh: dùng "## Tên cảnh" và "> mô tả hình".',
    oninput: (e) => { st.script = e.target.value; preview(); },
  });
  const presetBox = await presetSelect(st);
  const run = h('button', {
    class: 'btn accent lg', onclick: async () => {
      if (!st.script.trim()) return toast('Hãy dán kịch bản', 'err');
      run.disabled = true;
      try {
        const r = await api.post('/quick', st);
        m.close();
        pipelinePanel(r.job, `Tạo video 1 chạm · ${r.project.name}`).catch((e) => toast(e.message, 'err'));
      } catch (e) { toast(e.message, 'err'); run.disabled = false; }
    },
  }, icon('sparkle'), 'Tạo video ngay');
  const m = modal({
    title: 'Tạo video 1 chạm', wide: true,
    body: h('div', { class: 'modal-b stack' },
      h('div', { class: 'grid2' },
        h('label', { class: 'field' }, 'Tên video (để trống = lấy câu đầu)', h('input', { class: 'input', oninput: (e) => { st.name = e.target.value; } })),
        h('div', { class: 'field' }, h('label', { class: 'field' }, 'Khung hình'), seg(ASPECTS.map(([a, l]) => [a, l.split(' · ')[0]]), st.aspect, (v) => { st.aspect = v; }))),
      presetBox, ta, stats,
      sysHints()),
    footer: [h('button', { class: 'btn', onclick: () => m.close() }, 'Huỷ'), run],
  });
  setTimeout(() => ta.focus(), 50);
}

function sysHints() {
  const s = S.system || {};
  const tts = s.tts || {};
  const voiceOk = (tts.edge && tts.edge.available) || (tts.piper && tts.piper.available && tts.piper.voices.length) || (tts.espeak && tts.espeak.available);
  const providers = s.imageProviders || [];
  return h('div', { class: 'auto-hints' },
    h('span', { class: `tag ${providers.length ? 'green' : 'red'}` }, providers.length ? `Ảnh: ${providers.join(' → ')}` : 'Chưa bật nguồn ảnh'),
    h('span', { class: `tag ${voiceOk ? 'green' : 'amber'}` }, voiceOk ? 'Có giọng đọc tự động' : 'Chưa có giọng tự động (video không tiếng)'),
    h('span', { class: 'tag' }, `Mã hoá: ${s.encoder || 'libx264'}`),
    S.settings && S.settings.telegramEnabled ? h('span', { class: 'tag blue' }, 'Báo Telegram khi xong') : null);
}

// Hàng loạt: nhiều kịch bản, ngăn cách bằng dòng ---
export async function batchCreate() {
  const st = { text: '', aspect: '9:16', preset: '' };
  const prev = h('div', { class: 'stack', style: { gap: '4px' } });
  const preview = debounce(async () => {
    const { items } = await api.post('/batch/preview', { text: st.text });
    prev.replaceChildren(items.length ? h('div', { class: 'small' }, h('b', {}, items.length), ' video sẽ được tạo:') : null,
      ...items.map((it, i) => h('div', { class: 'small muted' }, `${i + 1}. ${it.name} – ${it.scenes} cảnh`)));
  }, 400);
  const ta = h('textarea', {
    class: 'input', rows: 14, oninput: (e) => { st.text = e.target.value; preview(); },
    placeholder: 'Tiêu đề: Bài học từ người thợ gốm\nMột ngày nọ, người thợ gốm già hỏi học trò…\n---\nTiêu đề: Vì sao bầu trời màu xanh?\nBạn có bao giờ tự hỏi…\n---\n(mỗi kịch bản cách nhau bằng một dòng ---)',
  });
  const presetBox = await presetSelect(st);
  const run = h('button', {
    class: 'btn accent lg', onclick: async () => {
      run.disabled = true;
      try {
        const { items } = await api.post('/batch', st);
        m.close(); toast(`Đã xếp hàng ${items.length} video`, 'ok'); jobsPanel();
      } catch (e) { toast(e.message, 'err'); run.disabled = false; }
    },
  }, icon('film'), 'Bắt đầu hàng loạt');
  const m = modal({
    title: 'Tạo video hàng loạt', wide: true,
    body: h('div', { class: 'modal-b stack' },
      h('div', { class: 'grid2' },
        h('div', { class: 'field' }, h('label', { class: 'field' }, 'Khung hình'), seg(ASPECTS.map(([a, l]) => [a, l.split(' · ')[0]]), st.aspect, (v) => { st.aspect = v; })),
        presetBox || h('div')),
      ta, prev,
      h('div', { class: 'muted small' }, 'Các video chạy lần lượt (mỗi lần một video để máy không quá tải) – có thể để chạy qua đêm. Mỗi video xong sẽ báo Telegram nếu đã bật.')),
    footer: [h('button', { class: 'btn', onclick: () => m.close() }, 'Huỷ'), run],
  });
}

// Danh sách việc đang chạy / vừa xong
export function jobsPanel() {
  const list = h('div', { class: 'stack', style: { gap: '8px' } });
  let timer = null;
  const KIND = { pipeline: 'Video 1 chạm', render: 'Xuất video', images: 'Tạo ảnh', image: 'Tạo ảnh', tts: 'Giọng đọc', download: 'Tải giọng' };
  const tick = async () => {
    const { jobs } = await api.get('/jobs');
    list.replaceChildren(...(jobs.length ? jobs.map((j) => h('div', { class: 'job-row' },
      h('div', { class: 'row' }, h('b', {}, KIND[j.kind] || j.kind), h('span', { class: 'muted small' }, j.project || ''),
        h('div', { style: { flex: 1 } }),
        h('span', { class: `tag ${j.state === 'done' ? 'green' : j.state === 'error' ? 'red' : j.state === 'running' ? 'blue' : ''}` },
          { done: 'Xong', error: 'Lỗi', running: `${Math.round(j.progress * 100)}%`, queued: 'Đang chờ' }[j.state]),
        j.project && j.state === 'done' ? h('button', { class: 'btn xs', onclick: () => { m.close(); go(`#/p/${j.project}/export`); } }, 'Mở') : null,
        j.state === 'running' || j.state === 'queued' ? h('button', { class: 'btn xs ghost', onclick: () => api.post(`/jobs/${j.id}/cancel`) }, 'Huỷ') : null),
      h('div', { class: 'progress' }, h('div', { style: { width: (j.progress * 100) + '%' } })),
      h('div', { class: 'small muted', style: { whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' } }, j.state === 'error' ? j.error : j.message)))
      : [h('div', { class: 'muted' }, 'Chưa có việc nào.')]));
    timer = setTimeout(tick, 1500);
  };
  const m = modal({ title: 'Việc đang chạy', wide: true, body: h('div', { class: 'modal-b' }, list), onClose: () => clearTimeout(timer) });
  tick();
}

// Preset: lưu / áp dụng bộ cài đặt (khung hình, giọng, xuất video) cho dự án
export async function presetBar(p, onApplied) {
  const box = h('div', { class: 'row', style: { gap: '6px', flexWrap: 'wrap' } });
  const draw = async () => {
    const { presets, default: def } = await api.get('/presets');
    box.replaceChildren(
      presets.length ? h('select', {
        class: 'input', style: { width: 'auto', height: '30px', padding: '0 8px' }, onchange: async (e) => {
          if (!e.target.value) return;
          await flush();
          Object.assign(p, await api.post(`/projects/${p.id}/preset`, { name: e.target.value }));
          toast(`Đã áp dụng preset "${e.target.value}"`, 'ok'); onApplied && onApplied();
        },
      }, h('option', { value: '' }, `Áp dụng preset (${presets.length})`), presets.map((n) => h('option', { value: n }, n + (n === def ? ' ★' : '')))) : null,
      h('button', {
        class: 'btn sm', onclick: async () => {
          const name = prompt('Tên preset (khung hình + giọng + cài đặt xuất video hiện tại):', p.name);
          if (!name) return;
          await flush();
          const def2 = confirm('Dùng preset này làm mặc định cho dự án mới?');
          await api.post('/presets', { name, project: p.id, default: def2 });
          toast('Đã lưu preset', 'ok'); draw();
        },
      }, icon('dup'), 'Lưu preset'),
      presets.length ? h('button', {
        class: 'btn sm ghost', title: 'Xoá preset', onclick: async () => {
          const name = prompt('Gõ tên preset cần xoá:\n' + presets.join('\n'));
          if (name && presets.includes(name)) { await api.del('/presets/' + encodeURIComponent(name)); draw(); }
        },
      }, icon('trash')) : null);
  };
  draw();
  return box;
}
