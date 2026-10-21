# Nguồn nội dung 3 trang TOPIK, chia theo từng section Flatsome.
# Chạy: python3 sections.py  → tạo wp/page-*.txt (dán vào WordPress) và 1/2/3-*.html (xem thử).
import os, re

os.chdir(os.path.dirname(os.path.abspath(__file__)))

PINK, WHITE, SOFT = '#fbeaf1', '#ffffff', '#fff7fa'

def crumb(*items):
    out = ['<a href="/">Trang chủ</a>']
    for label, href in items:
        out.append(f'<span>›</span><a href="{href}">{label}</a>' if href else f'<span>›</span>{label}')
    return '<nav class="tp-crumb">' + ''.join(out) + '</nav>'

def head(eyebrow, h2=None, sub=None, anchor=None):
    h = f'<div id="{anchor}" class="tp-anchor"></div>' if anchor else ''
    h += f'<div class="tp-head"><div class="tp-eyebrow">{eyebrow}</div>'
    if h2: h += f'<h2>{h2}</h2>'
    if sub: h += f'<p class="tp-sub">{sub}</p>'
    return h + '</div>'

def meta(*pairs):
    return '<div class="tp-meta">' + ''.join(f'<div><small>{a}</small><b>{b}</b></div>' for a, b in pairs) + '</div>'

def table(cols, rows):
    th = ''.join(f'<th>{c}</th>' for c in cols)
    tr = ''.join('<tr>' + ''.join((f'<td><b>{v}</b></td>' if i == 0 else f'<td>{v}</td>') for i, v in enumerate(r)) + '</tr>' for r in rows)
    return f'<div class="tp-tablewrap"><table class="tp-table"><thead><tr>{th}</tr></thead><tbody>{tr}</tbody></table></div>'

def note(t, p):
    return f'<div class="tp-note"><div class="tp-note-h">{t}</div><p>{p}</p></div>'

def part(time, title, items):
    lis = ''.join(f'<li>{x}</li>' for x in items)
    return f'<article class="tp-card tp-card--part"><div class="tp-time">{time}</div><h3>{title}</h3><ul>{lis}</ul><a class="tp-link" href="#tp-de">Luyện riêng phần này →</a></article>'

SRC = '[tqp_category_landing category="Topik"]'

