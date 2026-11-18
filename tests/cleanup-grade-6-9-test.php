<?php
declare(strict_types=1);

/**
 * Automated verification for scripts/cleanup-grade-6-9-students.php and
 * database/migrations/2026_17_cleanup_grade_6_9_students.sql on a disposable local MariaDB clone.
 *
 * Verifies:
 *  1. Strict filtering: deletes ONLY role='student' with explicit grade 6..9.
 *  2. Preserves: Grades 10-12 students, blank/NULL/'Chọn lớp' students, and admin even if grade='Lớp 8'.
 *  3. Cascades personal dependent data (assessments, assessment_matches, ai_logs), preserves audit_logs with SET NULL.
 *  4. Preserves 100% of shared tables (questions, careers, majors, schools, etc.) and other users' data.
 *  5. Backup JSON + restorable SQL are created & verified before deletion, and SQL restore brings back 100% of deleted records.
 *  6. Re-running when 0 matching accounts exist cleanly skips deletion (no-op).
 */

require_once __DIR__ . '/../scripts/cleanup-grade-6-9-students.php';

function c69_assert_eq($expected, $actual, string $msg): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$msg}\n  Expected: " . var_export($expected, true) . "\n  Actual:   " . var_export($actual, true) . "\n");
        exit(1);
    }
    echo "✓ {$msg}\n";
}

function c69_assert_true(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
    echo "✓ {$msg}\n";
}

$pdo = cleanup69_connect();
echo "=== Running Grade 6–9 Student Cleanup Verification Tests ===\n";

// Seed test fixture mirroring real schema edge cases
$insU = $pdo->prepare('INSERT INTO users (name, email, password, role, grade, school) VALUES (?, ?, ?, ?, ?, ?)');
$insU->execute(['Student Grade 8', 's8@example.test', 'hash', 'student', 'Lớp 8', 'THCS Bình Minh']);
$idS8 = (int)$pdo->lastInsertId();
$insU->execute(['Student Grade 7 Raw', 's7@example.test', 'hash', 'student', '7', 'THCS Demo']);
$idS7 = (int)$pdo->lastInsertId();
$insU->execute(['Student Grade 11', 's11@example.test', 'hash', 'student', 'Lớp 11', 'Trường PTDTNT THPT Tỉnh']);
$idS11 = (int)$pdo->lastInsertId();
$insU->execute(['Student Blank Grade', 'sblank@example.test', 'hash', 'student', '', 'Trường PTDTNT THPT Tỉnh']);
$idBlank = (int)$pdo->lastInsertId();
$insU->execute(['Student Invalid Grade', 'sinvalid@example.test', 'hash', 'student', 'Chọn lớp', 'Trường PTDTNT THPT Tỉnh']);
$idInvalid = (int)$pdo->lastInsertId();
$insU->execute(['Student Null Grade', 'snull@example.test', 'hash', 'student', null, 'THCS Bình Minh']);
$idNull = (int)$pdo->lastInsertId();
$insU->execute(['Admin With Grade 8', 'adm8@example.test', 'hash', 'admin', 'Lớp 8', '']);
$idAdm8 = (int)$pdo->lastInsertId();

// Attach personal dependent data to s8 AND s11 (to prove s11's data is untouched)
$careerId = (int)$pdo->query('SELECT id FROM careers LIMIT 1')->fetchColumn();
$insAss = $pdo->prepare("INSERT INTO assessments (user_id, answers, scores, result_code, ai_status) VALUES (?, '{}', '{}', 'IRA', 'completed')");
$insAss->execute([$idS8]);
$assS8 = (int)$pdo->lastInsertId();
$insAss->execute([$idS11]);
$assS11 = (int)$pdo->lastInsertId();

$insMatch = $pdo->prepare('INSERT INTO assessment_matches (assessment_id, career_id, match_percent, rank_order) VALUES (?, ?, ?, ?)');
$insMatch->execute([$assS8, $careerId, 88, 1]);
$insMatch->execute([$assS11, $careerId, 92, 1]);

$insAi = $pdo->prepare("INSERT INTO ai_logs (user_id, assessment_id, model, status) VALUES (?, ?, 'test', 'completed')");
$insAi->execute([$idS8, $assS8]);
$insAi->execute([$idS11, $assS11]);

$insAudit = $pdo->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?, 'Đăng nhập')");
$insAudit->execute([$idS8]);
$auditS8Id = (int)$pdo->lastInsertId();

$questionsBefore = (int)$pdo->query('SELECT COUNT(*) FROM questions')->fetchColumn();
$careersBefore = (int)$pdo->query('SELECT COUNT(*) FROM careers')->fetchColumn();
$schoolsBefore = (int)$pdo->query('SELECT COUNT(*) FROM schools')->fetchColumn();

// 1. Dry-run audit checks
$audit = cleanup69_audit($pdo);
c69_assert_eq(2, $audit['target_count'], 'Dry-run identifies exactly 2 matching Grade 6-9 students (Lớp 8 & 7)');
c69_assert_eq([$idS8, $idS7], $audit['target_ids'], 'Dry-run excludes Lớp 11, blank, Chọn lớp, NULL, and admin with Lớp 8');
c69_assert_eq([], $audit['unsafe_cascades'], 'Zero FK cascades into shared catalog tables');
c69_assert_eq(1, $audit['dependents']['assessments']['count'], 'Audit counts 1 dependent assessment');
c69_assert_eq(1, $audit['dependents']['assessment_matches']['count'], 'Audit counts 1 second-level assessment_match');
c69_assert_eq(1, $audit['dependents']['ai_logs']['count'], 'Audit counts 1 dependent ai_log');
c69_assert_eq(1, $audit['dependents']['audit_logs']['count'], 'Audit counts 1 audit_log (SET NULL)');

