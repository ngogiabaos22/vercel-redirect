"""Sinh nội dung UX Builder cho trang Đại học + các trang môn. Chạy: python3 build.py"""
import json, os, re
D = os.path.dirname(os.path.abspath(__file__))
css = re.sub(r'/\*.*?\*/', '', open(f'{D}/style.css', encoding='utf-8').read(), flags=re.S)
css = ''.join(l.strip() for l in css.splitlines())
js = open(f'{D}/script.js', encoding='utf-8').read().strip()

# 1) UX Block dùng chung (CSS + JS)
assets = ('[ux_html label="CSS + JS trang Đại học (dùng chung)"]\n'
          f'<style id="dh-css">{css}</style>\n<script id="dh-js">\n{js}\n</script>\n[/ux_html]\n')
open(f'{D}/block-dai-hoc-assets.txt', 'w', encoding='utf-8').write(assets)

BLOCK = '[block id="dai-hoc-assets"]'
BASE = 'https://tracnghiemplus.com/dai-hoc/'
SUBJECTS = json.load(open(f'{D}/subjects.json', encoding='utf-8'))
ICONS = {
 'chinh-tri': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M5 21V10M19 21V10M9 21V10M15 21V10M2 10l10-6 10 6"/></svg>',
 'dai-cuong': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 016.5 17H20V3H6.5A2.5 2.5 0 004 5.5z"/><path d="M4 19.5A2.5 2.5 0 006.5 22H20v-5"/></svg>',
 'chuyen-nganh': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>',
}
GROUPS = [
 ('chinh-tri', 'Môn chính trị', 'Các học phần lý luận chính trị bắt buộc với mọi sinh viên đại học.'),
 ('dai-cuong', 'Môn đại cương', 'Khối kiến thức giáo dục đại cương, nền tảng cho các môn chuyên ngành.'),
 ('chuyen-nganh', 'Môn chuyên ngành', 'Các học phần cơ sở ngành và chuyên ngành phổ biến.'),
]