# ---------------------------------------------------------------- TRANG THI THỬ TOPIK
TOPIK = [
 dict(label='1. Hero – Thi thử TOPIK', bg=PINK, pad='56px', html=
  '<div class="tp-eyebrow">Thi thử năng lực tiếng Hàn · TOPIK &amp; EPS-TOPIK</div>'
  '<h1>Thi thử TOPIK online</h1>'
  '<p class="tp-lead">Hai hệ đề riêng biệt: TOPIK thường niên dùng cho du học – định cư (TOPIK I cấp 1–2, TOPIK II cấp 3–6) và EPS-TOPIK dành cho người lao động đi Hàn Quốc theo chương trình EPS. Mỗi bộ đề mô phỏng đúng số câu, thời lượng và thang điểm đề thi thật.</p>'
  + meta(('Bộ đề', '3 nhóm'), ('TOPIK I', '70 câu · 100 phút'), ('TOPIK II', '104 câu · 180 phút'), ('EPS-TOPIK', '40 câu · 50 phút')) +
  '<div class="tp-actions"><a class="tp-btn" href="#tp-levels">Thi thử TOPIK ↓</a><a class="tp-btn tp-btn--ghost" href="/thi-thu-eps/">Thi thử EPS →</a></div>'
  + crumb(('Thi thử TOPIK', None))),
 dict(label='2. Chọn cấp độ – TOPIK I / TOPIK II', bg=WHITE, html=
  head('Chọn cấp độ', None, 'TOPIK I dành cho trình độ sơ cấp (cấp 1–2), thi 1 buổi. TOPIK II dành cho trình độ trung – cao cấp (cấp 3–6), thi 2 buổi và có thêm phần Viết.', 'tp-levels') +
  '<div class="tp-grid tp-grid--2">'
  '<article class="tp-card"><div class="tp-cardtop"><span class="tp-chip">Cấp 1 · Cấp 2</span><span class="tp-tag">200 điểm</span></div><h2>TOPIK I</h2><p>70 câu · 100 phút · 200 điểm</p><div class="tp-skill"><span>Nghe</span><span>30 câu · 40 phút · 100đ</span></div><div class="tp-skill"><span>Đọc</span><span>40 câu · 60 phút · 100đ</span></div><div class="tp-btnrow" style="margin-top:14px"><a class="tp-btn" href="/thi-thu-topik-i/">Xem bộ đề TOPIK I →</a></div></article>'
  '<article class="tp-card"><div class="tp-cardtop"><span class="tp-chip">Cấp 3 · 4 · 5 · 6</span><span class="tp-tag">300 điểm</span></div><h2>TOPIK II</h2><p>104 câu · 180 phút · 300 điểm</p><div class="tp-skill"><span>Nghe</span><span>50 câu · 60 phút · 100đ</span></div><div class="tp-skill"><span>Viết</span><span>4 câu · 50 phút · 100đ</span></div><div class="tp-skill"><span>Đọc</span><span>50 câu · 70 phút · 100đ</span></div><div class="tp-btnrow" style="margin-top:14px"><a class="tp-btn" href="/thi-thu-topik-ii/">Xem bộ đề TOPIK II →</a></div></article>'
  '</div>'),
 dict(label='3. Phân biệt TOPIK / IBT / EPS', bg=SOFT, html=
  head('Xác định theo mục đích', 'Phân biệt TOPIK thường niên, TOPIK IBT và EPS-TOPIK') +
  '<div class="tp-grid tp-grid--3">'
  '<article class="tp-card"><h3>TOPIK thường niên</h3><p>Kỳ thi do NIIED tổ chức chính thức, dùng cho du học, định cư và xin việc – tổ chức nhiều kỳ mỗi năm.</p></article>'
  '<article class="tp-card"><h3>TOPIK IBT (thi trên máy)</h3><p>TOPIK cấu trúc TOPIK I/II nhưng làm bài trên máy tính, kết quả có sớm hơn thi giấy.</p></article>'
  '<article class="tp-card"><h3>EPS-TOPIK (XKLĐ Hàn Quốc)</h3><p>Kỳ thi tiếng Hàn dành cho lao động theo Chương trình cấp phép việc làm EPS của HRD Korea, gồm 40 câu (20 Đọc + 20 Nghe).</p></article>'
  '</div>'),
 dict(label='4. Bộ đề EPS', bg=WHITE, html=
  head('Bộ đề EPS', 'Thi thử EPS-TOPIK cho người xuất khẩu lao động Hàn Quốc', 'Đề thi gồm 40 câu (20 Đọc + 20 Nghe), làm trên máy tính trong 50 phút, thang 200 điểm.') +
  table(['Phần thi', 'Số câu', 'Thời gian', 'Điểm', 'Nội dung'], [
    ['Đọc', '20 câu', '25 phút', '100 điểm', 'Từ vựng, ngữ pháp, đọc hiểu ngắn'],
    ['Nghe', '20 câu', '25 phút', '100 điểm', 'Hội thoại, thông báo trong môi trường làm việc']]) +
  '<div class="tp-chips">' + ''.join(f'<a href="/thi-thu-eps/#tp-nganh">{x}</a>' for x in ['Sản xuất chế tạo', 'Nông nghiệp', 'Xây dựng', 'Ngư nghiệp', 'Dịch vụ']) + '</div>' +
  note('Điểm đạt EPS-TOPIK', 'Thí sinh cần tối thiểu 80/200 điểm để được đưa vào danh sách hồ sơ dự tuyển. Kết quả có hiệu lực 2 năm, xếp hạng theo điểm số.') +
  note('Khác biệt với TOPIK thường', 'EPS-TOPIK không chia cấp 1–6, không có phần Viết, và nội dung xoay quanh môi trường làm việc, an toàn lao động, sinh hoạt tại Hàn Quốc.') +
  '<div class="tp-actions"><a class="tp-btn" href="/thi-thu-eps/">Luyện thi EPS →</a></div>'),
]

