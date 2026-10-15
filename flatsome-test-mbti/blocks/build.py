#!/usr/bin/env python3
"""Tách mbti.js (bản đang chạy) thành 3 nội dung UX Block:
   out/css.txt, out/data.txt, out/script.txt  + blocks/main.js, blocks/data.js (bản đọc được)."""
import re, sys, os
HERE = os.path.dirname(os.path.abspath(__file__))
src = open(os.path.join(HERE, '..', 'mbti.js')).read()
css = open(os.path.join(HERE, '..', 'mbti.css')).read()

def grab(start, end):
    i = src.index(start); j = src.index(end, i) + len(end)
    return src[i:j]
groups = grab('    const groups = {', '\n    };')
types = grab('    const types = {', '\n    };')
dims = grab('    const dimensions = [', '\n    ];')

def convert(block, name, open_ch, close_ch):
    lines = block.split('\n')
    mid = [l[2:] if l.startswith('  ') else l for l in lines[1:-1]]
    return '\n'.join(['  %s: %s' % (name, open_ch)] + mid + ['  %s,' % close_ch])

dims_c = convert(dims, 'dimensions', '[', ']').replace('<br>', '\\n')
ui = '''  ui: {
    heroMeta: [["clock", "8–10 phút"], ["list", "32 câu hỏi"], ["shield", "Không cần đăng ký"]],
    floatingNote: "Bạn là mảnh ghép nào?",
    caption: ["16 tính cách.", "Một phiên bản duy nhất là bạn."],
    benefits: [
      ["fingerprint", "Hiểu rõ bản thân", "Gọi tên những điều làm nên bạn"],
      ["sparkles", "Nhìn ra thế mạnh", "Để tiềm năng được tỏa sáng"],
      ["chat", "Kết nối tốt hơn", "Hiểu mình, thấu hiểu người khác"],
      ["compass", "Mở lối tương lai", "Thêm góc nhìn cho lựa chọn mới"]
    ],
    personalitiesCta: ["Chưa biết mình thuộc nhóm nào?", "Thử bài test miễn phí"],
    dimensionsNote: "Không có xu hướng nào tốt hơn xu hướng nào. Chạm vào từng lựa chọn để tìm hiểu thêm.",
    before: {
      title: "Một vài điều trước khi bắt đầu.",
      text: "Không cần chuẩn bị kiến thức. Chỉ cần một chút thời gian và sự thành thật với chính mình.",
      steps: [
        ["Là bạn, không phải ai khác", "Chọn điều giống với con người hiện tại, không phải hình mẫu bạn muốn trở thành."],
        ["Đừng nghĩ quá lâu", "Không có đáp án đúng hay sai. Hãy chọn điều gần với bạn trong phần lớn thời gian."],
        ["Đón nhận, đừng đóng khung", "Kết quả là một điểm khởi đầu để khám phá, không phải giới hạn của con người bạn."]
      ]
    },
    cta: {
      eyebrow: "Tự hiểu mình, tự mở lối",
      line1: "Bạn không cần trở thành ai khác.",
      line2: "Bắt đầu từ chính mình.",
      button: "Khám phá tính cách của bạn",
      note: "32 câu hỏi · 8–10 phút · Một góc nhìn mới"
    },
    popup: {
      strengths: "Những thế mạnh thường gặp",
      growth: "Một góc nhỏ để phát triển",
      careers: "Những lĩnh vực bạn có thể tìm hiểu",
      note: "Đây là mô tả xu hướng và gợi ý khám phá, không phải khuôn mẫu hay giới hạn nghề nghiệp. Kỹ năng, sở thích và trải nghiệm thực tế của bạn luôn quan trọng."
    }
  }'''
data_js = ('/* MBTI – NỘI DUNG: sửa chữ trong dấu ngoặc kép. Không dùng dấu ngoặc kép thẳng bên trong nội dung, không chèn dòng trống, không dùng ký tự nhỏ hơn. */\n'
           'window.MBTI_DATA = {\n'
           + convert(groups, 'groups', '{', '}') + '\n'
           + convert(types, 'types', '{', '}') + '\n'
           + dims_c + '\n' + ui + '\n};\n')
