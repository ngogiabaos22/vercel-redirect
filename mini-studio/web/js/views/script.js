// Bước 1 – Kịch bản
import { api, watchJob } from '../api.js';
import { h, icon, modal, toast, debounce, fmtMs, confirmBox } from '../ui.js';
import { S, markDirty, flush, go, refreshChrome } from '../app.js';

export async function renderScript(view, p) {
  const spm = (S.settings && S.settings.readingSpeed) || 230;
  const outline = h('div', { class: 'outline' });
  const stats = h('div', { class: 'row', style: { gap: '16px' } });
  const ta = h('textarea', {
    class: 'editor', spellcheck: false, value: p.script || '',
    placeholder: '## Cảnh 1: Mở đầu\n> mô tả hình minh hoạ\nCâu thoại thứ nhất. Câu thoại thứ hai.\n\n## Cảnh 2 …',
  });

  const updateOutline = debounce(async () => {
    let scenes = [];
    try { scenes = (await api.post('/parse', { script: ta.value })).scenes; } catch (e) { return; }
    const words = ta.value.replace(/^\s*[#>].*$/gm, '').split(/\s+/).filter(Boolean).length;
    const nLines = scenes.reduce((a, s) => a + s.lines.length, 0);
    const estMs = words * 60000 / spm + nLines * 250 + scenes.length * 1300;
    stats.replaceChildren(
      h('span', {}, h('b', {}, words), ' chữ'),
      h('span', {}, h('b', {}, scenes.length), ' cảnh'),
      h('span', {}, h('b', {}, nLines), ' câu'),
      h('span', {}, '≈ ', h('b', {}, fmtMs(estMs)), ' khi đọc'),
    );
    outline.replaceChildren(...(scenes.length ? scenes.map((s, i) => h('div', { class: 'oscene' },
      h('div', { class: 'oh' }, h('span', { class: 'n' }, i + 1), s.title,
        h('span', { class: 'spacer', style: { flex: 1 } }), h('span', { class: 'tag' }, `${s.lines.length} câu`)),
      s.imageNote ? h('div', { class: 'note' }, icon('image'), s.imageNote) : null,
      h('ol', {}, s.lines.map((l) => h('li', {}, l))))) : [h('div', { class: 'empty' }, 'Dàn cảnh sẽ hiện ở đây khi bạn gõ kịch bản.')]));
  }, 350);

  ta.addEventListener('input', () => { p.script = ta.value; markDirty(); updateOutline(); });
  ta.addEventListener('keydown', (e) => {
    if (e.key === 'Tab') { e.preventDefault(); document.execCommand('insertText', false, '  '); }
  });

  const doSplit = async () => {
    await flush();
    const had = p.scenes.length;
    const np = await api.post(`/projects/${p.id}/split`, { script: ta.value });
    Object.assign(p, np);
    toast(`Đã tách thành ${np.scenes.length} cảnh${had ? ' (giữ lại ảnh và giọng đã có)' : ''}`, 'ok');
    refreshChrome();
    go(`#/p/${p.id}/storyboard`);
  };

  const insertTemplate = async () => {
    const tpls = await api.get('/templates');
    const m = modal({
      title: 'Chèn mẫu kịch bản',
      body: h('div', { class: 'modal-b stack' }, Object.entries(tpls).filter(([k]) => k !== 'blank').map(([k, v]) =>
        h('button', {
          class: 'btn', style: { justifyContent: 'flex-start', height: '44px' }, onclick: async () => {
            if (ta.value.trim() && !(await confirmBox('Thay nội dung hiện tại bằng mẫu?', 'Thay'))) return;
            ta.value = v.script; ta.dispatchEvent(new Event('input')); m.close();
          },
        }, v.label))),
    });
  };

  view.replaceChildren(h('div', { class: 'script-wrap' },
    h('div', { class: 'card editor-card' },
      h('div', { class: 'editor-toolbar' },
        h('button', { class: 'btn sm', onclick: insertTemplate }, icon('text'), 'Mẫu'),
        h('button', { class: 'btn sm', onclick: () => aiWrite(ta) }, icon('sparkle'), 'Viết bằng AI'),
        h('button', { class: 'btn sm', onclick: () => aiRun('polish', ta, true) }, icon('wand'), 'Chuốt văn nói'),
        h('button', { class: 'btn sm', onclick: () => aiRun('hook', ta) }, 'Gợi ý câu mở đầu'),
        h('button', { class: 'btn sm', onclick: () => aiRun('titles', ta) }, 'Tiêu đề & hashtag'),
        h('div', { style: { flex: 1 } }),
        h('span', { class: 'muted small' }, 'AI chạy bằng Ollama trên máy (tuỳ chọn)')),
      ta,
      h('div', { class: 'editor-foot' }, stats, h('div', { style: { flex: 1 } }),
        h('button', { class: 'btn accent', onclick: doSplit }, icon('scissors'), 'Tách cảnh & sang Storyboard'))),
    h('div', { class: 'stack', style: { minHeight: 0, overflow: 'auto' } },
      h('div', { class: 'card' },
        h('div', { class: 'card-h' }, h('h3', {}, 'Dàn cảnh'), h('span', { class: 'sub' }, 'xem trước cách app tách')),
        h('div', { class: 'card-b' }, outline)),
      h('div', { class: 'card' },
        h('div', { class: 'card-h' }, h('h3', {}, 'Cú pháp nhanh')),
        h('div', { class: 'card-b syntax' },
          h('div', {}, h('code', {}, '## Tên cảnh'), ' bắt đầu một cảnh mới'),
          h('div', {}, h('code', {}, '> mô tả hình'), ' ghi chú hình minh hoạ (dùng làm prompt tạo ảnh)'),
          h('div', {}, 'Câu thoại tự tách theo dấu ', h('code', {}, '. ! ?'), '; câu dài tự cắt ở dấu phẩy'),
          h('div', {}, 'Không dùng ', h('code', {}, '##'), ' thì mỗi đoạn (cách 1 dòng trống) là một cảnh'),
          h('div', { class: 'divider' }),
          h('div', { class: 'muted' }, 'Mẹo: mỗi cảnh 2–4 câu, 1 ý chính, 15–30 giây. Mỗi câu nên ứng với một đối tượng trong hình để bàn tay vẽ đúng lúc nhắc tới.')))),
  ));
  updateOutline();
}

async function aiWrite(ta) {
  const st = { topic: '', kind: 'explainer', seconds: 60, audience: 'người xem phổ thông' };
  const topic = h('textarea', { class: 'input', rows: 3, placeholder: 'Ví dụ: Vì sao lá cây đổi màu vào mùa thu?', oninput: (e) => { st.topic = e.target.value; } });
  const run = async () => {
    if (!st.topic.trim()) return toast('Hãy nhập chủ đề', 'err');
    btn.disabled = true; btn.textContent = 'AI đang viết… (có thể mất 10–60 giây)';
    try {
      const { text } = await api.post('/ai/script', st);
      if (ta.value.trim() && !(await confirmBox('Thay kịch bản hiện tại bằng bản AI vừa viết?', 'Thay'))) {
        ta.value = ta.value + '\n\n' + text;
      } else ta.value = text;
      ta.dispatchEvent(new Event('input')); m.close();
    } catch (e) { toast(e.message, 'err'); btn.disabled = false; btn.textContent = 'Viết kịch bản'; }
  };
  const btn = h('button', { class: 'btn accent', onclick: run }, 'Viết kịch bản');
  const m = modal({
    title: 'Viết kịch bản bằng AI cục bộ',
    body: h('div', { class: 'modal-b stack' },
      h('label', { class: 'field' }, 'Chủ đề', topic),
      h('div', { class: 'grid2' },
        h('label', { class: 'field' }, 'Thể loại', h('select', { class: 'input', onchange: (e) => { st.kind = e.target.value; } },
          h('option', { value: 'explainer' }, 'Giải thích kiến thức'), h('option', { value: 'story' }, 'Kể chuyện'),
          h('option', { value: 'lesson' }, 'Bài giảng'), h('option', { value: 'short' }, 'Video ngắn MXH'))),
        h('label', { class: 'field' }, 'Độ dài (giây)', h('input', { class: 'input', type: 'number', value: 60, min: 15, max: 600, oninput: (e) => { st.seconds = +e.target.value; } }))),
      h('label', { class: 'field' }, 'Người xem', h('input', { class: 'input', value: st.audience, oninput: (e) => { st.audience = e.target.value; } })),
      h('div', { class: 'muted small' }, 'Cần cài Ollama (miễn phí) và tải một mô hình, ví dụ: ', h('code', {}, 'ollama pull qwen2.5:7b'), '. Đổi mô hình trong Cài đặt → AI.')),
    footer: [h('button', { class: 'btn', onclick: () => m.close() }, 'Huỷ'), btn],
  });
}

async function aiRun(task, ta, replace = false) {
  const t = toast('AI đang xử lý…');
  try {
    const { text } = await api.post('/ai/' + task, { text: ta.value });
    if (replace) {
      if (await confirmBox(h('div', {}, h('p', {}, 'Bản AI đề xuất:'), h('pre', { class: 'prompt-box', style: { maxHeight: '320px', whiteSpace: 'pre-wrap' } }, text)), 'Dùng bản này')) {
        ta.value = text; ta.dispatchEvent(new Event('input'));
      }
    } else {
      modal({ title: 'Gợi ý từ AI', body: h('div', { class: 'modal-b' }, h('pre', { style: { whiteSpace: 'pre-wrap', fontFamily: 'inherit', margin: 0, lineHeight: 1.6 } }, text)) });
    }
  } catch (e) { toast(e.message, 'err'); }
}
