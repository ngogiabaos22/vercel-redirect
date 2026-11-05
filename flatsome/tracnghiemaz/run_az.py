import sys, os; sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..', 'tracnghiemplus'))
os.chdir(os.path.join(os.path.dirname(__file__), '..', 'tracnghiemplus'))
from build import build, check
import run  # reuse FAQ answers
U = "https://tracnghiemaz.com/wp-content/uploads/"
def az(d): return {k: v.replace("tracnghiemplus.com", "tracnghiemaz.com") for k, v in d.items()}
cfg = {
 "holland": dict(up=U+"2025/11/", links=[('href="#">Xem 6 nhóm nghề', 'href="#nhom-holland">Xem 6 nhóm nghề')],
                 anchors=[('class="ho-s ho-hero"', 'bat-dau-test')]),
 "mbti": dict(up=U+"2026/01/", links=[('href="#">Làm test MBTI ngay →', 'href="#bat-dau-test">Làm test MBTI ngay ↓'),
                                     ('href="#">Xem 16 nhóm tính cách', 'href="#16-nhom">Xem 16 nhóm tính cách'),
                                     ('href="#">Làm test MBTI miễn phí →', 'href="#bat-dau-test">Làm test MBTI miễn phí ↑')],
              anchors=[('class="mb-s mb-hero"', 'bat-dau-test')]),
 "disc": dict(up=U+"2026/01/", links=[], anchors=[('class="ds-s" padding', 'disc-20'), ('class="ds-s" padding', 'disc-30'), ('class="ds-s" padding', 'disc-40')]),
}
for name, c in cfg.items():
    base = run.pages[name]
    content = build(base["src"], base["prefix"], c["links"], az(base["faq"]),
                    base.get("extra_css", ""), base.get("after_faq", ""), up=c["up"], anchors_after=c["anchors"])
    check(content)
    assert "tracnghiemplus" not in content and "#tnp-test" not in content
    open(f"../tracnghiemaz/test-{name}.txt", "w").write(content)
    print(name, len(content), [a for a in ("#bat-dau-test", "#disc-20", "#disc-30", "#disc-40") if a in content])