// Ensure dry-run metadata contains NO PII fields (name, email, password)
foreach ($audit['targets_meta'] as $m) {
    c69_assert_true(!isset($m['name']) && !isset($m['email']) && !isset($m['password']), 'Audit output contains zero PII fields');
}

// 2. Execute transactional cleanup with backup
$backupDir = '/tmp/jobsimu_cleanup69_backup_test';
@array_map('unlink', glob($backupDir . '/*') ?: []);
$res = cleanup69_execute($pdo, $backupDir);
c69_assert_eq(false, $res['skipped'], 'Cleanup executed when matching accounts existed');
c69_assert_eq(2, $res['deleted_users'], 'Deleted exactly 2 Grade 6-9 student accounts');
c69_assert_true(file_exists($res['backup']['json_path']), 'JSON backup created and verified');
c69_assert_true(file_exists($res['backup']['sql_path']), 'Restorable SQL backup created and verified');

// Verify deletions & strict preservation
c69_assert_eq(0, (int)$pdo->query("SELECT COUNT(*) FROM users WHERE id IN ({$idS8}, {$idS7})")->fetchColumn(), 'Target Grade 6-9 student rows deleted');
c69_assert_eq(5, (int)$pdo->query("SELECT COUNT(*) FROM users WHERE id IN ({$idS11}, {$idBlank}, {$idInvalid}, {$idNull}, {$idAdm8})")->fetchColumn(), 'Lớp 11, blank, Chọn lớp, NULL, and admin (Lớp 8) accounts 100% preserved');

c69_assert_eq(0, (int)$pdo->query("SELECT COUNT(*) FROM assessments WHERE id = {$assS8}")->fetchColumn(), 'Target student assessment cascaded');
c69_assert_eq(0, (int)$pdo->query("SELECT COUNT(*) FROM assessment_matches WHERE assessment_id = {$assS8}")->fetchColumn(), 'Target student assessment_matches cascaded');
c69_assert_eq(0, (int)$pdo->query("SELECT COUNT(*) FROM ai_logs WHERE user_id = {$idS8}")->fetchColumn(), 'Target student ai_logs cascaded');

c69_assert_eq(1, (int)$pdo->query("SELECT COUNT(*) FROM assessments WHERE id = {$assS11}")->fetchColumn(), 'Other user (Lớp 11) assessment 100% preserved');
c69_assert_eq(1, (int)$pdo->query("SELECT COUNT(*) FROM assessment_matches WHERE assessment_id = {$assS11}")->fetchColumn(), 'Other user assessment_matches 100% preserved');
c69_assert_eq(1, (int)$pdo->query("SELECT COUNT(*) FROM ai_logs WHERE user_id = {$idS11}")->fetchColumn(), 'Other user ai_logs 100% preserved');

$alAfter = $pdo->query("SELECT id, user_id FROM audit_logs WHERE id = {$auditS8Id}")->fetch();
c69_assert_true($alAfter !== false && $alAfter['user_id'] === null, 'audit_logs row preserved with user_id = NULL');

c69_assert_eq($questionsBefore, (int)$pdo->query('SELECT COUNT(*) FROM questions')->fetchColumn(), 'Shared questions table untouched');
c69_assert_eq($careersBefore, (int)$pdo->query('SELECT COUNT(*) FROM careers')->fetchColumn(), 'Shared careers table untouched');
c69_assert_eq($schoolsBefore, (int)$pdo->query('SELECT COUNT(*) FROM schools')->fetchColumn(), 'Shared schools table untouched');

// 3. Re-run idempotency (0 matching rows -> safely skipped)
$res2 = cleanup69_execute($pdo, $backupDir);
c69_assert_eq(true, $res2['skipped'], 'Second run skips deletion cleanly when 0 matching rows remain');
c69_assert_eq(0, $res2['deleted_users'], 'Second run deletes 0 rows');

// 4. Verify generated SQL restore file restores 100% of deleted records & links
$restoreLines = array_filter(
    array_map('trim', file($res['backup']['sql_path']) ?: []),
    static fn(string $l): bool => $l !== '' && !str_starts_with($l, '--')
);
foreach ($restoreLines as $stmtSql) {
    $pdo->exec($stmtSql);
}
c69_assert_eq(2, (int)$pdo->query("SELECT COUNT(*) FROM users WHERE id IN ({$idS8}, {$idS7})")->fetchColumn(), 'SQL restore recovers both deleted user rows');
c69_assert_eq(1, (int)$pdo->query("SELECT COUNT(*) FROM assessments WHERE id = {$assS8}")->fetchColumn(), 'SQL restore recovers deleted assessment');
c69_assert_eq(1, (int)$pdo->query("SELECT COUNT(*) FROM assessment_matches WHERE assessment_id = {$assS8}")->fetchColumn(), 'SQL restore recovers deleted assessment_matches');
c69_assert_eq(1, (int)$pdo->query("SELECT COUNT(*) FROM ai_logs WHERE user_id = {$idS8}")->fetchColumn(), 'SQL restore recovers deleted ai_logs');
c69_assert_eq($idS8, (int)$pdo->query("SELECT user_id FROM audit_logs WHERE id = {$auditS8Id}")->fetchColumn(), 'SQL restore re-links audit_logs.user_id');

// Cleanup temporary backup dir
@array_map('unlink', glob($backupDir . '/*') ?: []);
@rmdir($backupDir);

echo "=== All Grade 6–9 Student Cleanup & Restore Verification Tests Passed! ===\n";
