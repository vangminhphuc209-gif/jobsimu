#!/usr/bin/env python3
"""Collect factual catalogue metadata, not copyrighted article bodies.
Row-aware HTML table extraction preserving exact major_name - raw_code - level
relationships without truncating specialization codes or fuzzy-matching names.
Dependencies: requests, beautifulsoup4. Run: python collect.py --out PATH --cache PATH
"""
import argparse, concurrent.futures, hashlib, json, re, time, unicodedata
from pathlib import Path
from datetime import datetime, timezone
from urllib.parse import urljoin
import requests
from bs4 import BeautifulSoup

BASE = 'https://huongnghiepviet.com'
INDEX = BASE + '/nganh-nghe'

MOET_7_RE = re.compile(r'(?:^|(?<![A-Za-z0-9_]))([56789]\d{6})(?![A-Za-z0-9_])')
CODE_TOKEN_RE = re.compile(r'^(?:[56789]\d{6}[A-Za-z0-9._-]{0,8}|[A-Z]{1,4}\d{1,3}[A-Za-z0-9-]{0,6}|7\d{2}-[A-Za-z0-9-]+)$')
COMB_RE = re.compile(r'(?<![A-Za-z0-9])[A-Z]\d{2}(?!\d)')


def clean(x):
    return re.sub(r'\s+', ' ', x or '').strip()


def txt(n):
    return clean(n.get_text(' ', strip=True)) if n else ''


def nfc(x):
    return unicodedata.normalize('NFC', clean(x))


def normalize_major_name_exact(x):
    s = nfc(x)
    s = re.sub(r'^ngành\s+', '', s, flags=re.I)
    s = s.replace('–', '-').replace('—', '-')
    s = re.sub(r'\s*-\s*', ' - ', s)
    s = re.sub(r'\s+', ' ', s).strip().lower()
    return s


def slug(x):
    x = unicodedata.normalize('NFD', x.lower().replace('đ', 'd'))
    return re.sub('[^a-z0-9]+', '-', ''.join(c for c in x if not unicodedata.combining(c))).strip('-')


def infer_level_from_moet_code(code):
    if not code or not MOET_7_RE.fullmatch(code):
        return None
    return {
        '5': 'Trung cấp',
        '6': 'Cao đẳng',
        '7': 'Đại học',
        '8': 'Thạc sĩ',
        '9': 'Tiến sĩ',
    }.get(code[0])


def detect_level_text(text):
    t = nfc(text).lower()
    if not t:
        return None
    if 'tiến sỹ' in t or 'tiến sĩ' in t:
        return 'Tiến sĩ'
    if 'thạc sỹ' in t or 'thạc sĩ' in t:
        return 'Thạc sĩ'
    if 'đại học' in t:
        return 'Đại học'
    if 'cao đẳng' in t:
        return 'Cao đẳng'
    if 'trung cấp' in t:
        return 'Trung cấp'
    return None


def get(url, cache):
    path = cache / (hashlib.sha256(url.encode()).hexdigest() + '.json')
    if path.exists():
        return json.loads(path.read_text())
    for attempt in range(3):
        try:
            r = requests.get(url, timeout=35, headers={'User-Agent': 'JobSimu-Research/1.0 (educational catalogue indexing)'})
            data = {'http_status': r.status_code, 'final_url': r.url, 'retrieved_at': datetime.now(timezone.utc).isoformat(), 'html': r.text}
            if r.status_code >= 500:
                raise RuntimeError('HTTP ' + str(r.status_code))
            path.write_text(json.dumps(data, ensure_ascii=False))
            return data
        except Exception as e:
            if attempt == 2:
                return {'http_status': None, 'final_url': url, 'retrieved_at': datetime.now(timezone.utc).isoformat(), 'html': '', 'error': str(e)}
            time.sleep(1 + attempt)


