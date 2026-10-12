"""Sinh nội dung UX Builder cho các trang TOEIC. Chạy: python3 build.py"""
import os, re
D = os.path.dirname(os.path.abspath(__file__))
css = re.sub(r'/\*.*?\*/', '', open(f'{D}/style.css', encoding='utf-8').read(), flags=re.S)
css = ''.join(l.strip() for l in css.splitlines())
js = open(f'{D}/script.js', encoding='utf-8').read().strip()
open(f'{D}/block-toeic-assets.txt', 'w', encoding='utf-8').write(
    '[ux_html label="CSS + JS trang TOEIC (dùng chung)"]\n'
    f'<style id="tc-css">{css}</style>\n<script id="tc-js">\n{js}\n</script>\n[/ux_html]\n')

SITE = 'https://tracnghiemplus.com'
BLOCK = '[block id="toeic-assets"]'
CAT = 'TOEIC'
ICON_L = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 18v-6a9 9 0 0118 0v6"/><path d="M21 19a2 2 0 01-2 2h-1v-6h3zM3 19a2 2 0 002 2h1v-6H3z"/></svg>'
ICON_R = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 4h7a3 3 0 013 3v13a2 2 0 00-2-2H2zM22 4h-7a3 3 0 00-3 3v13a2 2 0 012-2h8z"/></svg>'
PARTS = {
 'listening': [
  ('P1', 'Photographs', 'Mô tả tranh', '1–6', 6, 'Nghe 4 câu mô tả và chọn câu đúng nhất với bức ảnh.', 'Quan sát chủ thể, hành động và vị trí trong ảnh trước khi audio bắt đầu.'),
  ('P2', 'Question–Response', 'Hỏi – Đáp', '7–31', 25, 'Nghe 1 câu hỏi/câu nói và 3 câu trả lời, chọn câu đáp lại hợp lý.', 'Tập trung vào từ để hỏi đầu câu (Who/Where/When…), cẩn thận với đáp án lặp âm.'),
  ('P3', 'Conversations', 'Hội thoại', '32–70', 39, '13 đoạn hội thoại 2–3 người, mỗi đoạn 3 câu hỏi; có câu hỏi kèm bảng biểu.', 'Đọc trước 3 câu hỏi trong lúc nghe phần Directions.'),
  ('P4', 'Talks', 'Bài nói ngắn', '71–100', 30, '10 bài nói đơn (thông báo, quảng cáo, voicemail…), mỗi bài 3 câu hỏi.', 'Nhận diện loại bài nói ngay từ những giây đầu để đoán nội dung.')],
 'reading': [
  ('P5', 'Incomplete Sentences', 'Hoàn thành câu', '101–130', 30, 'Chọn từ/cụm từ đúng ngữ pháp – từ vựng để hoàn thành câu.', 'Xác định loại từ cần điền trước khi xét nghĩa của các đáp án.'),
  ('P6', 'Text Completion', 'Hoàn thành đoạn văn', '131–146', 16, '4 đoạn văn, mỗi đoạn 4 chỗ trống, trong đó có 1 câu hoàn chỉnh cần chèn.', 'Đọc cả câu trước và sau chỗ trống để chọn câu phù hợp ngữ cảnh.'),
  ('P7', 'Reading Comprehension', 'Đọc hiểu', '147–200', 54, 'Đoạn đơn (29 câu), đoạn đôi (10 câu) và đoạn ba (15 câu).', 'Đọc câu hỏi trước, dò từ khóa (scanning) và dành khoảng 55 phút cho Part 7.')]}

def sec(label, cls, inner, pad='70px', pad_sm='46px', bg='#f6f3ec'):
    return f'[section label="{label}" bg_color="{bg}" padding="{pad}" padding__sm="{pad_sm}" class="tc {cls}"]\n\n{inner}\n\n[/section]'

def part_rows(skill):
    return '\n'.join(f'<div class="tc-part"><div class="tc-part-no">{no}</div><div><div class="tc-part-name">{en} <span>– {vi}</span></div><div class="tc-part-desc">{desc}</div></div><div class="tc-part-n">{n} câu<small>{rng}</small></div></div>'
                     for no, en, vi, rng, n, desc, tip in PARTS[skill])

def skill_card(skill):
    if skill == 'listening':
        ico, name, vi, meta, link, lt = ICON_L, 'Listening', 'Nghe hiểu', 'Câu 1 – 100 · 45 phút · Điểm 5 – 495', '/toeic-listening/', 'Luyện Listening →'
    else:
        ico, name, vi, meta, link, lt = ICON_R, 'Reading', 'Đọc hiểu', 'Câu 101 – 200 · 75 phút · Điểm 5 – 495', '/toeic-reading/', 'Luyện Reading →'
    return (f'<div class="tc-skill"><div class="tc-skill-head"><div class="tc-ico">{ico}</div><div><h3>{name} <span>· {vi}</span></h3><small>{meta}</small></div></div>'
            f'<a class="tc-link" href="{SITE}{link}">{lt}</a>\n{part_rows(skill)}\n</div>')

