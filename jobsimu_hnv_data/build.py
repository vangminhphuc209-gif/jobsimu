"""Build and validate JSON/CSV/SQLite deliverables from collected source facts.
Retains raw observed table rows/codes, detects duplicate candidate codes and
level mismatches across links, and outputs honest review queue diagnostics.
"""
import collections
import csv
import hashlib
import json
import sqlite3
import zipfile
from pathlib import Path


def detect_duplicate_candidates(majors):
    counts = collections.Counter(
        (r.get('source_major_code_candidate') or '').strip()
        for r in majors
        if (r.get('source_major_code_candidate') or '').strip()
    )
    return {code for code, cnt in counts.items() if cnt > 1}


def detect_link_level_mismatches(links):
    notes = []
    for lk in links:
        flags = lk.get('level_mismatch_flags') or []
        if flags:
            notes.extend(flags)
            continue
        lvl = (lk.get('level_label') or '').lower()
        inst_lbl = (lk.get('institution_label') or '').lower()
        codes = lk.get('observed_major_codes') or []
        if ('tiến sỹ' in lvl or 'tiến sĩ' in lvl or 'thạc sỹ' in lvl or 'thạc sĩ' in lvl) and (
            any(str(c).startswith(('5', '6', '7')) for c in codes) or 'đại học chính quy' in inst_lbl
        ):
            notes.append('LEVEL_CONFLICTS_WITH_CODE_OR_LABEL')
        elif 'đại học' in lvl and 'cao đẳng' not in lvl and 'trung cấp' not in lvl:
            if any(str(c).startswith(('5', '6')) for c in codes):
                notes.append('LOWER_LEVEL_CODE_IN_UNIVERSITY_TABLE')
    return sorted(set(notes))


def csvfile(out_dir: Path, name: str, rows: list):
    if not rows:
        return
    fields = list(dict.fromkeys(k for r in rows for k in r))
    with (out_dir / name).open('w', encoding='utf-8-sig', newline='') as f:
        w = csv.DictWriter(f, fieldnames=fields)
        w.writeheader()
        for r in rows:
            w.writerow({k: json.dumps(v, ensure_ascii=False) if isinstance(v, (list, dict)) else v for k, v in r.items()})