def catalogue(html, fetched):
    # Source contains a malformed </spaan> closing an anchor. Repair before parsing.
    s = BeautifulSoup(html.replace('</spaan>', '</a>'), 'html.parser')
    root = s.select_one('.danhsach_nganhnghe')
    if not root:
        raise ValueError('Catalogue selector missing')
    groups = []
    subgroups = []
    records = []
    group = None
    sub = None
    for n in root.find_all(['h2', 'h3', 'a', 'span']):
        if n.name == 'h2':
            group = {'id': n.get('id'), 'name': txt(n), 'source_url': INDEX}
            groups.append(group)
            sub = None
        elif n.name == 'h3':
            sub = {'id': n.get('id'), 'name': txt(n), 'group_id': group['id'], 'source_url': INDEX}
            subgroups.append(sub)
        elif n.name == 'a' or 'none_content_nganhnghe' in n.get('class', []):
            name = txt(n)
            if not name:
                continue
            normalized = re.sub(r'^Ngành\s+', '', name, flags=re.I)
            badge = n.parent.select_one('.badge')
            count = re.search(r'\d+', txt(badge)) if badge else None
            records.append({
                'id': 'hnv-' + slug(normalized),
                'name': normalized,
                'source_label': name,
                'entity_type': 'training_major',
                'group_id': group['id'],
                'subgroup_id': sub['id'] if sub else None,
                'source_dom_code': n.get('id'),
                'source_url': urljoin(BASE, n['href']) if n.get('href') else None,
                'catalogue_url': INDEX,
                'listed_school_count': int(count[0]) if count else None,
                'listed_school_count_year': None,
                'retrieved_at': fetched,
                'verification_status': 'source_extracted_not_officially_verified',
                'detail_status': 'pending' if n.get('href') else 'not_linked_by_source',
            })
    return groups, subgroups, records


