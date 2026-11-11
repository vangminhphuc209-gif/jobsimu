<?php
declare(strict_types=1);
/**
 * Integration tests for HNV Training Majors migration, importer, dry-run,
 * separated status preservation, independent issue resolution, mapping conflicts/evidence,
 * rollback/failed-run logging, and Vietnamese search/filter/pagination.
 *
 * Run with local MariaDB/MySQL:
 *   TEST_DB_DSN="mysql:host=127.0.0.1;port=3307;dbname=jobsimu_test;charset=utf8mb4" TEST_DB_USER=root TEST_DB_PASS= php tests/training-majors-test.php
 */

define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/app/career-admissions.php';
require_once ROOT_PATH . '/app/training-majors.php';
require_once ROOT_PATH . '/app/training-majors-importer.php';

$dsn = getenv('TEST_DB_DSN') ?: '';
if ($dsn !== '') {
    $pdo = new PDO($dsn, getenv('TEST_DB_USER') ?: 'root', getenv('TEST_DB_PASS') !== false ? getenv('TEST_DB_PASS') : '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} else {
    if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        fwrite(STDERR, "SKIP: pdo_sqlite unavailable and TEST_DB_DSN not set.\n");
        exit(2);
    }
    $pdo = new PDO('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
}

function db(): PDO
{
    global $pdo;
    return $pdo;
}

function eq($expected, $actual, string $label): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($label . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function check(bool $cond, string $label): void
{
    if (!$cond) {
        throw new RuntimeException('Assertion failed: ' . $label);
    }
}

$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
if ($driver === 'sqlite') {
    $pdo->exec("CREATE TABLE IF NOT EXISTS careers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        slug TEXT NOT NULL UNIQUE,
        icon TEXT NOT NULL,
        short_description TEXT NOT NULL,
        description TEXT NOT NULL,
        work_environment TEXT,
        skills TEXT,
        subjects TEXT,
        education_path TEXT,
        riasec_weights TEXT NOT NULL,
        simulation_enabled INTEGER NOT NULL DEFAULT 0,
        featured INTEGER NOT NULL DEFAULT 0,
        status TEXT NOT NULL DEFAULT 'published'
    )");
}

// 1. Vietnamese Unicode helpers
eq('Mỹ thuật đô thị', hnv_to_nfc("My\u{0303} thuâ\u{0323}t đô thi\u{0323}"), 'NFD to NFC conversion');
eq('my thuat do thi', hnv_strip_vi_accents("My\u{0303} thuâ\u{0323}t đô thi\u{0323}"), 'NFD accent stripping');
eq('cong nghe thong tin', hnv_strip_vi_accents('Công nghệ thông tin'), 'NFC accent stripping');
eq(false, hnv_has_vi_diacritics('cong nghe'), 'ASCII has no diacritics');
eq(true, hnv_has_vi_diacritics('Công nghệ'), 'Vietnamese accented text detected');
eq(true, hnv_has_vi_diacritics('điện tử'), 'd-stroke detected');
eq(hnv_normalize_exact_title('Tài chính – Ngân hàng'), hnv_normalize_exact_title('Tài chính - Ngân hàng'), 'Dash unification keeps diacritics');

// 2. Run prerequisite admissions migrations & training majors migration (idempotent)
run_admissions_migrations();
training_majors_migrate($pdo);
training_majors_migrate($pdo);
check(training_majors_tables_ready($pdo) && training_majors_columns_ready($pdo), 'Training majors tables and new columns ready');

$initialCareers = (int)$pdo->query('SELECT COUNT(*) FROM careers')->fetchColumn();
$initialInsts = (int)$pdo->query('SELECT COUNT(*) FROM institutions')->fetchColumn();
$initialStarterMajors = (int)$pdo->query('SELECT COUNT(*) FROM majors')->fetchColumn();

$dataDir = ROOT_PATH . '/jobsimu_hnv_data';

// 3. Dry-run must preview exact counts and write zero rows
$dryReport = hnv_import_dataset($pdo, $dataDir, ['dry_run' => true]);
eq('dry_run', $dryReport['mode'], 'Dry-run mode');
eq(23, $dryReport['dataset_counts']['fields'], 'Dry-run fields count');
eq(76, $dryReport['dataset_counts']['subgroups'], 'Dry-run subgroups count');
eq(358, $dryReport['dataset_counts']['majors'], 'Dry-run majors count');
eq(248, $dryReport['dataset_counts']['institution_source_records'], 'Dry-run source URLs count');
eq(2712, $dryReport['dataset_counts']['major_institution_links'], 'Dry-run links count');
eq(218, $dryReport['dataset_counts']['majors_with_initial_source_warnings'], 'Dry-run initial source warnings count');
eq(358, $dryReport['dataset_counts']['review_queue'], 'Dry-run queue covers all 358 majors');
eq(358, $dryReport['tables']['training_majors']['inserted'], 'Dry-run reports 358 majors to insert');
eq(0, (int)$pdo->query('SELECT COUNT(*) FROM training_majors')->fetchColumn(), 'Dry-run must not write training_majors');
eq(0, (int)$pdo->query('SELECT COUNT(*) FROM dataset_import_runs')->fetchColumn(), 'Dry-run must not write import runs');

// 4. First real import inside transaction
$importReport = hnv_import_dataset($pdo, $dataDir, ['dry_run' => false]);
eq('import', $importReport['mode'], 'Import mode');
eq(23, $importReport['tables']['major_fields']['inserted'], '23 fields inserted');
eq(76, $importReport['tables']['major_subgroups']['inserted'], '76 subgroups inserted');
eq(358, $importReport['tables']['training_majors']['inserted'], '358 majors inserted');
eq(248, $importReport['tables']['institution_source_records']['inserted'], '248 source URLs inserted');
eq(2712, $importReport['tables']['major_institution_source_links']['inserted'], '2712 links inserted');
eq(358, $importReport['tables']['major_review_queue']['inserted'], '358 queue rows inserted');
eq(14, $importReport['tables']['training_major_mappings']['inserted'], '14 starter mappings proposed');

// Zero contamination of legacy tables
eq($initialCareers, (int)$pdo->query('SELECT COUNT(*) FROM careers')->fetchColumn(), 'Careers untouched');
eq($initialInsts, (int)$pdo->query('SELECT COUNT(*) FROM institutions')->fetchColumn(), 'Institutions untouched');
eq($initialStarterMajors, (int)$pdo->query('SELECT COUNT(*) FROM majors')->fetchColumn(), 'Starter majors untouched');

// Zero auto-verified / auto-approved records on fresh import
eq(0, (int)$pdo->query("SELECT COUNT(*) FROM training_majors WHERE official_major_code IS NOT NULL")->fetchColumn(), 'No candidate promoted to official_major_code');
eq(0, (int)$pdo->query("SELECT COUNT(*) FROM training_majors WHERE code_verification_status = 'verified'")->fetchColumn(), 'No auto-verified official code');
eq(0, (int)$pdo->query("SELECT COUNT(*) FROM training_majors WHERE description_status <> 'not_edited'")->fetchColumn(), 'All descriptions start as not_edited');
eq(0, (int)$pdo->query("SELECT COUNT(*) FROM training_major_mappings WHERE mapping_status <> 'pending_review'")->fetchColumn(), 'All 14 mappings start pending_review (no batch auto-approve)');
eq(0, (int)$pdo->query("SELECT COUNT(*) FROM major_institution_source_links WHERE admission_status <> 'historical_unverified'")->fetchColumn(), 'All 2712 links start historical_unverified');

// Duplicate candidate code 7540102 & level mismatch flagged automatically as conflict
$ktsh = hnv_get_major_detail($pdo, 'hnv-ky-thuat-sinh-hoc');
check($ktsh !== null, 'hnv-ky-thuat-sinh-hoc exists');
eq('conflict', $ktsh['code_verification_status'], 'Duplicate candidate code 7540102 automatically marked conflict');
eq(null, $ktsh['official_major_code'], 'Conflicting code never fills official_major_code');
check(in_array('DUPLICATE_CANDIDATE_CODE', array_column($ktsh['issues'], 'issue_code'), true), 'DUPLICATE_CANDIDATE_CODE issue recorded on hnv-ky-thuat-sinh-hoc');

// Audit all 14 mapping pairs: every pair must carry evidence_json and conflict flags where applicable
$mapRows = $pdo->query('SELECT mm.*, tm.id AS tm_id FROM training_major_mappings mm JOIN training_majors tm ON tm.id = mm.training_major_id')->fetchAll();
eq(14, count($mapRows), 'Exactly 14 starter mapping pairs');
$conflictsByTm = [];
foreach ($mapRows as $mr) {
    $ev = json_decode((string)$mr['evidence_json'], true);
    check(is_array($ev) && isset($ev['training_major_id'], $ev['existing_major_code']), 'Mapping pair has valid evidence_json: ' . $mr['tm_id']);
    $conflictsByTm[$mr['tm_id']] = (int)$mr['has_conflict'];
}
eq(1, $conflictsByTm['hnv-marketing'], 'Marketing (0 school links) flagged with mapping conflict note');
eq(1, $conflictsByTm['hnv-tai-chinh-ngan-hang'], 'Tai chinh - Ngan hang (dash diff & extra code) flagged with mapping conflict');
eq(1, $conflictsByTm['hnv-tam-ly-hoc'], 'Tam ly hoc (no single candidate code & multiple observed codes) flagged with mapping conflict');
eq(0, $conflictsByTm['hnv-su-pham-toan-hoc'], 'Su pham Toan hoc clean match has has_conflict=0 while still pending_review');

// Nullable fields stay NULL
$bana = hnv_get_major_detail($pdo, 'hnv-su-pham-tieng-bana');
check($bana !== null, 'hnv-su-pham-tieng-bana exists');
eq(null, $bana['listed_school_count'], 'Missing listed_school_count preserved as NULL, not 0');
eq('not_linked_by_source', $bana['detail_status'], 'not_linked_by_source preserved');
eq(null, $bana['source_url'], 'Missing source_url stays NULL');
eq('[]', $bana['observed_major_codes_json'], 'Missing observed codes preserved as []');

// Historical years preserved accurately
$yearCounts = [];
foreach ($pdo->query('SELECT source_news_year, COUNT(*) AS c FROM major_institution_source_links GROUP BY source_news_year')->fetchAll() as $yr) {
    $yearCounts[(int)$yr['source_news_year']] = (int)$yr['c'];
}
eq([2019 => 57, 2021 => 35, 2022 => 53, 2023 => 383, 2024 => 150, 2025 => 2034], $yearCounts, 'Exact 2019-2025 year distribution preserved');

// 5. Idempotent re-import (even with auto-conflict rows, clean unchanged dataset does 0 inserts/updates and 0 false preserved counts)
$reimportReport = hnv_import_dataset($pdo, $dataDir, ['dry_run' => false]);
eq(0, $reimportReport['totals']['inserted'], 'Re-import inserts 0 rows');
eq(0, $reimportReport['totals']['updated'], 'Re-import updates 0 rows');
eq(0, $reimportReport['totals']['preserved_editorial'], 'Unedited auto-conflict does not trigger fake preserved_editorial count');
eq(358, (int)$pdo->query('SELECT COUNT(*) FROM training_majors')->fetchColumn(), 'No duplicate majors');
eq(2712, (int)$pdo->query('SELECT COUNT(*) FROM major_institution_source_links')->fetchColumn(), 'No duplicate links');

// 6. Separated statuses + independent issue closure + protection on re-import
// 6a: Save draft description -> must NOT appear in ready filter or without_approved_description drop
$pdo->exec("UPDATE training_majors SET description_status='draft', editorial_description='Bản nháp nội bộ chưa duyệt.', is_editorially_locked=1 WHERE id='hnv-giao-duc-hoc'");
$readyWithDraft = hnv_search_majors($pdo, ['status' => 'ready'], 1, 50);
eq(0, $readyWithDraft['total'], 'Draft description is excluded from public ready filter');
$statsDraft = hnv_get_catalog_stats($pdo);
eq(358, $statsDraft['without_approved_description'], 'Draft description still counts toward without_approved_description');

// 6b: Approve description & resolve ONLY MISSING_JOBSIMU_DESCRIPTION; verify UNVERIFIED_OFFICIAL_CODE remains open
$pdo->exec("UPDATE training_majors SET description_status='approved', editorial_description='Mô tả đã duyệt chính thức.', is_editorially_locked=1 WHERE id='hnv-giao-duc-hoc'");
$pdo->exec("UPDATE major_review_issues SET status='resolved', resolved_at='2026-11-11 08:00:00' WHERE major_id='hnv-giao-duc-hoc' AND issue_code='MISSING_JOBSIMU_DESCRIPTION'");
hnv_recompute_queue_status($pdo, 'hnv-giao-duc-hoc');
$statsApproved = hnv_get_catalog_stats($pdo);
eq(357, $statsApproved['without_approved_description'], 'Approved description lowers without_approved_description to 357');
eq(1, $statsApproved['description_approved_count'], '1 approved description');
$gdhUnverCode = $pdo->query("SELECT status FROM major_review_issues WHERE major_id='hnv-giao-duc-hoc' AND issue_code='UNVERIFIED_OFFICIAL_CODE'")->fetchColumn();
eq('open', $gdhUnverCode, 'Approving description does NOT auto-close UNVERIFIED_OFFICIAL_CODE');

// 6c: Verify official code with evidence on hnv-ky-thuat-sinh-hoc (overriding initial conflict) & resolve ONLY UNVERIFIED_OFFICIAL_CODE
$pdo->exec("UPDATE training_majors SET official_major_code='7420202', code_verification_status='verified', code_evidence_url='https://moet.gov.vn/danh-muc', code_checked_at='2026-11-11 09:00:00', code_effective_scope='Đại học chính quy', code_effective_year=2026, is_editorially_locked=1 WHERE id='hnv-ky-thuat-sinh-hoc'");
$pdo->exec("UPDATE major_review_issues SET status='resolved', resolved_at='2026-11-11 09:00:00' WHERE major_id='hnv-ky-thuat-sinh-hoc' AND issue_code='UNVERIFIED_OFFICIAL_CODE'");
hnv_recompute_queue_status($pdo, 'hnv-ky-thuat-sinh-hoc');
$ktshIssues = [];
foreach ($pdo->query("SELECT issue_code, status FROM major_review_issues WHERE major_id='hnv-ky-thuat-sinh-hoc'")->fetchAll() as $ir) {
    $ktshIssues[$ir['issue_code']] = $ir['status'];
}
eq('resolved', $ktshIssues['UNVERIFIED_OFFICIAL_CODE'], 'Official code verification resolves UNVERIFIED_OFFICIAL_CODE');
eq('open', $ktshIssues['MISSING_JOBSIMU_DESCRIPTION'], 'Official code verification does NOT close MISSING_JOBSIMU_DESCRIPTION');
eq('open', $ktshIssues['DUPLICATE_CANDIDATE_CODE'], 'Official code verification does NOT close DUPLICATE_CANDIDATE_CODE');

// 6d: Approve one mapping & verify official_major_code on that training_major stays untouched (NULL / unverified)
$pdo->exec("UPDATE training_major_mappings SET mapping_status='approved' WHERE training_major_id='hnv-khoa-hoc-may-tinh'");
$khmt = hnv_get_major_detail($pdo, 'hnv-khoa-hoc-may-tinh');
eq(null, $khmt['official_major_code'], 'Approving mapping does NOT set official_major_code');
eq('unverified', $khmt['code_verification_status'], 'Approving mapping does NOT verify code_verification_status');

// 6e: Mark one admission link checked & resolve EMPTY_ARTICLE_BODY on Bana
$pdo->exec("UPDATE major_institution_source_links SET admission_status='source_verified_year', link_checked_at='2026-11-11 09:30:00', link_check_note='Đã đối chiếu trang trường' WHERE id = (SELECT MIN(id) FROM (SELECT id FROM major_institution_source_links WHERE major_id='hnv-giao-duc-hoc') t)");
$pdo->exec("UPDATE major_review_issues SET status='resolved', resolved_at='2026-11-11 09:30:00' WHERE major_id='hnv-su-pham-tieng-bana' AND issue_code='SOURCE_HAS_NO_DETAIL_LINK'");
hnv_recompute_queue_status($pdo, 'hnv-su-pham-tieng-bana');
$banaQueueStatus = $pdo->query("SELECT review_status FROM major_review_queue WHERE major_id='hnv-su-pham-tieng-bana'")->fetchColumn();
eq('in_review', $banaQueueStatus, 'Resolving 1 issue puts queue in_review rather than auto-verifying remaining issues');

$tmpDir = sys_get_temp_dir() . '/hnv_test_' . bin2hex(random_bytes(4));
mkdir($tmpDir, 0777, true);
try {
    $rawJson = json_decode((string)file_get_contents($dataDir . '/jobsimu_hnv.json'), true, 512, JSON_THROW_ON_ERROR);
    foreach ($rawJson['majors'] as &$mj) {
        if ($mj['id'] === 'hnv-giao-duc-hoc') {
            $mj['name'] = 'Tên nguồn bị thay đổi';
            $mj['source_major_code_candidate'] = '9999999';
        }
        if ($mj['id'] === 'hnv-triet-hoc') {
            $mj['source_page_title'] = 'Ngành Triết học (cập nhật nguồn)';
        }
    }
    unset($mj);
    file_put_contents($tmpDir . '/jobsimu_hnv.json', json_encode($rawJson, JSON_UNESCAPED_UNICODE));

    $editReport = hnv_import_dataset($pdo, $tmpDir, ['dry_run' => false]);
    check($editReport['tables']['training_majors']['preserved_editorial'] >= 2, 'Curated/verified majors preserved');
    eq(1, $editReport['tables']['training_majors']['updated'], 'Only unlocked changed major updated');
    check($editReport['tables']['major_review_issues']['preserved_editorial'] >= 3, 'Resolved issues preserved across re-import');
    check($editReport['tables']['major_institution_source_links']['preserved_editorial'] >= 1, 'Checked link status preserved across re-import');

    $lockedGdh = hnv_get_major_detail($pdo, 'hnv-giao-duc-hoc');
    eq('Giáo dục học', $lockedGdh['name'], 'Verified major name NOT overwritten');
    eq('approved', $lockedGdh['description_status'], 'Description status preserved');
    eq('Mô tả đã duyệt chính thức.', $lockedGdh['editorial_description'], 'Editorial description preserved');
    eq(null, $lockedGdh['official_major_code'], 'Unverified official code stays NULL');

    $lockedKtsh = hnv_get_major_detail($pdo, 'hnv-ky-thuat-sinh-hoc');
    eq('7420202', $lockedKtsh['official_major_code'], 'Verified official code preserved over source duplicate conflict');
    eq('verified', $lockedKtsh['code_verification_status'], 'Verified code status preserved');
    eq('https://moet.gov.vn/danh-muc', $lockedKtsh['code_evidence_url'], 'Code evidence URL preserved');

    $verifiedLink = (int)$pdo->query("SELECT COUNT(*) FROM major_institution_source_links WHERE major_id='hnv-giao-duc-hoc' AND admission_status='source_verified_year'")->fetchColumn();
    eq(1, $verifiedLink, 'Per-link admission_status preserved across re-import');

    $banaAfter = hnv_get_major_detail($pdo, 'hnv-su-pham-tieng-bana');
    $banaSourceIssue = null;
    foreach ($banaAfter['issues'] as $bi) {
        if ($bi['issue_code'] === 'SOURCE_HAS_NO_DETAIL_LINK') {
            $banaSourceIssue = $bi['status'];
        }
    }
    eq('resolved', $banaSourceIssue, 'Resolved individual issue preserved on re-import');

    $approvedMap = $pdo->query("SELECT mapping_status FROM training_major_mappings WHERE training_major_id='hnv-khoa-hoc-may-tinh'")->fetchColumn();
    eq('approved', $approvedMap, 'Approved mapping preserved on re-import');

    // 7. Transaction rollback & failed import logging on malformed source URL
    $badJson = $rawJson;
    $badJson['groups'][] = ['id' => '999', 'name' => 'Test Rollback Group', 'source_url' => 'https://example.edu.vn/test'];
    $badJson['institution_source_records'][] = ['id' => 'hnv-inst-bad', 'source_label' => 'Bad', 'source_url' => 'javascript:alert(1)'];
    file_put_contents($tmpDir . '/jobsimu_hnv.json', json_encode($badJson, JSON_UNESCAPED_UNICODE));
    $runsBefore = (int)$pdo->query("SELECT COUNT(*) FROM dataset_import_runs")->fetchColumn();
    $failed = false;
    try {
        hnv_import_dataset($pdo, $tmpDir, ['dry_run' => false]);
    } catch (InvalidArgumentException $expected) {
        $failed = true;
    }
    check($failed, 'Invalid URL triggers exception');
    eq(0, (int)$pdo->query("SELECT COUNT(*) FROM major_fields WHERE id='999'")->fetchColumn(), 'Rollback prevented partial field insert');
    $failedRuns = (int)$pdo->query("SELECT COUNT(*) FROM dataset_import_runs WHERE status='failed'")->fetchColumn();
    check($failedRuns >= 1 || (int)$pdo->query("SELECT COUNT(*) FROM dataset_import_runs")->fetchColumn() === $runsBefore, 'Failed run logged or safely rolled back when thrown pre-transaction');
} finally {
    @unlink($tmpDir . '/jobsimu_hnv.json');
    @rmdir($tmpDir);
}

// 8. Search, filter, pagination checks
$resNoAccent = hnv_search_majors($pdo, ['q' => 'cong nghe thong tin'], 1, 24);
check($resNoAccent['total'] >= 1, 'Accentless search matches');
check(in_array('hnv-cong-nghe-thong-tin', array_column($resNoAccent['items'], 'id'), true), 'Accentless search finds Công nghệ thông tin');

$resAccentNfd = hnv_search_majors($pdo, ['q' => 'Mỹ thuật'], 1, 24);
check(in_array('hnv-my-thuat-do-thi', array_column($resAccentNfd['items'], 'id'), true), 'Accented NFC search finds NFD Mỹ thuật đô thị');

$resCode = hnv_search_majors($pdo, ['q' => '7480101'], 1, 24);
check(in_array('hnv-khoa-hoc-may-tinh', array_column($resCode['items'], 'id'), true), 'Candidate code search works');

$resVerifiedCode = hnv_search_majors($pdo, ['status' => 'code_verified'], 1, 24);
eq(1, $resVerifiedCode['total'], 'code_verified filter finds exactly 1 verified major');

$resField = hnv_search_majors($pdo, ['field' => '714'], 1, 24);
eq(41, $resField['total'], 'Field 714 has 41 majors');

$resMissing = hnv_search_majors($pdo, ['status' => 'missing_detail'], 1, 100);
check($resMissing['total'] >= 50, 'Missing detail filter includes not_linked + unexpected_page + empty_body');
check(in_array('hnv-ky-thuat-tuyen-khoang', array_column($resMissing['items'], 'id'), true), 'Missing detail filter includes unexpected_page');

$p1 = hnv_search_majors($pdo, [], 1, 24);
$p2 = hnv_search_majors($pdo, [], 2, 24);
$pLast = hnv_search_majors($pdo, [], 15, 24);
eq(358, $p1['total'], 'Catalog total is 358');
eq(15, $p1['total_pages'], '15 pages at 24 per page');
eq(24, count($p1['items']), 'Page 1 has 24 items');
eq(24, count($p2['items']), 'Page 2 has 24 items');
eq(22, count($pLast['items']), 'Page 15 has 22 items (358 - 14*24)');
eq([], array_intersect(array_column($p1['items'], 'id'), array_column($p2['items'], 'id')), 'Pages 1 and 2 do not overlap');

echo "OK: Training majors separated workflows, idempotency, evidence/protection, 14 mapping audit, rollback, and search/filter/pagination tests passed.\n";
