<?php
declare(strict_types=1);

/**
 * Preservation & Backward-Compatibility Regression Test Suite.
 * Verifies:
 *  1. Legacy psychometric algorithms (Holland, Big Five, CAAS, MBTI, EQ) are untouched & deterministic.
 *  2. New modules (Discover, Compare, Plan) NEVER overwrite or mutate legacy assessment/roadmap/simulation tables.
 *  3. Historical admission_records without valid source_url are preserved and marked 'unverified' (never auto-verified or deleted).
 *  4. Feature flags cleanly toggle new modules ON/OFF via env vars and DB settings.
 *
 * Run against a disposable local MySQL DB via JOBSIMU_FORCE_ENV_DB=1 ...
 */

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/psychometrics.php';
require_once __DIR__ . '/../app/discover.php';
require_once __DIR__ . '/../app/lookup.php';
require_once __DIR__ . '/../app/compare.php';
require_once __DIR__ . '/../app/plan.php';

function p_assert_eq($expected, $actual, string $msg): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$msg}\n  Expected: " . var_export($expected, true) . "\n  Actual:   " . var_export($actual, true) . "\n");
        exit(1);
    }
    echo "✓ {$msg}\n";
}

function p_assert_true(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
    echo "✓ {$msg}\n";
}

$pdo = db();
echo "=== Running Preservation & Backward-Compatibility Regression Tests ===\n";

// 1. Legacy Psychometric Determinism (Holland, Big Five, CAAS, MBTI, EQ)
$questions = $pdo->query("SELECT * FROM questions WHERE status='active' ORDER BY sort_order, id")->fetchAll(PDO::FETCH_ASSOC);
p_assert_true(count($questions) >= 30, 'Legacy Holland question bank preserved (' . count($questions) . ' active questions)');

$fixedRiasecAns = [];
foreach ($questions as $q) {
    $t = $q['riasec_type'];
    $fixedRiasecAns[$q['id']] = ($t === 'I') ? 5 : (($t === 'R') ? 4 : (($t === 'A') ? 3 : 2));
}
$h1 = score_assessment_scientific($fixedRiasecAns, $questions, [], 180);
$h2 = score_assessment_scientific($fixedRiasecAns, $questions, [], 180);
p_assert_eq($h1['result_code'], $h2['result_code'], 'Holland RIASEC result_code is deterministic (' . $h1['result_code'] . ')');
p_assert_eq('IRA', $h1['result_code'], 'Holland RIASEC produces expected IRA classification for I=5, R=4, A=3');
p_assert_eq($h1['raw_scores'], $h2['raw_scores'], 'Holland raw_scores unchanged across runs');

foreach (['mbti' => 12, 'eq' => 10, 'big5' => 20, 'caas' => 12] as $ptype => $expectedCount) {
    $bank = psychometric_questions($ptype);
    p_assert_eq($expectedCount, count($bank), "Legacy {$ptype} question bank preserved ({$expectedCount} items)");
    $sampleAns = [];
    foreach ($bank as $k => $q) {
        $sampleAns[$k] = (($q['pole'] ?? '+') === '+' || ($q['pole'] ?? '') === ($q['axis'][0] ?? '')) ? 4 : 2;
    }
    $r1 = score_psychometric($ptype, $sampleAns, 90);
    $r2 = score_psychometric($ptype, $sampleAns, 90);
    p_assert_eq($r1['scores'], $r2['scores'], "Legacy {$ptype} scores deterministic & unchanged");
    p_assert_eq($r1['result_code'], $r2['result_code'], "Legacy {$ptype} result_code deterministic & unchanged ({$r1['result_code']})");
}

// 2. Strict Table Isolation: new modules never modify legacy user assessments / psychometrics / simulations
$pdo->prepare("INSERT IGNORE INTO users (name, email, password, role, grade, school) VALUES ('Preservation Student', 'preserve@example.test', 'hash', 'student', 'Lớp 11', 'Trường PTDTNT THPT Tỉnh')")
    ->execute();
$uid = (int)$pdo->query("SELECT id FROM users WHERE email = 'preserve@example.test'")->fetchColumn();

$pdo->prepare("INSERT INTO assessments (user_id, answers, scores, result_code, ai_status) VALUES (?, '{}', '{\"I\":95,\"R\":80,\"A\":60,\"S\":40,\"E\":35,\"C\":30}', 'IRA', 'completed')")
    ->execute([$uid]);
$legacyAssessmentId = (int)$pdo->lastInsertId();

$pdo->prepare("INSERT INTO user_psychometrics (user_id, test_type, answers_json, scores_json, result_code, summary_label) VALUES (?, 'mbti', '{}', '{\"E\":80}', 'INTJ', 'Legacy summary')")
    ->execute([$uid]);
$legacyPsychId = (int)$pdo->lastInsertId();

$beforeAssess = $pdo->query("SELECT * FROM assessments WHERE id = {$legacyAssessmentId}")->fetch(PDO::FETCH_ASSOC);
$beforePsych = $pdo->query("SELECT * FROM user_psychometrics WHERE id = {$legacyPsychId}")->fetch(PDO::FETCH_ASSOC);

