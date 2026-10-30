// Trang Dự án: danh sách + tạo mới
import { api, fileUrl } from '../api.js';
import { h, icon, modal, toast, confirmBox, fmtMs, timeAgo } from '../ui.js';
import { go } from '../app.js';
import { quickCreate, batchCreate, jobsPanel } from '../auto.js';

const TIPS = [
  ['1', '#FDE8DA', '#E8590C', 'Kịch bản', 'Dán văn xuôi hoặc nhờ AI viết. App tự chia cảnh theo độ dài.'],
  ['2', '#E3ECFD', '#2563EB', 'Storyboard', 'Tự tạo ảnh từ nhiều nguồn AI (có nguồn miễn phí), tự khoanh vùng và gán câu thoại.'],
  ['3', '#E2F3E8', '#2F855A', 'Giọng đọc', 'Piper TTS offline, Edge TTS hoặc tự thu âm. Giọng quyết định nhịp vẽ.'],
  ['4', '#F1E8FD', '#7C3AED', 'Xuất video', 'Bàn tay vẽ theo lời đọc, phụ đề, nhạc nền, ảnh bìa, SRT – xong báo Telegram.'],
];

export async function renderHome(view) {
  const grid = h('div', { class: 'projects' });
  view.replaceChildren(
    h('div', { class: 'page-h' },
      h('div', {}, h('h1', {}, 'Xưởng video whiteboard của bạn'),
        h('p', {}, 'Dán kịch bản, bấm "Tạo video 1 chạm": app tự chia cảnh, tạo ảnh, giọng đọc và xuất video.')),
      h('div', { class: 'spacer' }),
      h('div', { class: 'btn-row', style: { flexWrap: 'wrap', justifyContent: 'flex-end' } },
        h('button', { class: 'btn accent lg', onclick: quickCreate }, icon('sparkle'), 'Tạo video 1 chạm'),
        h('button', { class: 'btn lg', onclick: jobsPanel }, icon('history'), 'Việc đang chạy'),
        h('button', { class: 'btn lg', onclick: batchCreate }, icon('film'), 'Hàng loạt'),
        h('button', { class: 'btn lg', onclick: newProject }, icon('plus'), 'Dự án mới'))),
    h('div', { class: 'hero-tips' }, TIPS.map(([n, bg, fg, t, d]) => h('div', { class: 'card tip' },
      h('div', { class: 'ic', style: { background: bg, color: fg } }, n), h('div', {}, h('b', {}, t), h('span', {}, d))))),
    grid,
  );
  let list = [];
  try { list = (await api.get('/projects')).projects; } catch (e) { toast(e.message, 'err'); }
  grid.replaceChildren(
    h('div', { class: 'card pcard new', onclick: newProject }, h('div', { class: 'plus' }, icon('plus')), 'Tạo dự án mới'),
    ...list.map((p) => projectCard(p, () => renderHome(view))),
  );
}

function projectCard(p, refresh) {
  const pct = p.lines ? Math.round(100 * p.voiced / p.lines) : 0;
  return h('div', { class: 'card pcard', onclick: () => go(`#/p/${p.id}/script`) },
    h('div', { class: 'thumb' }, p.thumb ? h('img', { src: fileUrl(p.id, p.thumb), loading: 'lazy' })
      : h('div', { class: 'ph' }, icon('image'), 'Chưa có ảnh')),
    h('div', { class: 'meta' },
      h('div', { class: 'name' }, p.name),
      h('div', { class: 'tags' },
        h('span', { class: 'tag' }, p.aspect),
        h('span', { class: 'tag' }, `${p.scenes} cảnh · ${p.lines} câu`),
        p.lines ? h('span', { class: `tag ${pct === 100 ? 'green' : 'amber'}` }, `Giọng ${pct}%`) : null,
        p.renders ? h('span', { class: 'tag blue' }, `${p.renders} video${p.durationMs ? ' · ' + fmtMs(p.durationMs) : ''}`) : null),
      h('div', { class: 'foot' }, h('span', {}, 'Sửa ' + timeAgo(p.updated)),
        h('div', { class: 'btn-row' },
          h('button', {
            class: 'btn ghost xs', title: 'Nhân bản', onclick: async (e) => {
              e.stopPropagation(); await api.post(`/projects/${p.id}/duplicate`); toast('Đã nhân bản', 'ok'); refresh();
            },
          }, icon('dup')),
          h('button', {
            class: 'btn ghost xs danger', title: 'Xoá', onclick: async (e) => {
              e.stopPropagation();
              if (await confirmBox(`Xoá dự án "${p.name}"? (chuyển vào data/trash, có thể khôi phục thủ công)`, 'Xoá', true)) {
                await api.del(`/projects/${p.id}`); refresh();
              }
            },
          }, icon('trash'))))));
}

const ASPECTS = [['16:9', 'YouTube', 40, 22.5], ['9:16', 'TikTok / Reels', 20, 35.5], ['1:1', 'Vuông', 30, 30], ['4:5', 'Facebook', 28, 35]];
const TPL_META = {
  explainer: ['💡', '#FDE8DA', 'Giải thích một hiện tượng, khái niệm'],
  story: ['📖', '#E3ECFD', 'Mở đầu → biến cố → hành trình → kết'],
  lesson: ['🎓', '#E2F3E8', 'Mục tiêu → các ý chính → tổng kết'],
  blank: ['✏️', '#F3EFE7', 'Tự viết từ đầu'],
};

async function newProject() {
  const tpls = await api.get('/templates');
  const st = { name: '', aspect: '16:9', template: 'explainer' };
  const aspects = h('div', { class: 'aspects' });
  const tplBox = h('div', { class: 'tpls' });
  const drawA = () => aspects.replaceChildren(...ASPECTS.map(([a, lab, w, hh]) => h('div', {
    class: `aspect ${st.aspect === a ? 'on' : ''}`, onclick: () => { st.aspect = a; drawA(); },
  }, h('i', { style: { width: w + 'px', height: hh + 'px' } }), a, h('small', {}, lab))));
  const drawT = () => tplBox.replaceChildren(...Object.entries(tpls).map(([k, v]) => h('div', {
    class: `tpl ${st.template === k ? 'on' : ''}`, onclick: () => { st.template = k; drawT(); },
  }, h('div', { class: 'ti', style: { background: TPL_META[k]?.[1] } }, TPL_META[k]?.[0] || '•'),
    h('div', {}, h('b', {}, v.label), h('span', {}, TPL_META[k]?.[2] || '')))));
  drawA(); drawT();
  const nameInp = h('input', { class: 'input', placeholder: 'Ví dụ: Vì sao bầu trời màu xanh?', oninput: (e) => { st.name = e.target.value; } });
  const create = async () => {
    const p = await api.post('/projects', { ...st, name: st.name || 'Dự án mới' });
    m.close(); go(`#/p/${p.id}/script`);
  };
  nameInp.addEventListener('keydown', (e) => { if (e.key === 'Enter') create(); });
  const m = modal({
    title: 'Tạo dự án mới',
    body: h('div', { class: 'modal-b stack' },
      h('label', { class: 'field' }, 'Tên dự án', nameInp),
      h('div', { class: 'field' }, h('label', { class: 'field' }, 'Khung hình'), aspects),
      h('div', { class: 'field' }, h('label', { class: 'field' }, 'Bắt đầu từ mẫu'), tplBox)),
    footer: [h('button', { class: 'btn', onclick: () => m.close() }, 'Huỷ'),
      h('button', { class: 'btn accent', onclick: create }, 'Tạo dự án', icon('arrow'))],
  });
  setTimeout(() => nameInp.focus(), 50);
}
