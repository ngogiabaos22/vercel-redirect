// Cài đặt: chung, giọng đọc, AI cục bộ, hệ thống
import { api, watchJob } from '../api.js';
import { h, icon, modal, toast, pickFile } from '../ui.js';
import { S, refreshSystem } from '../app.js';

export async function openSettings(tab = 'general') {
  const [settings, sys] = await Promise.all([api.get('/settings'), refreshSystem()]);
  const body = h('div', { class: 'modal-b' });
  const tabs = h('div', { class: 'tabs' });
  const TABS = [['general', 'Chung'], ['voices', 'Giọng đọc'], ['ai', 'AI cục bộ'], ['system', 'Hệ thống']];
  const save = async (patch) => {
    Object.assign(settings, patch);
    S.settings = await api.put('/settings', patch);
  };
  const show = (t) => {
    tab = t;
    tabs.replaceChildren(...TABS.map(([k, l]) => h('button', { class: k === t ? 'on' : '', onclick: () => show(k) }, l)));
    ({ general, voices, ai, system })[t]();
  };

  async function general() {
    const { hands } = await api.get('/hands');
    const handsBox = h('div', { class: 'hands' });
    const drawHands = (list) => handsBox.replaceChildren(...list.map((n) => h('div', {
      class: `hand ${settings.hand === n ? 'on' : ''}`, onclick: async () => { await save({ hand: n }); drawHands(list); },
    }, h('img', { src: '/hands/' + n }), n.replace('.png', '').replace('drawing-hand', 'tay').slice(0, 16))),
    h('div', {
      class: 'hand', style: { justifyContent: 'center' }, onclick: async () => {
        const f = await pickFile('image/png'); if (!f) return;
        const r = await api.upload('/hands', f); settings.hand = r.hand; drawHands(r.hands); toast('Đã thêm bàn tay mới', 'ok');
      },
    }, icon('plus'), 'Tải PNG'));
    drawHands(hands);
    body.replaceChildren(h('div', { class: 'stack' },
      h('div', { class: 'grid2' },
        h('label', { class: 'field' }, 'Màu giấy nền', h('div', { class: 'row' },
          h('input', { type: 'color', value: settings.paperColor, onchange: (e) => save({ paperColor: e.target.value.toUpperCase() }) }),
          h('span', { class: 'muted small' }, 'Mặc định #F5EBD7 (giấy kem)'))),
        h('label', { class: 'field' }, h('span', {}, 'Tốc độ đọc ước tính ', h('span', { class: 'hint' }, '(âm tiết/phút, dùng khi chưa có giọng)')),
          h('input', { class: 'input', type: 'number', value: settings.readingSpeed, min: 120, max: 400, onchange: (e) => save({ readingSpeed: +e.target.value }) }))),
      h('label', { class: 'field' }, h('span', {}, 'Font phụ đề ', h('span', { class: 'hint' }, '(đường dẫn .ttf có dấu tiếng Việt; để trống = tự chọn)')),
        h('input', { class: 'input', value: settings.fontPath, placeholder: sys.font || 'C:\\Windows\\Fonts\\arialbd.ttf', onchange: (e) => save({ fontPath: e.target.value }) })),
      h('div', { class: 'field' }, h('label', { class: 'field' }, 'Bàn tay cầm bút'), handsBox,
        h('span', { class: 'muted small' }, 'Ảnh PNG nền trong suốt, đầu bút nằm ở góc trên-trái ảnh.'))));
  }

  async function voices() {
    const piper = sys.tts.piper;
    const rows = h('div', { class: 'stack', style: { gap: '6px' } });
    const draw = () => rows.replaceChildren(...piper.catalog.map((c) => {
      const prog = h('span', { class: 'muted small' });
      return h('div', { class: 'kv' }, h('span', {}, c.label, h('br'), h('span', { class: 'muted small' }, c.id)),
        c.installed ? h('span', { class: 'tag green' }, 'Đã tải') : h('div', { class: 'row' }, prog, h('button', {
          class: 'btn xs', onclick: async (e) => {
            e.target.disabled = true;
            try {
              const job = await api.post('/voices/download', { id: c.id });
              await watchJob(job, (j) => { prog.textContent = j.message; });
              c.installed = true; toast('Đã tải giọng ' + c.id, 'ok'); await refreshSystem(); draw();
            } catch (err) { toast(err.message, 'err'); e.target.disabled = false; }
          },
        }, icon('download'), 'Tải')));
    }));
    draw();
    body.replaceChildren(h('div', { class: 'stack' },
      h('div', { class: 'card', style: { boxShadow: 'none' } }, h('div', { class: 'card-b stack' },
        h('div', { class: 'row' }, h('b', {}, 'Piper TTS'), piper.available ? h('span', { class: 'tag green' }, 'Đã cài') : h('span', { class: 'tag red' }, 'Chưa cài')),
        piper.available ? null : h('div', { class: 'muted small' }, 'Cài bằng lệnh: ', h('code', {}, 'pip install piper-tts'), ' rồi khởi động lại Mini Studio.'),
        h('div', { class: 'muted small' }, 'Giọng tải về thư mục data/voices (khoảng 20–65 MB mỗi giọng). Sau khi tải, mọi thứ chạy offline.'),
        rows)),
      h('div', { class: 'kv' }, h('span', {}, h('b', {}, 'Edge TTS'), h('br'), h('span', { class: 'muted small' }, 'Giọng Hoài My / Nam Minh rất tự nhiên, miễn phí nhưng cần internet')),
        sys.tts.edge.available ? h('span', { class: 'tag green' }, 'Đã cài') : h('code', {}, 'pip install edge-tts')),
      h('div', { class: 'kv' }, h('span', {}, h('b', {}, 'eSpeak NG'), h('br'), h('span', { class: 'muted small' }, 'Offline, giọng máy, rất nhẹ')),
        sys.tts.espeak.available ? h('span', { class: 'tag green' }, 'Đã cài') : h('span', { class: 'muted small' }, 'Cài từ github.com/espeak-ng')),
      h('div', { class: 'kv' }, h('span', {}, h('b', {}, 'Tự thu âm'), h('br'), h('span', { class: 'muted small' }, 'Dùng micro ngay trong trình duyệt, hoặc tải file ghi âm')), h('span', { class: 'tag green' }, 'Luôn có'))));
  }

  async function ai() {
    const models = (await api.get('/ollama/models')).models;
    const svc = sys.services;
    const dot = (on, label) => h('div', { class: 'svc' }, h('span', { class: `d ${on ? 'on' : ''}` }), label, on ? ' – đang chạy' : ' – chưa chạy');
    body.replaceChildren(h('div', { class: 'stack' },
      h('h4', { style: { margin: 0 } }, 'Viết kịch bản – Ollama'),
      dot(svc.ollama, 'Ollama'),
      h('div', { class: 'grid2' },
        h('label', { class: 'field' }, 'Địa chỉ', h('input', { class: 'input', value: settings.ollamaUrl, onchange: (e) => save({ ollamaUrl: e.target.value }) })),
        h('label', { class: 'field' }, 'Mô hình', models.length
          ? h('select', { class: 'input', onchange: (e) => save({ ollamaModel: e.target.value }) }, models.map((m) => h('option', { selected: m === settings.ollamaModel }, m)))
          : h('input', { class: 'input', value: settings.ollamaModel, onchange: (e) => save({ ollamaModel: e.target.value }) }))),
      h('div', { class: 'muted small' }, 'Gợi ý mô hình viết tiếng Việt tốt: qwen2.5:7b, gemma2:9b, llama3.1:8b. Tải bằng: ', h('code', {}, 'ollama pull qwen2.5:7b')),
      h('div', { class: 'divider' }),
      h('h4', { style: { margin: 0 } }, 'Tạo ảnh – Stable Diffusion'),
      h('div', { class: 'row' }, dot(svc.a1111, 'WebUI / Forge'), dot(svc.comfy, 'ComfyUI')),
      h('label', { class: 'field' }, 'Dùng', h('select', { class: 'input', onchange: (e) => save({ imageBackend: e.target.value }) },
        h('option', { value: 'a1111', selected: settings.imageBackend !== 'comfy' }, 'Stable Diffusion WebUI / Forge (chạy với --api)'),
        h('option', { value: 'comfy', selected: settings.imageBackend === 'comfy' }, 'ComfyUI'))),
      h('div', { class: 'grid3' },
        h('label', { class: 'field' }, 'Địa chỉ WebUI', h('input', { class: 'input', value: settings.a1111Url, onchange: (e) => save({ a1111Url: e.target.value }) })),
        h('label', { class: 'field' }, 'Địa chỉ ComfyUI', h('input', { class: 'input', value: settings.comfyUrl, onchange: (e) => save({ comfyUrl: e.target.value }) })),
        h('label', { class: 'field' }, 'Checkpoint ComfyUI', h('input', { class: 'input', value: settings.comfyCheckpoint, placeholder: 'sd_xl_base_1.0.safetensors', onchange: (e) => save({ comfyCheckpoint: e.target.value }) }))),
      h('div', { class: 'muted small' }, 'Không có GPU? Dùng nút "Chép prompt" ở Storyboard rồi tạo ảnh bằng ChatGPT / Gemini / Copilot bản miễn phí.')));
  }

  function system() {
    const row = (ok, label, detail) => h('div', { class: 'kv' }, h('span', {}, label), h('span', { class: `tag ${ok ? 'green' : 'red'}`, title: detail || '' }, ok ? (detail ? detail.slice(-38) : 'OK') : 'Thiếu'));
    body.replaceChildren(h('div', { class: 'stack', style: { gap: 0 } },
      row(true, 'Mini Studio', sys.version),
      row(true, 'Python', sys.python + ' · ' + sys.platform),
      row(!!sys.ffmpeg, 'ffmpeg (ghép video/âm thanh)', sys.ffmpeg || ''),
      row(!!sys.font, 'Font phụ đề tiếng Việt', sys.font || ''),
      row(sys.tts.piper.available, 'Piper TTS'),
      row(sys.tts.edge.available, 'Edge TTS'),
      row(sys.services.ollama, 'Ollama'),
      row(sys.services.a1111 || sys.services.comfy, 'Stable Diffusion (WebUI / ComfyUI)'),
      h('div', { class: 'muted small', style: { marginTop: '12px' } }, 'Dữ liệu dự án nằm trong thư mục data/projects cạnh app. Sao lưu = copy thư mục này.')));
  }

  modal({ title: 'Cài đặt', wide: true, body: h('div', {}, tabs, body) });
  show(tab);
}
