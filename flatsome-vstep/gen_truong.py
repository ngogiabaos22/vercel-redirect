# Sinh nội dung trang từng trường từ mẫu 3-trang-truong-mau-van-lang.txt
import os
SCHOOLS = [
    # (page_id, slug-file, tên ngắn, tên đầy đủ, địa điểm, category TestSpace)
    (565, 'van-lang', 'Văn Lang', 'Trường Đại học Văn Lang', 'TP. Hồ Chí Minh', 'VSTEP Văn Lang'),
    (593, 'su-pham', 'Sư Phạm', 'Trường Đại học Sư phạm', 'TP. Hồ Chí Minh', 'VSTEP Sư Phạm'),
    (595, 'ngan-hang', 'Ngân Hàng', 'Trường Đại học Ngân hàng', 'TP. Hồ Chí Minh', 'VSTEP Ngân Hàng'),
    (599, 'ngoai-ngu-hn', 'Ngoại Ngữ HN', 'Trường Đại học Ngoại ngữ – ĐHQG Hà Nội', 'Hà Nội', 'VSTEP Ngoại Ngữ HN'),
    (601, 'cong-nghiep', 'Công Nghiệp', 'Trường Đại học Công nghiệp', 'TP. Hồ Chí Minh', 'VSTEP Công Nghiệp'),
    (603, 'bach-khoa', 'Bách Khoa', 'Trường Đại học Bách khoa', 'Hà Nội', 'VSTEP Bách Khoa'),
    (605, 'can-tho', 'Cần Thơ', 'Trường Đại học Cần Thơ', 'Cần Thơ', 'VSTEP Cần Thơ'),
    (607, 'tra-vinh', 'Trà Vinh', 'Trường Đại học Trà Vinh', 'Trà Vinh', 'VSTEP Trà Vinh'),
]
here = os.path.dirname(os.path.abspath(__file__))
tpl = open(os.path.join(here, '3-trang-truong-mau-van-lang.txt'), encoding='utf-8').read()
for pid, slug, short, full, loc, cat in SCHOOLS:
    s = tpl.replace('category="VSTEP Văn Lang"', 'category="@@CAT@@"')
    s = s.replace('Trường Đại học Văn Lang', '@@FULL@@').replace('TP. Hồ Chí Minh', '@@LOC@@').replace('Văn Lang', short)
    s = s.replace('@@CAT@@', cat).replace('@@FULL@@', full).replace('@@LOC@@', loc)
    open(os.path.join(here, 'truong', f'{pid}-{slug}.txt'), 'w', encoding='utf-8').write(s)
    print(pid, slug, cat)
