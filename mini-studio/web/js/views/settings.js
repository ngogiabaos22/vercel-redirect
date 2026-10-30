// Cài đặt: chung, nguồn ảnh, AI, giọng đọc, tự động, thông báo, hệ thống
import { api, watchJob } from '../api.js';
import { h, icon, modal, toast, pickFile, toggle } from '../ui.js';
import { S, refreshSystem } from '../app.js';

export async function openSettings(tab = 'general') {
  const [settings, sys] = await Promise.all([api.get('/settings'), refreshSystem()]);
  const body = h('div', { class: 'modal-b' });
  const tabs = h('div', { class: 'tabs' });
  const TABS = [['general', 'Chung'], ['images', 'Nguồn ảnh'], ['ai', 'AI viết'], ['voices', 'Giọng đọc'], ['auto', 'Tự động'], ['notify', 'Thông báo'], ['system', 'Hệ thống']];
  const save = async (patch) => {
    Object.assign(settings, patch);
    S.settings = await api.put('/settings', patch);
  };
  const show = (t) => {
    tab = t;
    tabs.replaceChildren(...TABS.map(([k, l]) => h('button', { class: k === t ? 'on' : '', onclick: () => show(k) }, l)));
    ({ general, images, voices, ai, auto, notify, system })[t]();
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
    const extra = h('div', { class: 'stack' });
    const LLM = {
      pollinations: ['Pollinations (miễn phí, không cần key)', 'Không cần cài gì. Dùng để viết kịch bản, chuốt văn và viết prompt ảnh tự động.'],
      ollama: ['Ollama trên máy (miễn phí, offline)', 'Cần cài Ollama và tải mô hình. Chạy hoàn toàn trên máy.'],
      gemini: ['Google Gemini API', 'Lấy key miễn phí tại aistudio.google.com. Mô hình gợi ý: gemini-2.5-flash.'],
      openai: ['API kiểu OpenAI', 'OpenAI, OpenRouter, Groq, DeepSeek… điền Base URL, key và tên mô hình.'],
    };
    const drawExtra = () => {
      const b = settings.llmBackend || 'pollinations';
      extra.replaceChildren(h('div', { class: 'muted small' }, LLM[b][1]),
        b === 'ollama' ? [dot(svc.ollama, 'Ollama'),
          h('div', { class: 'grid2' },
            h('label', { class: 'field' }, 'Địa chỉ', h('input', { class: 'input', value: settings.ollamaUrl, onchange: (e) => save({ ollamaUrl: e.target.value }) })),
            h('label', { class: 'field' }, 'Mô hình', models.length
              ? h('select', { class: 'input', onchange: (e) => save({ ollamaModel: e.target.value }) }, models.map((m) => h('option', { selected: m === settings.ollamaModel }, m)))
              : h('input', { class: 'input', value: settings.ollamaModel, onchange: (e) => save({ ollamaModel: e.target.value }) }))),
          h('div', { class: 'muted small' }, 'Gợi ý mô hình viết tiếng Việt tốt: qwen2.5:7b, gemma2:9b, llama3.1:8b. Tải bằng: ', h('code', {}, 'ollama pull qwen2.5:7b'))]
          : h('div', { class: 'grid3' },
            b !== 'gemini' ? h('label', { class: 'field' }, 'Base URL (để trống = mặc định)', h('input', { class: 'input', value: settings.llmBaseUrl, onchange: (e) => save({ llmBaseUrl: e.target.value.trim() }) })) : null,
            h('label', { class: 'field' }, b === 'pollinations' ? 'Token (tuỳ chọn)' : 'API key', h('input', { class: 'input', type: 'password', value: settings.llmApiKey, onchange: (e) => save({ llmApiKey: e.target.value.trim() }) })),
            h('label', { class: 'field' }, 'Mô hình (để trống = mặc định)', h('input', { class: 'input', value: settings.llmModel, onchange: (e) => save({ llmModel: e.target.value.trim() }) }))));
    };
    drawExtra();
    body.replaceChildren(h('div', { class: 'stack' },
      h('div', { class: 'muted' }, 'AI viết chữ dùng cho: viết kịch bản, chuốt văn nói, gợi ý tiêu đề và tự viết prompt ảnh cho từng cảnh khi chạy 1 chạm. Lỗi thì app vẫn chạy (dùng câu thoại làm prompt).'),
      h('label', { class: 'field' }, 'Dùng', h('select', { class: 'input', onchange: (e) => { save({ llmBackend: e.target.value }); drawExtra(); } },
        Object.entries(LLM).map(([k, [l]]) => h('option', { value: k, selected: (settings.llmBackend || 'pollinations') === k }, l)))),
      extra,
      h('button', {
        class: 'btn sm', style: { alignSelf: 'flex-start' }, onclick: async (e) => {
          e.target.disabled = true;
          try { const { text } = await api.post('/ai/hook', { text: 'Vì sao bầu trời có màu xanh' }); modal({ title: 'AI trả lời', body: h('div', { class: 'modal-b' }, h('pre', { style: { whiteSpace: 'pre-wrap', margin: 0 } }, text)) }); }
          catch (err) { toast(err.message, 'err'); }
          e.target.disabled = false;
        },
      }, icon('sparkle'), 'Thử AI')));
  }

  async function images() {
    const { types } = await api.get('/image/types');
    const list = h('div', { class: 'stack', style: { gap: '10px' } });
    const provs = (settings.imageProviders || []).map((x) => ({ ...x }));
    const persist = () => save({ imageProviders: provs });
    const FIELD = { apiKey: ['API key / token', 'password'], model: ['Mô hình', 'text'], baseUrl: ['Địa chỉ (Base URL)', 'text'], accountId: ['Account ID', 'text'], size: ['Kích thước (vd 1024x1536)', 'text'], extra: ['Thêm JSON', 'text'] };
    const draw = () => list.replaceChildren(...(provs.length ? provs.map((pv, i) => {
      const t = types[pv.type] || {};
      const out = h('div', { class: 'small muted' });
      const img = h('img', { style: { display: 'none', width: '120px', borderRadius: '8px', border: '1px solid var(--line)' } });
      return h('div', { class: `prov ${pv.enabled ? '' : 'off'}` },
        h('div', { class: 'row' },
          h('span', { class: 'prov-n' }, i + 1),
          toggle(pv.enabled, (v) => { pv.enabled = v; persist(); draw(); }),
          h('input', { class: 'input', style: { flex: 1, height: '30px' }, value: pv.name || t.label, onchange: (e) => { pv.name = e.target.value; persist(); } }),
          h('span', { class: 'tag' }, t.free || ''),
          h('button', { class: 'btn xs ghost', title: 'Ưu tiên cao hơn', disabled: i === 0, onclick: () => { provs.splice(i - 1, 0, provs.splice(i, 1)[0]); persist(); draw(); } }, '↑'),
          h('button', { class: 'btn xs ghost', title: 'Ưu tiên thấp hơn', disabled: i === provs.length - 1, onclick: () => { provs.splice(i + 1, 0, provs.splice(i, 1)[0]); persist(); draw(); } }, '↓'),
          h('button', { class: 'btn xs ghost danger', title: 'Xoá nguồn', onclick: () => { provs.splice(i, 1); persist(); draw(); } }, icon('trash'))),
        (t.fields || []).length ? h('div', { class: 'grid3' }, t.fields.map((f) => h('label', { class: 'field' }, FIELD[f][0],
          h('input', { class: 'input', type: FIELD[f][1], value: pv[f] || '', placeholder: t[f] || '', onchange: (e) => { pv[f] = e.target.value.trim(); persist(); } })))) : null,
        h('div', { class: 'row' }, h('span', { class: 'muted small', style: { flex: 1 } }, t.help || ''),
          h('button', {
            class: 'btn xs', onclick: async (e) => {
              e.target.disabled = true; out.textContent = 'Đang tạo ảnh thử…'; img.style.display = 'none';
              try { const r = await api.post('/image/test', { provider: pv }); img.src = r.image; img.style.display = 'block'; out.textContent = `OK sau ${r.seconds}s`; }
              catch (err) { out.textContent = 'Lỗi: ' + err.message; }
              e.target.disabled = false;
            },
          }, icon('sparkle'), 'Thử')),
        h('div', { class: 'row' }, img, out));
    }) : [h('div', { class: 'muted' }, 'Chưa có nguồn ảnh nào – thêm ít nhất một nguồn bên dưới.')]));
    draw();
    const addSel = h('select', { class: 'input', style: { width: 'auto' } }, Object.entries(types).map(([k, t]) => h('option', { value: k }, `${t.label} – ${t.free}`)));
    body.replaceChildren(h('div', { class: 'stack' },
      h('div', { class: 'muted' }, 'App thử lần lượt từ nguồn số 1 trở xuống: nguồn nào lỗi, hết hạn mức hay sai key thì tự chuyển sang nguồn sau. Kết hợp nhiều nguồn miễn phí để gần như không bao giờ bị dừng.'),
      list,
      h('div', { class: 'row' }, addSel, h('button', {
        class: 'btn sm primary', onclick: () => {
          const t = types[addSel.value];
          provs.push({ id: addSel.value + '-' + Date.now().toString(36), type: addSel.value, name: t.label, enabled: true, model: t.model || '', baseUrl: t.baseUrl || '', apiKey: '' });
          persist(); draw();
        },
      }, icon('plus'), 'Thêm nguồn')),
      h('div', { class: 'card', style: { boxShadow: 'none' } }, h('div', { class: 'card-b small stack', style: { gap: '4px' } },
        h('b', {}, 'Tạo ảnh miễn phí – gợi ý thứ tự'),
        h('div', {}, '1. Pollinations: miễn phí, không cần đăng ký (có sẵn).'),
        h('div', {}, '2. Cloudflare Workers AI: đăng ký miễn phí, khoảng vài trăm ảnh FLUX mỗi ngày.'),
        h('div', {}, '3. Gemini API (AI Studio) hoặc Hugging Face: key miễn phí, hạn mức nhỏ – làm nguồn dự phòng.'),
        h('div', {}, '4. Máy có GPU: Stable Diffusion WebUI / ComfyUI – miễn phí không giới hạn.'),
        h('div', { class: 'muted' }, 'Hạn mức miễn phí do từng nhà cung cấp quyết định và có thể thay đổi.')))));
  }

  function auto() {
    const num = (key, label, min, max, step = 1, hint = '') => h('label', { class: 'field' }, h('span', {}, label, hint ? h('span', { class: 'hint' }, ` (${hint})`) : null),
      h('input', { class: 'input', type: 'number', min, max, step, value: settings[key], onchange: (e) => save({ [key]: +e.target.value }) }));
    const voices = [['auto', 'Tự chọn (Edge → Piper → eSpeak)'], ['edge', 'Edge TTS (cần mạng, tự nhiên)'], ['piper', 'Piper (offline)'], ['espeak', 'eSpeak (offline, giọng máy)'], ['estimate', 'Không giọng (video im lặng)']];
    body.replaceChildren(h('div', { class: 'stack' },
      h('h4', { style: { margin: 0 } }, 'Tạo ảnh hàng loạt'),
      h('div', { class: 'grid2' },
        num('imageConcurrency', 'Số cảnh tạo cùng lúc', 1, 6, 1, '1–6; nguồn miễn phí nên để 1–2'),
        num('imageRetries', 'Số lần thử lại mỗi nguồn', 0, 5, 1, 'trước khi chuyển nguồn sau')),
      h('h4', { style: { margin: '6px 0 0' } }, 'Tách cảnh tự động'),
      num('autoSceneSeconds', 'Độ dài mỗi cảnh (giây)', 5, 40, 1, 'kịch bản không có ## sẽ tự chia theo độ dài này'),
      h('h4', { style: { margin: '6px 0 0' } }, 'Giọng đọc khi dự án chưa chọn giọng'),
      h('div', { class: 'grid2' },
        h('label', { class: 'field' }, 'Động cơ', h('select', { class: 'input', onchange: (e) => save({ autoVoiceEngine: e.target.value }) },
          voices.map(([k, l]) => h('option', { value: k, selected: settings.autoVoiceEngine === k }, l)))),
        h('label', { class: 'field' }, 'Giọng (để trống = giọng đầu tiên)', h('input', { class: 'input', value: settings.autoVoice, placeholder: 'vi-VN-HoaiMyNeural', onchange: (e) => save({ autoVoice: e.target.value.trim() }) }))),
      h('h4', { style: { margin: '6px 0 0' } }, 'Mã hoá video'),
      h('label', { class: 'field' }, h('span', {}, 'Bộ mã hoá ', h('span', { class: 'hint' }, `(đang dùng: ${sys.encoder})`)), h('select', { class: 'input', onchange: (e) => save({ videoEncoder: e.target.value }) },
        [['auto', 'Tự chọn GPU nếu có (NVIDIA → Intel → AMD)'], ['libx264', 'CPU (libx264)'], ['h264_nvenc', 'NVIDIA NVENC'], ['h264_qsv', 'Intel Quick Sync'], ['h264_amf', 'AMD AMF']]
          .map(([k, l]) => h('option', { value: k, selected: settings.videoEncoder === k }, l)))),
      h('div', { class: 'muted small' }, 'Chọn GPU mà máy không hỗ trợ thì app tự quay về CPU.')));
  }

  function notify() {
    const out = h('div', { class: 'small muted' });
    const chats = h('div', { class: 'row', style: { flexWrap: 'wrap', gap: '6px' } });
    const chatInp = h('input', { class: 'input', value: settings.telegramChatId, placeholder: 'vd 123456789 hoặc -100…', onchange: (e) => save({ telegramChatId: e.target.value.trim() }) });
    const tokenInp = h('input', { class: 'input', type: 'password', value: settings.telegramToken, placeholder: '123456:ABC-DEF…', onchange: (e) => save({ telegramToken: e.target.value.trim() }) });
    body.replaceChildren(h('div', { class: 'stack' },
      h('div', { class: 'kv' }, h('span', {}, h('b', {}, 'Gửi thông báo Telegram khi video xong')), toggle(settings.telegramEnabled, (v) => save({ telegramEnabled: v }))),
      h('div', { class: 'grid2' }, h('label', { class: 'field' }, 'Bot Token', tokenInp), h('label', { class: 'field' }, 'Chat ID', chatInp)),
      h('div', { class: 'btn-row' },
        h('button', {
          class: 'btn sm', onclick: async () => {
            out.textContent = 'Đang tìm…';
            try {
              const r = await api.post('/notify/chats', { token: tokenInp.value.trim() });
              out.textContent = r.chats.length ? 'Bấm để chọn:' : 'Chưa thấy tin nhắn nào – hãy nhắn một tin cho bot rồi thử lại.';
              chats.replaceChildren(...r.chats.map((c) => h('button', { class: 'btn xs', onclick: () => { chatInp.value = c.id; save({ telegramChatId: c.id }); toast('Đã chọn ' + (c.name || c.id), 'ok'); } }, `${c.name || '?'} (${c.id})`)));
            } catch (e) { out.textContent = 'Lỗi: ' + e.message; }
          },
        }, icon('scan'), 'Tìm Chat ID'),
        h('button', {
          class: 'btn sm primary', onclick: async () => {
            out.textContent = 'Đang gửi…';
            try { await api.post('/notify/test', { telegramToken: tokenInp.value.trim(), telegramChatId: chatInp.value.trim() }); out.textContent = 'Đã gửi tin thử – kiểm tra Telegram.'; }
            catch (e) { out.textContent = 'Lỗi: ' + e.message; }
          },
        }, 'Gửi thử')),
      out, chats,
      h('div', { class: 'kv' }, h('span', {}, 'Gửi kèm file video (≤ 50 MB, lớn hơn thì gửi ảnh bìa)'), toggle(settings.telegramSendVideo, (v) => save({ telegramSendVideo: v }))),
      h('div', { class: 'kv' }, h('span', {}, 'Báo cả khi tạo video lỗi'), toggle(settings.telegramOnError, (v) => save({ telegramOnError: v }))),
      h('div', { class: 'card', style: { boxShadow: 'none' } }, h('div', { class: 'card-b small stack', style: { gap: '4px' } },
        h('b', {}, 'Cách lấy Bot Token và Chat ID'),
        h('div', {}, '1. Mở Telegram, tìm @BotFather → gõ /newbot → đặt tên → nhận Bot Token.'),
        h('div', {}, '2. Mở bot vừa tạo, bấm Start và nhắn một tin bất kỳ (muốn báo vào nhóm: thêm bot vào nhóm và nhắn trong nhóm).'),
        h('div', {}, '3. Dán Token ở trên → bấm "Tìm Chat ID" → chọn → "Gửi thử".')))));
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
      row(sys.imageProviders.length > 0, 'Nguồn ảnh đang bật', sys.imageProviders.join(' → ')),
      row(true, 'Mã hoá video', sys.encoder),
      h('div', { class: 'muted small', style: { marginTop: '12px' } }, 'Dữ liệu dự án nằm trong thư mục data/projects cạnh app. Sao lưu = copy thư mục này.')));
  }

  modal({ title: 'Cài đặt', wide: true, body: h('div', {}, tabs, body), onClose: () => refreshSystem() });
  show(tab);
}
