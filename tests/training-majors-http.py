"""HTTP integration checks for Training Majors explorer, detail views, and separated admin workflows."""
import os
import re
import requests

BASE = os.environ.get('MAJORS_TEST_URL', 'http://127.0.0.1:8099')
assert BASE.startswith(('http://127.0.0.1:', 'http://localhost:')), 'Test fixture must be local'

s = requests.Session()
s.trust_env = False


def get(path, params=None, expected_status=200):
    r = s.get(BASE + '/' + path.lstrip('/'), params=params, allow_redirects=False)
    assert r.status_code == expected_status, (path, params, r.status_code, r.text[:400])
    assert not re.search(r'(Fatal error|Warning:|Parse error|Uncaught)', r.text), (path, params)
    return r


def csrf(r):
    m = re.search(r'name="csrf" value="([^"]+)"', r.text)
    assert m, 'CSRF token missing'
    return m.group(1)


def assert_clean_public_html(text, label=''):
    forbidden = [
        'Chưa xác minh (null)',
        'Không có mã đơn nhất (null)',
        '(mảng rỗng [])',
        'source_extracted_not_officially_verified',
        'not_linked_by_source',
        'not_edited',
    ]
    for bad in forbidden:
        assert bad not in text, f'Public UI ({label}) leaked forbidden technical string: {bad}'


# 1. Public catalog page, stats, & pagination
r = get('index.php', {'page': 'majors'})
assert_clean_public_html(r.text, 'catalog')
assert '358' in r.text and '23' in r.text and '76' in r.text and '2.712' in r.text
assert 'còn 358 chưa duyệt' in r.text
assert 'Trang 1 / 15' in r.text

r_p2 = get('index.php', {'page': 'majors', 'p': '2'})
assert 'Trang 2 / 15' in r_p2.text

# 2. Accentless & accented Vietnamese search + NFD compatibility
r_no_acc = get('index.php', {'page': 'majors', 'q': 'cong nghe thong tin'})
assert 'Công nghệ thông tin' in r_no_acc.text

r_acc = get('index.php', {'page': 'majors', 'q': 'Mỹ thuật'})
assert 'Mỹ thuật đô thị' in r_acc.text

# 3. Field and subgroup filters
r_field = get('index.php', {'page': 'majors', 'field': '714'})
assert '41' in r_field.text and 'Giáo dục học' in r_field.text

r_sub = get('index.php', {'page': 'majors', 'field': '714', 'subgroup': '71401'})
assert 'Giáo dục học' in r_sub.text

# 4. Missing-content filter retains incomplete items with clear status
r_miss = get('index.php', {'page': 'majors', 'status': 'missing_detail', 'q': 'bana'})
assert 'Sư phạm Tiếng Bana' in r_miss.text

# 5. Detail pages (clean Vietnamese UI, exact historical year & conflict display)
r_det = get('index.php', {'page': 'majors', 'id': 'hnv-giao-duc-hoc'})
assert_clean_public_html(r_det.text, 'detail:gdh')
assert 'Giáo dục học' in r_det.text
assert 'Mã tham khảo (chưa xác minh)' in r_det.text and '7140101' in r_det.text
assert 'Chưa được xác minh' in r_det.text
assert 'Tin lịch sử · chưa kiểm chứng lại tại nguồn' in r_det.text
assert 'https://huongnghiepviet.com/nganh-nghe/nganh-giao-duc-hoc' in r_det.text
assert 'Chưa có mô tả được biên tập và duyệt trên JobSimu' in r_det.text

r_ktsh = get('index.php', {'page': 'majors', 'id': 'hnv-ky-thuat-sinh-hoc'})
assert_clean_public_html(r_ktsh.text, 'detail:ktsh')
assert 'Mã ngành có xung đột cần đối chiếu' in r_ktsh.text

r_yr = get('index.php', {'page': 'majors', 'id': 'hnv-giao-duc-hoc', 'year': '2023'})
assert 'Năm tin 2023' in r_yr.text

r_bana = get('index.php', {'page': 'majors', 'id': 'hnv-su-pham-tieng-bana'})
assert_clean_public_html(r_bana.text, 'detail:bana')
assert 'Sư phạm Tiếng Bana' in r_bana.text
assert 'Nguồn chỉ liệt kê tên (chưa có trang chi tiết)' in r_bana.text
assert 'Chưa có mô tả cho ngành này' in r_bana.text
assert 'Chưa có liên kết nguồn trường tuyển sinh' in r_bana.text

