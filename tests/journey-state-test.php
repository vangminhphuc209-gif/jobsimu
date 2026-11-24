<?php
declare(strict_types=1);

/**
 * Unit verification for journey_user_state and journey_resolve_next_step.
 * Uses a safe transactional fixture with guaranteed rollback.
 */

define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/app/bootstrap.php';

function jtest_assert(bool $cond, string $msg): void {
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
    echo "✓ {$msg}\n";
}

echo "=== Running Journey State & Next-Step Resolver Tests ===\n";

// 1. Anonymous user (userId = 0)
$anonState = journey_user_state(0);
jtest_assert($anonState['state_code'] === 'anonymous', 'Anonymous user resolves to state anonymous');
$anonNext = journey_resolve_next_step(0);
jtest_assert($anonNext['action_key'] === 'take_holland', 'Anonymous next action is take_holland');
jtest_assert($anonNext['route'] === 'index.php?page=assessment', 'Anonymous route is index.php?page=assessment');

// Begin safe transaction for testing states
$pdo = db();
$pdo->beginTransaction();

try {
    // Create a temporary test user
    $insU = $pdo->prepare("INSERT INTO users (name, email, password, role, grade, school) VALUES ('Journey Test Student', 'jtest_tmp@jobsimu.test', 'hash', 'student', 'Lớp 11', 'THPT Test')");
    $insU->execute();
    $testUid = (int)$pdo->lastInsertId();

    // 2. State 1: not_started (0 assessments)
    $s1 = journey_user_state($testUid);
    jtest_assert($s1['state_code'] === 'not_started', 'User with 0 assessments is not_started');
    $n1 = journey_resolve_next_step($testUid);
    jtest_assert($n1['action_key'] === 'take_holland', 'State 1 next action is take_holland');
    jtest_assert($n1['route'] === 'index.php?page=assessment', 'State 1 route is index.php?page=assessment');

    // 3. State 2: profile_ready (Completed Holland, 0 favorites)
    $insAss = $pdo->prepare("INSERT INTO assessments (user_id, answers, scores, result_code, ai_status) VALUES (?, '{}', '{}', 'IRC', 'completed')");
    $insAss->execute([$testUid]);
    $assId = (int)$pdo->lastInsertId();

    $s2 = journey_user_state($testUid);
    jtest_assert($s2['state_code'] === 'profile_ready', 'User with Holland and 0 favorites is profile_ready');
    $n2 = journey_resolve_next_step($testUid);
    jtest_assert($n2['action_key'] === 'save_favorite_career', 'State 2 next action is save_favorite_career');
    jtest_assert(str_contains($n2['route'], "id={$assId}"), "State 2 route links directly to result id={$assId}");

    // 4. State 3: career_saved (Saved favorite career, 0 simulations)
    $careerId = (int)$pdo->query("SELECT id FROM careers LIMIT 1")->fetchColumn();
    $insFav = $pdo->prepare("INSERT INTO favorites (user_id, career_id) VALUES (?, ?)");
    $insFav->execute([$testUid, $careerId]);

    $s3 = journey_user_state($testUid);
    jtest_assert($s3['state_code'] === 'career_saved', 'User with favorites and 0 simulations is career_saved');
    $n3 = journey_resolve_next_step($testUid);
    jtest_assert($n3['action_key'] === 'try_simulation', 'State 3 next action is try_simulation');
    jtest_assert($n3['route'] === 'index.php?page=simulations', 'State 3 route is index.php?page=simulations');

    // 5. State 4: simulated (Completed simulation, no roadmap yet)
    $insSim = $pdo->prepare("INSERT INTO simulation_attempts (user_id, career_id, score, decisions, skill_scores, reflection_feeling, reflection_note) VALUES (?, ?, 85, '[]', '{}', 'excited', 'Cảm nhận rất tốt')");
    $insSim->execute([$testUid, $careerId]);

    $s4 = journey_user_state($testUid);
    jtest_assert($s4['state_code'] === 'simulated', 'User with simulation and no roadmap is simulated');
    $n4 = journey_resolve_next_step($testUid);
    jtest_assert($n4['action_key'] === 'setup_roadmap', 'State 4 next action is setup_roadmap');
    jtest_assert($n4['route'] === 'index.php?page=ai-roadmap', 'State 4 route is index.php?page=ai-roadmap');

    // 6. State 5: planning (Roadmap active with milestones)
    $insRm = $pdo->prepare("INSERT INTO user_roadmaps (user_id, target_career_title, goal_title, target_timeframe) VALUES (?, 'Lập trình viên', 'Mục tiêu năm học', 'Lớp 11 - 12')");
    $insRm->execute([$testUid]);
    $rmId = (int)$pdo->lastInsertId();

    $insMs = $pdo->prepare("INSERT INTO user_roadmap_milestones (roadmap_id, user_id, title, stage) VALUES (?, ?, 'Tìm hiểu 2 trường đào tạo', 'discovery')");
    $insMs->execute([$rmId, $testUid]);

    $s5 = journey_user_state($testUid);
    jtest_assert($s5['state_code'] === 'planning', 'User with roadmap and milestones is planning');
    $n5 = journey_resolve_next_step($testUid);
    jtest_assert($n5['action_key'] === 'execute_monthly_task', 'State 5 next action is execute_monthly_task');
    jtest_assert($n5['route'] === 'index.php?page=ai-roadmap', 'State 5 route is index.php?page=ai-roadmap');

    echo "=== All 5 Journey States & Next-Step Resolver Tests Passed! ===\n";
} finally {
    // ALWAYS rollback to guarantee zero database pollution
    $pdo->rollBack();
    echo "✓ Test transaction safely rolled back, database preserved 100%.\n";
}
