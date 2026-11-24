-- JobSimu Cleanup Migration: Remove student accounts explicitly marked Grades 6–9
--
-- IMPORTANT SAFETY NOTICE:
-- 1. DO NOT run directly on production without FIRST backing up affected rows
--    using: php scripts/cleanup-grade-6-9-students.php --dry-run
--    and:   php scripts/cleanup-grade-6-9-students.php --execute --backup-dir=/secure/backups
-- 2. Strict filter: ONLY deletes accounts with `role = 'student'` AND explicit grade in 6..9.
--    Never touches admin/teacher accounts, NULL/blank/invalid grades (e.g. 'Chọn lớp'),
--    Grades 10–12, or any shared catalog/test definition tables.
-- 3. Dependent personal tables (assessments, assessment_matches, ai_logs, favorites,
--    simulation_attempts, user_psychometrics, soka_mirror_*, user_roadmaps*, etc.)
--    cascade cleanly via foreign keys; `audit_logs.user_id` is preserved with SET NULL.

START TRANSACTION;

DELETE FROM `users`
WHERE `role` = 'student'
  AND TRIM(COALESCE(`grade`, '')) IN (
    '6', '7', '8', '9',
    'Lớp 6', 'Lớp 7', 'Lớp 8', 'Lớp 9',
    'lớp 6', 'lớp 7', 'lớp 8', 'lớp 9',
    'Lop 6', 'Lop 7', 'Lop 8', 'Lop 9',
    'lop 6', 'lop 7', 'lop 8', 'lop 9'
  );

COMMIT;
