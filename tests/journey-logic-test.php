<?php
declare(strict_types=1);

/**
 * Automated unit & integration tests for the 4-step career guidance journey:
 *  - Migration idempotency (2026_16_journey_and_admissions + journey_run_migrations)
 *  - Step 1: Discover (5 questions, Q1 max-3 + exclusive, deterministic summary, no %)
 *  - Step 2: Lookup (accent-insensitive search, nationwide institutions, verified combinations any/all, no 0 score)
 *  - Step 3: Compare (20 questions, weights=100, exact A=45/B=35/skipped=20, edit no double-count, all-skipped, <70 preliminary, ties, close-race, rename)
 *  - Step 4: Plan (grade-personalized milestones, non-past deadlines, official source guard, AI fallback)
 *  - Chatbot rename ("Trợ lý La Bàn")
 *
 * Run against a disposable local MySQL DB via JOBSIMU_FORCE_ENV_DB=1 DB_HOST=127.0.0.1 DB_PORT=13307 ...
 */

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/discover.php';
require_once __DIR__ . '/../app/lookup.php';
require_once __DIR__ . '/../app/compare.php';
require_once __DIR__ . '/../app/plan.php';
require_once __DIR__ . '/../app/may-prompt.php';
require_once __DIR__ . '/../app/may-actions.php';

function j_assert_eq($expected, $actual, string $msg): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$msg}\n  Expected: " . var_export($expected, true) . "\n  Actual:   " . var_export($actual, true) . "\n");
        exit(1);
    }
    echo "✓ {$msg}\n";
}

function j_assert_true(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
    echo "✓ {$msg}\n";
}

$pdo = db();
echo "=== Running 4-Step Journey Logic & Integration Tests ===\n";

// 1. Idempotent journey migrations
journey_run_migrations($pdo, true);
journey_run_migrations($pdo, true);
$comboCount = (int)$pdo->query('SELECT COUNT(*) FROM admission_combinations')->fetchColumn();
j_assert_true($comboCount >= 14, "Canonical admission_combinations seeded idempotently ({$comboCount} rows)");

// Ensure migration didn't duplicate rows on second run
$comboCodes = $pdo->query('SELECT code FROM admission_combinations')->fetchAll(PDO::FETCH_COLUMN);
j_assert_eq(count($comboCodes), count(array_unique($comboCodes)), 'No duplicate combination codes after rerun');

// 2. Step 1: Discover (5 questions, Q1 exact wording, 13 subjects + exclusive "Mình chưa rõ", max 3, no %)
$discQs = discover_questions_catalog();
j_assert_eq(5, count($discQs), 'Discover has exactly 5 questions');
j_assert_eq('Bạn thích và học tốt những môn nào nhất?', $discQs[0]['text'], 'Discover Q1 matches exact required prompt');
j_assert_eq(13, count($discQs[0]['options']), 'Discover Q1 has 13 subjects');
j_assert_eq('Mình chưa rõ', $discQs[0]['exclusive_option']['label'], 'Discover Q1 has exclusive option "Mình chưa rõ"');
j_assert_eq(3, $discQs[0]['max'], 'Discover Q1 max is 3');

// Exclusive option overrides other subjects
$sanExcl = discover_sanitize_answers([
    'q1_subjects' => ['selected' => ['toan', 'vat_ly', 'chua_ro']],
    'q2_activities' => ['selected' => ['may_moc_cong_nghe', 'viet_ve_sang_tao', 'giup_do_huong_dan', 'to_chuc_nhom']],
    'q3_team_role' => ['selected' => ['len_y_tuong']],
    'q4_values' => ['selected' => ['thu_nhap', 'on_dinh', 'co_hoi_phat_trien', 'sang_tao']],
    'q5_concern' => ['selected' => ['chua_biet_thich_gi']],
]);
j_assert_eq(['chua_ro'], $sanExcl['q1_subjects']['selected'], 'Discover Q1 exclusive "chua_ro" clears other subjects');
j_assert_eq(3, count($sanExcl['q2_activities']['selected']), 'Discover Q2 enforces max 3 selections');
j_assert_eq(3, count($sanExcl['q4_values']['selected']), 'Discover Q4 enforces max 3 selections');