# ---------------------------------------------------------------- TRANG TOPIK I / II
def level_page(lv):
    one = lv == 'i'
    name = 'TOPIK I' if one else 'TOPIK II'
    other = ('/thi-thu-topik-ii/', 'TOPIK II', 'Cấp 3–6') if one else ('/thi-thu-topik-i/', 'TOPIK I', 'Cấp 1–2')
    if one:
        hero_meta = meta(('Hình thức', 'Trắc nghiệm'), ('Số phần', '2 phần'), ('Thang điểm', '200 điểm'), ('Cấp đạt', 'Cấp 1 · Cấp 2'))
        lead = 'Thi 1 buổi duy nhất. Dành cho người học sơ cấp, đủ điều kiện xin visa du học tiếng Hàn và một số ngành làm việc.'
        parts = part('Buổi 1 · 40 phút', 'Nghe', ['Câu 1–6: nghe câu hỏi chọn câu trả lời phù hợp', 'Câu 7–10: nghe đoạn đối thoại, chọn đáp án đúng', 'Câu 11–30: nghe hội thoại, chọn nội dung đúng và ý chính']) + \
                part('Buổi 1 · 60 phút', 'Đọc', ['Câu 31–39: chọn câu hỏi còn thiếu', 'Câu 40–56: đọc hiểu, tìm thông tin, biển báo', 'Câu 57–70: đọc đoạn văn ngắn – sắp xếp câu – suy luận'])
        grid = 'tp-grid--2'
        skills, desc = 'doc,nghe,tuvung,nguphap', 'Nghe 30 câu · Đọc 40 câu · 100 phút'
        t1 = table(['Phần thi', 'Số câu', 'Thời gian'], [['Nghe', '30 câu', '40 phút'], ['Đọc', '40 câu', '60 phút'], ['Tổng', '70 câu', '100 phút']])
        t2 = table(['Xếp cấp theo tổng điểm', 'Điểm'], [['Cấp 1', '80 – 139 điểm'], ['Cấp 2', '140 – 200 điểm']])
        n = note('Lưu ý', 'TOPIK I không có phần Viết. Dưới 80 điểm là không đạt cấp.')
    else:
        hero_meta = meta(('Số câu', '104 câu'), ('Số phần', '3 phần'), ('Thang điểm', '300 điểm'), ('Cấp đạt', '3 · 4 · 5 · 6'))
        lead = 'Thi 2 buổi: buổi 1 (Nghe + Viết) và buổi 2 (Đọc). Yêu cầu bắt buộc cho du học đại học và làm việc tại Hàn.'
        parts = part('Buổi 1 · 60 phút', 'Nghe', ['Câu 1–3: chọn hình phù hợp', 'Câu 4–20: hội thoại đời sống, chọn đáp án đúng', 'Câu 21–30: nghe hội thoại chuyên đề, ý chính người nói', 'Câu 31–50: phỏng vấn, thuyết trình, tin tức, học thuật']) + \
                part('Buổi 1 · 50 phút', 'Viết', ['Câu 51–52: viết câu điền vào chỗ trống trong văn bản ngắn', 'Câu 53: viết đoạn 200–300 chữ mô tả biểu đồ, số liệu', 'Câu 54: bài luận 600–700 chữ theo chủ đề cho sẵn']) + \
                part('Buổi 2 · 70 phút', 'Đọc', ['Câu 1–12: ngữ pháp, từ vựng, điền từ', 'Câu 13–24: sắp xếp câu, điền vào chỗ trống', 'Câu 25–31: đọc hiểu, tìm ý chính, đoạn văn ngắn', 'Câu 32–50: bài đọc dài, ý nghĩa, suy luận'])
        grid = 'tp-grid--3'
        skills, desc = 'doc,nghe,viet,tuvung,nguphap', 'Nghe 50 câu · Viết 4 câu · Đọc 50 câu · 180 phút'
        t1 = table(['Phần thi', 'Số câu', 'Thời gian'], [['Nghe', '50 câu', '60 phút'], ['Viết', '4 câu', '50 phút'], ['Đọc', '50 câu', '70 phút'], ['Tổng', '104 câu', '180 phút']])
        t2 = table(['Xếp cấp theo tổng điểm', 'Điểm'], [['Cấp 3', '120 – 149 điểm'], ['Cấp 4', '150 – 189 điểm'], ['Cấp 5', '190 – 229 điểm'], ['Cấp 6', '230 – 300 điểm']])
        n = note('Lưu ý', 'Câu 53–54 chấm theo tiêu chí nội dung – cấu trúc – ngôn ngữ và chiếm phần lớn điểm Viết, nên cần luyện Viết thường xuyên.')
    return [
     dict(label=f'1. Hero – {name}', bg=PINK, pad='56px', html=
      f'<div class="tp-eyebrow">{name} · {"Cấp 1 – Cấp 2" if one else "Cấp 3 – Cấp 6"}</div><h1>Đề thi thử {name} online</h1><p class="tp-lead">{lead}</p>' + hero_meta +
      f'<div class="tp-actions"><a class="tp-btn" href="#tp-de">Thi full đề ↓</a><a class="tp-btn tp-btn--ghost" href="{other[0]}">Chuyển sang {other[1]} →</a></div>' +
      crumb(('Thi thử TOPIK', '/thi-thu-topik/'), (name, None))),
     dict(label='2. Cấu trúc đề thi', bg=WHITE, html=
      head('Cấu trúc đề thi thật', f'Các phần thi và dạng câu hỏi trong {name}', 'Số liệu câu hỏi được giữ đúng như đề thi chính thức để bạn quen với vị trí từng dạng bài.') +
      f'<div class="tp-grid {grid}">{parts}</div>'),
     dict(label='3. Danh sách đề (JS tự dựng từ shortcode ở cột bên dưới)', bg=SOFT, src=True, html=
      head('Bộ đề thi thử', f'Đề thi thử {name}', 'Bấm "Xem đề" ở từng kỹ năng để luyện riêng, hoặc "Thi full đề" để làm trọn bộ đề tổng hợp.', 'tp-de') +
      f'<div class="tp-grid tp-grid--2" id="tp-exam-list" data-level="{lv}" data-skills="{skills}" data-desc="{desc}"><div class="tp-empty">Đang tải danh sách đề…</div></div>'),
     dict(label='4. Thang điểm & xếp cấp', bg=WHITE, html=
      head('Thang điểm &amp; xếp cấp', f'Cách quy đổi điểm {name}') + t1 + t2 + n),
     dict(label='5. Liên kết trang khác', bg=SOFT, pad='30px', html=
      f'<div class="tp-crosslinks"><a class="tp-btn tp-btn--ghost" href="{other[0]}">Chuyển sang {other[1]} ({other[2]}) →</a><a class="tp-btn tp-btn--ghost" href="/thi-thu-topik/">Thi thử TOPIK →</a><a class="tp-btn tp-btn--ghost" href="/thi-thu-eps/">Bộ đề EPS-TOPIK cho lao động →</a></div>'),
    ]