def parse_table_rows(block, target_major_name, block_level_label=None):
    """Parse admission <table> elements row-by-row preserving exact name-code-level bindings."""
    target_norm = normalize_major_name_exact(target_major_name)
    rows_out = []

    for tbl in block.select('table'):
        trs = tbl.find_all('tr')
        if not trs:
            continue

        col_idx = {'name': None, 'code': None, 'comb': None, 'level': None}
        start_row = 0
        first_cells = [txt(c) for c in trs[0].find_all(['th', 'td'])]
        first_has_th = bool(trs[0].find_all('th'))
        header_joined = ' '.join(first_cells).lower()
        looks_like_header = first_has_th or any(k in header_joined for k in ['tên ngành', 'mã ngành', 'mã xét tuyển', 'tổ hợp', 'chỉ tiêu'])
        if looks_like_header:
            start_row = 1
            for idx, htxt in enumerate(first_cells):
                hl = nfc(htxt).lower()
                if ('tên ngành' in hl or 'ngành' in hl) and 'mã' not in hl and col_idx['name'] is None:
                    col_idx['name'] = idx
                elif ('mã ngành' in hl or 'mã xt' in hl or 'mã xét' in hl or hl == 'mã') and col_idx['code'] is None:
                    col_idx['code'] = idx
                elif ('tổ hợp' in hl or 'khối' in hl) and col_idx['comb'] is None:
                    col_idx['comb'] = idx
                elif ('trình độ' in hl or 'hệ' in hl) and col_idx['level'] is None:
                    col_idx['level'] = idx

        for tr in trs[start_row:]:
            cells = [txt(c) for c in tr.find_all(['td', 'th'])]
            if not cells or all(c == '' for c in cells):
                continue

            row_code_raw = None
            row_moet_code = None
            row_major_name = None
            row_combs = []
            row_level = None

            # Extract code from dedicated header column if available, else scan cells conservatively
            code_candidates = [cells[col_idx['code']]] if (col_idx['code'] is not None and col_idx['code'] < len(cells)) else cells
            for cell_val in code_candidates:
                token = clean(cell_val)
                if not token:
                    continue
                if CODE_TOKEN_RE.fullmatch(token):
                    row_code_raw = token
                    if MOET_7_RE.fullmatch(token):
                        row_moet_code = token
                    break
                # Check if cell embeds a single raw token like "7540102A" or pure 7-digit code at token boundaries
                m_raw = re.search(r'(?:^|\s)([56789]\d{6}[A-Za-z0-9._-]{1,8})(?=$|\s)', token)
                if m_raw and col_idx['code'] is not None:
                    row_code_raw = m_raw.group(1)
                    break
                m7 = MOET_7_RE.search(token)
                if m7 and (col_idx['code'] is not None or len(token) <= 20):
                    row_moet_code = m7.group(1)
                    row_code_raw = token if token == row_moet_code else row_moet_code
                    break

            # Extract combinations
            comb_src = cells[col_idx['comb']] if (col_idx['comb'] is not None and col_idx['comb'] < len(cells)) else ' '.join(cells)
            row_combs = sorted(set(COMB_RE.findall(comb_src)))

            # Extract row level
            if col_idx['level'] is not None and col_idx['level'] < len(cells):
                row_level = clean(cells[col_idx['level']]) or None
            if not row_level:
                row_level = infer_level_from_moet_code(row_moet_code) or detect_level_text(block_level_label)

            # Extract row major name
            if col_idx['name'] is not None and col_idx['name'] < len(cells):
                row_major_name = clean(cells[col_idx['name']]) or None
            else:
                for c_idx, cell_val in enumerate(cells):
                    cv = clean(cell_val)
                    if not cv:
                        continue
                    if re.fullmatch(r'\d+(?:[.,]\d+)?', cv):
                        continue
                    if row_code_raw and cv == row_code_raw:
                        continue
                    if CODE_TOKEN_RE.fullmatch(cv) or COMB_RE.fullmatch(cv):
                        continue
                    if len(cv) >= 3:
                        row_major_name = cv
                        break

            exact_match = bool(row_major_name and normalize_major_name_exact(row_major_name) == target_norm)
            near_match_only = bool(
                not exact_match
                and row_major_name
                and target_norm
                and (target_norm in normalize_major_name_exact(row_major_name))
            )

            rows_out.append({
                'row_major_name': row_major_name,
                'row_code_raw': row_code_raw,
                'row_moet_code_7digit': row_moet_code,
                'row_level_label': row_level,
                'row_combinations': row_combs,
                'exact_name_match': exact_match,
                'contains_target_name_only': near_match_only,
            })

    return rows_out


def parse_school_blocks(main_soup, target_major_name, major_id, evidence_url, retrieved_at):
    """Extract per-school links, preserving raw tokens and row-matched MOET codes separately."""
    rel = []
    seen = set()
    for block in main_soup.select('.list-tuyensinh > div'):
        a = block.select_one('b a[href]')
        if not a:
            continue
        link = urljoin(BASE, a['href'])
        text = txt(block)
        year_m = re.search(r'tin\s+(20\d{2})', text)
        year = int(year_m[1]) if year_m else None
        key = (link, year)
        if key in seen:
            continue
        seen.add(key)

        inst_label = txt(a)
        level_el = block.select_one('span.small')
        level_label = txt(level_el) if level_el else None

        rows = parse_table_rows(block, target_major_name, level_label)
        all_table_text = ' '.join(txt(t) for t in block.select('table'))

        # Strict boundary regex: never truncate 7540102A -> 7540102
        table_moet_codes = MOET_7_RE.findall(all_table_text)
        raw_tokens = []
        row_matched_moet = []
        row_matched_raw = []
        for r in rows:
            if r['row_code_raw']:
                raw_tokens.append(r['row_code_raw'])
            if r['row_moet_code_7digit']:
                table_moet_codes.append(r['row_moet_code_7digit'])
            if r['exact_name_match']:
                if r['row_moet_code_7digit']:
                    row_matched_moet.append(r['row_moet_code_7digit'])
                if r['row_code_raw']:
                    row_matched_raw.append(r['row_code_raw'])

        obs_moet = sorted(set(table_moet_codes))
        obs_raw = sorted(set(raw_tokens) | set(obs_moet))
        matched_moet = sorted(set(row_matched_moet))
        matched_raw = sorted(set(row_matched_raw))
        obs_combs = sorted(set(COMB_RE.findall(all_table_text)))

        mismatch_flags = []
        lbl_level = detect_level_text(level_label)
        inst_level = detect_level_text(inst_label)
        if lbl_level and inst_level and lbl_level != inst_level:
            mismatch_flags.append(f'LEVEL_LABEL_CONFLICTS_WITH_INSTITUTION_LABEL:{lbl_level}!={inst_level}')
        for code in obs_moet:
            code_level = infer_level_from_moet_code(code)
            if lbl_level and code_level and lbl_level != code_level:
                mismatch_flags.append(f'LEVEL_LABEL_CONFLICTS_WITH_CODE:{lbl_level}!={code}')

        rel.append({
            'major_id': major_id,
            'institution_label': inst_label,
            'institution_source_url': link,
            'source_news_year': year,
            'level_label': level_label,
            'observed_major_codes': obs_moet,
            'observed_raw_codes': obs_raw,
            'row_matched_major_codes': matched_moet,
            'row_matched_raw_codes': matched_raw,
            'observed_table_rows': rows,
            'observed_subject_combination_codes': obs_combs,
            'level_mismatch_flags': sorted(set(mismatch_flags)),
            'evidence_url': evidence_url,
            'retrieved_at': retrieved_at,
            'verification_status': 'historical_source_listing_not_current_admission_confirmation',
        })
    return rel