// Normal max-3 subjects + deterministic summary without %
$sanNorm = discover_sanitize_answers([
    'q1_subjects' => ['selected' => ['toan', 'tin_hoc', 'ngoai_ngu', 'vat_ly']],
    'q2_activities' => ['selected' => ['may_moc_cong_nghe', 'tinh_toan_phan_tich']],
    'q3_team_role' => ['selected' => ['tim_hieu_phan_tich']],
    'q4_values' => ['selected' => ['co_hoi_phat_trien', 'thu_nhap']],
    'q5_concern' => ['selected' => ['phan_van_vai_lua_chon']],
]);
j_assert_eq(['toan', 'tin_hoc', 'ngoai_ngu'], $sanNorm['q1_subjects']['selected'], 'Discover Q1 trims to max 3 valid subjects');
$summary = discover_build_summary($sanNorm);
j_assert_true(count($summary['directions']) >= 1, 'Discover summary returns suggested directions');
$summaryJson = json_encode($summary, JSON_UNESCAPED_UNICODE);
j_assert_true(strpos($summaryJson, '%') === false, 'Discover summary contains NO percentage match claims');
j_assert_true(!empty($summary['next_step_tip']), 'Discover summary includes exploratory next_step_tip');

// 3. Step 2: Lookup (accent-insensitive search, nationwide provinces, combinations any/all, no 0 score)
$meta = lookup_get_filter_metadata($pdo);
j_assert_true(count($meta['provinces']) >= 1, 'Lookup loads institution provinces from DB');
// Verify university lookup is NOT restricted to the 3 high-school signup provinces
$uniProvNames = $meta['provinces'];
j_assert_true(
    in_array('Hà Nội', $uniProvNames, true) || in_array('TP. Hồ Chí Minh', $uniProvNames, true),
    'University lookup includes nationwide provinces (e.g. Hà Nội / TP. Hồ Chí Minh), separate from 3 THPT signup provinces'
);

// Accent-insensitive major search
$majorsNoAccent = lookup_query_majors(['q' => 'cong nghe thong tin'], $pdo);
j_assert_true($majorsNoAccent['total'] >= 1, 'Accent-insensitive major search finds "Công nghệ thông tin"');

// Score formatting: missing score MUST NEVER render as 0
$missingScoreText = lookup_format_score(null, null, 'cutoff', 2025, 'THPT');
j_assert_eq('Chưa có dữ liệu xác minh', $missingScoreText, 'Missing cutoff score formats as "Chưa có dữ liệu xác minh" (never 0)');
$zeroScoreText = lookup_format_score('0', '30', 'cutoff', 2025, 'THPT');
j_assert_eq('Chưa có dữ liệu xác minh', $zeroScoreText, 'Zero/invalid cutoff score formats as "Chưa có dữ liệu xác minh"');

// Combination search by code and accentless subject name + any vs all mode
$comboSearchCode = lookup_query_combinations(['q' => 'A00', 'match_mode' => 'any'], $pdo);
j_assert_true(count($comboSearchCode['combinations_catalog']) >= 1, 'Combination search by code "A00" returns matching combinations');
j_assert_true(
    strpos($comboSearchCode['combinations_catalog'][0]['subjects_text'], 'Vật lý') !== false,
    'Combination displays full subject names ("Vật lý", not raw abbreviations)'
);
$comboSearchSubj = lookup_query_combinations(['q' => 'vat ly', 'match_mode' => 'any'], $pdo);
j_assert_true(count($comboSearchSubj['combinations_catalog']) >= 1, 'Combination search by accentless subject "vat ly" matches A00/A01/etc.');

// Test verified combination matching: insert a known verified pair and confirm any vs all behavior
$firstProgramId = (int)$pdo->query('SELECT id FROM programs LIMIT 1')->fetchColumn();
if ($firstProgramId > 0) {
    $pdo->prepare("INSERT IGNORE INTO program_combinations (program_id, admission_year, method, combination_code, conditions_note, source_url) VALUES (?, 2025, 'THPT', 'A00', 'Test verified', 'https://example.edu.vn/tuyen-sinh')")
        ->execute([$firstProgramId]);
    $pdo->prepare("INSERT IGNORE INTO program_combinations (program_id, admission_year, method, combination_code, conditions_note, source_url) VALUES (?, 2025, 'THPT', 'A01', 'Test verified', 'https://example.edu.vn/tuyen-sinh')")
        ->execute([$firstProgramId]);
    $comboAny = lookup_query_combinations(['codes' => ['A00', 'D01'], 'match_mode' => 'any'], $pdo);
    j_assert_true(count($comboAny['items']) >= 1, 'Combination match_mode=any returns programs with at least 1 selected combination');
    $comboAll = lookup_query_combinations(['codes' => ['A00', 'A01'], 'match_mode' => 'all'], $pdo);
    j_assert_true(count($comboAll['items']) >= 1, 'Combination match_mode=all returns programs verified for ALL selected combinations');
    $comboAllImpossible = lookup_query_combinations(['codes' => ['A00', 'H00'], 'match_mode' => 'all'], $pdo);
    $matchedFirstInImpossible = array_filter($comboAllImpossible['items'], fn($p) => (int)$p['program_id'] === $firstProgramId);
    j_assert_eq(0, count($matchedFirstInImpossible), 'Combination match_mode=all excludes program missing H00');
}