def kho(skill, title, sub, tabs):
    tabs_html = ''
    if tabs:
        tabs_html = ('<div class="tc-tabs" data-target="#tc-cards-main"><div class="tc-tab" data-skill="listening">Listening</div>'
                     '<div class="tc-tab" data-skill="reading">Reading</div><div class="tc-tab is-active" data-skill="all">Tất cả</div></div>\n')
    return f'''[row width="custom" custom_width="1200px" class="tc-row"]

[col span="12"]

[ux_html label="Kho đề – tiêu đề + thẻ đề (tự dựng từ TestSpace)"]
<div id="kho-de" class="tc-anchor"></div>
<div class="tc-head"><div class="tc-badge">Ngân hàng đề</div><h2 class="tc-h2">{title}</h2><p class="tc-sub">{sub}</p></div>
{tabs_html}<div class="tc-cards" id="tc-cards-main" data-skill="{skill}"><div class="tc-empty">Đang tải danh sách đề…</div></div>
[/ux_html]

[/col]
[col span="12" class="tc-src"]

[tqp_category_landing category="{CAT}"]

[/col]

[/row]'''

MOCK = '''<div class="tc-mock" aria-hidden="true">
<div class="tc-mock-bar"><div class="tc-live"></div><div>TOEIC Test – Đề 1 · LISTENING</div><div class="tc-q">Câu 47/100</div><div class="tc-timer">⏱ 32:14</div></div>
<div class="tc-mock-body">
<div class="tc-mock-part">Part 3 – Conversations</div>
<div class="tc-audio"><div class="tc-play">▶</div><div class="tc-track"><div></div></div><small>0:31</small></div>
<div class="tc-mock-qtext"><span>47.</span>What does the man ask the woman to do?</div>
<div class="tc-opt"><b>A</b>Contact some customers</div>
<div class="tc-opt"><b>B</b>Attend a meeting</div>
<div class="tc-opt is-on"><b>C</b>Prepare a schedule</div>
<div class="tc-opt"><b>D</b>Visit another branch</div>
</div>
<div class="tc-float"><div class="tc-ok">✓</div><div><strong>Xem kết quả ngay</strong><small>Sau khi nộp bài</small></div></div>
</div>'''

def hub():
    hero = f'''[row width="custom" custom_width="1200px" v_align="middle" class="tc-row"]

[col span="7" span__md="12" span__sm="12"]

{BLOCK}

[ux_html label="Giới thiệu"]
<div class="tc-crumb"><a href="{SITE}/">⌂ Trang chủ</a><i>›</i><b>Thi thử TOEIC</b></div>
<div class="tc-badge">Đề TOEIC Listening &amp; Reading</div>
<h1 class="tc-h1">Đề Thi Thử<br><span>TOEIC Online</span><br><em>Miễn Phí</em></h1>
<p class="tc-lead">Bộ đề TOEIC Listening &amp; Reading theo đúng cấu trúc đề thi thật: <b>7 Part</b>, Listening 100 câu – 45 phút, Reading 100 câu – 75 phút. Giao diện mô phỏng phòng thi, làm bài trực tuyến và xem kết quả ngay sau khi nộp.</p>
<div class="tc-actions"><a class="tc-btn tc-btn-blue" href="#kho-de" data-tc-first="all">Bắt đầu thi ngay →</a><a class="tc-btn tc-btn-white" href="{SITE}/toeic-listening/">Luyện Listening</a></div>
<div class="tc-stats"><div class="tc-stat"><strong data-tc-stat="total">–</strong><span>đề thi thử</span></div><div class="tc-stat"><strong data-tc-stat="listening">–</strong><span>đề Listening</span></div><div class="tc-stat"><strong data-tc-stat="reading">–</strong><span>đề Reading</span></div></div>
[/ux_html]

[/col]
[col span="5" span__md="12" span__sm="12"]

[ux_html label="Minh họa phòng thi"]
{MOCK}
[/ux_html]

[/col]

[/row]'''
    struct = f'''[row width="custom" custom_width="1200px" class="tc-row"]

[col span="12"]

[ux_html label="Cấu trúc đề thi"]
<div class="tc-head"><div class="tc-badge">Cấu trúc đề thi thật</div><h2 class="tc-h2">Đề thi đúng chuẩn ETS – 200 câu, 7 Part, 120 phút</h2><p class="tc-sub">Mỗi đề thi thử được trình bày theo đúng thứ tự, số câu và Directions của bài thi TOEIC Listening &amp; Reading thực tế.</p></div>
<div class="tc-struct">
{skill_card('listening')}
{skill_card('reading')}
</div>
[/ux_html]

[/col]

[/row]'''
    return '\n\n'.join([
        sec('1. Giới thiệu', 'tc-hero', hero, '56px', '30px'),
        sec('2. Cấu trúc đề thi', 'tc-page', struct, '60px', '40px'),
        sec('3. Kho đề thi thử', 'tc-page', kho('all', 'Đề Thi Thử TOEIC', 'Chọn đề Listening hoặc Reading để luyện. Mỗi đề làm trực tuyến, chấm điểm và xem kết quả ngay sau khi nộp bài.', True), '50px', '36px'),
    ]) + '\n'

