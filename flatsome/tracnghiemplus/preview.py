import re, sys
UP="https://tracnghiemplus.com/wp-content/uploads/"
LOCAL={"ho":"holland-test","mb":"mbti-test","ds":"disc-test"}
def attrs(a): return dict(re.findall(r'([\w_]+)="([^"]*)"',a))
def render(s, prefix):
    s=re.sub(r'\[ux_html[^\]]*\](.*?)\[/ux_html\]',r'\1',s,flags=re.S)
    s=re.sub(r'\[scroll_to[^\]]*link="#([^"]+)"[^\]]*\]',r'<a name="\1"></a>',s)
    def sec(m):
        a=attrs(m.group(1)); return f'<section class="section {a.get("class","")}" style="padding:{a.get("padding","30px")} 0"><div class="section-content">'
    s=re.sub(r'\[section([^\]]*)\]',sec,s); s=s.replace('[/section]','</div></section>')
    def row(m):
        a=attrs(m.group(1)); c='row '+a.get('class','')
        if a.get('v_align')=='middle':c+=' align-middle'
        if a.get('v_align')=='equal':c+=' align-equal'
        if a.get('h_align')=='center':c+=' align-center'
        return f'<div class="{c}">'
    s=re.sub(r'\[row([^\]]*)\]',row,s); s=s.replace('[/row]','</div>')
    def col(m):
        a=attrs(m.group(1)); sp=a.get('span','12'); md=a.get('span__md',sp); sm=a.get('span__sm','12')
        return f'<div class="col {a.get("class","")} large-{sp} medium-{md} small-{sm}"><div class="col-inner {"text-center" if a.get("align")=="center" else ""}">'
    s=re.sub(r'\[col([^\]]*)\]',col,s); s=s.replace('[/col]','</div></div>')
    s=s.replace('[accordion]','<div class="accordion">').replace('[/accordion]','</div>')
    first=[True]
    def acc(m):
        act=' active' if first[0] else ''; first[0]=False
        return f'<div class="accordion-item"><a class="accordion-title plain{act}"><button class="toggle">⌄</button><span>{m.group(1)}</span></a><div class="accordion-inner"{" style=display:block" if act else ""}>'
    s=re.sub(r'\[accordion-item title="([^"]*)"\]',acc,s); s=s.replace('[/accordion-item]','</div></div>')
    s=s.replace(UP, f'/home/user/vercel-redirect/public/{LOCAL[prefix]}/png/')
    s=re.sub(r'src="(/home[^"]+)"',r'src="file://\1"',s)
    leftover=re.findall(r'\[/?[a-z_\-]+[ \]]',re.sub(r'<style>.*?</style>','',s,flags=re.S))
    assert not leftover, leftover[:5]
    shim='''body{margin:0;font-family:Lato,sans-serif;color:#777;background:#fff}*{box-sizing:border-box}img{max-width:100%;height:auto;display:inline-block}a{color:#446084}
.row{display:flex;flex-wrap:wrap;max-width:1110px;margin:0 auto;width:100%}.align-middle{align-items:center}.align-center{justify-content:center}.align-equal>.col{display:flex}.align-equal>.col>.col-inner{width:100%}
.col{padding:0 15px 30px;position:relative;width:100%;flex-basis:100%;max-width:100%}.text-center{text-align:center}
@media(min-width:850px){'''+"".join(f'.large-{i}{{flex-basis:{i/12*100:.3f}%;max-width:{i/12*100:.3f}%}}' for i in range(1,13))+'''}
@media(min-width:550px) and (max-width:849px){'''+"".join(f'.medium-{i}{{flex-basis:{i/12*100:.3f}%;max-width:{i/12*100:.3f}%}}' for i in range(1,13))+'''}
@media(max-width:549px){'''+"".join(f'.small-{i}{{flex-basis:{i/12*100:.3f}%;max-width:{i/12*100:.3f}%}}' for i in range(1,13))+'''}
h1,h2,h3{color:#555;margin:0 0 .5em;font-weight:700}h1{font-size:1.7em}h2{font-size:1.6em}h3{font-size:1.25em}p{margin:0 0 1.3em}ul{margin:0 0 1.3em 1.2em}
.button{display:inline-block;text-transform:uppercase;letter-spacing:.03em;background:#446084;color:#fff}
.accordion-title{display:block;position:relative;border-top:1px solid #ddd;padding:.6em 2.3em;text-decoration:none}.accordion-title .toggle{position:absolute;left:0;top:.7em;width:2.3em;border:0;background:none}.accordion-inner{display:none}
table{width:100%;border-collapse:collapse}'''
    return f'<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>{shim}</style></head><body>{s}</body></html>'
for name,prefix in [("holland","ho"),("mbti","mb"),("disc","ds")]:
    open(f"/tmp/claude-0/-home-user-vercel-redirect/9ac5b71f-6ef6-5bb5-8329-744a81a4eb05/scratchpad/plus-{name}.html","w").write(render(open(f"test-{name}.txt").read(),prefix))
print("ok")