def resolve_major_code_candidate(rec, rel):
    """Assign source_major_code_candidate only when unambiguous; keep conflicts/specializations unmerged."""
    all_obs_moet = sorted(set(c for r in rel for c in r.get('observed_major_codes', [])))
    all_obs_raw = sorted(set(c for r in rel for c in r.get('observed_raw_codes', [])))
    matched_moet = sorted(set(c for r in rel for c in r.get('row_matched_major_codes', [])))
    matched_raw = sorted(set(c for r in rel for c in r.get('row_matched_raw_codes', [])))
    has_level_mismatch = any(r.get('level_mismatch_flags') for r in rel)

    rec['school_listing_count_extracted'] = len(rel)
    rec['observed_major_codes'] = all_obs_moet
    rec['observed_raw_codes'] = all_obs_raw
    rec['row_matched_major_codes'] = matched_moet
    rec['row_matched_raw_codes'] = matched_raw

    dom = clean(rec.get('source_dom_code') or '')
    dom_valid = dom if MOET_7_RE.fullmatch(dom) else None

    ambiguity = []
    if len(matched_moet) > 1:
        ambiguity.append('MULTIPLE_EXACT_ROW_MOET_CODES:' + ','.join(matched_moet))
    if dom_valid and matched_moet and (matched_moet != [dom_valid]):
        ambiguity.append(f'DOM_CONFLICTS_WITH_ROW_CODES:{dom_valid}!={",".join(matched_moet)}')
    if has_level_mismatch:
        ambiguity.append('LEVEL_CODE_MISMATCH_IN_LINKS')
    spec_only = [c for c in matched_raw if not MOET_7_RE.fullmatch(c)]
    if spec_only and not matched_moet:
        ambiguity.append('SPECIALIZATION_OR_SCHOOL_CODE_ONLY:' + ','.join(spec_only))

    if dom_valid and not ambiguity:
        rec['source_major_code_candidate'] = dom_valid
        rec['major_code_basis'] = 'catalogue_dom_id_confirmed_by_rows' if matched_moet == [dom_valid] else 'catalogue_dom_id'
    elif not dom_valid and len(matched_moet) == 1 and not ambiguity:
        rec['source_major_code_candidate'] = matched_moet[0]
        rec['major_code_basis'] = 'exact_row_match'
    else:
        if not dom_valid and len(all_obs_moet) > 0 and not matched_moet:
            ambiguity.append('OBSERVED_CODES_ONLY_ON_NON_EXACT_ROWS:' + ','.join(all_obs_moet))
        rec['source_major_code_candidate'] = None
        rec['major_code_basis'] = None

    rec['code_ambiguity_reasons'] = ambiguity
    rec['major_code_requires_official_verification'] = True
    return rec