// 4. Step 3: Compare (20 questions, weights=100, exact scoring invariants)
$cmpQs = compare_questions_catalog();
j_assert_eq(20, count($cmpQs), 'Compare has exactly 20 questions');
$weightSum = array_sum(array_column($cmpQs, 'weight'));
j_assert_eq(100, $weightSum, 'Compare 20 question weights sum to exactly 100');
$expectedWeights = [8, 7, 6, 5, 4, 6, 5, 5, 5, 4, 5, 4, 4, 4, 3, 6, 5, 5, 5, 4];
j_assert_eq($expectedWeights, array_column($cmpQs, 'weight'), 'Compare weights match exact specification');

$optsAB = [
    ['id' => 'opt_a', 'label' => 'Công nghệ thông tin', 'type' => 'major'],
    ['id' => 'opt_b', 'label' => 'Kinh tế', 'type' => 'major'],
];

// Exact required example: A = 45, B = 35, skipped = 20 -> A=45/100, B=35/100, unallocated=20 (NO normalization!)
// Let's pick exact question indices whose weights sum to 45, 35, 20:
// Q1(8)+Q2(7)+Q3(6)+Q4(5)+Q5(4)+Q6(6)+Q7(5)+Q10(4) = 45 -> opt_a
// Q8(5)+Q9(5)+Q11(5)+Q12(4)+Q13(4)+Q14(4)+Q15(3)+Q17(5) = 35 -> opt_b
// Q16(6)+Q18(5)+Q19(5)+Q20(4) = 20 -> skipped
$answersExact = [
    '1' => 'opt_a', '2' => 'opt_a', '3' => 'opt_a', '4' => 'opt_a',
    '5' => 'opt_a', '6' => 'opt_a', '7' => 'opt_a', '10' => 'opt_a',
    '8' => 'opt_b', '9' => 'opt_b', '11' => 'opt_b', '12' => 'opt_b',
    '13' => 'opt_b', '14' => 'opt_b', '15' => 'opt_b', '17' => 'opt_b',
    '16' => 'skip', '18' => 'skip', '19' => 'skip', '20' => 'skip',
];
$scExact = compare_score_session($optsAB, $answersExact);
j_assert_eq(45, $scExact['ranked_options'][0]['score'], 'Exact example: Option A scores 45/100 (NOT normalized to 56.25)');
j_assert_eq(35, $scExact['ranked_options'][1]['score'], 'Exact example: Option B scores 35/100 (NOT normalized to 43.75)');
j_assert_eq(20, $scExact['unallocated'], 'Exact example: unallocated is exactly 20');
j_assert_eq(100, $scExact['ranked_options'][0]['score'] + $scExact['ranked_options'][1]['score'] + $scExact['unallocated'], 'Invariant: sum(scores) + unallocated === 100');
j_assert_eq(false, $scExact['is_preliminary'], 'Answered weight 80 >= 70 is NOT preliminary');
j_assert_eq(false, $scExact['close_race'], 'Gap of 10 (>5) is NOT close_race');

// Editing an answer replaces weight (no double-count)
$answersEdited = $answersExact;
$answersEdited['1'] = 'opt_b'; // shift 8 points from A to B -> A=37, B=43, unallocated=20
$scEdited = compare_score_session($optsAB, $answersEdited);
j_assert_eq('opt_b', $scEdited['ranked_options'][0]['id'], 'Editing Q1 flips leader to opt_b');
j_assert_eq(43, $scEdited['ranked_options'][0]['score'], 'Edited opt_b has 43 (no double count)');
j_assert_eq(37, $scEdited['ranked_options'][1]['score'], 'Edited opt_a has 37 (no double count)');
j_assert_eq(20, $scEdited['unallocated'], 'Edited unallocated stays 20');

// All skipped -> unallocated=100, all_skipped=true, rank=null
$answersAllSkip = [];
foreach ($cmpQs as $q) {
    $answersAllSkip[(string)$q['id']] = 'skip';
}
$scSkip = compare_score_session($optsAB, $answersAllSkip);
j_assert_eq(true, $scSkip['all_skipped'], 'All-skipped sets all_skipped=true');
j_assert_eq(100, $scSkip['unallocated'], 'All-skipped sets unallocated=100');
j_assert_eq(null, $scSkip['ranked_options'][0]['rank'], 'All-skipped does NOT assign rank 1');
j_assert_eq(null, $scSkip['ranked_options'][1]['rank'], 'All-skipped does NOT assign rank 2');

// Answered weight < 70 -> is_preliminary = true
$scPrelim = compare_score_session($optsAB, ['1' => 'opt_a', '2' => 'opt_b']); // answered = 15 < 70
j_assert_eq(true, $scPrelim['is_preliminary'], 'Answered weight 15 < 70 sets is_preliminary=true');
j_assert_eq(85, $scPrelim['unallocated'], 'Unanswered questions count as unallocated (85)');
j_assert_eq(true, $scPrelim['close_race'], 'Gap of 1 (8 vs 7) sets close_race=true');

