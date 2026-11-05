"""Convert the static mockups into Flatsome UX Builder shortcode content for tracnghiemplus.com."""
import re, sys, json
from bs4 import BeautifulSoup, NavigableString

UP = "https://tracnghiemplus.com/wp-content/uploads/"
BASE_BTN = ".tpx-btn{display:inline-block;margin:0 1em 1em 0;text-decoration:none;cursor:pointer}"

def minify_css(css):
    css = re.sub(r'/\*.*?\*/', '', css, flags=re.S)
    css = re.sub(r'\s*\n\s*', '', css)
    css = re.sub(r'\s*([{};,>])\s*', r'\1', css).replace(';}', '}')
    return css.strip()

def build(src, prefix, links, faq_answers, extra_css="", after_faq=""):
    html = open(src, encoding="utf-8").read()
    style = re.search(r'<style>(.*?)</style>', html, re.S).group(1)
    design = style.split('design ----')[1].split('*/', 1)[1]
    design = re.split(r'/\* mockup', design)[0]
    design = re.sub(r'\.mock-[^{]*\{[^}]*\}', '', design)
    design = re.sub(r'\.button\b', '.tpx-btn', design)
    design = design.replace('.accordion-item.open .accordion-title', '.accordion-title.active')
    # Flatsome compatibility: reset theme button/accordion look inside our scope
    design += (f".{prefix}-s .tpx-btn{{text-transform:none;letter-spacing:0;font-family:inherit}}"
               f".{prefix}-s .accordion-title{{border:0!important;display:block;position:relative}}"
               f".{prefix}-s .accordion-title .toggle{{left:12px}}"
               f".{prefix}-s p:empty{{display:none}}"
               f".{prefix}-s .col-inner>div>img:first-child{{margin-bottom:0}}")
    font = "@import url('https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap');"
    soup = BeautifulSoup(html, "html.parser")
    var_classes, var_css = {}, []
    def var_class(style):
        style = style.strip().rstrip(';')
        if style not in var_classes:
            name = f"{prefix}-v{len(var_classes)+1}"
            var_classes[style] = name
            var_css.append(f".{name}{{{style}}}")
        return var_classes[style]

    out = []
    for sec in soup.body.find_all("section", recursive=False):
        classes = " ".join(c for c in sec.get("class", []))
        pad = re.search(r'padding:\s*(\d+)px', sec.get("style", ""))
        pad = pad.group(1) if pad else "60"
        for n in sec.find_all(class_=re.compile("^mock-")):
            n.decompose()
        sid = sec.get("id")
        if sid:
            out.append(f'[scroll_to title="{sid}" link="#{sid}" bullet="false"]\n')
        label = (sec.find(["h1", "h2"]).get_text(" ", strip=True)[:40] if sec.find(["h1", "h2"]) else prefix)
        out.append(f'[section label="{label}" class="{classes}" padding="{pad}px" padding__sm="40px"]\n')
        for row in sec.find_all("div", class_="row", recursive=False):
            rc = row.get("class", [])
            attrs = []
            if "align-middle" in rc: attrs.append('v_align="middle"')
            if "align-equal" in rc: attrs.append('v_align="equal"')
            if "align-center" in rc: attrs.append('h_align="center"')
            rclass = []
            if row.get("style"): rclass.append(var_class(row["style"]))
            if rclass: attrs.append(f'class="{" ".join(rclass)}"')
            out.append(f'[row {" ".join(attrs)}]\n'.replace('[row ]', '[row]'))
            for col in row.find_all("div", class_="col", recursive=False):
                cc = col.get("class", [])
                span = next((c.split('-')[1] for c in cc if c.startswith('large-')), "12")
                md = next((c.split('-')[1] for c in cc if c.startswith('medium-')), None)
                sm = next((c.split('-')[1] for c in cc if c.startswith('small-')), "12")
                extra = [c for c in cc if not re.match(r'^(col|large-\d+|medium-\d+|small-\d+)$', c)]
                if col.get("style"): extra.append(var_class(col["style"]))
                a = [f'span="{span}"', f'span__sm="{sm}"'] + ([f'span__md="{md}"'] if md else [])
                inner = col.find(class_="col-inner")
                if "text-center" in inner.get("class", []): a.append('align="center"')
                if extra: a.append(f'class="{" ".join(extra)}"')
                out.append(f'[col {" ".join(a)}]\n\n')
                # split content around accordions
                parts = []
                buf = []
                for child in list(inner.children):
                    acc = child.find("div", class_="accordion") if hasattr(child, "find") and child.name and "accordion" not in child.get("class", []) else None
                    if getattr(child, "name", None) and "accordion" in child.get("class", []):
                        parts.append(("html", "".join(buf))); buf = []; parts.append(("acc", child))
                    else:
                        buf.append(str(child))
                parts.append(("html", "".join(buf)))
                for kind, val in parts:
                    if kind == "html":
                        h = re.sub(r'\s*\n\s*', ' ', val).strip()
                        if h:
                            out.append(f'<div class="{prefix}-wrap">{h}</div>\n\n')
                    else:
                        out.append('[accordion]\n\n')
                        for item in val.find_all(class_="accordion-item"):
                            q = item.find(class_="accordion-title").find("span").get_text(strip=True)
                            ans = faq_answers.get(q)
                            if ans is None:
                                p = item.find(class_="accordion-inner")
                                ans = p.decode_contents().strip() if p else ""
                            assert ans, f"missing FAQ answer: {q}"
                            if not ans.startswith("<p>"): ans = f"<p>{ans}</p>"
                            out.append(f'[accordion-item title="{q}"]\n\n{ans}\n\n[/accordion-item]\n')
                        out.append('\n[/accordion]\n\n')
                        if after_faq:
                            out.append(after_faq + "\n\n")
                out.append('[/col]\n')
            out.append('\n[/row]\n')
        out.append('\n[/section]\n')
    body = "".join(out)
    body = re.sub(r'\[row\]\n\[col span="12" span__sm="12"\]\n\n\[/col\]\n\n\[/row\]\n', '', body)
    # images -> uploaded PNGs, links -> live quiz hashes
    body = re.sub(r'\.\./\.\./public/[a-z-]+/([a-z0-9-]+)\.svg', lambda m: UP + m.group(1) + ".png", body)
    body = re.sub(r'class="button( |")', lambda m: 'class="tpx-btn' + m.group(1), body)
    for old, new in links:
        assert old in body, f"link anchor not found: {old}"
        body = body.replace(old, new)
    css = minify_css(font + BASE_BTN + design + "".join(var_css) + extra_css)
    assert "\n" not in css
    return f'[ux_html label="CSS {prefix}"]<style>{css}</style>[/ux_html]\n' + body

def check(content):
    s = re.sub(r'<style>.*?</style>', '', content, flags=re.S)
    stack = []
    for c, t in re.findall(r'\[(/?)([a-z_\-]+)', s):
        if t in {"scroll_to", "button", "ux_image", "gap"}: continue
        if c: assert stack and stack[-1] == t, (t, stack[-3:]); stack.pop()
        else: stack.append(t)
    assert not stack, stack
    assert "../../public" not in s and 'href="#"' not in s, "leftover placeholder"
    return True
