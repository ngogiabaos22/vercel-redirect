// Sinh code Flatsome (UX Builder shortcode) cho 6 trang JLPT. Chạy: node build.js
const fs = require('fs');
const one = s => s.replace(/\n\s*/g, '');           // gộp 1 dòng để wpautop không chèn <p>
const html = s => `[ux_html]${one(s)}[/ux_html]`;
const sec = (label, body, extra = '') =>
`[section label="${label}" class="jl-sec" padding="30px" padding__md="40px" ${extra}]
[row]
[col span="12" span__sm="12"]
${body}
[/col]
[/row]
[/section]
`;

const SKILLS = [
  { k: 'doc-hieu', name: 'Đọc hiểu', icon: 'fa-book-open' },
  { k: 'nghe-hieu', name: 'Nghe hiểu', icon: 'fa-headphones' },
  { k: 'tu-vung', name: 'Từ vựng', icon: 'fa-spell-check' },
  { k: 'tu-vung-giao-tiep', name: 'Từ vựng giao tiếp', icon: 'fa-comments' },
  { k: 'ngu-phap', name: 'Ngữ pháp', icon: 'fa-language' },
];
// số đề: sửa tại đây nếu bạn có số đề khác
const LV = {
  n5: { N: 'N5', de: 18, min: 90,  pass: 80,  desc: 'Cấp độ nhập môn: hiểu tiếng Nhật cơ bản qua Hiragana, Katakana và các câu thường dùng trong đời sống.', level: 'Sơ cấp' },
  n4: { N: 'N4', de: 16, min: 125, pass: 90,  desc: 'Hiểu tiếng Nhật cơ bản trong giao tiếp hằng ngày: đọc được đoạn văn ngắn, nghe hội thoại chậm.', level: 'Sơ cấp' },
  n3: { N: 'N3', de: 16, min: 140, pass: 95,  desc: 'Cấp độ cầu nối từ sơ cấp lên trung cấp: đọc được bài viết ngắn, hiểu hội thoại tốc độ gần tự nhiên.', level: 'Trung cấp' },
  n2: { N: 'N2', de: 14, min: 155, pass: 90,  desc: 'Hiểu tiếng Nhật trong nhiều tình huống: đọc báo, tạp chí, nghe hội thoại và bài giảng tốc độ tự nhiên.', level: 'Trung - Cao cấp' },
  n1: { N: 'N1', de: 12, min: 170, pass: 100, desc: 'Cấp độ cao nhất: hiểu văn bản trừu tượng, bài luận, tin tức và hội thoại phức tạp.', level: 'Cao cấp' },
};
const pad = n => String(n).padStart(2, '0');
const HEAD = `[block id="jlpt-style"]\n`; // UX Block riêng tư chứa CSS + JS (xem ux-block-jlpt-style.txt)

