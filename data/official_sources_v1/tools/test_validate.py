"""Regression checks for corrupt files and semantically invalid staging records."""
import hashlib
import json
import shutil
import tempfile
import unittest
from pathlib import Path

from validate import ROOT, validate


class ValidationTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.root = Path(self.temp.name) / 'dataset'
        shutil.copytree(ROOT, self.root)

    def tearDown(self):
        self.temp.cleanup()

    def mutate_admissions(self, change):
        path = self.root / 'admissions/hust_2026.json'
        rows = json.loads(path.read_text())
        change(rows)
        path.write_text(json.dumps(rows, ensure_ascii=False))
        manifest_path = self.root / 'manifest.json'
        manifest = json.loads(manifest_path.read_text())
        for entry in manifest['files']:
            if entry['path'] == 'admissions/hust_2026.json':
                entry['sha256'] = hashlib.sha256(path.read_bytes()).hexdigest()
        manifest_path.write_text(json.dumps(manifest))

    def test_valid_package(self):
        self.assertEqual(validate(self.root)['admission_programs'], 68)

    def test_corrupt_file(self):
        with (self.root / 'onet/occupation_data.csv').open('a') as stream:
            stream.write('corrupt\n')
        with self.assertRaisesRegex(ValueError, 'Hash mismatch'):
            validate(self.root)

    def test_duplicate_program(self):
        self.mutate_admissions(lambda rows: rows[1].update(program_code=rows[0]['program_code']))
        with self.assertRaisesRegex(ValueError, 'Duplicate program'):
            validate(self.root)

    def test_invented_cutoff(self):
        self.mutate_admissions(lambda rows: rows[0].update(cutoff_score=25))
        with self.assertRaisesRegex(ValueError, 'Unsupported tuition/cutoff'):
            validate(self.root)

    def test_unverified_national_code(self):
        self.mutate_admissions(lambda rows: rows[0].update(official_major_code='BF1'))
        with self.assertRaisesRegex(ValueError, 'Unverified national major code'):
            validate(self.root)

    def test_missing_source(self):
        self.mutate_admissions(lambda rows: rows[0].update(source_id='unknown'))
        with self.assertRaisesRegex(ValueError, 'Unknown admission source'):
            validate(self.root)

    def test_premature_publication(self):
        self.mutate_admissions(lambda rows: rows[0].update(publication_status='approved'))
        with self.assertRaisesRegex(ValueError, 'Unsafe publication state'):
            validate(self.root)


if __name__ == '__main__':
    unittest.main()