PAGES = {'topik': ('1-thi-thu-topik.html', TOPIK), 'topik-i': ('2-thi-thu-topik-1.html', level_page('i')), 'topik-ii': ('3-thi-thu-topik-2.html', level_page('ii'))}

def wp_page(sections):
    out = ['[block id="topik-assets"]']
    for s in sections:
        pad = s.get('pad', '48px')
        src = f'\n[col span="12" class="tp-src"]\n<p>{SRC}</p>\n[/col]' if s.get('src') else ''
        out.append(f'''[section label="{s['label']}" bg_color="{s['bg']}" padding="{pad}" padding__sm="32px" class="tqp-tp tp-s"]
[row width="custom" custom_width="1100px" class="tp-row"]
[col span="12" span__sm="12"]
[ux_html label="{s['label']}"]
{s['html']}
[/ux_html]
[/col]{src}
[/row]
[/section]''')
    return '\n'.join(out)

def preview(sections):
    body = ''
    for s in sections:
        pad = s.get('pad', '48px')
        src = f'<div class="tp-src">{SRC}</div>' if s.get('src') else ''
        body += f'<!-- {s["label"]} -->\n<section class="section tqp-tp tp-s" style="background:{s["bg"]};padding:{pad} 0"><div class="row tp-row" style="max-width:1100px;margin:0 auto;padding:0 16px"><div class="col">{s["html"]}{src}</div></div></section>\n'
    return body

for key, (fname, secs) in PAGES.items():
    open(f'wp/page-{key}.txt', 'w', encoding='utf8').write(wp_page(secs))
    open(fname, 'w', encoding='utf8').write(f'<!-- Bản xem thử – nội dung thật nằm ở wp/page-{key}.txt (mỗi section = 1 [section] Flatsome) -->\n' + preview(secs))
print('ok')