// ---------- Trang cấp độ (N5..N1) ----------
function levelPage(key) {
  const L = LV[key], n = L.N;
  let out = `<!-- TRANG ${n}: Thi thử JLPT ${n} — mỗi [section] là 1 phần, sửa riêng được trong UX Builder -->\n` + HEAD;

  out += sec('JLPT ' + n + ' - Hero', html(`
    <div class="jl-wrap">
      <span class="jl-badge"><i class="fa-solid fa-graduation-cap"></i> JLPT ${n}</span>
      <h1 class="jl-title">Đề thi thử JLPT ${n} online</h1>
      <p class="jl-lead">${L.desc}</p>
      <div class="jl-stats">
        <div class="jl-stat"><i class="fa-solid fa-file-lines"></i><small>Số đề</small><b>${L.de} đề</b></div>
        <div class="jl-stat"><i class="fa-solid fa-layer-group"></i><small>Phần thi</small><b>3 phần</b></div>
        <div class="jl-stat"><i class="fa-regular fa-clock"></i><small>Thời gian</small><b>${L.min} phút</b></div>
        <div class="jl-stat"><i class="fa-solid fa-bullseye"></i><small>Điểm đạt</small><b>${L.pass}/180</b></div>
      </div>
      <div class="jl-btns">
        <a class="jl-btn" href="#bo-de-${key}" data-jl-scroll="#bo-de-${key}"><i class="fa-solid fa-play"></i> Thi thử ${n}</a>
        <a class="jl-btn jl-btn--ghost" href="#ky-nang-${key}" data-jl-scroll="#ky-nang-${key}"><i class="fa-solid fa-dumbbell"></i> Luyện theo kỹ năng</a>
      </div>
    </div>`));

  // Kỹ năng + Làm ngay (mở panel chứa shortcode plugin)
  const cards = SKILLS.map(s => `
      <div class="jl-skill">
        <div class="jl-skill__ic"><i class="fa-solid ${s.icon}"></i></div>
        <h3>${s.name}</h3>
        <a class="jl-btn jl-btn--ghost" href="#" data-jl-open="${key}-${s.k}">Làm ngay <i class="fa-solid fa-arrow-right"></i></a>
      </div>`).join('');
  let skillBody = html(`
    <div class="jl-wrap" id="ky-nang-${key}">
      <h2 class="jl-h2">Luyện theo kỹ năng ${n}</h2>
      <p class="jl-lead">Chọn kỹ năng muốn luyện, bấm “Làm ngay” để xem các đề của kỹ năng đó.</p>
      <div class="jl-skills">${cards}</div>
    </div>`);
  // panel: [row id] chứa shortcode plugin theo tag/category
  SKILLS.forEach(s => {
    skillBody += `
[row class="jl-panel" id="${key}-${s.k}"]
[col span="12" span__sm="12"]
${html(`<div class="jl-panel-head"><h3><i class="fa-solid ${s.icon}"></i> ${s.name} ${n}</h3><button type="button" class="jl-panel-close" data-jl-close aria-label="Đóng"><i class="fa-solid fa-xmark"></i></button></div>`)}
[tqp_category_landing category="${key}-${s.k}"]
[/col]
[/row]`;
  });
  out += sec('JLPT ' + n + ' - Kỹ năng', skillBody);

  // Bộ đề
  const exams = Array.from({ length: L.de }, (_, i) => {
    const no = pad(i + 1);
    const links = SKILLS.map(s => `<li><a href="#"><i class="fa-solid ${s.icon}"></i>${s.name}<i class="fa-solid fa-chevron-right"></i></a></li>`).join('');
    return `
      <div class="jl-exam">
        <div class="jl-exam__top"><h3>Đề ${i + 1} thi thử ${n}</h3>${i < 2 ? '<span class="jl-tag-new">MỚI</span>' : ''}</div>
        <ul class="jl-exam__list">${links}</ul>
        <a class="jl-btn jl-btn--block" href="#"><i class="fa-solid fa-play"></i> Thi Full đề</a>
      </div>`;
  }).join('');
  out += sec('JLPT ' + n + ' - Bộ đề', html(`
    <div class="jl-wrap" id="bo-de-${key}">
      <h2 class="jl-h2">${L.de} bộ đề thi thử JLPT ${n}</h2>
      <p class="jl-lead">Đề mô phỏng theo cấu trúc kỳ thi thật, kèm đáp án và giải thích bằng tiếng Việt.</p>
      <div class="jl-exams">${exams}</div>
    </div>`));
  // Danh sách đề full từ plugin (tag: n5-thi-full ...). Bỏ comment nếu muốn hiển thị thêm
  out += `<!-- [tqp_category_landing category="${key}-thi-full"] : đặt trong 1 [col] nếu muốn liệt kê đề Full từ plugin -->\n`;
  fs.writeFileSync(`0${{ n5: 2, n4: 3, n3: 4, n2: 5, n1: 6 }[key]}-trang-jlpt-${key}.txt`, out);
}

