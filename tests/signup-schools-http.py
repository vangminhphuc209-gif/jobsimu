"""HTTP end-to-end tests for signup, schools API, profile update, and admin users view.
Run only against a local disposable test server (never production)."""
import os
import re
import sys
import requests

BASE = os.environ.get('SIGNUP_TEST_URL', 'http://127.0.0.1:8095')
assert BASE.startswith(('http://127.0.0.1:', 'http://localhost:')), 'Test server must be local'


def new_session():
    s = requests.Session()
    s.trust_env = False
    return s


def extract_csrf(html: str) -> str:
    m = re.search(r'name="csrf" value="([^"]+)"', html)
    assert m, 'CSRF token missing in response'
    return m.group(1)


def run():
    s = new_session()

    # 1. Inspect GET register page
    r = s.get(f'{BASE}/index.php', params={'page': 'register'})
    assert r.status_code == 200, f'GET register failed: {r.status_code}'
    html = r.text
    assert not re.search(r'(Fatal error|Warning:|Parse error)', html), 'PHP error on register page'

    # Required grouping, labels, placeholders, helper text
    assert 'Thông tin tài khoản' in html
    assert 'Thông tin trường học' in html
    assert 'Tỉnh/Thành phố' in html
    assert 'Chọn tỉnh/thành phố' in html
    assert 'Trường học' in html
    assert 'Vui lòng chọn tỉnh/thành phố trước' in html
    assert 'Hiện JobSimu hỗ trợ đăng ký cho các trường trong danh sách.' in html
    assert 'name="phone"' not in html and 'name="birthday"' not in html and 'name="address"' not in html

    # Check province select has exactly 3 real options (+1 placeholder)
    prov_match = re.search(r'<select[^>]*id="auth-province"[^>]*>(.*?)</select>', html, re.S)
    assert prov_match, 'Province select not found'
    prov_opts = re.findall(r'<option value="([^"]*)"[^>]*>(.*?)</option>', prov_match.group(1), re.S)
    assert prov_opts[0] == ('', 'Chọn tỉnh/thành phố'), f'Unexpected placeholder: {prov_opts[0]}'
    prov_names = [name.strip() for val, name in prov_opts[1:]]
    assert prov_names == ['Thành phố Hải Phòng', 'Tỉnh Điện Biên', 'Tỉnh Lai Châu'], f'Unexpected provinces: {prov_names}'

    # Check school select is initially disabled with required placeholder
    school_tag = re.search(r'<select[^>]*id="auth-school"[^>]*>', html, re.S)
    assert school_tag and 'disabled' in school_tag.group(0), 'School select must be initially disabled'
    form_match = re.search(r'<form[^>]*id="registerForm"[^>]*>(.*?)</form>', html, re.S)
    assert form_match and 'Khác' not in form_match.group(1), 'Must not contain "Khác" option inside register form'

    # Check grade select strictly offers Lớp 10, Lớp 11, Lớp 12 (no Lớp 6-9)
    assert 'Khối lớp' in html
    grade_match = re.search(r'<select[^>]*id="auth-grade"[^>]*>(.*?)</select>', html, re.S)
    assert grade_match, 'Grade select not found'
    grade_opts = re.findall(r'<option value="([^"]*)"[^>]*>(.*?)</option>', grade_match.group(1), re.S)
    assert grade_opts[0] == ('', 'Chọn khối lớp'), f'Unexpected grade placeholder: {grade_opts[0]}'
    assert [v for v, _ in grade_opts[1:]] == ['Lớp 10', 'Lớp 11', 'Lớp 12'], f'Unexpected grades: {grade_opts}'
    for forbidden in ('Lớp 6', 'Lớp 7', 'Lớp 8', 'Lớp 9'):
        assert forbidden not in grade_match.group(1), f'Forbidden grade {forbidden} found in register select'

    # 2. Verify JSON API single source of truth
    api_provs = s.get(f'{BASE}/index.php', params={'page': 'schools-api'}).json()
    assert api_provs['ok'] is True
    assert [p['name'] for p in api_provs['provinces']] == ['Thành phố Hải Phòng', 'Tỉnh Điện Biên', 'Tỉnh Lai Châu']
    prov_map = {p['code']: p['id'] for p in api_provs['provinces']}

    hp_api = s.get(f'{BASE}/index.php', params={'page': 'schools-api', 'province_id': prov_map['hai-phong']}).json()
    assert hp_api['ok'] is True
    assert [x['name'] for x in hp_api['schools']] == ['Trường THPT Lương Thế Vinh Hải Dương']

    db_api = s.get(f'{BASE}/index.php', params={'page': 'schools-api', 'province_id': prov_map['dien-bien']}).json()
    assert db_api['ok'] is True
    assert [x['name'] for x in db_api['schools']] == ['Trường PTDTNT THPT tỉnh Điện Biên']

    lc_api = s.get(f'{BASE}/index.php', params={'page': 'schools-api', 'province_id': prov_map['lai-chau']}).json()
    assert lc_api['ok'] is True
    assert [x['name'] for x in lc_api['schools']] == [
        'Trường THPT Chu Văn An',
        'Trường PTDTNT THPT Tỉnh',
        'Trường THPT Quyết Thắng',
        'Trường PTDTNT THPT Mường Tè',
    ]

    bad_api = s.get(f'{BASE}/index.php', params={'page': 'schools-api', 'province_id': 99999})
    assert bad_api.status_code == 400 and bad_api.json()['ok'] is False

    hp_school_id = hp_api['schools'][0]['id']
    db_school_id = db_api['schools'][0]['id']
    lc_cva_id = lc_api['schools'][0]['id']

    # 3. Backend rejects missing province & school and preserves entered fields
    csrf = extract_csrf(html)
    r_missing = s.post(f'{BASE}/index.php?page=register', data={
        'csrf': csrf,
        'name': 'Nguyễn Minh Anh',
        'email': 'minhanh.missing@example.test',
        'password': 'Password123',
        'grade': 'Lớp 11',
        'province_id': '',
        'school_id': '',
        'agree': '1',
    }, allow_redirects=False)
    assert r_missing.status_code == 422, f'Expected 422 on missing province/school, got {r_missing.status_code}'
    assert 'Vui lòng chọn tỉnh/thành phố.' in r_missing.text
    assert 'value="Nguyễn Minh Anh"' in r_missing.text
    assert 'value="minhanh.missing@example.test"' in r_missing.text
    assert 'value="Lớp 11" selected' in r_missing.text

    # 3b. Backend rejects out-of-range grades (Lớp 8 / Lớp 9 / empty) on direct POST
    csrf = extract_csrf(r_missing.text)
    r_bad_grade = s.post(f'{BASE}/index.php?page=register', data={
        'csrf': csrf,
        'name': 'Học Sinh Lớp 8',
        'email': 'lop8.blocked@example.test',
        'password': 'Password123',
        'grade': 'Lớp 8',
        'province_id': str(prov_map['hai-phong']),
        'school_id': str(hp_school_id),
        'agree': '1',
    }, allow_redirects=False)
    assert r_bad_grade.status_code == 422, f'Expected 422 on Lớp 8 signup, got {r_bad_grade.status_code}'
    assert 'Lớp 10, Lớp 11, Lớp 12' in r_bad_grade.text

    # 4. Backend rejects cross-province mismatch (Hải Phòng + Chu Văn An Lai Châu)
    csrf = extract_csrf(r_bad_grade.text)
    r_mismatch = s.post(f'{BASE}/index.php?page=register', data={
        'csrf': csrf,
        'name': 'Nguyễn Minh Anh',
        'email': 'minhanh.mismatch@example.test',
        'password': 'Password123',
        'grade': 'Lớp 12',
        'province_id': str(prov_map['hai-phong']),
        'school_id': str(lc_cva_id),
        'agree': '1',
    }, allow_redirects=False)
    assert r_mismatch.status_code == 422, f'Expected 422 on mismatch, got {r_mismatch.status_code}'
    assert 'Trường học đã chọn không thuộc tỉnh/thành phố tương ứng.' in r_mismatch.text

    # 5. Backend rejects out-of-list school/province IDs
    csrf = extract_csrf(r_mismatch.text)
    r_ool = s.post(f'{BASE}/index.php?page=register', data={
        'csrf': csrf,
        'name': 'Nguyễn Minh Anh',
        'email': 'minhanh.ool@example.test',
        'password': 'Password123',
        'grade': 'Lớp 12',
        'province_id': '99999',
        'school_id': '88888',
        'agree': '1',
    }, allow_redirects=False)
    assert r_ool.status_code == 422
    assert 'Tỉnh/Thành phố không nằm trong danh sách hỗ trợ.' in r_ool.text
    assert 'Trường học không nằm trong danh sách được phép đăng ký.' in r_ool.text

    # 6. Successful signup for Hải Phòng -> redirects to existing onboarding (page=assessment)
    csrf = extract_csrf(r_ool.text)
    r_ok = s.post(f'{BASE}/index.php?page=register', data={
        'csrf': csrf,
        'name': 'Trần Hải Đăng',
        'email': 'haidang.hp@example.test',
        'password': 'Password123',
        'grade': 'Lớp 11',
        'province_id': str(prov_map['hai-phong']),
        'school_id': str(hp_school_id),
        'agree': '1',
    }, allow_redirects=False)
    assert r_ok.status_code in (301, 302), f'Expected redirect on valid signup, got {r_ok.status_code}'
    assert 'page=assessment' in r_ok.headers.get('Location', ''), f'Unexpected redirect: {r_ok.headers.get("Location")}'

    # Dashboard displays derived province & school
    r_dash = s.get(f'{BASE}/index.php', params={'page': 'dashboard'})
    assert r_dash.status_code == 200
    assert 'Thành phố Hải Phòng' in r_dash.text
    assert 'Trường THPT Lương Thế Vinh Hải Dương' in r_dash.text

    # Profile update rejects cross-province mismatch, then succeeds with Điện Biên
    r_set = s.get(f'{BASE}/index.php', params={'page': 'dashboard', 'tab': 'settings'})
    assert r_set.status_code == 200
    csrf_prof = extract_csrf(r_set.text)
    r_prof_bad = s.post(f'{BASE}/index.php?page=profile', data={
        'csrf': csrf_prof,
        'name': 'Trần Hải Đăng',
        'grade': 'Lớp 12',
        'province_id': str(prov_map['dien-bien']),
        'school_id': str(hp_school_id),
    }, allow_redirects=False)
    assert r_prof_bad.status_code == 422
    assert 'Trường học đã chọn không thuộc tỉnh/thành phố tương ứng.' in r_prof_bad.text

    csrf_prof2 = extract_csrf(r_prof_bad.text)
    r_prof_ok = s.post(f'{BASE}/index.php?page=profile', data={
        'csrf': csrf_prof2,
        'name': 'Trần Hải Đăng',
        'grade': 'Lớp 12',
        'province_id': str(prov_map['dien-bien']),
        'school_id': str(db_school_id),
    }, allow_redirects=False)
    assert r_prof_ok.status_code in (301, 302)
    r_dash2 = s.get(f'{BASE}/index.php', params={'page': 'dashboard'})
    assert 'Tỉnh Điện Biên' in r_dash2.text
    assert 'Trường PTDTNT THPT tỉnh Điện Biên' in r_dash2.text

    # 7. Duplicate email rejected inline with preserved values
    s2 = new_session()
    r_reg2 = s2.get(f'{BASE}/index.php', params={'page': 'register'})
    r_dup = s2.post(f'{BASE}/index.php?page=register', data={
        'csrf': extract_csrf(r_reg2.text),
        'name': 'Người Trùng Email',
        'email': 'haidang.hp@example.test',
        'password': 'Password123',
        'grade': 'Lớp 10',
        'province_id': str(prov_map['lai-chau']),
        'school_id': str(lc_cva_id),
        'agree': '1',
    }, allow_redirects=False)
    assert r_dup.status_code == 422
    assert 'Email này đã được sử dụng.' in r_dup.text
    assert 'value="Người Trùng Email"' in r_dup.text

    # 8. Legacy seeded user login shows Tỉnh Lai Châu + preserves school
    s_leg = new_session()
    r_login = s_leg.get(f'{BASE}/index.php', params={'page': 'login'})
    r_leg_post = s_leg.post(f'{BASE}/index.php?page=login', data={
        'csrf': extract_csrf(r_login.text),
        'email': 'legacy_tinh@example.test',
        'password': 'TestPassword123',
    })
    assert r_leg_post.status_code == 200
    assert 'Tỉnh Lai Châu' in r_leg_post.text
    assert 'Trường PTDTNT THPT Tỉnh' in r_leg_post.text

    # 9. Admin users view shows derived province & school consistently
    s_adm = new_session()
    r_adm_login = s_adm.get(f'{BASE}/index.php', params={'page': 'login'})
    s_adm.post(f'{BASE}/index.php?page=login', data={
        'csrf': extract_csrf(r_adm_login.text),
        'email': 'admin@example.test',
        'password': 'TestPassword123',
    })
    r_adm_users = s_adm.get(f'{BASE}/admin.php', params={'section': 'users'})
    assert r_adm_users.status_code == 200
    assert 'Tỉnh/Thành phố' in r_adm_users.text
    assert 'Tỉnh Điện Biên' in r_adm_users.text
    assert 'Trường PTDTNT THPT tỉnh Điện Biên' in r_adm_users.text
    assert 'Tỉnh Lai Châu' in r_adm_users.text
    assert 'Trường PTDTNT THPT Tỉnh' in r_adm_users.text
    assert 'THCS Bình Minh' in r_adm_users.text

    print('All HTTP signup, API, profile, legacy preservation, and admin checks passed!')


if __name__ == '__main__':
    run()
