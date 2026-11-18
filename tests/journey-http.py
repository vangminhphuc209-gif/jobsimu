"""HTTP end-to-end tests for the 4-step JobSimu career guidance journey:
  - Chatbot rename ("Trợ lý La Bàn", subtitle, greeting, starter chips)
  - Step 1: Discover (page + discover-api save/reload)
  - Step 2: Lookup (3-card landing, Majors tab + detail, Schools tab nationwide, Combinations tab any/all)
  - Step 3: Compare (picker -> 20-question save/score -> confirm primary/backup + cross-account isolation)
  - Step 4: Plan (handoff via comparison_id, save & reload, AI draft fallback, revocable read-only share link)
  - Regression checks for existing pages (login, assessment, simulations, careers, majors, admin)

Run ONLY against a local disposable test server (never production).
"""
import json
import os
import re
import requests

BASE = os.environ.get('JOURNEY_TEST_URL', 'http://127.0.0.1:8095')
assert BASE.startswith(('http://127.0.0.1:', 'http://localhost:')), 'Test server must be local'


def new_session():
    s = requests.Session()
    s.trust_env = False
    return s


def extract_csrf(html: str) -> str:
    m = re.search(r'name="csrf" value="([^"]+)"', html)
    if m:
        return m.group(1)
    m2 = re.search(r'"csrf"\s*:\s*"([^"]+)"', html)
    assert m2, 'CSRF token missing in response'
    return m2.group(1)


def assert_clean_html(r, label: str):
    assert r.status_code == 200, f'{label} returned HTTP {r.status_code}'
    assert not re.search(r'(Fatal error|Warning:|Parse error|Uncaught )', r.text), f'PHP error in {label}'


def login_as(email: str, password: str):
    s = new_session()
    r_get = s.get(f'{BASE}/index.php', params={'page': 'login'})
    assert_clean_html(r_get, 'GET login')
    csrf = extract_csrf(r_get.text)
    r_post = s.post(f'{BASE}/index.php?page=login', data={
        'csrf': csrf,
        'email': email,
        'password': password,
    })
    assert_clean_html(r_post, f'POST login {email}')
    return s