def detail(rec, cache):
    d = get(rec['source_url'], cache)
    rec.update({k: d[k] for k in ['http_status', 'final_url', 'retrieved_at']})
    if d['http_status'] != 200:
        rec['detail_status'] = 'fetch_failed'
        rec['error'] = d.get('error', 'HTTP ' + str(d['http_status']))
        return rec, []
    s = BeautifulSoup(d['html'], 'html.parser')
    main = s.find('main') or s
    h = main.find('h1')
    body = main.select_one('section.article-content')
    rec['source_page_title'] = txt(h) if h else None
    rec['has_article_body'] = bool(body and txt(body))
    rec['detail_status'] = 'fetched' if h else 'unexpected_page'
    rec['body_sha256'] = hashlib.sha256(txt(body).encode()).hexdigest() if body else None
    rec['content_topics_present'] = []
    bodytext = txt(body).lower() if body else ''
    for topic, pattern in {
        'employment': 'việc làm|vị trí công việc|nghề nghiệp',
        'curriculum': 'chương trình|môn học|kiến thức',
        'skills': 'kỹ năng|năng lực|tố chất',
        'duration': 'thời gian đào tạo',
        'admission': 'xét tuyển|tuyển sinh|tổ hợp',
    }.items():
        if re.search(pattern, bodytext):
            rec['content_topics_present'].append(topic)

    rel = parse_school_blocks(main, rec['name'], rec['id'], rec['source_url'], d['retrieved_at'])
    resolve_major_code_candidate(rec, rel)
    return rec, rel


def main():
    p = argparse.ArgumentParser()
    p.add_argument('--out', default='data')
    p.add_argument('--cache', default='.cache')
    args = p.parse_args()
    out = Path(args.out)
    out.mkdir(parents=True, exist_ok=True)
    cache = Path(args.cache)
    cache.mkdir(parents=True, exist_ok=True)
    d = get(INDEX, cache)
    if d['http_status'] != 200:
        raise RuntimeError('Index fetch failed')
    groups, subs, records = catalogue(d['html'], d['retrieved_at'])
    for name, obj in [('groups', groups), ('subgroups', subs), ('catalogue', records)]:
        (out / (name + '.json')).write_text(json.dumps(obj, ensure_ascii=False, indent=2))
    print(f'Catalogue: {len(records)}, groups: {len(groups)}, subgroups: {len(subs)}', flush=True)
    results = {r['id']: r for r in records}
    rels = []
    todo = [r for r in records if r['source_url']]
    with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool:
        fs = [pool.submit(detail, r, cache) for r in todo]
        for i, f in enumerate(concurrent.futures.as_completed(fs), 1):
            r, links = f.result()
            results[r['id']] = r
            rels.extend(links)
            (out / 'checkpoint.json').write_text(json.dumps({'majors': list(results.values()), 'major_institution_links': rels}, ensure_ascii=False))
            if i % 20 == 0 or i == len(todo):
                print(f'Details {i}/{len(todo)}; relationships {len(rels)}', flush=True)
    data = {
        'schema_version': '1.1.0',
        'source': INDEX,
        'retrieved_at': d['retrieved_at'],
        'groups': groups,
        'subgroups': subs,
        'majors': [results[r['id']] for r in records],
        'major_institution_links': sorted(rels, key=lambda x: (x['major_id'], x['institution_source_url'], x['source_news_year'] or 0)),
    }
    (out / 'jobsimu_hnv.json').write_text(json.dumps(data, ensure_ascii=False, indent=2))
    (out / 'checkpoint.json').unlink()
    print('Done', flush=True)


if __name__ == '__main__':
    main()