def build_package(base_dir: Path, create_zip: bool = True) -> dict:
    d = json.loads((base_dir / 'jobsimu_hnv.json').read_text())
    groups = {r['id']: r for r in d['groups']}
    subs = {r['id']: r for r in d['subgroups']}
    assert len(groups) == len(d['groups']) and len(subs) == len(d['subgroups'])
    assert len({r['id'] for r in d['majors']}) == len(d['majors'])

    for r in d['majors']:
        assert r['group_id'] in groups and r['subgroup_id'] in subs
        assert subs[r['subgroup_id']]['group_id'] == r['group_id']
        if r['name'] == 'Địa lý tự nhiênkỹ thuật' and r.get('source_page_title') == 'Ngành Địa lý tự nhiên kỹ thuật':
            r['name'] = 'Địa lý tự nhiên kỹ thuật'
            r['normalization_note'] = 'Inserted missing space using detail page heading; source_label and original ID retained.'
        r['source_major_code_candidate'] = r.pop('major_code', r.get('source_major_code_candidate'))
        r['official_major_code'] = None
        r['group_name'] = groups[r['group_id']]['name']
        r['subgroup_name'] = subs[r['subgroup_id']]['name']
        for k in ['source_page_title', 'http_status', 'final_url', 'body_sha256', 'major_code_basis', 'school_listing_count_extracted']:
            r.setdefault(k, None)
        for k in ['observed_major_codes', 'observed_raw_codes', 'row_matched_major_codes', 'row_matched_raw_codes', 'code_ambiguity_reasons', 'content_topics_present']:
            r.setdefault(k, [])
        r.setdefault('has_article_body', False)
        r['major_code_requires_official_verification'] = True
        r['career_mapping_status'] = 'not_inferred_from_training_major'
        r['assessment_mapping_status'] = 'not_scored'

    inst = {}
    links_by_major = collections.defaultdict(list)
    for r in d['major_institution_links']:
        url = r['institution_source_url']
        iid = 'hnv-inst-' + hashlib.sha256(url.encode()).hexdigest()[:16]
        r['institution_source_id'] = iid
        r.setdefault('observed_raw_codes', list(r.get('observed_major_codes') or []))
        r.setdefault('observed_table_rows', [])
        r.setdefault('level_mismatch_flags', [])
        inst[iid] = {'id': iid, 'source_label': r['institution_label'], 'source_url': url, 'official_institution_id': None}
        links_by_major[r['major_id']].append(r)

    d['institution_source_records'] = sorted(inst.values(), key=lambda r: r['id'])
    ids = {r['id'] for r in d['majors']}
    assert all(r['major_id'] in ids for r in d['major_institution_links'])
    keys = [(r['major_id'], r['institution_source_url'], r['source_news_year']) for r in d['major_institution_links']]
    assert len(keys) == len(set(keys))

    dup_candidates = detect_duplicate_candidates(d['majors'])

    stats = {
        'catalogue_items': len(d['majors']),
        'groups': len(groups),
        'subgroups': len(subs),
        'detail_status_counts': dict(collections.Counter(r['detail_status'] for r in d['majors'])),
        'majors_with_article_body': sum(bool(r['has_article_body']) for r in d['majors']),
        'majors_with_school_links': len({r['major_id'] for r in d['major_institution_links']}),
        'major_institution_links': len(keys),
        'unique_institution_source_urls': len(inst),
        'source_news_year_counts': dict(sorted(collections.Counter(str(r['source_news_year']) for r in d['major_institution_links']).items())),
        'majors_with_code_candidates': sum(bool(r['source_major_code_candidate']) for r in d['majors']),
        'duplicate_candidate_codes': sorted(dup_candidates),
        'officially_verified_major_codes': 0,
        'failed_details': [{'id': r['id'], 'url': r['source_url'], 'status': r['detail_status']} for r in d['majors'] if r['detail_status'] not in ['fetched', 'not_linked_by_source']],
        'validation': {
            'unique_major_ids': True,
            'unique_group_ids': True,
            'unique_subgroup_ids': True,
            'valid_group_references': True,
            'valid_major_references': True,
            'no_duplicate_relationships': True,
        },
    }
    d['coverage'] = stats
    (base_dir / 'jobsimu_hnv.json').write_text(json.dumps(d, ensure_ascii=False, indent=2))
    (base_dir / 'quality_report.json').write_text(json.dumps(stats, ensure_ascii=False, indent=2))

    csvfile(base_dir, 'majors.csv', d['majors'])
    csvfile(base_dir, 'major_institution_links.csv', d['major_institution_links'])
    csvfile(base_dir, 'institution_source_records.csv', d['institution_source_records'])
    csvfile(base_dir, 'groups.csv', d['groups'])
    csvfile(base_dir, 'subgroups.csv', d['subgroups'])

    review = []
    for r in d['majors']:
        issues = []
        if r['detail_status'] == 'not_linked_by_source':
            issues.append('SOURCE_HAS_NO_DETAIL_LINK')
        elif r['detail_status'] != 'fetched':
            issues.append('DETAIL_FETCH_NOT_SUCCESSFUL')
        elif not r['has_article_body']:
            issues.append('EMPTY_ARTICLE_BODY')
        if not r['source_major_code_candidate']:
            issues.append('NO_SINGLE_MAJOR_CODE_CANDIDATE')
        elif r['source_major_code_candidate'] in dup_candidates:
            issues.append('DUPLICATE_CANDIDATE_CODE')
        if len(r['observed_major_codes']) > 1 or len(r.get('observed_raw_codes', [])) > 1:
            issues.append('MULTIPLE_CODES_IN_SOURCE_TABLES')
        if r.get('code_ambiguity_reasons') or detect_link_level_mismatches(links_by_major[r['id']]):
            issues.append('LEVEL_CODE_MISMATCH')
        if r['detail_status'] == 'fetched' and not r['school_listing_count_extracted']:
            issues.append('NO_SCHOOL_LISTINGS_EXTRACTED')
        if issues:
            review.append({'major_id': r['id'], 'name': r['name'], 'issues': issues, 'source_url': r['source_url'] or r['catalogue_url']})
    csvfile(base_dir, 'review_queue.csv', review)

    db_path = base_dir / 'jobsimu_hnv.sqlite'
    db_path.unlink(missing_ok=True)
    con = sqlite3.connect(db_path)
    con.executescript('''
    PRAGMA foreign_keys=ON;
    CREATE TABLE groups(id TEXT PRIMARY KEY,name TEXT NOT NULL,source_url TEXT NOT NULL);
    CREATE TABLE subgroups(id TEXT PRIMARY KEY,name TEXT NOT NULL,group_id TEXT REFERENCES groups(id),source_url TEXT NOT NULL);
    CREATE TABLE majors(id TEXT PRIMARY KEY,name TEXT NOT NULL,group_id TEXT REFERENCES groups(id),subgroup_id TEXT REFERENCES subgroups(id),source_url TEXT,detail_status TEXT NOT NULL,source_major_code_candidate TEXT,official_major_code TEXT,payload_json TEXT NOT NULL);
    CREATE TABLE institution_source_records(id TEXT PRIMARY KEY,source_label TEXT,source_url TEXT UNIQUE NOT NULL);
    CREATE TABLE major_institution_links(id INTEGER PRIMARY KEY,major_id TEXT REFERENCES majors(id),institution_source_id TEXT REFERENCES institution_source_records(id),source_news_year INTEGER,evidence_url TEXT NOT NULL,payload_json TEXT NOT NULL);
    CREATE INDEX major_name_idx ON majors(name);
    CREATE INDEX major_group_idx ON majors(group_id);
    CREATE INDEX relation_major_idx ON major_institution_links(major_id);
    CREATE INDEX relation_year_idx ON major_institution_links(source_news_year);
    CREATE TABLE metadata(key TEXT PRIMARY KEY,value TEXT NOT NULL);
    ''')
    for r in d['groups']:
        con.execute('INSERT INTO groups VALUES (?,?,?)', (r['id'], r['name'], r['source_url']))
    for r in d['subgroups']:
        con.execute('INSERT INTO subgroups VALUES (?,?,?,?)', (r['id'], r['name'], r['group_id'], r['source_url']))
    for r in d['majors']:
        con.execute('INSERT INTO majors VALUES (?,?,?,?,?,?,?,?,?)', tuple(r[k] for k in ['id', 'name', 'group_id', 'subgroup_id', 'source_url', 'detail_status', 'source_major_code_candidate', 'official_major_code']) + (json.dumps(r, ensure_ascii=False),))
    for r in d['institution_source_records']:
        con.execute('INSERT INTO institution_source_records VALUES (?,?,?)', (r['id'], r['source_label'], r['source_url']))
    for r in d['major_institution_links']:
        con.execute('INSERT INTO major_institution_links(major_id,institution_source_id,source_news_year,evidence_url,payload_json) VALUES (?,?,?,?,?)', (r['major_id'], r['institution_source_id'], r['source_news_year'], r['evidence_url'], json.dumps(r, ensure_ascii=False)))
    for key in ['source', 'schema_version', 'retrieved_at', 'coverage']:
        con.execute('INSERT INTO metadata VALUES (?,?)', (key, json.dumps(d[key], ensure_ascii=False)))
    assert not con.execute('PRAGMA foreign_key_check').fetchall()
    assert con.execute('PRAGMA integrity_check').fetchone()[0] == 'ok'
    con.commit()
    con.close()

    report = '\n'.join(f"| {g['name']} | {sum(r['group_id']==g['id'] for r in d['majors'])} |" for g in d['groups'])
    readme = f'''# Bộ dữ liệu ngành đào tạo cho JobSimu

Nguồn: {d['source']}
Ngày thu thập: {d['retrieved_at']} (UTC).
Phạm vi: toàn bộ mục trong khối danh mục ngành đào tạo tại URL trên; không phải toàn bộ website hoặc danh mục pháp lý đã xác minh.

## Độ bao phủ
- {stats['catalogue_items']} ngành, {stats['groups']} lĩnh vực, {stats['subgroups']} nhóm ngành theo cách phân nhóm của nguồn.
- Trạng thái trang chi tiết: {json.dumps(stats['detail_status_counts'],ensure_ascii=False)}.
- {stats['majors_with_article_body']} ngành có phần bài giới thiệu đọc được.
- {stats['major_institution_links']} liên kết ngành–nguồn trường, thuộc {stats['unique_institution_source_urls']} URL thông tin trường/tuyển sinh khác nhau.
- Số URL nguồn trường không đồng nghĩa số trường độc lập: một trường có thể có nhiều URL/hệ đào tạo.
- Đã kiểm tra ID duy nhất, tham chiếu nhóm/ngành, quan hệ trùng và tính toàn vẹn SQLite.

## Phân bố theo lĩnh vực của nguồn
| Lĩnh vực | Số mục ngành |
|---|---:|
{report}
'''
    (base_dir / 'README.md').write_text(readme)
    files = [
        'jobsimu_hnv.json', 'jobsimu_hnv.sqlite', 'majors.csv', 'groups.csv', 'subgroups.csv',
        'major_institution_links.csv', 'institution_source_records.csv', 'quality_report.json',
        'review_queue.csv', 'README.md', 'collect.py', 'build.py',
    ]
    (base_dir / 'checksums.sha256').write_text('\n'.join(hashlib.sha256((base_dir / f).read_bytes()).hexdigest() + '  ' + f for f in files) + '\n')
    if create_zip:
        with zipfile.ZipFile(base_dir.parent / 'jobsimu_hnv_data_2026-11-11.zip', 'w', zipfile.ZIP_DEFLATED) as z:
            for f in files + ['checksums.sha256']:
                z.write(base_dir / f, 'jobsimu_hnv_data/' + f)
    return stats


if __name__ == '__main__':
    print(json.dumps(build_package(Path(__file__).resolve().parent), ensure_ascii=False, indent=2))