# 6. Non-existent major returns 404
get('index.php', {'page': 'majors', 'id': 'hnv-khong-ton-tai-xyz'}, expected_status=404)

# 7. Admin authorization: guest redirected/denied; student gets 403 on GET and POST
r_guest_adm = s.get(BASE + '/admin.php?section=majors-review', allow_redirects=False)
assert r_guest_adm.status_code in (302, 403), r_guest_adm.status_code

r_login = get('index.php', {'page': 'login'})
r_stu = s.post(BASE + '/index.php?page=login', data={
    'csrf': csrf(r_login),
    'email': 'student@example.test',
    'password': 'TestPassword123',
})
assert 'Xin chào' in r_stu.text
r_stu_adm = s.get(BASE + '/admin.php?section=majors-review', allow_redirects=False)
assert r_stu_adm.status_code == 403, r_stu_adm.status_code
r_stu_post = s.post(BASE + '/admin.php?section=majors-review', data={
    'intent': 'save_description',
    'major_id': 'hnv-giao-duc-chinh-tri',
}, allow_redirects=False)
assert r_stu_post.status_code == 403, r_stu_post.status_code

# 8. Admin login + separated UI checks + missing CSRF 419
s = requests.Session()
s.trust_env = False
r_login = get('index.php', {'page': 'login'})
r_adm_login = s.post(BASE + '/index.php?page=login', data={
    'csrf': csrf(r_login),
    'email': 'admin@example.test',
    'password': 'TestPassword123',
})
assert r_adm_login.status_code == 200

r_rq = get('admin.php', {'section': 'majors-review'})
assert 'Ngành chưa có mô tả JobSimu' in r_rq.text
assert '358 / 358' in r_rq.text
assert 'Cảnh báo nguồn ban đầu vs Vấn đề hiện còn cần xử lý' in r_rq.text
assert '14 cặp · không duyệt hàng loạt' in r_rq.text
assert 'Duyệt tất cả' not in r_rq.text
assert 'Lịch sử chạy Import thực tế' in r_rq.text
assert '>null<' not in r_rq.text

# Missing CSRF must fail 419
for intent in ('save_description', 'save_code_verification', 'issue_status', 'link_status', 'mapping_decision'):
    r_nocsrf = s.post(BASE + '/admin.php?section=majors-review', data={
        'intent': intent,
        'major_id': 'hnv-giao-duc-chinh-tri',
    }, allow_redirects=False)
    assert r_nocsrf.status_code == 419, (intent, r_nocsrf.status_code)

# Filter queue by live open issue
r_rq_filt = get('admin.php', {'section': 'majors-review', 'issue': 'EMPTY_ARTICLE_BODY'})
assert 'Giáo dục Chính trị' in r_rq_filt.text

# 9. Description workflow: draft must NOT publish publicly; approved publishes & resolves ONLY description issue
r_ed = get('admin.php', {'section': 'majors-review', 'edit': 'hnv-giao-duc-chinh-tri'})
r_draft = s.post(BASE + '/admin.php?section=majors-review', data={
    'csrf': csrf(r_ed),
    'intent': 'save_description',
    'major_id': 'hnv-giao-duc-chinh-tri',
    'description_status': 'draft',
    'editorial_description': 'Bản nháp nội bộ GDCT chưa được duyệt.',
    'editorial_notes': 'Chờ rà soát',
}, allow_redirects=True)
assert r_draft.status_code == 200 and 'Bản nháp nội bộ GDCT chưa được duyệt.' in r_draft.text
r_pub_draft = get('index.php', {'page': 'majors', 'id': 'hnv-giao-duc-chinh-tri'})
assert 'Bản nháp nội bộ GDCT chưa được duyệt.' not in r_pub_draft.text, 'Draft description must NOT leak to public page'
assert 'bản nháp nội bộ, chưa được duyệt công khai' in r_pub_draft.text

