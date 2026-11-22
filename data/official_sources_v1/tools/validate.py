"""Offline validation of source hashes, references and admission scope. No dependencies."""
import csv
import hashlib
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def require(condition, message):
    if not condition:
        raise ValueError(message)


def validate(root=ROOT):
    manifest = json.loads((root / 'manifest.json').read_text(encoding='utf-8'))
    sources = {s['source_id']: s for s in manifest['sources']}
    require(len(sources) == len(manifest['sources']), 'Duplicate source ID')
    paths = set()
    tables = {}
    for entry in manifest['files']:
        relative = entry['path']
        path = root / relative
        require(path.resolve().is_relative_to(root.resolve()), 'Path escapes dataset')
        require(relative not in paths, 'Duplicate file path')
        paths.add(relative)
        require(entry['source_id'] in sources, 'Unknown source reference')
        require(hashlib.sha256(path.read_bytes()).hexdigest() == entry['sha256'], f'Hash mismatch: {relative}')
        if path.suffix == '.csv':
            with path.open(encoding='utf-8', newline='') as stream:
                rows = list(csv.DictReader(stream))
            require(all(None not in r and None not in r.values() for r in rows), f'Malformed CSV: {relative}')
        else:
            rows = json.loads(path.read_text(encoding='utf-8'))
        require(len(rows) == entry['rows'], f'Count mismatch: {relative}')
        tables[relative] = rows
    occupations = tables['onet/occupation_data.csv']
    codes = {r['O*NET-SOC Code'] for r in occupations}
    require(len(codes) == len(occupations) == 1016, 'Unexpected occupation count or duplicate')
    require(all(re.fullmatch(r'\d{2}-\d{4}\.\d{2}', c) for c in codes), 'Invalid SOC code')
    tasks = [r for p, rows in tables.items() if p.startswith('onet/tasks/') for r in rows]
    require(len(tasks) == 18838, 'Unexpected task count')
    require(len({(r['O*NET-SOC Code'], r['Task ID']) for r in tasks}) == len(tasks), 'Duplicate task')
    require(all(r['O*NET-SOC Code'] in codes for r in tasks), 'Task references unknown occupation')
    elements = {r['Element ID'] for r in tables['onet/content_model_reference.csv']}
    scales = {r['Scale ID']: r for r in tables['onet/scales_reference.csv']}
    interests = tables['onet/career_interest_types.csv']
    require(len({(r['O*NET-SOC Code'], r['Element ID'], r['Scale ID']) for r in interests}) == len(interests), 'Duplicate interest')
    for row in interests:
        require(row['O*NET-SOC Code'] in codes, 'Unknown interest occupation')
        require(row['Element ID'] in elements, 'Unknown interest element')
        require(row['Scale ID'] in scales, 'Unknown interest scale')
        scale = scales[row['Scale ID']]
        require(float(scale['Minimum']) <= float(row['Data Value']) <= float(scale['Maximum']), 'Interest value outside scale')
    admissions = tables['admissions/hust_2026.json']
    require(len(admissions) == len({r['record_id'] for r in admissions}) == 68, 'Duplicate/missing admission record')
    require(len({r['program_code'] for r in admissions}) == 68, 'Duplicate program')
    require(sum(r['planned_total_quota'] for r in admissions) == 9880, 'Quota mismatch')
    for row in admissions:
        require(row['source_id'] in sources, 'Unknown admission source')
        require(row['admission_year'] == 2026 and row['admission_method'] == 'THPT', 'Wrong admission scope')
        require(row['official_major_code'] is None, 'Unverified national major code')
        require(row['tuition_vnd'] is None and row['cutoff_score'] is None, 'Unsupported tuition/cutoff')
        require(row['publication_status'] == 'staging' and not row['score_calculation_ready'], 'Unsafe publication state')
        combinations = row['combination_codes']
        require(bool(combinations) and len(set(combinations)) == len(combinations), 'Empty/duplicate combinations')
        require(all(re.fullmatch(r'[A-Z][A-Z0-9]\d', c) for c in combinations), 'Invalid combination syntax')
    return {'occupations': len(occupations), 'tasks': len(tasks), 'interest_rows': len(interests),
            'admission_programs': len(admissions), 'planned_quota': 9880, 'files_checked': len(paths)}


if __name__ == '__main__':
    print(json.dumps(validate(), ensure_ascii=False, indent=2))
