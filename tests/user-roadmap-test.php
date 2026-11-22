<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/user-roadmap.php';

echo "=== Running User Custom Roadmap Tests ===" . PHP_EOL;

// 1. Check stages and library
$stages = user_roadmap_stages();
assert(isset($stages['discovery'], $stages['academic'], $stages['skills'], $stages['decision']), 'All 4 stages must be defined');
echo "✓ All 4 roadmap stages defined" . PHP_EOL;

$lib = user_roadmap_library_milestones();
assert(count($lib['discovery']) >= 3, 'Discovery library should have at least 3 milestones');
assert(count($lib['academic']) >= 3, 'Academic library should have at least 3 milestones');
assert(count($lib['skills']) >= 3, 'Skills library should have at least 3 milestones');
assert(count($lib['decision']) >= 3, 'Decision library should have at least 3 milestones');
echo "✓ Milestone suggestions library populated with standard templates" . PHP_EOL;

// 2. Safe transaction with dedicated test user
$pdo = db();
$pdo->beginTransaction();

try {
    $email = 'test_student_rm_' . time() . '@example.test';
    $stmt = $pdo->prepare("INSERT INTO users(name, email, password, role, status) VALUES('Test Student Roadmap', ?, 'secret', 'student', 'active')");
    $stmt->execute([$email]);
    $testUserId = (int)$pdo->lastInsertId();
    assert($testUserId > 0, 'Must have a valid test student ID');

    // 3. Roadmap get or create
    $roadmap = user_roadmap_get_or_create($testUserId);
    assert(!empty($roadmap['id']), 'Roadmap ID must be present');
    assert(isset($roadmap['milestones']['discovery']), 'Grouped milestones must contain discovery stage');
    echo "✓ user_roadmap_get_or_create successfully initialized/retrieved roadmap" . PHP_EOL;

// 4. Assessment insights derivation
$insights = user_roadmap_derive_assessment_insights($testUserId);
assert(is_array($insights['strengths']), 'Strengths must be an array');
assert(is_array($insights['growth_areas']), 'Growth areas must be an array');
assert(is_array($insights['recommended_milestones']), 'Recommended milestones must be an array');
echo "✓ user_roadmap_derive_assessment_insights generated recommendations from test results" . PHP_EOL;

// 5. Add custom milestone
$newMilestoneId = user_roadmap_add_milestone((int)$roadmap['id'], $testUserId, [
    'stage' => 'skills',
    'title' => 'Test Milestone: Ôn tập 3 chuyên đề KHTN',
    'description' => 'Mô tả chi tiết nhiệm vụ rèn luyện kỹ năng',
    'action_url' => 'index.php?page=knowledge-hub',
    'source' => 'custom',
]);
assert($newMilestoneId > 0, 'New milestone ID must be positive');
echo "✓ user_roadmap_add_milestone successfully inserted custom milestone" . PHP_EOL;

// 6. Toggle milestone completion
$toggleRes = user_roadmap_toggle_milestone($newMilestoneId, $testUserId);
assert($toggleRes['is_completed'] === true, 'Milestone should be toggled to completed');
assert($toggleRes['progress']['completed'] > 0, 'Completed count must increment');
echo "✓ user_roadmap_toggle_milestone toggled completion and updated progress" . PHP_EOL;

// 7. Update roadmap details (goals)
user_roadmap_update_details((int)$roadmap['id'], $testUserId, [
    'goal_title' => 'Trở thành Kỹ sư Công nghệ thông tin',
    'target_career_title' => 'Lập trình viên',
    'target_timeframe' => 'Lớp 11 - 12',
    'notes' => 'Tập trung học tốt môn Toán và Tin học',
]);
$updatedRoadmap = user_roadmap_get_or_create($testUserId);
assert($updatedRoadmap['goal_title'] === 'Trở thành Kỹ sư Công nghệ thông tin', 'Goal title must match updated value');
assert($updatedRoadmap['target_career_title'] === 'Lập trình viên', 'Target career must match updated value');
echo "✓ user_roadmap_update_details successfully saved user-defined goal and career target" . PHP_EOL;

// 8. AI suggestions generation (deterministic fallback or LLM)
$aiSuggestions = user_roadmap_ai_generate_suggestions($testUserId, $updatedRoadmap);
assert(isset($aiSuggestions['suggestions']) && count($aiSuggestions['suggestions']) > 0, 'AI must return suggestions');
assert(!empty($aiSuggestions['suggestions'][0]['title']), 'AI suggestion must have a title');
echo "✓ user_roadmap_ai_generate_suggestions successfully produced actionable milestones" . PHP_EOL;

// 9. Clean up test milestone
user_roadmap_delete_milestone($newMilestoneId, $testUserId);
$milestonesAfterDelete = user_roadmap_get_milestones((int)$roadmap['id'], $testUserId);
$found = false;
foreach ($milestonesAfterDelete['skills']['items'] as $item) {
    if ((int)$item['id'] === $newMilestoneId) { $found = true; break; }
}
assert(!$found, 'Test milestone must be deleted');
echo "✓ user_roadmap_delete_milestone successfully removed milestone" . PHP_EOL;

echo "=== All User Custom Roadmap Tests Passed! ===" . PHP_EOL;

} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (isset($testUserId) && $testUserId > 0) {
        $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$testUserId]);
    }
    echo "✓ Test student cleaned up safely, real database preserved 100%." . PHP_EOL;
}
