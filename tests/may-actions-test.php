<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/may-actions.php';

function expect_actions(bool $cond, string $msg): void {
    if (!$cond) throw new RuntimeException("Actions test failed: {$msg}");
}

echo "Running Mây Actions & Registry Tests...\n";

// 1. Registry catalog completeness
$registry = may_action_registry();
$expected = ['open_assessments', 'view_my_report', 'explore_majors', 'search_schools', 'compare_schools', 'explore_scenarios', 'explore_careers', 'view_roadmap'];
foreach ($expected as $exp) {
    expect_actions(isset($registry[$exp]), "Action {$exp} exists in registry");
    expect_actions(str_starts_with($registry[$exp]['path'], 'index.php?page='), "Action {$exp} has safe canonical route");
}

// 2. Guest vs Authenticated User permissions for protected action (view_my_report)
$guestBtn = may_build_action_button('view_my_report', null);
expect_actions($guestBtn !== null, 'Guest button generated');
expect_actions($guestBtn['login_required'] === true, 'Guest button marked login_required');
expect_actions($guestBtn['url'] === 'index.php?page=login', 'Guest redirected to login');

$studentBtn = may_build_action_button('view_my_report', ['id' => 1, 'role' => 'student']);
expect_actions($studentBtn !== null, 'Student button generated');
expect_actions($studentBtn['url'] === 'index.php?page=assessment-report', 'Student routed directly to report');

// 3. Dynamic career button
$careerBtn = may_build_action_button('view_career', null, ['career_id' => 5]);
expect_actions($careerBtn !== null, 'Dynamic career button generated');
expect_actions($careerBtn['url'] === 'index.php?page=career&id=5', 'Career URL has correct ID');

// 4. Dynamic major button
$majorBtn = may_build_action_button('view_major', null, ['major_code' => '7480201']);
expect_actions($majorBtn !== null, 'Dynamic major button generated');
expect_actions($majorBtn['url'] === 'index.php?page=majors&search=7480201', 'Major URL has correct code');

// 5. Keyword-based action detection
$detected = may_detect_actions('Tôi muốn làm bài test trắc nghiệm tính cách Holland', null);
expect_actions(count($detected) > 0, 'Detected actions from keyword');
expect_actions($detected[0]['id'] === 'open_assessments', 'Matched open_assessments action');

// 6. Explicit tag parsing from AI response: [action:open_assessments]
$tagged = may_detect_actions('Bạn có thể bắt đầu với [action:open_assessments] ngay nhé.', null);
expect_actions(count($tagged) > 0, 'Extracted explicit action tag');
expect_actions($tagged[0]['id'] === 'open_assessments', 'Extracted tag matches open_assessments');

// 7. Clean action tags from raw prose
$rawText = 'Hãy nhấp vào [action:search_schools] để xem điểm chuẩn.';
$cleaned = may_clean_action_tags($rawText);
expect_actions(!str_contains($cleaned, '[action:'), 'Action tag removed from prose');
expect_actions(str_contains($cleaned, 'Hãy nhấp vào'), 'Retained surrounding prose');

echo "✓ All Mây Actions & Registry Tests passed successfully!\n";