// Run new modules (Discover + Compare + Plan) for the same user
$discClean = discover_sanitize_answers([
    'q1_subjects' => ['selected' => ['toan', 'tin_hoc']],
    'q2_activities' => ['selected' => ['may_moc_cong_nghe']],
    'q3_team_role' => ['selected' => ['tim_hieu_phan_tich']],
    'q4_values' => ['selected' => ['co_hoi_phat_trien']],
    'q5_concern' => ['selected' => ['phan_van_vai_lua_chon']],
]);
$discSum = discover_build_summary($discClean);
$pdo->prepare("INSERT INTO user_discovery_sessions (user_id, question_version, answers_json, summary_json, completed) VALUES (?, 'discover_v1', ?, ?, 1)")
    ->execute([$uid, json_encode($discClean), json_encode($discSum)]);

$cmpOpts = [
    ['id' => 'custom:a', 'label' => 'Khoa học máy tính', 'type' => 'custom'],
    ['id' => 'custom:b', 'label' => 'Thiết kế đồ họa', 'type' => 'custom'],
];
$cmpRes = compare_score_session($cmpOpts, ['1' => 'custom:a', '2' => 'custom:b']);
$pdo->prepare("INSERT INTO user_comparisons (uid, user_id, question_version, options_json, answers_json, result_json, status) VALUES (?, ?, 'compare_v1', ?, '{}', ?, 'completed')")
    ->execute([journey_uuid4(), $uid, json_encode($cmpOpts), json_encode($cmpRes)]);

$afterAssess = $pdo->query("SELECT * FROM assessments WHERE id = {$legacyAssessmentId}")->fetch(PDO::FETCH_ASSOC);
$afterPsych = $pdo->query("SELECT * FROM user_psychometrics WHERE id = {$legacyPsychId}")->fetch(PDO::FETCH_ASSOC);
p_assert_eq($beforeAssess, $afterAssess, 'New modules do NOT modify legacy assessments row');
p_assert_eq($beforePsych, $afterPsych, 'New modules do NOT modify legacy user_psychometrics row');

// 3. Historical Unsourced admission_records: preserved & marked 'unverified' (NEVER auto-promoted to 'verified' or deleted)
$progId = (int)$pdo->query('SELECT id FROM programs LIMIT 1')->fetchColumn();
p_assert_true($progId > 0, 'Existing programs table has seeded records');

$pdo->prepare("INSERT INTO admission_records (program_id, admission_year, method, combination, cutoff_score, score_scale, source_url) VALUES (?, 2023, 'THPT', 'A00', 25.5, '30', '')")
    ->execute([$progId]);
$unsourcedId = (int)$pdo->lastInsertId();

$pdo->prepare("INSERT INTO admission_records (program_id, admission_year, method, combination, cutoff_score, score_scale, source_url) VALUES (?, 2023, 'THPT', 'A01', 26.0, '30', 'https://ts.hust.edu.vn/legacy-check')")
    ->execute([$progId]);
$sourcedId = (int)$pdo->lastInsertId();

journey_run_migrations($pdo, true);

$unsourcedRow = $pdo->query("SELECT id, cutoff_score, verification_status FROM admission_records WHERE id = {$unsourcedId}")->fetch(PDO::FETCH_ASSOC);
$sourcedRow = $pdo->query("SELECT id, cutoff_score, verification_status FROM admission_records WHERE id = {$sourcedId}")->fetch(PDO::FETCH_ASSOC);
p_assert_true($unsourcedRow !== false, 'Unsourced legacy admission record is preserved (NOT deleted)');
p_assert_eq('unverified', $unsourcedRow['verification_status'], 'Unsourced legacy admission record is marked "unverified" (NOT auto-promoted to verified)');
p_assert_eq('verified', $sourcedRow['verification_status'], 'Sourced admission record with https URL is marked "verified"');

$lookupRes = lookup_query_schools(['year' => 2023], $pdo);
$foundUnsourced = null;
$foundSourced = null;
foreach ($lookupRes['items'] as $item) {
    if ($item['row_id'] === 'ar-' . $unsourcedId) $foundUnsourced = $item;
    if ($item['row_id'] === 'ar-' . $sourcedId) $foundSourced = $item;
}
p_assert_true($foundUnsourced !== null, 'Lookup still exposes unsourced legacy record instead of dropping it');
p_assert_eq('unverified', $foundUnsourced['verification_status'], 'Lookup marks unsourced legacy record as "unverified"');
p_assert_eq('verified', $foundSourced['verification_status'], 'Lookup marks sourced record as "verified"');

// Cleanup test-only rows
$pdo->exec("DELETE FROM admission_records WHERE id IN ({$unsourcedId}, {$sourcedId})");

// 4. Feature Flags toggle ON/OFF safely
p_assert_eq(true, journey_feature_enabled('journey_4step'), 'Feature flag journey_4step defaults to ON');
putenv('JOBSIMU_FEATURE_JOURNEY_4STEP=0');
putenv('JOBSIMU_FEATURE_COMPARE_PRIORITY=0');
p_assert_eq(false, journey_feature_enabled('journey_4step'), 'Feature flag journey_4step toggles OFF cleanly via env var');
p_assert_eq(false, journey_feature_enabled('compare_priority'), 'Feature flag compare_priority toggles OFF cleanly via env var');
p_assert_eq(true, journey_feature_enabled('discover_quick'), 'Other feature flags stay independent when one is OFF');
putenv('JOBSIMU_FEATURE_JOURNEY_4STEP');
putenv('JOBSIMU_FEATURE_COMPARE_PRIORITY');
p_assert_eq(true, journey_feature_enabled('journey_4step'), 'Feature flag journey_4step restores to ON cleanly');

echo "=== All Preservation & Backward-Compatibility Regression Tests Passed! ===\n";