def run():
    print('=== Running 4-Step Journey HTTP E2E Tests ===')

    # 1. Home page & Chatbot rename checks
    s_guest = new_session()
    r_home = s_guest.get(f'{BASE}/index.php')
    assert_clean_html(r_home, 'Home page')
    assert 'Trợ lý La Bàn' in r_home.text, 'Home page renders Trợ lý La Bàn widget'
    assert 'Cùng bạn tìm hướng nghề nghiệp' in r_home.text, 'Widget renders required subtitle'
    assert 'Mây - Trợ Lý AI' not in r_home.text, 'Old chatbot display name must not appear'
    assert 'Khám phá bản thân' in r_home.text
    assert 'Tra cứu' in r_home.text
    assert 'So sánh' in r_home.text
    assert 'Lập kế hoạch' in r_home.text
    print('✓ Home page & Trợ lý La Bàn rename verified')

    # 2. Login as Student A (legacy Lớp 11 student) and Student B (for account isolation)
    sA = login_as('legacy_tinh@example.test', 'TestPassword123')
    sB = login_as('student_b@example.test', 'TestPassword123')

    # 3. Step 1: Discover page & API
    r_disc = sA.get(f'{BASE}/index.php', params={'page': 'discover'})
    assert_clean_html(r_disc, 'GET discover')
    assert 'Bạn thích và học tốt những môn nào nhất?' in r_disc.text
    csrfA = extract_csrf(r_disc.text)

    r_disc_api = sA.post(
        f'{BASE}/index.php?page=discover-api',
        headers={'X-CSRF-Token': csrfA},
        json={
            'csrf': csrfA,
            'answers': {
                'q1_subjects': {'selected': ['toan', 'tin_hoc', 'ngoai_ngu']},
                'q2_activities': {'selected': ['may_moc_cong_nghe', 'tinh_toan_phan_tich']},
                'q3_team_role': {'selected': ['tim_hieu_phan_tich']},
                'q4_values': {'selected': ['co_hoi_phat_trien', 'thu_nhap']},
                'q5_concern': {'selected': ['phan_van_vai_lua_chon']},
            },
        },
    )
    assert r_disc_api.status_code == 200, f'discover-api failed: {r_disc_api.text}'
    disc_data = r_disc_api.json()
    assert disc_data['ok'] is True
    assert '%' not in json.dumps(disc_data['summary'], ensure_ascii=False), 'Discover summary must not show %'
    assert len(disc_data['summary']['directions']) >= 1
    print('✓ Step 1 Discover page & API verified')

    # 4. Step 2: Lookup landing (3 cards, no heavy table on landing) + 3 API tabs + major detail
    r_lkp = sA.get(f'{BASE}/index.php', params={'page': 'lookup'})
    assert_clean_html(r_lkp, 'GET lookup landing')
    assert 'Ngành học' in r_lkp.text
    assert 'Trường đào tạo' in r_lkp.text
    assert 'Tổ hợp xét tuyển' in r_lkp.text

    # Tab A: Majors API
    r_maj = sA.get(f'{BASE}/index.php', params={'page': 'lookup-api', 'tab': 'majors', 'q': 'cong nghe thong tin'})
    assert r_maj.status_code == 200
    d_maj = r_maj.json()
    assert d_maj['ok'] is True and d_maj['data']['total'] >= 1
    first_major_id = d_maj['data']['items'][0]['id']
    first_major_name = d_maj['data']['items'][0]['name']

    # Major detail view
    r_mdet = sA.get(f'{BASE}/index.php', params={'page': 'lookup', 'tab': 'majors', 'major_id': first_major_id})
    assert_clean_html(r_mdet, 'GET lookup major detail')
    assert 'Thêm vào so sánh' in r_mdet.text

    # Tab B: Schools API (nationwide, not limited to 3 signup provinces)
    r_sch = sA.get(f'{BASE}/index.php', params={'page': 'lookup-api', 'tab': 'schools'})
    assert r_sch.status_code == 200
    d_sch = r_sch.json()
    assert d_sch['ok'] is True and d_sch['data']['total'] >= 1
    school_provs = {item.get('province') for item in d_sch['data']['items']}
    assert any(p not in ('Thành phố Hải Phòng', 'Tỉnh Điện Biên', 'Tỉnh Lai Châu') for p in school_provs if p), \
        f'Expected nationwide university provinces, got {school_provs}'

    # Tab C: Combinations API (accentless subject search + any/all mode)
    r_cmb = sA.get(f'{BASE}/index.php', params={'page': 'lookup-api', 'tab': 'combinations', 'q': 'vat ly', 'match_mode': 'any'})
    assert r_cmb.status_code == 200
    d_cmb = r_cmb.json()
    assert d_cmb['ok'] is True and len(d_cmb['data']['combinations_catalog']) >= 1
    assert any('Vật lý' in item['subjects_text'] for item in d_cmb['data']['combinations_catalog'])
    print('✓ Step 2 Lookup landing, 3 API tabs, nationwide scope & major detail verified')

    # 5. Step 3: Compare (picker, 20 questions, scoring, confirm, cross-account isolation)
    r_cmp = sA.get(f'{BASE}/index.php', params={'page': 'compare'})
    assert_clean_html(r_cmp, 'GET compare')
    assert 'Hiểu mình cần gì' in r_cmp.text
    csrfCmpA = extract_csrf(r_cmp.text)

    options_payload = [
        {'id': f'major:{first_major_id}', 'label': first_major_name, 'type': 'major', 'major_id': first_major_id},
        {'id': 'custom:kinh_te_1', 'label': 'Kinh tế quốc tế', 'type': 'custom'},
    ]
    # Exact 45 / 35 / 20 split
    answers_payload = {
        '1': options_payload[0]['id'], '2': options_payload[0]['id'], '3': options_payload[0]['id'],
        '4': options_payload[0]['id'], '5': options_payload[0]['id'], '6': options_payload[0]['id'],
        '7': options_payload[0]['id'], '10': options_payload[0]['id'],
        '8': options_payload[1]['id'], '9': options_payload[1]['id'], '11': options_payload[1]['id'],
        '12': options_payload[1]['id'], '13': options_payload[1]['id'], '14': options_payload[1]['id'],
        '15': options_payload[1]['id'], '17': options_payload[1]['id'],
        '16': 'skip', '18': 'skip', '19': 'skip', '20': 'skip',
    }
    r_cmp_save = sA.post(
        f'{BASE}/index.php?page=compare-api',
        headers={'X-CSRF-Token': csrfCmpA},
        json={
            'csrf': csrfCmpA,
            'action': 'save',
            'status': 'confirmed',
            'options': options_payload,
            'answers': answers_payload,
            'research_notes': [{'question_id': 'q16', 'question_title': 'Chi phí học tập', 'note': 'Kiểm tra học phí chính thức'}],
            'primary_option_id': options_payload[0]['id'],
            'backup_option_id': options_payload[1]['id'],
        },
    )
    assert r_cmp_save.status_code == 200, f'compare-api save failed: {r_cmp_save.text}'
    d_cmp = r_cmp_save.json()
    assert d_cmp['ok'] is True
    cmp_uid = d_cmp['uid']
    assert cmp_uid, 'compare-api did not return comparison uid'
    assert d_cmp['result']['ranked_options'][0]['score'] == 45
    assert d_cmp['result']['ranked_options'][1]['score'] == 35
    assert d_cmp['result']['unallocated'] == 20

    # Grounded info snippet endpoint
    r_cmp_info = sA.post(
        f'{BASE}/index.php?page=compare-api',
        headers={'X-CSRF-Token': csrfCmpA},
        json={'csrf': csrfCmpA, 'action': 'info', 'options': options_payload},
    )
    assert r_cmp_info.status_code == 200 and r_cmp_info.json()['ok'] is True

    # Cross-account isolation: Student B MUST NOT load Student A's comparison_id
    r_plan_b = sB.get(f'{BASE}/index.php', params={'page': 'plan', 'comparison_id': cmp_uid})
    assert_clean_html(r_plan_b, 'GET plan as Student B with Student A comparison_id')
    assert 'Kinh tế quốc tế' not in r_plan_b.text, 'Account isolation violated: Student B saw Student A comparison data!'
    print('✓ Step 3 Compare API, exact 45/35/20 scoring, info lookup & account isolation verified')

    # 6. Step 4: Plan handoff, save/reload, AI draft, and revocable read-only share link
    r_plan_a = sA.get(f'{BASE}/index.php', params={'page': 'plan', 'comparison_id': cmp_uid})
    assert_clean_html(r_plan_a, 'GET plan with Student A comparison_id')
    assert first_major_name in r_plan_a.text
    assert 'Kinh tế quốc tế' in r_plan_a.text
    csrfPlanA = extract_csrf(r_plan_a.text)

    # Save edited plan
    r_plan_save = sA.post(
        f'{BASE}/index.php?page=plan-api',
        headers={'X-CSRF-Token': csrfPlanA},
        json={
            'csrf': csrfPlanA,
            'action': 'save',
            'comparison_uid': cmp_uid,
            'primary_label': first_major_name,
            'backup_label': 'Kinh tế quốc tế',
            'grade': 'Lớp 11',
            'horizon': '6_months',
            'start_month': '2026-12',
            'items': [
                {
                    'month_key': '2026-12',
                    'deadline_kind': 'self',
                    'goal': 'Củng cố Toán & Vật lý học kỳ I',
                    'tasks': 'Làm 3 đề mỗi tuần\nTổng hợp lỗi sai',
                    'expected_outcome': 'Điểm trung bình >= 8.0',
                    'status': 'in_progress',
                },
                {
                    'month_key': '2027-02',
                    'deadline_kind': 'official',
                    'official_source_url': '',  # Missing URL -> must be downgraded to 'self'
                    'goal': 'Kiểm tra đề án tuyển sinh',
                    'tasks': 'Đọc thông báo chính thức của trường',
                    'expected_outcome': 'Chốt danh sách 3 trường mục tiêu',
                    'status': 'not_started',
                },
            ],
        },
    )
    assert r_plan_save.status_code == 200, f'plan-api save failed: {r_plan_save.text}'
    d_plan = r_plan_save.json()
    assert d_plan['ok'] is True
    plan_uid = d_plan['plan']['uid']
    assert d_plan['plan']['items'][1]['deadline_kind'] == 'self', 'Official deadline without source_url must be downgraded to self'

    # Reload plan page -> verify persistence
    r_plan_reload = sA.get(f'{BASE}/index.php', params={'page': 'plan'})
    assert_clean_html(r_plan_reload, 'Reload plan page')
    assert 'Củng cố Toán' in r_plan_reload.text

    # AI draft preview (works with either live Gemini or deterministic fallback)
    r_ai_draft = sA.post(
        f'{BASE}/index.php?page=plan-api',
        headers={'X-CSRF-Token': csrfPlanA},
        json={
            'csrf': csrfPlanA,
            'action': 'ai_draft',
            'primary_label': first_major_name,
            'backup_label': 'Kinh tế quốc tế',
            'grade': 'Lớp 11',
            'horizon': '6_months',
        },
    )
    assert r_ai_draft.status_code == 200, f'plan-api ai_draft failed: {r_ai_draft.text}'
    d_ai = r_ai_draft.json()
    assert d_ai['ok'] is True and len(d_ai['items']) >= 3

    # Enable read-only share link
    r_sh_on = sA.post(
        f'{BASE}/index.php?page=plan-api',
        headers={'X-CSRF-Token': csrfPlanA},
        json={'csrf': csrfPlanA, 'action': 'toggle_share', 'uid': plan_uid, 'enable': True},
    )
    assert r_sh_on.status_code == 200 and r_sh_on.json()['ok'] is True, f'toggle_share on failed: {r_sh_on.text}'
    share_token = r_sh_on.json()['share_token']
    assert share_token

    # Unauthenticated guest can view read-only shared plan
    r_pub = s_guest.get(f'{BASE}/index.php', params={'page': 'plan-share', 'token': share_token})
    assert_clean_html(r_pub, 'GET public plan-share')
    assert 'Củng cố Toán' in r_pub.text
    assert 'chỉ đọc' in r_pub.text.lower()

    # Student B cannot revoke Student A's share link
    r_plan_b2 = sB.get(f'{BASE}/index.php', params={'page': 'plan'})
    csrfPlanB = extract_csrf(r_plan_b2.text)
    r_sh_b_revoke = sB.post(
        f'{BASE}/index.php?page=plan-api',
        headers={'X-CSRF-Token': csrfPlanB},
        json={'csrf': csrfPlanB, 'action': 'toggle_share', 'uid': plan_uid, 'enable': False},
    )
    assert r_sh_b_revoke.status_code == 404, f'Student B should not revoke Student A plan, got {r_sh_b_revoke.status_code}'

    # Student A revokes share link -> public URL immediately returns 404
    r_sh_off = sA.post(
        f'{BASE}/index.php?page=plan-api',
        headers={'X-CSRF-Token': csrfPlanA},
        json={'csrf': csrfPlanA, 'action': 'toggle_share', 'uid': plan_uid, 'enable': False},
    )
    assert r_sh_off.status_code == 200 and r_sh_off.json()['ok'] is True
    r_pub_revoked = s_guest.get(f'{BASE}/index.php', params={'page': 'plan-share', 'token': share_token})
    assert r_pub_revoked.status_code == 404, f'Revoked share link must return 404, got {r_pub_revoked.status_code}'
    print('✓ Step 4 Plan handoff, save/reload, AI draft & revocable read-only share link verified')

    # 7. Existing flows & legacy assessments regression checks
    for existing_page in (
        'assessment', 'assessment-hub', 'test-big5', 'test-caas', 'test-mbti', 'test-eq',
        'assessment-report', 'soka-mirror', 'simulations', 'simulation-3d',
        'careers', 'majors', 'university-explorer', 'university-compare',
        'knowledge-hub', 'ai-roadmap', 'dashboard'
    ):
        r_ex = sA.get(f'{BASE}/index.php', params={'page': existing_page})
        assert_clean_html(r_ex, f'Existing page={existing_page}')

    # Legacy Lớp 8 student login still works, keeps Lớp 8 & THCS Bình Minh, and can update display name without being forced to switch to Lớp 10
    s_l8 = login_as('legacy_lop8@example.test', 'TestPassword123')
    r_l8_dash = s_l8.get(f'{BASE}/index.php', params={'page': 'dashboard', 'tab': 'settings'})
    assert_clean_html(r_l8_dash, 'Legacy Lớp 8 profile settings')
    assert 'Lớp 8' in r_l8_dash.text, 'Legacy Lớp 8 preserved on profile page'
    assert 'JobSimu hiện dành cho học sinh THPT (Lớp 10–12)' in r_l8_dash.text, 'Legacy Lớp 8 sees clear completion guidance'

    r_l8_save = s_l8.post(f'{BASE}/index.php?page=profile', data={
        'csrf': extract_csrf(r_l8_dash.text),
        'name': 'Học Sinh Lớp 8 (Giữ Nguyên)',
        'grade': 'Lớp 8',
        'province_id': '',
        'school_id': '',
    }, allow_redirects=False)
    assert r_l8_save.status_code in (301, 302), f'Legacy Lớp 8 student should still be able to update profile keeping Lớp 8, got {r_l8_save.status_code}'
    r_l8_verify = s_l8.get(f'{BASE}/index.php', params={'page': 'dashboard'})
    assert 'Lớp 8' in r_l8_verify.text and 'THCS Bình Minh' in r_l8_verify.text, 'Legacy Lớp 8 and THCS Bình Minh preserved after profile edit'

    # Existing Lớp 11 student still CANNOT change their grade to Lớp 8
    r_sA_set = sA.get(f'{BASE}/index.php', params={'page': 'dashboard', 'tab': 'settings'})
    r_sA_bad = sA.post(f'{BASE}/index.php?page=profile', data={
        'csrf': extract_csrf(r_sA_set.text),
        'name': 'Nguyễn Gia Huy',
        'grade': 'Lớp 8',
        'province_id': '',
        'school_id': '',
    }, allow_redirects=False)
    assert r_sA_bad.status_code == 422, f'Existing Lớp 11 student must not be allowed to change grade to Lớp 8, got {r_sA_bad.status_code}'

    # Admin login & profile update with blank grade/school still allowed
    s_adm = login_as('admin@example.test', 'TestPassword123')
    r_adm_set = s_adm.get(f'{BASE}/index.php', params={'page': 'dashboard', 'tab': 'settings'})
    assert_clean_html(r_adm_set, 'Admin profile settings')
    r_adm_save = s_adm.post(f'{BASE}/index.php?page=profile', data={
        'csrf': extract_csrf(r_adm_set.text),
        'name': 'Admin JobSimu',
        'grade': '',
        'province_id': '',
        'school_id': '',
    }, allow_redirects=False)
    assert r_adm_save.status_code in (301, 302), f'Admin profile update should not be blocked by student grade/school rules, got {r_adm_save.status_code}'

    r_adm_panel = s_adm.get(f'{BASE}/admin.php', params={'section': 'chatbot'})
    assert_clean_html(r_adm_panel, 'Admin chatbot panel')
    assert 'Trợ lý La Bàn' in r_adm_panel.text
    print('✓ All legacy assessments, university-compare, knowledge-hub, ai-roadmap, Lớp 8 preservation & admin checks passed')
    print('=== All 4-Step Journey HTTP E2E Tests Passed! ===')


if __name__ == '__main__':
    run()
