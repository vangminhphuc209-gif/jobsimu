"""Build a source-pinned staging dataset; never connect to the application database.

Dependencies: openpyxl, PyMuPDF. Run with --cache /path/to/download-cache.
Existing manifest hashes prevent silent changes to upstream source files.
"""
import argparse
import csv
import hashlib
import json
import re
import urllib.parse
import urllib.request
from collections import defaultdict
from datetime import datetime, timezone
from pathlib import Path

import fitz
from openpyxl import load_workbook

ROOT = Path(__file__).resolve().parents[1]
HUST_URL = 'https://hust.edu.vn/uploads/sys/tuyen-sinh/2026_05/danh-muc-chuong-trinh-dao-tao-2026.pdf'
ONET_BASE = 'https://www.onetcenter.org/dl_files/database/db_31_0_excel/'
BOOKS = ['Occupation Data', 'Task Statements', 'Career Interest Types', 'Content Model Reference', 'Scales Reference']


def sha(data):
    return hashlib.sha256(data).hexdigest()


def write_json(path, data):
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(data, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--cache', type=Path, required=True)
    args = parser.parse_args()
    args.cache.mkdir(parents=True, exist_ok=True)
    old = json.loads((ROOT / 'manifest.json').read_text()) if (ROOT / 'manifest.json').exists() else {}
    expected = {x['source_id']: x['sha256'] for x in old.get('sources', [])}
    sources, files = [], []

    def download(sid, url, filename):
        path = args.cache / filename
        if not path.exists():
            with urllib.request.urlopen(url, timeout=60) as response:
                path.write_bytes(response.read())
        data = path.read_bytes()
        digest = sha(data)
        if sid in expected and digest != expected[sid]:
            raise ValueError(f'Source changed: {sid}; review in a NEW dataset version, do not overwrite v1')
        sources.append({'source_id': sid, 'url': url, 'sha256': digest,
                        'publisher': 'USDOL/ETA' if sid.startswith('onet') else 'Đại học Bách khoa Hà Nội',
                        'license': 'CC-BY-4.0' if sid.startswith('onet') else 'public_factual_admissions_table_no_open_license_asserted'})
        return path

    def csv_file(relative, header, rows, sid):
        path = ROOT / relative
        path.parent.mkdir(parents=True, exist_ok=True)
        with path.open('w', encoding='utf-8', newline='') as out:
            writer = csv.writer(out, lineterminator='\n')
            writer.writerow(header)
            writer.writerows(rows)
        files.append({'path': relative, 'rows': len(rows), 'sha256': sha(path.read_bytes()), 'source_id': sid})

    for name in BOOKS:
        sid = 'onet-31.0-' + name.lower().replace(' ', '-')
        path = download(sid, ONET_BASE + urllib.parse.quote(name) + '.xlsx', name + '.xlsx')
        workbook = load_workbook(path, read_only=True, data_only=True)
        values = iter(workbook.active.values)
        header = next(values)
        rows = [list(row) for row in values if row[0] is not None]
        if name == 'Task Statements':
            groups = defaultdict(list)
            for row in rows:
                groups[row[0][:2]].append(row)
            for family, group in sorted(groups.items()):
                csv_file(f'onet/tasks/{family}.csv', header, group, sid)
        else:
            csv_file('onet/' + name.lower().replace(' ', '_') + '.csv', header, rows, sid)
        workbook.close()

    sid = 'hust-admissions-2026'
    pdf = download(sid, HUST_URL, 'hust.pdf')
    document = fitz.open(pdf)
    rows = []
    pattern = re.compile(r'^\s*(\d{1,2})\s+(.+?)\s+(\d{2,3})\s+([A-Z0-9-]+)\s+\uf0d6\s+\uf0d6\s+((?:[A-Z][A-Z0-9]\d)(?:,\s*[A-Z][A-Z0-9]\d)*)')
    for page_number, page in enumerate(document, 1):
        for line in page.get_text(sort=True).splitlines():
            match = pattern.match(line)
            if not match:
                # Row 67 spans two lines, with title printed above and after its numeric cells.
                match67 = re.match(r'^\s*67\s+90\s+FL2\s+\uf0d6\s+\uf0d6\s+K01,\s*D01', line)
                if not match67:
                    continue
                number, name, quota, code, combinations = 67, 'Tiếng Anh chuyên nghiệp quốc tế (do ĐHBK Hà Nội và ĐH Plymouth Marjon - Vương quốc Anh cấp bằng)', 90, 'FL2', 'K01, D01'
            else:
                number, name, quota, code, combinations = match.groups()
                number, quota = int(number), int(quota)
            group = ('standard' if number <= 38 else 'elitech_english' if number <= 57 else
                     'elitech_foreign_language' if number <= 60 else 'elitech_other_language_outcome' if number <= 64 else
                     'pfiev' if number <= 66 else 'international_joint')
            rows.append({'record_id': f'hust-2026-{code.lower()}', 'institution_id': 'hust',
                         'institution_name': 'Đại học Bách khoa Hà Nội', 'admission_year': 2026,
                         'source_row': number, 'source_pdf_page': page_number,
                         'program_code': code, 'official_major_code': None, 'program_name': name.strip(),
                         'program_group': group, 'planned_total_quota': quota,
                         'admission_method': 'THPT',
                         'combination_codes': [v.strip() for v in combinations.split(',')],
                         'subject_weights': None, 'eligibility_conditions_complete': False,
                         'cutoff_score': None, 'tuition_vnd': None,
                         'source_id': sid, 'verification_status': 'official_source_extracted',
                         'publication_status': 'staging', 'score_calculation_ready': False,
                         'review_notes': ['PDF bold type determines Math coefficient; not represented here. Confirm conditions in annual admission notice.']})
    if [r['source_row'] for r in rows] != list(range(1, 69)):
        raise ValueError('Expected exactly rows 1..68; extraction failed')
    if sum(r['planned_total_quota'] for r in rows) != 9880:
        raise ValueError('Quota total does not match source')
    target = ROOT / 'admissions/hust_2026.json'
    write_json(target, rows)
    files.append({'path': 'admissions/hust_2026.json', 'rows': len(rows),
                  'sha256': sha(target.read_bytes()), 'source_id': sid})
    write_json(ROOT / 'manifest.json', {
        'dataset_id': 'jobsimu-official-sources-v1', 'schema_version': 1,
        'retrieved_at': old.get('retrieved_at', datetime.now(timezone.utc).isoformat()),
        'status': 'staging_not_imported', 'onet_version': '31.0',
        'geography': {'onet': 'US', 'admissions': 'VN'},
        'sources': sources, 'files': files,
        'limitations': ['Not all Vietnamese universities or majors are covered.',
                       'No new cutoffs or tuition values in this release.',
                       'O*NET occupations are not Vietnamese training majors.',
                       'US occupational interest values are not student test norms or suitability probabilities.']})
    print(json.dumps({f['path']: f['rows'] for f in files}, ensure_ascii=False, indent=2))


if __name__ == '__main__':
    main()