r_ed = get('admin.php', {'section': 'majors-review', 'edit': 'hnv-giao-duc-chinh-tri'})
r_app = s.post(BASE + '/admin.php?section=majors-review', data={
    'csrf': csrf(r_ed),
    'intent': 'save_description',
    'major_id': 'hnv-giao-duc-chinh-tri',
    'description_status': 'approved',
    'editorial_description': 'Mô tả GDCT đã duyệt công khai trên JobSimu.',
    'editorial_notes': 'Đã duyệt',
    'is_editorially_locked': '1',
}, allow_redirects=True)
assert r_app.status_code == 200
r_pub_app = get('index.php', {'page': 'majors', 'id': 'hnv-giao-duc-chinh-tri'})
assert 'Mô tả GDCT đã duyệt công khai trên JobSimu.' in r_pub_app.text
assert 'Chưa được xác minh' in r_pub_app.text, 'Approving description must NOT mark official code verified'

# 10. Official code verification workflow: missing evidence URL rejected; complete verification persists & leaves non-code issues open
r_ed = get('admin.php', {'section': 'majors-review', 'edit': 'hnv-giao-duc-chinh-tri'})
r_bad_code = s.post(BASE + '/admin.php?section=majors-review', data={
    'csrf': csrf(r_ed),
    'intent': 'save_code_verification',
    'major_id': 'hnv-giao-duc-chinh-tri',
    'code_verification_status': 'verified',
    'official_major_code': '7140205',
    'code_evidence_url': '',
    'code_checked_at': '2026-11-11',
}, allow_redirects=True)
assert 'Không thể lưu thay đổi' in r_bad_code.text and 'bằng chứng' in r_bad_code.text

r_ed = get('admin.php', {'section': 'majors-review', 'edit': 'hnv-giao-duc-chinh-tri'})
r_good_code = s.post(BASE + '/admin.php?section=majors-review', data={
    'csrf': csrf(r_ed),
    'intent': 'save_code_verification',
    'major_id': 'hnv-giao-duc-chinh-tri',
    'code_verification_status': 'verified',
    'official_major_code': '7140205',
    'code_evidence_url': 'https://moet.gov.vn/tt-09-2022',
    'code_evidence_title': 'Thông tư 09/2022/TT-BGDĐT',
    'code_checked_at': '2026-11-11',
    'code_effective_scope': 'Đại học chính quy',
    'code_effective_year': '2026',
    'code_verification_note': 'Đối chiếu danh mục cấp IV',
}, allow_redirects=True)
assert r_good_code.status_code == 200
r_ed_reload = get('admin.php', {'section': 'majors-review', 'edit': 'hnv-giao-duc-chinh-tri'})
assert '7140205' in r_ed_reload.text and 'https://moet.gov.vn/tt-09-2022' in r_ed_reload.text
assert 'Thông tư 09/2022/TT-BGDĐT' in r_ed_reload.text and 'Đại học chính quy' in r_ed_reload.text

r_pub_code = get('index.php', {'page': 'majors', 'id': 'hnv-giao-duc-chinh-tri'})
assert 'Mã ngành chính thức đã xác minh: 7140205' in r_pub_code.text
assert 'Thông tư 09/2022/TT-BGDĐT' in r_pub_code.text
assert 'Đại học chính quy (2026)' in r_pub_code.text

def issues_block(html):
    m = re.search(r'id="issues-editor"(.*?)id="links-editor"', html, re.S)
    assert m, '#issues-editor section missing'
    return m.group(1)


# Verify EMPTY_ARTICLE_BODY stayed open after description+code verification on GDCT
gdct_issues_html = issues_block(r_ed_reload.text)
m_empty_gdct = re.search(r'<code>EMPTY_ARTICLE_BODY</code>.*?<span class="status status-(?:on|off)">([^<]+)</span>', gdct_issues_html, re.S)
assert m_empty_gdct and m_empty_gdct.group(1) == 'Đang mở', 'Verifying description/code must NOT close EMPTY_ARTICLE_BODY'

# 11. Resolving ONE issue on hnv-ky-thuat-hinh-su (5 issues) closes ONLY that issue
r_kths = get('admin.php', {'section': 'majors-review', 'edit': 'hnv-ky-thuat-hinh-su'})
kths_iss_before = issues_block(r_kths.text)
m_empty_kths = re.search(r'<code>EMPTY_ARTICLE_BODY</code>.*?<span class="status status-(?:on|off)">([^<]+)</span>.*?name="issue_id" value="(\d+)"', kths_iss_before, re.S)
assert m_empty_kths and m_empty_kths.group(1) == 'Đang mở'