def hub():
    chips = ''.join(f'<div class="dh-chip" data-q="{c}">{c}</div>' for c in
                    ['Pháp luật đại cương', 'Triết học Mác Lênin', 'Tư tưởng Hồ Chí Minh', 'Tin học đại cương', 'Kinh tế vi mô', 'Marketing căn bản'])
    out = [BLOCK, '', '''[section label="1. Giới thiệu" bg_color="#f5f1e8" padding="48px" padding__sm="28px" class="dh dh-hero"]

[row width="custom" custom_width="1200px" v_align="middle" class="dh-row"]

[col span="7" span__md="12" span__sm="12"]

[ux_html label="Giới thiệu – nội dung + tìm kiếm"]
<div class="dh-crumb"><a href="https://tracnghiemplus.com/">⌂ Trang chủ</a><i>›</i><b>Đại học</b></div>
<div class="dh-kicker">Kho đề trắc nghiệm online</div>
<h1 class="dh-h1">Đề trắc nghiệm các môn <u>Đại học</u></h1>
<p class="dh-lead">Kho đề trắc nghiệm các môn học cấp Đại học, bao gồm các môn <b>Chính trị</b> (Triết học Mác – Lênin, Lịch sử Đảng…), môn <b>Đại cương</b> và môn <b>Chuyên ngành</b>. Mỗi môn có đề tổng hợp, đề theo chương và đề thi của các trường đại học – thi thử online miễn phí.</p>
<form class="dh-search" role="search" onsubmit="return false"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg><input type="search" placeholder="Tìm môn học: pháp luật, marketing…" aria-label="Tìm môn học"><button type="submit">Tìm kiếm</button></form>
<div class="dh-popular"><span>Phổ biến:</span>''' + chips + '''</div>
[/ux_html]

[/col]
[col span="5" span__md="12" span__sm="12"]

[ux_html label="Thống kê kho đề (tự đếm)"]
<div class="dh-statcard">
<div class="dh-stamp"><div><b data-dh-stat="total">0</b>môn học</div></div>
<div class="dh-statcard-label">Thống kê kho đề</div>
<div class="dh-tiles">
<div class="dh-tile"><small>Môn học</small><strong data-dh-stat="total">0</strong></div>
<div class="dh-tile"><small>Đã có trang đề</small><strong data-dh-stat="open">0</strong></div>
<div class="dh-tile"><small>Nhóm môn</small><strong>3</strong></div>
<div class="dh-tile"><small>Thi thử</small><strong>Miễn phí</strong></div>
</div>
<div class="dh-mini"><div><b data-dh-stat="chinh-tri">0</b><span>chính trị</span></div><div><b data-dh-stat="dai-cuong">0</b><span>đại cương</span></div><div><b data-dh-stat="chuyen-nganh">0</b><span>chuyên ngành</span></div></div>
</div>
[/ux_html]

[/col]

[/row]

[/section]

[section label="2. Thanh lọc (dính khi cuộn)" bg_color="#f5f1e8" padding="0px" class="dh dh-tabs-sec"]

[row width="custom" custom_width="1200px" class="dh-row"]

[col span="12"]

[ux_html label="Tab nhóm môn"]
<div class="dh-tabs"><div class="dh-tab is-active" data-group="all">Tất cả</div><div class="dh-tab" data-group="chinh-tri">Môn chính trị</div><div class="dh-tab" data-group="dai-cuong">Môn đại cương</div><div class="dh-tab" data-group="chuyen-nganh">Môn chuyên ngành</div></div>
[/ux_html]

[/col]

[/row]

[/section]''']
    for i, (g, name, desc) in enumerate(GROUPS, 3):
        items = [s for s in SUBJECTS if s['group'] == g]
        cards = []
        for s in items:
            tag = f'<div class="dh-tag {s.get("tagcls", "")}">{s["tag"]}</div>'
            if s.get('page_id'):
                cards.append(f'<div class="dh-subject is-link" data-group="{g}">{tag}<h3><a class="dh-cover" href="{BASE}{s["slug"]}/">{s["name"]}</a></h3>'
                             f'<div class="dh-subject-foot"><span>Đề tổng hợp · theo chương</span><b>Xem tất cả →</b></div></div>')
            else:
                cards.append(f'<div class="dh-subject is-soon" data-group="{g}">{tag}<h3>{s["name"]}</h3>'
                             f'<div class="dh-subject-foot"><span>Đang biên soạn đề</span><b>Sắp có</b></div></div>')
        out.append(f'''[section label="{i}. {name}" bg_color="#f5f1e8" padding="46px" padding__sm="34px" class="dh dh-page dh-group dh-g-{g}"]

[row width="custom" custom_width="1200px" class="dh-row"]

[col span="12"]

[ux_html label="{name} – danh sách môn"]
<div id="{g}" class="dh-anchor"></div>
<div class="dh-ghead"><div class="dh-kicker"><span data-dh-stat="{g}">{len(items)}</span> môn học</div><div class="dh-gtitle"><div class="dh-gicon">{ICONS[g]}</div><h2 class="dh-h2">{name}</h2></div><div class="dh-gdesc">{desc}</div></div>
<div class="dh-subjects">
''' + '\n'.join(cards) + '''
</div>
[/ux_html]

[/col]

[/row]

[/section]''')
    out.append('''[section label="6. Không tìm thấy" bg_color="#f5f1e8" padding="0px" class="dh dh-page"]

[row width="custom" custom_width="1200px" class="dh-row"]

[col span="12"]

[ux_html label="Thông báo không có kết quả"]
<div class="dh-noresult">Không tìm thấy môn học phù hợp. Hãy thử từ khóa khác.</div>
<div style="height:40px"></div>
[/ux_html]

[/col]

[/row]

[/section]''')
    return '\n\n'.join(out) + '\n'