data_js = data_js.replace('/* MBTI – NỘI DUNG', '/* MBTI NOI DUNG', 1) if False else data_js

# ---- main ----
m = src
head_end = m.index('(function () {\n  "use strict";')
m = m[head_end:]                       # bỏ phần nạp font (font nằm trong CSS @import)
m = m.replace(groups, '    const { groups, types, dimensions, ui } = window.MBTI_DATA;', 1)
m = m.replace(types + '\n', '', 1).replace(dims + '\n', '', 1)
def rep(a, b, count=1):
    global m
    assert a in m, a[:60]
    m = m.replace(a, b, count)
rep('''<span>${icon("clock")} 8–10 phút</span>
        <span>${icon("list")} 32 câu hỏi</span>
        <span>${icon("shield")} Không cần đăng ký</span>''', '${ui.heroMeta.map(([name, text]) => `<span>${icon(name)} ${text}</span>`).join("")}')
rep('</span>Bạn là mảnh ghép nào?</div>', '</span>${ui.floatingNote}</div>')
rep('<p class="art-caption">16 tính cách. <span>Một phiên bản duy nhất là bạn.</span></p>', '<p class="art-caption">${ui.caption[0]} <span>${ui.caption[1]}</span></p>')
m = re.sub(r'\$\{\[\n\s*\["fingerprint".*?\]\.map\(\(\[name, title, text\]\)', '${ui.benefits.map(([name, title, text])', m, count=1, flags=re.S)
rep('Chưa biết mình thuộc nhóm nào? <a class="text-link" href="${hrefOf("personalities")}">Thử bài test miễn phí ${icon("arrow-right")}</a>', '${ui.personalitiesCta[0]} <a class="text-link" href="${hrefOf("personalities")}">${ui.personalitiesCta[1]} ${icon("arrow-right")}</a>')
rep('<h3>${d.question}</h3>', '<h3>${d.question.replace(/\\n/g, "<br>")}</h3>')
rep('${icon("info")} Không có xu hướng nào tốt hơn xu hướng nào. Chạm vào từng lựa chọn để tìm hiểu thêm.', '${icon("info")} ${ui.dimensionsNote}')
i = m.index('fill("before"'); j = m.index('fill("cta"')
m = m[:i] + '''fill("before", `<div class="before-panel">
        <div class="before-heading">
          <h2>${ui.before.title}</h2>
          <p>${ui.before.text}</p>
        </div>
        <div class="step-grid">
          ${ui.before.steps.map(([title, text], index) => `<div class="step"><span class="step-number">${String(index + 1).padStart(2, "0")}</span><div><h3>${title}</h3><p>${text}</p></div></div>`).join("")}
        </div>
      </div>`);

    ''' + m[j:]
rep('<span class="eyebrow">Tự hiểu mình, tự mở lối</span>', '<span class="eyebrow">${ui.cta.eyebrow}</span>')
rep('<h2>Bạn không cần trở thành ai khác.<br><span>Bắt đầu từ chính mình.</span></h2>', '<h2>${ui.cta.line1}<br><span>${ui.cta.line2}</span></h2>')
rep('">Khám phá tính cách của bạn ${icon("arrow-right")}</a>', '">${ui.cta.button} ${icon("arrow-right")}</a>')
rep('<p>32 câu hỏi · 8–10 phút · Một góc nhìn mới</p>', '<p>${ui.cta.note}</p>')
rep('<h3>Những thế mạnh thường gặp</h3>', '<h3>${ui.popup.strengths}</h3>')
rep('<h3>Một góc nhỏ để phát triển</h3>', '<h3>${ui.popup.growth}</h3>')
rep('<h3>Những lĩnh vực bạn có thể tìm hiểu</h3>', '<h3>${ui.popup.careers}</h3>')
rep('<p class="detail-note">Đây là mô tả xu hướng và gợi ý khám phá, không phải khuôn mẫu hay giới hạn nghề nghiệp. Kỹ năng, sở thích và trải nghiệm thực tế của bạn luôn quan trọng.</p>', '<p class="detail-note">${ui.popup.note}</p>')
rep('event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom', 'bounds.left > event.clientX || event.clientX > bounds.right || bounds.top > event.clientY || event.clientY > bounds.bottom')
open(os.path.join(HERE, 'main.js'), 'w').write(m)
open(os.path.join(HERE, 'data.js'), 'w').write(data_js)

