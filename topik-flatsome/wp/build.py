import re,os
os.chdir(os.path.join(os.path.dirname(os.path.abspath(__file__)),'..'))
css=open('tqp-topik.css',encoding='utf8').read()
css=re.sub(r'/\*.*?\*/','',css,flags=re.S)
css=re.sub(r'\s*\n\s*','',css)
css=re.sub(r'\s*([{};:,>])\s*',r'\1',css).replace(';}','}')
js=open('tqp-topik.js',encoding='utf8').read()
js=re.sub(r'^/\*.*?\*/\n','',js,flags=re.S)
js='\n'.join(l.strip() for l in js.split('\n') if l.strip())
assert '<' not in js and '<' not in css
open('wp/block-assets.txt','w',encoding='utf8').write("[ux_html label=\"CSS + JS trang TOPIK / EPS (dùng chung)\"]\n<style id=\"tp-css\">\n@import url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css');\n"+css+"\n</style>\n<script id=\"tp-js\">\n"+js+"\n</script>\n[/ux_html]")
def compact(h):
    h=re.sub(r'<!--.*?-->','',h,flags=re.S)
    return ''.join(l.strip() for l in h.split('\n') if l.strip())
src={'topik':'1-thi-thu-topik.html','topik-i':'2-thi-thu-topik-1.html','topik-ii':'3-thi-thu-topik-2.html','eps':'4-thi-thu-eps.html'}
for k,f in src.items():
    body=compact(open(f,encoding='utf8').read())
    srccol='\n[col span="12" class="tp-src"]\n<p>[tqp_category_landing category="Topik"]</p>\n[/col]' if k in('topik-i','topik-ii') else ''
    open(f'wp/page-{k}.txt','w',encoding='utf8').write(f'''[section label="Trang {k}" padding="0px" class="tp-sec0"]
[row width="full-width"]
[col span="12"]
[block id="topik-assets"]
[ux_html label="Nội dung trang"]
{body}
[/ux_html]
[/col]{srccol}
[/row]
[/section]''')