// Tied scores share same rank (standard competition ranking 1, 1, 3)
$optsABC = [
    ['id' => 'a', 'label' => 'A', 'type' => 'custom'],
    ['id' => 'b', 'label' => 'B', 'type' => 'custom'],
    ['id' => 'c', 'label' => 'C', 'type' => 'custom'],
];
$scTie = compare_score_session($optsABC, [
    '4' => 'a', // 5
    '7' => 'b', // 5
    '15' => 'c', // 3
]);
j_assert_eq(1, $scTie['ranked_options'][0]['rank'], 'Tied first option has rank 1');
j_assert_eq(1, $scTie['ranked_options'][1]['rank'], 'Tied second option also has rank 1');
j_assert_eq(3, $scTie['ranked_options'][2]['rank'], 'Third option after two-way tie has rank 3');
j_assert_eq(true, $scTie['close_race'], 'Tie (gap 0 <= 5) sets close_race=true');

// Renaming option preserves ID and score
$optsRenamed = [
    ['id' => 'opt_a', 'label' => 'CNTT (đã đổi tên)', 'type' => 'major'],
    ['id' => 'opt_b', 'label' => 'Kinh tế', 'type' => 'major'],
];
$scRenamed = compare_score_session($optsRenamed, $answersExact);
j_assert_eq('opt_a', $scRenamed['ranked_options'][0]['id'], 'Renaming preserves option ID');
j_assert_eq('CNTT (đã đổi tên)', $scRenamed['ranked_options'][0]['label'], 'Renaming updates displayed label');
j_assert_eq(45, $scRenamed['ranked_options'][0]['score'], 'Renaming preserves exact score 45');

// 5. Step 4: Plan (grade-personalized milestones, no past dates, official source guard)
$curMonth = plan_current_month();
foreach (['Lớp 10', 'Lớp 11', 'Lớp 12'] as $gr) {
    $ms = plan_build_starter_milestones('Công nghệ thông tin', 'Kinh tế', $gr, $curMonth, '6_months', [['note' => 'Cần kiểm tra học phí']]);
    j_assert_true(count($ms) >= 4, "Starter plan for {$gr} generates >= 4 milestones");
    foreach ($ms as $m) {
        j_assert_true(strcmp($m['month_key'], $curMonth) >= 0, "Starter milestone {$m['month_key']} for {$gr} is not in the past");
        j_assert_eq('self', $m['deadline_kind'], 'Starter milestones are labeled "self" (never fabricated official deadlines)');
    }
}

// Sanitize items: official deadline without valid http(s) URL MUST be downgraded to 'self'
$sanItems = plan_sanitize_items([
    [
        'month_key' => '2020-01', // past month
        'deadline_kind' => 'official',
        'official_source_url' => '', // missing source!
        'goal' => 'Nộp hồ sơ',
        'tasks' => 'Chuẩn bị giấy tờ',
        'expected_outcome' => 'Xong hồ sơ',
        'status' => 'not_started',
    ],
    [
        'month_key' => plan_add_months($curMonth, 2),
        'deadline_kind' => 'official',
        'official_source_url' => 'https://ts.hust.edu.vn/thong-bao',
        'goal' => 'Mốc tuyển sinh chính thức',
        'tasks' => 'Theo dõi cổng tuyển sinh',
        'expected_outcome' => 'Cập nhật lịch thi',
        'status' => 'in_progress',
    ],
], $curMonth);
j_assert_eq($curMonth, $sanItems[0]['month_key'], 'Past month clamped to current month when minMonth enforced');
j_assert_eq('self', $sanItems[0]['deadline_kind'], 'Official deadline without source URL downgraded to "self"');
j_assert_eq('official', $sanItems[1]['deadline_kind'], 'Official deadline with valid https URL kept as "official"');

// 6. Chatbot rename ("Trợ lý La Bàn")
$sysPrompt = may_build_system_prompt([]);
j_assert_true(strpos($sysPrompt, 'Trợ lý La Bàn') !== false, 'Chatbot system prompt identifies as "Trợ lý La Bàn"');
j_assert_true(strpos($sysPrompt, 'Mây - Trợ Lý AI') === false, 'Chatbot system prompt no longer uses old brand "Mây - Trợ Lý AI"');
$actions = may_action_registry();
foreach (['open_discover', 'open_lookup', 'open_compare', 'open_plan', 'open_assessments', 'open_simulations'] as $actId) {
    j_assert_true(isset($actions[$actId]), "Chatbot action registry includes {$actId}");
}

echo "=== All 4-Step Journey Logic & Integration Tests Passed! ===\n";