def skill_page(skill):
    L = skill == 'listening'
    name = 'Listening' if L else 'Reading'
    other = ('Reading', '/toeic-reading/') if L else ('Listening', '/toeic-listening/')
    badge = 'Câu 1 – 100 · 45 phút' if L else 'Câu 101 – 200 · 75 phút'
    lead = ('Luyện nghe TOEIC theo đúng 4 Part của đề thật: mô tả tranh, hỏi – đáp, hội thoại và bài nói ngắn. Làm bài trực tuyến để quen nhịp độ và áp lực phòng thi.' if L else
            'Luyện đọc TOEIC theo đúng 3 Part của đề thật: ngữ pháp – từ vựng, hoàn thành đoạn văn và đọc hiểu. Quản lý 75 phút cho 100 câu như khi thi thật.')
    foot = 'Giọng đọc: Mỹ · Anh · Canada · Úc' if L else 'Gợi ý phân bổ: Part 5 ~10 phút · Part 6 ~10 phút · Part 7 ~55 phút'
    t, ico = ('45′', ICON_L) if L else ('75′', ICON_R)
    hero = f'''[row width="custom" custom_width="1200px" v_align="middle" class="tc-row"]

[col span="7" span__md="12" span__sm="12"]

{BLOCK}

[ux_html label="Giới thiệu {name}"]
<div class="tc-crumb"><a href="{SITE}/">⌂ Trang chủ</a><i>›</i><a href="{SITE}/thi-thu-toeic/">Thi thử TOEIC</a><i>›</i><b>{name} TOEIC</b></div>
<div class="tc-badge">{badge}</div>
<h1 class="tc-h1 tc-h1-icon"><span class="tc-ico">{ico}</span><span>{name}</span> TOEIC</h1>
<p class="tc-lead">{lead}</p>
<div class="tc-actions"><a class="tc-btn tc-btn-blue" href="#kho-de" data-tc-first="{skill}">Thi thử {name} TOEIC ngay →</a><a class="tc-btn tc-btn-white" href="{SITE}{other[1]}">Đề {other[0]} TOEIC</a></div>
[/ux_html]

[/col]
[col span="5" span__md="12" span__sm="12"]

[ux_html label="Thông số {name}"]
<div class="tc-statbox"><div class="tc-stats"><div class="tc-stat"><strong>100</strong><span>câu hỏi</span></div><div class="tc-stat"><strong>{t}</strong><span>thời gian</span></div><div class="tc-stat"><strong>495</strong><span>điểm tối đa</span></div></div><div class="tc-statbox-foot">{foot} · <b data-tc-stat="{skill}">–</b> đề đang có</div></div>
[/ux_html]

[/col]

[/row]'''
    cards = '\n'.join(f'<div class="tc-pcard"><div class="tc-pcard-top"><div class="tc-part-no">{no}</div><div><h3>{en} <span>– {vi}</span></h3><div class="tc-pcard-range">Câu {rng}</div></div></div>'
                      f'<div class="tc-pcard-meta">{n} câu</div><p>{desc}</p><div class="tc-tip">💡 {tip}</div></div>'
                      for no, en, vi, rng, n, desc, tip in PARTS[skill])
    n_parts = len(PARTS[skill])
    struct = f'''[row width="custom" custom_width="1200px" class="tc-row"]

[col span="12"]

[ux_html label="Các Part của {name}"]
<div class="tc-head"><div class="tc-badge">Cấu trúc</div><h2 class="tc-h2">{n_parts} Part của {name} TOEIC</h2><p class="tc-sub">Nắm vững từng Part để luyện đúng trọng tâm và phân bổ thời gian hợp lý.</p></div>
<div class="tc-parts">
{cards}
</div>
[/ux_html]

[/col]

[/row]'''
    return '\n\n'.join([
        sec(f'1. Giới thiệu {name}', 'tc-hero', hero, '56px', '30px'),
        sec(f'2. {n_parts} Part', 'tc-page', struct, '56px', '36px'),
        sec('3. Đề thi', 'tc-page', kho(skill, f'Đề {name} TOEIC – 100 câu', f'Mỗi đề gồm 100 câu {name}, làm trực tuyến và xem kết quả ngay sau khi nộp bài.', False), '40px', '30px'),
    ]) + '\n'

open(f'{D}/page-thi-thu-toeic.txt', 'w', encoding='utf-8').write(hub())
open(f'{D}/page-toeic-listening.txt', 'w', encoding='utf-8').write(skill_page('listening'))
open(f'{D}/page-toeic-reading.txt', 'w', encoding='utf-8').write(skill_page('reading'))
print('ok')