// ---------- Trang tổng JLPT ----------
function hubPage() {
  let out = `<!-- TRANG TỔNG: Thi thử JLPT (N5 → N1) -->\n` + HEAD;
  out += sec('JLPT - Hero', html(`
    <div class="jl-wrap">
      <span class="jl-badge"><i class="fa-solid fa-graduation-cap"></i> JLPT</span>
      <h1 class="jl-title">Thi thử JLPT online từ N5 đến N1</h1>
      <p class="jl-lead">Bộ đề thi thử JLPT trình bày theo đúng cấu trúc kỳ thi thật: Từ vựng - Ngữ pháp - Đọc hiểu - Nghe hiểu. Mỗi cấp độ có trang riêng với danh sách đề, thời lượng và điểm đạt rõ ràng.</p>
      <div class="jl-stats">
        <div class="jl-stat"><i class="fa-solid fa-signal"></i><small>Cấp độ</small><b>N5 → N1</b></div>
        <div class="jl-stat"><i class="fa-solid fa-calendar-days"></i><small>Kỳ thi</small><b>Tháng 7 & 12</b></div>
        <div class="jl-stat"><i class="fa-regular fa-clock"></i><small>Thời lượng</small><b>90 - 170 phút</b></div>
      </div>
      <div class="jl-btns"><a class="jl-btn" href="#cap-do" data-jl-scroll="#cap-do"><i class="fa-solid fa-play"></i> Thi thử JLPT</a></div>
    </div>`));

  const lv = {
    n5: ['Hiragana, Katakana và khoảng 100 chữ Kanji', 'Câu giao tiếp đơn giản hằng ngày', 'Nghe hội thoại chậm, rõ ràng'],
    n4: ['Khoảng 300 chữ Kanji, ~1.500 từ vựng', 'Đọc đoạn văn ngắn về đời sống', 'Nghe hội thoại tốc độ chậm'],
    n3: ['Khoảng 650 chữ Kanji, ~3.750 từ vựng', 'Đọc bài viết có độ khó trung cấp', 'Nghe hội thoại gần tốc độ tự nhiên'],
    n2: ['Khoảng 1.000 chữ Kanji, ~6.000 từ vựng', 'Đọc báo, tạp chí, bình luận', 'Nghe tin tức và bài giảng'],
    n1: ['Khoảng 2.000 chữ Kanji, ~10.000 từ vựng', 'Đọc văn bản trừu tượng, bài luận', 'Nghe hội thoại phức tạp, tốc độ tự nhiên'],
  };
  const cards = Object.keys(LV).map(k => {
    const L = LV[k];
    return `
      <div class="jl-level">
        <div class="jl-level__n">${L.N}</div>
        <span class="jl-badge" style="align-self:flex-start;margin:0">${L.level}</span>
        <p>${L.desc}</p>
        <ul>${lv[k].map(t => `<li><i class="fa-solid fa-circle-check"></i>${t}</li>`).join('')}</ul>
        <a class="jl-btn" href="#"><i class="fa-solid fa-play"></i> Thi thử ${L.N}</a>
      </div>`;
  }).join('');
  out += sec('JLPT - 5 cấp độ', html(`
    <div class="jl-wrap" id="cap-do">
      <h2 class="jl-h2">Đề thi thử theo từng cấp độ</h2>
      <p class="jl-lead">Chọn cấp độ phù hợp với trình độ hiện tại của bạn.</p>
      <div class="jl-levels">${cards}</div>
    </div>`));

  const rows = Object.keys(LV).map(k => { const L = LV[k]; return `<tr><td><b>${L.N}</b></td><td>${L.min} phút</td><td>3 phần</td><td>${L.pass}/180</td></tr>`; }).join('');
  out += sec('JLPT - Bảng so sánh', html(`
    <div class="jl-wrap">
      <h2 class="jl-h2">Bảng thời lượng và điểm đạt</h2>
      <p class="jl-lead">Mỗi cấp độ đều cần đạt tổng điểm tối thiểu và điểm sàn của từng phần thi.</p>
      <div class="jl-table-scroll"><table class="jl-table"><thead><tr><th>Cấp độ</th><th>Thời lượng</th><th>Phần thi</th><th>Điểm đạt</th></tr></thead><tbody>${rows}</tbody></table></div>
    </div>`));
  fs.writeFileSync('01-trang-thi-thu-jlpt.txt', out);
}

hubPage();
Object.keys(LV).forEach(levelPage);

// ---------- UX Block (CSS + JS) ----------
const css = fs.readFileSync('src/jlpt.css', 'utf8'), js = fs.readFileSync('src/jlpt.js', 'utf8');
fs.writeFileSync('ux-block-jlpt-style.txt',
`[ux_html]<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"><style>${css.replace(/\n/g, '')}</style><script>${js.replace(/\/\*.*?\*\//g,'').replace(/\n\s*/g, ' ')}</script>[/ux_html]\n`);
console.log('done');