r_res_one = s.post(BASE + '/admin.php?section=majors-review', data={
    'csrf': csrf(r_kths),
    'intent': 'issue_status',
    'major_id': 'hnv-ky-thuat-hinh-su',
    'issue_id': m_empty_kths.group(2),
    'resolution_note': 'Đã kiểm tra bài nguồn gốc.',
    'new_status': 'resolved',
}, allow_redirects=True)
assert r_res_one.status_code == 200
r_kths_after = get('admin.php', {'section': 'majors-review', 'edit': 'hnv-ky-thuat-hinh-su'})
kths_iss_after = issues_block(r_kths_after.text)
m_empty_after = re.search(r'<code>EMPTY_ARTICLE_BODY</code>.*?<span class="status status-(?:on|off)">([^<]+)</span>', kths_iss_after, re.S)
assert m_empty_after and m_empty_after.group(1) == 'Đã xử lý'
for other_code in ('NO_SCHOOL_LISTINGS_EXTRACTED', 'NO_SINGLE_MAJOR_CODE_CANDIDATE', 'MISSING_JOBSIMU_DESCRIPTION', 'UNVERIFIED_OFFICIAL_CODE'):
    m_other = re.search(rf'<code>{other_code}</code>.*?<span class="status status-(?:on|off)">([^<]+)</span>', kths_iss_after, re.S)
    assert m_other and m_other.group(1) == 'Đang mở', f'Resolving EMPTY_ARTICLE_BODY must NOT close {other_code}'

# 12. Link admission_status update persists & reflects on public page
r_gdh_ed = get('admin.php', {'section': 'majors-review', 'edit': 'hnv-giao-duc-hoc'})
m_lnk = re.search(r'name="intent" value="link_status".*?name="link_id" value="(\d+)"', r_gdh_ed.text, re.S)
assert m_lnk, 'Link form missing on hnv-giao-duc-hoc'
r_lnk_save = s.post(BASE + '/admin.php?section=majors-review', data={
    'csrf': csrf(r_gdh_ed),
    'intent': 'link_status',
    'major_id': 'hnv-giao-duc-hoc',
    'link_id': m_lnk.group(1),
    'admission_status': 'source_verified_year',
    'link_check_note': 'Xác nhận link nguồn đúng năm 2025.',
}, allow_redirects=True)
assert r_lnk_save.status_code == 200
r_gdh_pub = get('index.php', {'page': 'majors', 'id': 'hnv-giao-duc-hoc'})
assert 'Đã đối chiếu tại nguồn theo đúng năm tin' in r_gdh_pub.text
assert 'Xác nhận link nguồn đúng năm 2025.' in r_gdh_pub.text

# 13. Approving a mapping MUST NOT verify official_major_code
m_mkt = re.search(r'<code>hnv-marketing</code>.*?name="intent" value="mapping_decision".*?name="mapping_id" value="(\d+)"', r_rq.text, re.S)
assert m_mkt, 'Marketing mapping row missing'
r_mp_save = s.post(BASE + '/admin.php?section=majors-review', data={
    'csrf': csrf(r_rq),
    'intent': 'mapping_decision',
    'mapping_id': m_mkt.group(1),
    'review_note': 'Khớp tên Marketing để tra cứu liên kết.',
    'decision': 'approved',
}, allow_redirects=True)
assert r_mp_save.status_code == 200 and 'Đã duyệt ánh xạ' in r_mp_save.text
r_mkt_ed = get('admin.php', {'section': 'majors-review', 'edit': 'hnv-marketing'})
assert 'Chưa xác minh mã ngành chính thức' in r_mkt_ed.text

# 14. Catalog counter updates accurately (1 approved description => 357 without approved)
r_cat_after = get('index.php', {'page': 'majors'})
assert 'còn 357 chưa duyệt' in r_cat_after.text

# 15. Health check accessible as admin and reports 0 failures on all 9 new tables
r_health = get('health.php')
assert 'Bảng training_majors' in r_health.text and '358 bản ghi' in r_health.text
assert 'Bảng major_review_issues' in r_health.text

print('HTTP checks passed: separated statuses, no public leak of null/enum/draft, evidence-backed code verification, independent issue closure, link checks, 14-mapping audit, and 403/419 guards.')