def subject(s):
    name, cat = s['name'], s['category']
    cat_tr = s.get('category_truong', f'{cat} - Đề các trường')
    groupname = dict((g, n) for g, n, _ in GROUPS)[s['group']]
    return f'''{BLOCK}

[section label="1. Giới thiệu môn" bg_color="#f5f1e8" padding="44px" padding__sm="28px" class="dh dh-hero"]

[row width="custom" custom_width="1200px" v_align="middle" class="dh-row"]

[col span="7" span__md="12" span__sm="12"]

[ux_html label="Giới thiệu môn"]
<div class="dh-crumb"><a href="https://tracnghiemplus.com/">⌂ Trang chủ</a><i>›</i><a href="{BASE}">Đại học</a><i>›</i><b>Trắc nghiệm {name}</b></div>
<div class="dh-badges"><div class="dh-tag {s.get("tagcls", "")}">{s["tag"]}</div><div class="dh-pill">{groupname}</div></div>
<h1 class="dh-h1">Trắc nghiệm {name}</h1>
<p class="dh-lead"><b>Trắc nghiệm {name}</b> bao gồm các bài tập, bài kiểm tra và đề thi thử môn {name}, nằm trong kho tài liệu <a href="{BASE}">trắc nghiệm đại học</a>. {s["intro"]} Bộ câu hỏi tập trung vào các chủ đề cốt lõi: <b>{s["topics"]}</b>.</p>
<div class="dh-actions"><a class="dh-btn dh-btn-red" href="#de-thi" data-dh-first>▶ Làm đề số 1 ngay</a><a class="dh-btn dh-btn-white" href="#de-truong" data-dh-schools>🏛 Đề của các trường</a></div>
[/ux_html]

[/col]
[col span="5" span__md="12" span__sm="12"]

[ux_html label="Thống kê (tự đếm từ TestSpace)"]
<div class="dh-kpis">
<div class="dh-kpi"><small>📄 Tổng số đề</small><strong data-dh-stat="de">–</strong></div>
<div class="dh-kpi"><small>☰ Câu hỏi</small><strong data-dh-stat="cau">–</strong></div>
<div class="dh-kpi"><small>📚 Đề theo chương</small><strong data-dh-stat="chuong">–</strong></div>
<div class="dh-kpi"><small>🏛 Đề các trường</small><strong data-dh-stat="truong">–</strong></div>
</div>
[/ux_html]

[/col]

[/row]

[/section]

[section label="2. Nhóm đề 1 – theo môn học" bg_color="#f5f1e8" padding="44px" padding__sm="30px" class="dh dh-page"]

[row width="custom" custom_width="1200px" class="dh-row"]

[col span="12"]

[ux_html label="Nhóm đề 1 – tiêu đề + danh sách"]
<div id="de-thi" class="dh-anchor"></div>
<div class="dh-ghead"><div class="dh-kicker">Nhóm đề 1</div><h2 class="dh-h2">Đề trắc nghiệm theo môn học</h2><div class="dh-gdesc">Bộ đề tổng hợp và đề theo từng chương của môn {name}, bám sát giáo trình chuẩn.</div></div>
<div class="dh-count" data-dh-stat="mon">…</div>
<div class="dh-panel"><div class="dh-panel-title">Trắc nghiệm {name} – đề tổng hợp <span data-dh-count="dh-list-tonghop"></span></div><div class="dh-list" id="dh-list-tonghop"></div></div>
<div class="dh-panel"><div class="dh-panel-title">Trắc nghiệm {name} theo chương <span data-dh-count="dh-list-chuong"></span></div><div class="dh-list" id="dh-list-chuong"></div></div>
<div class="dh-empty" id="dh-empty-mon" style="display:none">Đề trắc nghiệm môn này đang được cập nhật. Bạn quay lại sau nhé!</div>
[/ux_html]

[/col]
[col span="12" class="dh-src dh-src-mon"]

[tqp_category_landing category="{cat}"]

[/col]

[/row]

[/section]

[section label="3. Nhóm đề 2 – đề các trường" bg_color="#f5f1e8" padding="44px" padding__sm="30px" class="dh dh-page dh-g-truong"]

[row width="custom" custom_width="1200px" class="dh-row"]

[col span="12"]

[ux_html label="Nhóm đề 2 – tiêu đề + danh sách"]
<div id="de-truong" class="dh-anchor"></div>
<div class="dh-ghead"><div class="dh-kicker">Nhóm đề 2</div><h2 class="dh-h2">Đề trắc nghiệm {name} của các trường đại học</h2><div class="dh-gdesc">Đề thi giữa kỳ, cuối kỳ được sưu tầm từ các trường đại học – phù hợp để luyện sát cấu trúc đề của trường bạn.</div></div>
<div class="dh-count" data-dh-stat="truong-count">…</div>
<div class="dh-panel"><div class="dh-list" id="dh-list-truong"></div></div>
[/ux_html]

[/col]
[col span="12" class="dh-src dh-src-truong"]

[tqp_category_landing category="{cat_tr}"]

[/col]

[/row]

[/section]

[section label="4. Môn khác" bg_color="#f5f1e8" padding="10px" padding__sm="10px" class="dh dh-page"]

[row width="custom" custom_width="1200px" class="dh-row"]

[col span="12"]

[ux_html label="Quay lại kho đề"]
<div class="dh-actions" style="justify-content:center;margin:0 0 50px"><a class="dh-btn dh-btn-white" href="{BASE}">← Xem tất cả môn đại học</a></div>
[/ux_html]

[/col]

[/row]

[/section]
'''

open(f'{D}/page-dai-hoc.txt', 'w', encoding='utf-8').write(hub())
for s in SUBJECTS:
    if s.get('page_id'):
        open(f'{D}/mon-hoc/{s["slug"]}.txt', 'w', encoding='utf-8').write(subject(s))
print('ok')