# ---- đóng gói an toàn cho WordPress ----
def pack_js(code):
    code = re.sub(r'/\*.*?\*/', '', code, flags=re.S)
    assert '//' not in re.sub(r'https?://', '', code), 'line comment'
    code = ' '.join(l.strip() for l in code.split('\n') if l.strip())
    code = re.sub(r'<(?=[A-Za-z/!])', r'\\x3c', code)
    code = re.sub(r'\[(?=[A-Za-z_])', '[ ', code)
    assert '<' not in code
    return code
def nolines(text):
    return '\n'.join(l for l in text.split('\n') if l.strip())

main_packed = pack_js(m)
data_body = nolines(data_js)
assert '<' not in data_body and not re.search(r'\[[A-Za-z_]', data_body)
UNUSED = ('.quiz-', '.choice', '.answer-', '.question-', '.progress-', '.result-', '.spectrum-', '.match-', '.restart-view',
          '.toast', '.key-hint', '.modal-topbar', '.modal-title', '.brand', '.btn-primary', '.btn-outline', '.resume-link', '.sr-only')
def prune(text):
    out = []
    for line in text.split('\n'):
        st = line.strip()
        if '{' in st and st.endswith('}') and not st.startswith('@'):
            sel = st.split('{', 1)[0]
            if any(u in sel for u in UNUSED):
                continue
        out.append(line)
    res = '\n'.join(out)
    # bỏ @media rỗng
    res = re.sub(r'@media[^{]*\{\s*\}\s*', '', res)
    return res
css_body = nolines(prune(css))
css_body = css_body.replace('/* ==========================================================\n   TEST MBTI', '/* ==========================================================\n   TEST MBTI')
css_body = css_body.replace("   TEST MBTI — CSS cho Flatsome\n   Dán vào: Flatsome > Advanced > Custom CSS > All screens\n   Mọi selector đều nằm trong .mbti nên không ảnh hưởng phần còn lại của site.", "   TEST MBTI - CSS giao diện (UX Block). Mọi selector nằm trong .mbti nên không ảnh hưởng phần khác của site.\n   Mỗi quy tắc nằm trên một dòng. Không chèn dòng trống, không dùng ký tự nhỏ hơn.")
css_full = "@import url('https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap');\n" + css_body
for bad in re.findall(r'\[([a-z_]+)', css_full):
    if bad in ('button','row','col','section','title','tab','search','video','audio','gallery','caption','block','share','map','logo','gap','divider','follow','code','embed','accordion','banner','text','ux_html'):
        raise SystemExit('shortcode-like in css: '+bad)
assert '<' not in css_full

out = os.path.join(HERE, 'out'); os.makedirs(out, exist_ok=True)
open(out + '/css.txt', 'w').write('[ux_html label="MBTI - CSS giao diện"]\n<style id="mbti-css">\n' + css_full + '\n</style>\n[/ux_html]')
open(out + '/data.txt', 'w').write('[ux_html label="MBTI - Nội dung chữ & dữ liệu 16 tính cách"]\n<script id="mbti-data">\n' + data_body + '\n</script>\n[/ux_html]')
open(out + '/script.txt', 'w').write('[ux_html label="MBTI - Script dựng giao diện (không cần sửa)"]\n<script id="mbti-js">\n' + main_packed + '\n</script>\n[/ux_html]')
for n in ('css', 'data', 'script'):
    print(n, os.path.getsize(out + '/%s.txt' % n))
