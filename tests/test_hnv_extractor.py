#!/usr/bin/env python3
"""Unit tests for row-by-row HNV HTML table extraction & ambiguity preservation."""
import json
import sys
import tempfile
import unittest
from pathlib import Path
from bs4 import BeautifulSoup

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / 'jobsimu_hnv_data'))

import collect  # noqa: E402
import build    # noqa: E402


class TestHnvExtractor(unittest.TestCase):
    def _soup(self, html: str) -> BeautifulSoup:
        return BeautifulSoup(html, 'html.parser')

    def test_does_not_truncate_specialization_code_or_match_near_similar_name(self):
        html = """
        <main>
          <div class="list-tuyensinh">
            <div>
              <b><a href="/truong/ddc">ĐH Công nghệ Đồng Nai (tin 2024)</a></b>
              <span class="small">Hệ Đại học</span>
              <table>
                <tr><th>STT</th><th>Tên ngành</th><th>Mã ngành</th><th>Chỉ tiêu</th></tr>
                <tr><td>20</td><td>Kỹ thuật thực phẩm (chuyên ngành Kỹ thuật sinh học thực phẩm)</td><td>7540102A</td><td>30</td></tr>
              </table>
            </div>
            <div>
              <b><a href="/truong/bka">ĐH Bách khoa Hà Nội (tin 2024)</a></b>
              <span class="small">Hệ Đại học</span>
              <table>
                <tr><th>Tên ngành</th><th>Mã XT</th><th>Chỉ tiêu</th><th>Tổ hợp</th></tr>
                <tr><td>Kỹ thuật Sinh học</td><td>BF1</td><td>80</td><td>A00, B00</td></tr>
              </table>
            </div>
          </div>
        </main>
        """
        links = collect.parse_school_blocks(
            self._soup(html),
            'Kỹ thuật sinh học',
            'hnv-ky-thuat-sinh-hoc',
            'https://huongnghiepviet.com/nganh-ky-thuat-sinh-hoc',
            '2026-11-11T00:00:00+00:00',
        )
        self.assertEqual(len(links), 2)

        ddc = links[0]
        self.assertEqual(ddc['observed_major_codes'], [], 'Must NEVER truncate 7540102A into 7540102')
        self.assertIn('7540102A', ddc['observed_raw_codes'])
        self.assertEqual(ddc['row_matched_major_codes'], [], 'Near-similar specialization row must NOT match Kỹ thuật sinh học')
        self.assertTrue(ddc['observed_table_rows'][0]['contains_target_name_only'])

        bka = links[1]
        self.assertEqual(bka['row_matched_raw_codes'], ['BF1'])
        self.assertEqual(bka['observed_subject_combination_codes'], ['A00', 'B00'])

        rec = {'id': 'hnv-ky-thuat-sinh-hoc', 'name': 'Kỹ thuật sinh học', 'source_dom_code': None}
        collect.resolve_major_code_candidate(rec, links)
        self.assertIsNone(rec['source_major_code_candidate'])
        self.assertIn('BF1', rec['observed_raw_codes'])
        self.assertIn('7540102A', rec['observed_raw_codes'])
        self.assertTrue(any('SPECIALIZATION_OR_SCHOOL_CODE_ONLY:BF1' in a for a in rec['code_ambiguity_reasons']))

    def test_extracts_exact_row_from_multi_row_table(self):
        html = """
        <main>
          <div class="list-tuyensinh">
            <div>
              <b><a href="/truong/htn">Học viện Thanh thiếu niên Việt Nam (tin 2024)</a></b>
              <span class="small">Hệ Đại học</span>
              <table>
                <tr><td>1</td><td>Công tác thanh thiếu niên</td><td>7760102</td><td>C00, D01</td></tr>
                <tr><td>3</td><td>Tâm lý học</td><td>7310401</td><td>A01, C00, D01</td></tr>
              </table>
            </div>
          </div>
        </main>
        """
        links = collect.parse_school_blocks(
            self._soup(html),
            'Tâm lý học',
            'hnv-tam-ly-hoc',
            'https://huongnghiepviet.com/nganh-tam-ly-hoc',
            '2026-11-11T00:00:00+00:00',
        )
        self.assertEqual(links[0]['observed_major_codes'], ['7310401', '7760102'])
        self.assertEqual(links[0]['row_matched_major_codes'], ['7310401'])

        rec = {'id': 'hnv-tam-ly-hoc', 'name': 'Tâm lý học', 'source_dom_code': None}
        collect.resolve_major_code_candidate(rec, links)
        self.assertEqual(rec['source_major_code_candidate'], '7310401')
        self.assertEqual(rec['major_code_basis'], 'exact_row_match')
        self.assertEqual(rec['code_ambiguity_reasons'], [])

    def test_flags_level_mismatch_and_holds_verification_when_ambiguous(self):
        html = """
        <main>
          <div class="list-tuyensinh">
            <div>
              <b><a href="/truong/donga">ĐH Đông Á - Hệ đại học chính quy (tin 2023)</a></b>
              <span class="small">Hệ Tiến sỹ</span>
              <table>
                <tr><th>Tên ngành</th><th>Mã ngành</th></tr>
                <tr><td>Tâm lý học</td><td>7310401</td></tr>
              </table>
            </div>
          </div>
        </main>
        """
        links = collect.parse_school_blocks(
            self._soup(html),
            'Tâm lý học',
            'hnv-tam-ly-hoc',
            'https://huongnghiepviet.com/nganh-tam-ly-hoc',
            '2026-11-11T00:00:00+00:00',
        )
        self.assertTrue(links[0]['level_mismatch_flags'])
        rec = {'id': 'hnv-tam-ly-hoc', 'name': 'Tâm lý học', 'source_dom_code': None}
        collect.resolve_major_code_candidate(rec, links)
        self.assertIsNone(rec['source_major_code_candidate'], 'Ambiguous level mismatch must keep candidate unset for manual verification')
        self.assertIn('LEVEL_CODE_MISMATCH_IN_LINKS', rec['code_ambiguity_reasons'])

    def test_dom_conflict_against_exact_rows_keeps_candidate_unassigned(self):
        html = """
        <main>
          <div class="list-tuyensinh">
            <div>
              <b><a href="/truong/a">Trường A (tin 2024)</a></b>
              <span class="small">Hệ Đại học</span>
              <table>
                <tr><th>Tên ngành</th><th>Mã ngành</th></tr>
                <tr><td>Khoa học máy tính</td><td>7480101</td></tr>
              </table>
            </div>
            <div>
              <b><a href="/truong/b">Trường B (tin 2024)</a></b>
              <span class="small">Hệ Đại học</span>
              <table>
                <tr><th>Tên ngành</th><th>Mã ngành</th></tr>
                <tr><td>Khoa học máy tính</td><td>7480201</td></tr>
              </table>
            </div>
          </div>
        </main>
        """
        links = collect.parse_school_blocks(
            self._soup(html),
            'Khoa học máy tính',
            'hnv-khoa-hoc-may-tinh',
            'https://huongnghiepviet.com/nganh-khmt',
            '2026-11-11T00:00:00+00:00',
        )
        rec = {'id': 'hnv-khoa-hoc-may-tinh', 'name': 'Khoa học máy tính', 'source_dom_code': '7480101'}
        collect.resolve_major_code_candidate(rec, links)
        self.assertIsNone(rec['source_major_code_candidate'])
        self.assertTrue(any('MULTIPLE_EXACT_ROW_MOET_CODES' in r for r in rec['code_ambiguity_reasons']))

    def test_build_flags_duplicate_candidate_and_level_mismatch_in_temp_dir(self):
        with tempfile.TemporaryDirectory() as tmp:
            tdir = Path(tmp) / 'pkg'
            tdir.mkdir()
            for f in ['collect.py', 'build.py']:
                (tdir / f).write_text('# stub')
            payload = {
                'source': 'https://huongnghiepviet.com/nganh-nghe',
                'schema_version': '1.1.0',
                'retrieved_at': '2026-11-11T00:00:00+00:00',
                'groups': [{'id': 'g1', 'name': 'Nhóm 1', 'source_url': 'https://huongnghiepviet.com/nganh-nghe'}],
                'subgroups': [{'id': 'sg1', 'name': 'Phân nhóm 1', 'group_id': 'g1', 'source_url': 'https://huongnghiepviet.com/nganh-nghe'}],
                'majors': [
                    {
                        'id': 'm1', 'name': 'Ngành 1', 'source_label': 'Ngành 1',
                        'group_id': 'g1', 'subgroup_id': 'sg1',
                        'source_url': 'https://huongnghiepviet.com/m1',
                        'catalogue_url': 'https://huongnghiepviet.com/nganh-nghe',
                        'detail_status': 'fetched', 'has_article_body': True,
                        'source_major_code_candidate': '7540102', 'observed_major_codes': ['7540102'],
                        'school_listing_count_extracted': 1,
                    },
                    {
                        'id': 'm2', 'name': 'Ngành 2', 'source_label': 'Ngành 2',
                        'group_id': 'g1', 'subgroup_id': 'sg1',
                        'source_url': 'https://huongnghiepviet.com/m2',
                        'catalogue_url': 'https://huongnghiepviet.com/nganh-nghe',
                        'detail_status': 'fetched', 'has_article_body': True,
                        'source_major_code_candidate': '7540102', 'observed_major_codes': ['7540102'],
                        'school_listing_count_extracted': 1,
                    },
                ],
                'major_institution_links': [
                    {
                        'major_id': 'm1', 'institution_label': 'Trường X - Hệ đại học',
                        'institution_source_url': 'https://huongnghiepviet.com/x',
                        'source_news_year': 2024, 'level_label': 'Hệ Tiến sỹ',
                        'observed_major_codes': ['7540102'], 'observed_subject_combination_codes': ['A00'],
                        'evidence_url': 'https://huongnghiepviet.com/m1',
                    },
                    {
                        'major_id': 'm2', 'institution_label': 'Trường Y',
                        'institution_source_url': 'https://huongnghiepviet.com/y',
                        'source_news_year': 2024, 'level_label': 'Hệ Đại học',
                        'observed_major_codes': ['7540102'], 'observed_subject_combination_codes': ['A00'],
                        'evidence_url': 'https://huongnghiepviet.com/m2',
                    },
                ],
            }
            (tdir / 'jobsimu_hnv.json').write_text(json.dumps(payload, ensure_ascii=False))
            stats = build.build_package(tdir, create_zip=False)
            self.assertEqual(stats['duplicate_candidate_codes'], ['7540102'])
            rq_text = (tdir / 'review_queue.csv').read_text(encoding='utf-8-sig')
            self.assertIn('DUPLICATE_CANDIDATE_CODE', rq_text)
            self.assertIn('LEVEL_CODE_MISMATCH', rq_text)


if __name__ == '__main__':
    unittest.main(verbosity=2)
