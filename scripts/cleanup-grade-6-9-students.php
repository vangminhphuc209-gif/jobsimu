#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Safe, transactional cleanup utility for student accounts explicitly marked Grades 6–9.
 *
 * Rules enforced:
 *  - Targets ONLY rows in `users` where `role = 'student'` AND `TRIM(grade)` explicitly equals
 *    6, 7, 8, or 9 (or "Lớp 6".."Lớp 9" / accentless equivalents).
 *  - NEVER infers grade from name, email, age, or school.
 *  - NEVER deletes accounts with missing/NULL/blank/invalid/unknown grade (e.g. '', 'Chọn lớp').
 *  - NEVER deletes admin, teacher, or multi-role accounts.
 *  - NEVER deletes shared catalogs (questions, careers, majors, schools, institutions, etc.).
 *  - Defaults to DRY-RUN (read-only). Execution requires `--execute --backup-dir=<dir>`.
 *  - Creates and verifies a restorable JSON + SQL backup BEFORE deleting inside a transaction.
 *  - Never outputs PII (name, email, password hash) to stdout/logs.
 *
 * Usage:
 *   php scripts/cleanup-grade-6-9-students.php --dry-run
 *   php scripts/cleanup-grade-6-9-students.php --execute --backup-dir=/secure/path/backups
 */

const GRADE_6_9_EXACT_VALUES = [
    '6', '7', '8', '9',
    'Lớp 6', 'Lớp 7', 'Lớp 8', 'Lớp 9',
    'lớp 6', 'lớp 7', 'lớp 8', 'lớp 9',
    'Lop 6', 'Lop 7', 'Lop 8', 'Lop 9',
    'lop 6', 'lop 7', 'lop 8', 'lop 9',
];

const SHARED_PROTECTED_TABLES = [
    'questions', 'careers', 'career_groups', 'majors', 'training_majors',
    'major_fields', 'major_subgroups', 'institutions', 'programs',
    'admission_records', 'admission_combinations', 'program_combinations',
    'institution_tuition_bands', 'institution_admission_notices',
    'provinces', 'schools', 'knowledge_articles', 'external_portals',
    'settings',
];

function cleanup69_connect(): PDO
{
    $forceEnv = getenv('JOBSIMU_FORCE_ENV_DB') === '1';
    if ($forceEnv || getenv('TEST_DB_DSN')) {
        $dsn = getenv('TEST_DB_DSN') ?: sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            getenv('DB_HOST') ?: '127.0.0.1',
            (int)(getenv('DB_PORT') ?: 3306),
            getenv('DB_NAME') ?: 'jobsimu'
        );
        return new PDO($dsn, getenv('DB_USER') ?: 'root', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    $cfg = require dirname(__DIR__) . '/config.php';
    $db = $cfg['db'];
    return new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'] ?? 3306, $db['name']),
        $db['user'],
        $db['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
}

/**
 * Inspect matching accounts, FK cascade graph, and dependent row counts (strictly read-only).
 */
function cleanup69_audit(PDO $pdo): array
{
    $placeholders = implode(',', array_fill(0, count(GRADE_6_9_EXACT_VALUES), '?'));
    $stmt = $pdo->prepare("
        SELECT id, role, grade, status, created_at
        FROM users
        WHERE role = 'student'
          AND TRIM(COALESCE(grade, '')) IN ({$placeholders})
        ORDER BY id ASC
    ");
    $stmt->execute(GRADE_6_9_EXACT_VALUES);
    $targets = $stmt->fetchAll();
    $targetIds = array_map(static fn(array $r): int => (int)$r['id'], $targets);

    // Inspect FK graph referencing `users`
    $fks = $pdo->query("
        SELECT k.TABLE_NAME, k.COLUMN_NAME, k.CONSTRAINT_NAME, r.DELETE_RULE
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE k
        JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS r
          ON k.CONSTRAINT_SCHEMA = r.CONSTRAINT_SCHEMA
         AND k.CONSTRAINT_NAME = r.CONSTRAINT_NAME
        WHERE k.TABLE_SCHEMA = DATABASE()
          AND k.REFERENCED_TABLE_NAME = 'users'
        ORDER BY k.TABLE_NAME, k.COLUMN_NAME
    ")->fetchAll();

    // Guard against any accidental FK cascade into shared catalog tables
    $unsafeCascades = [];
    foreach ($fks as $fk) {
        if (in_array($fk['TABLE_NAME'], SHARED_PROTECTED_TABLES, true) && strtoupper((string)$fk['DELETE_RULE']) === 'CASCADE') {
            $unsafeCascades[] = $fk['TABLE_NAME'] . '.' . $fk['COLUMN_NAME'];
        }
    }

    // Discover all direct user_id tables + second-level dependent tables
    $userColTables = $pdo->query("
        SELECT TABLE_NAME, COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND COLUMN_NAME = 'user_id'
          AND TABLE_NAME != 'users'
        ORDER BY TABLE_NAME
    ")->fetchAll();

    $fkRuleByTable = [];
    foreach ($fks as $fk) {
        $fkRuleByTable[$fk['TABLE_NAME'] . '.' . $fk['COLUMN_NAME']] = strtoupper((string)$fk['DELETE_RULE']);
    }

    $dependents = [];
    if (!empty($targetIds)) {
        $in = implode(',', $targetIds);
        foreach ($userColTables as $tc) {
            $tbl = $tc['TABLE_NAME'];
            $col = $tc['COLUMN_NAME'];
            $cnt = (int)$pdo->query("SELECT COUNT(*) FROM `{$tbl}` WHERE `{$col}` IN ({$in})")->fetchColumn();
            $dependents[$tbl] = [
                'table' => $tbl,
                'column' => $col,
                'delete_rule' => $fkRuleByTable["{$tbl}.{$col}"] ?? 'NO_FK',
                'count' => $cnt,
            ];
        }

        // Second-level dependent tables (cascaded via parent tables)
        $secondLevelChecks = [
            ['table' => 'assessment_matches', 'parent' => 'assessments', 'fk_col' => 'assessment_id'],
            ['table' => 'career_action_plan_items', 'parent' => 'career_action_plans', 'fk_col' => 'plan_id'],
            ['table' => 'plan_share_tokens', 'parent' => 'career_action_plans', 'fk_col' => 'plan_id'],
        ];
        foreach ($secondLevelChecks as $sl) {
            $hasTbl = !empty($pdo->query("SHOW TABLES LIKE '" . $sl['table'] . "'")->fetchAll());
            $hasParent = !empty($pdo->query("SHOW TABLES LIKE '" . $sl['parent'] . "'")->fetchAll());
            if (!$hasTbl || !$hasParent) {
                continue;
            }
            $cnt = (int)$pdo->query("
                SELECT COUNT(*) FROM `{$sl['table']}` c
                JOIN `{$sl['parent']}` p ON p.id = c.`{$sl['fk_col']}`
                WHERE p.user_id IN ({$in})
            ")->fetchColumn();
            $dependents[$sl['table']] = [
                'table' => $sl['table'],
                'column' => $sl['fk_col'] . ' -> ' . $sl['parent'] . '.id',
                'delete_rule' => 'CASCADE_L2',
                'count' => $cnt,
            ];
        }
    }

    return [
        'target_count' => count($targetIds),
        'target_ids' => $targetIds,
        'targets_meta' => $targets, // id, role, grade, status, created_at only (NO PII)
        'fk_graph' => $fks,
        'unsafe_cascades' => $unsafeCascades,
        'dependents' => $dependents,
    ];
}

function cleanup69_sql_quote(PDO $pdo, $val): string
{
    if ($val === null) {
        return 'NULL';
    }
    if (is_int($val) || is_float($val)) {
        return (string)$val;
    }
    return $pdo->quote((string)$val);
}

function cleanup69_build_insert_sql(PDO $pdo, string $table, array $rows): array
{
    $lines = [];
    foreach ($rows as $row) {
        $cols = array_map(static fn(string $c): string => "`{$c}`", array_keys($row));
        $vals = array_map(static fn($v): string => cleanup69_sql_quote($pdo, $v), array_values($row));
        $lines[] = "INSERT INTO `{$table}` (" . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ');';
    }
    return $lines;
}

/**
 * Export full backup (JSON + restorable SQL) of target users and all dependent rows BEFORE deleting.
 */
function cleanup69_create_backup(PDO $pdo, array $audit, string $backupDir): array
{
    if (!is_dir($backupDir) && !@mkdir($backupDir, 0700, true)) {
        throw new RuntimeException("Không thể tạo thư mục sao lưu: {$backupDir}");
    }
    $targetIds = $audit['target_ids'];
    if (empty($targetIds)) {
        return ['json_path' => null, 'sql_path' => null, 'tables_backed_up' => []];
    }

    $in = implode(',', $targetIds);
    $snapshot = [
        'created_at' => gmdate('c'),
        'target_ids' => $targetIds,
        'tables' => [],
    ];
    $sqlLines = [
        '-- Restorable backup for Grade 6-9 student cleanup (' . gmdate('c') . ')',
        'SET FOREIGN_KEY_CHECKS = 0;',
        'START TRANSACTION;',
    ];

    // 1. users
    $userRows = $pdo->query("SELECT * FROM `users` WHERE id IN ({$in}) ORDER BY id")->fetchAll();
    $snapshot['tables']['users'] = $userRows;
    $sqlLines = array_merge($sqlLines, cleanup69_build_insert_sql($pdo, 'users', $userRows));

    // 2. Direct user_id tables in parent-before-child order (so restore succeeds even with FK checks ON)
    $parentFirstPriority = [
        'assessments' => 10,
        'soka_mirror_requests' => 11,
        'user_roadmaps' => 12,
        'career_action_plans' => 13,
        'favorites' => 20,
        'simulation_attempts' => 21,
        'user_psychometrics' => 22,
        'user_discovery_sessions' => 23,
        'user_comparisons' => 24,
        'ai_chat_messages' => 25,
        'ai_logs' => 30,
        'soka_mirror_responses' => 31,
        'user_roadmap_milestones' => 32,
        'audit_logs' => 90,
    ];
    $orderedDeps = $audit['dependents'];
    uksort($orderedDeps, static function (string $a, string $b) use ($parentFirstPriority): int {
        $pa = $parentFirstPriority[$a] ?? 50;
        $pb = $parentFirstPriority[$b] ?? 50;
        return $pa <=> $pb ?: strcmp($a, $b);
    });

    foreach ($orderedDeps as $tbl => $meta) {
        if ($meta['count'] <= 0 || $meta['delete_rule'] === 'CASCADE_L2') {
            continue;
        }
        $col = $meta['column'];
        $rows = $pdo->query("SELECT * FROM `{$tbl}` WHERE `{$col}` IN ({$in})")->fetchAll();
        $snapshot['tables'][$tbl] = $rows;
        if ($tbl === 'audit_logs') {
            foreach ($rows as $al) {
                $sqlLines[] = sprintf('UPDATE `audit_logs` SET `user_id` = %d WHERE `id` = %d;', (int)$al['user_id'], (int)$al['id']);
            }
        } else {
            $sqlLines = array_merge($sqlLines, cleanup69_build_insert_sql($pdo, $tbl, $rows));
        }
    }

    // 3. Second-level dependent tables
    $secondLevelQueries = [
        'assessment_matches' => "SELECT c.* FROM `assessment_matches` c JOIN `assessments` p ON p.id = c.assessment_id WHERE p.user_id IN ({$in})",
        'career_action_plan_items' => "SELECT c.* FROM `career_action_plan_items` c JOIN `career_action_plans` p ON p.id = c.plan_id WHERE p.user_id IN ({$in})",
        'plan_share_tokens' => "SELECT c.* FROM `plan_share_tokens` c JOIN `career_action_plans` p ON p.id = c.plan_id WHERE p.user_id IN ({$in})",
    ];
    foreach ($secondLevelQueries as $tbl => $q) {
        if (empty($audit['dependents'][$tbl]) || $audit['dependents'][$tbl]['count'] <= 0) {
            continue;
        }
        $rows = $pdo->query($q)->fetchAll();
        $snapshot['tables'][$tbl] = $rows;
        $sqlLines = array_merge($sqlLines, cleanup69_build_insert_sql($pdo, $tbl, $rows));
    }

    $sqlLines[] = 'COMMIT;';
    $sqlLines[] = 'SET FOREIGN_KEY_CHECKS = 1;';

    $stamp = gmdate('Ymd_His') . '_' . bin2hex(random_bytes(3));
    $jsonPath = rtrim($backupDir, '/') . "/grade6_9_backup_{$stamp}.json";
    $sqlPath = rtrim($backupDir, '/') . "/grade6_9_restore_{$stamp}.sql";

    $jsonBytes = file_put_contents($jsonPath, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $sqlBytes = file_put_contents($sqlPath, implode("\n", $sqlLines) . "\n");
    if ($jsonBytes === false || $jsonBytes < 10 || $sqlBytes === false || $sqlBytes < 10) {
        throw new RuntimeException('Ghi tệp sao lưu thất bại; hủy thao tác xóa.');
    }
    @chmod($jsonPath, 0600);
    @chmod($sqlPath, 0600);

    // Verify backup readability before proceeding
    $decoded = json_decode((string)file_get_contents($jsonPath), true);
    if (!is_array($decoded) || count($decoded['tables']['users'] ?? []) !== count($targetIds)) {
        throw new RuntimeException('Xác minh tệp sao lưu thất bại; hủy thao tác xóa.');
    }

    return [
        'json_path' => $jsonPath,
        'sql_path' => $sqlPath,
        'tables_backed_up' => array_map('count', $snapshot['tables']),
    ];
}

/**
 * Execute transactional deletion of strictly matched Grade 6-9 student accounts.
 */
function cleanup69_execute(PDO $pdo, string $backupDir): array
{
    $audit = cleanup69_audit($pdo);
    if (!empty($audit['unsafe_cascades'])) {
        throw new RuntimeException('Phát hiện FK cascade bất thường tới bảng dùng chung: ' . implode(', ', $audit['unsafe_cascades']));
    }
    if ($audit['target_count'] === 0) {
        return [
            'executed' => true,
            'skipped' => true,
            'deleted_users' => 0,
            'deleted_user_ids' => [],
            'dependents_affected' => [],
            'backup' => null,
        ];
    }

    // Mandatory verified backup BEFORE opening deletion transaction
    $backup = cleanup69_create_backup($pdo, $audit, $backupDir);

    $placeholders = implode(',', array_fill(0, count(GRADE_6_9_EXACT_VALUES), '?'));
    $pdo->beginTransaction();
    try {
        // Re-lock and re-verify exact matching IDs inside transaction
        $lockStmt = $pdo->prepare("
            SELECT id
            FROM users
            WHERE role = 'student'
              AND TRIM(COALESCE(grade, '')) IN ({$placeholders})
            ORDER BY id ASC
            FOR UPDATE
        ");
        $lockStmt->execute(GRADE_6_9_EXACT_VALUES);
        $lockedIds = array_map('intval', $lockStmt->fetchAll(PDO::FETCH_COLUMN));

        if ($lockedIds !== $audit['target_ids']) {
            throw new RuntimeException('Danh sách tài khoản khớp đã thay đổi giữa lúc sao lưu và thực thi; đã rollback để chạy lại an toàn.');
        }

        $idPlaceholders = implode(',', array_fill(0, count($lockedIds), '?'));

        // Delete any direct non-FK dependent personal rows first (if any tables lack FK cascade)
        foreach ($audit['dependents'] as $tbl => $meta) {
            if ($meta['count'] <= 0 || $meta['delete_rule'] !== 'NO_FK') {
                continue;
            }
            if (in_array($tbl, SHARED_PROTECTED_TABLES, true)) {
                continue;
            }
            $col = $meta['column'];
            $delDep = $pdo->prepare("DELETE FROM `{$tbl}` WHERE `{$col}` IN ({$idPlaceholders})");
            $delDep->execute($lockedIds);
        }

        // Delete strictly filtered student rows (cascades personal tables, SET NULL on audit_logs)
        $delUsers = $pdo->prepare("
            DELETE FROM users
            WHERE id IN ({$idPlaceholders})
              AND role = 'student'
              AND TRIM(COALESCE(grade, '')) IN ({$placeholders})
        ");
        $delUsers->execute(array_merge($lockedIds, GRADE_6_9_EXACT_VALUES));
        $deletedCount = $delUsers->rowCount();

        if ($deletedCount !== count($lockedIds)) {
            throw new RuntimeException("Số bản ghi xóa ({$deletedCount}) không khớp số ID đã khóa (" . count($lockedIds) . '); đã rollback.');
        }

        $pdo->commit();

        return [
            'executed' => true,
            'skipped' => false,
            'deleted_users' => $deletedCount,
            'deleted_user_ids' => $lockedIds,
            'dependents_affected' => array_filter($audit['dependents'], static fn(array $d): bool => $d['count'] > 0),
            'backup' => $backup,
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

// CLI Entrypoint (only when invoked directly, not when required by tests)
if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $args = array_slice($argv, 1);
    $execute = in_array('--execute', $args, true);
    $backupDir = '';
    foreach ($args as $a) {
        if (str_starts_with($a, '--backup-dir=')) {
            $backupDir = substr($a, strlen('--backup-dir='));
        }
    }

    $pdo = cleanup69_connect();
    $audit = cleanup69_audit($pdo);

    echo "=== Kiểm tra tài khoản học sinh khối lớp 6–9 (chế độ: " . ($execute ? 'EXECUTE' : 'DRY-RUN') . ") ===\n";
    echo "Số tài khoản khớp điều kiện (role='student' & grade rõ ràng 6..9): {$audit['target_count']}\n";
    if (!empty($audit['targets_meta'])) {
        foreach ($audit['targets_meta'] as $t) {
            echo sprintf("  - user_id=%d | role=%s | grade=%s | status=%s\n", $t['id'], $t['role'], $t['grade'], $t['status']);
        }
    }
    if (!empty($audit['unsafe_cascades'])) {
        fwrite(STDERR, 'LỖI AN TOÀN: Phát hiện FK cascade vào bảng dùng chung: ' . implode(', ', $audit['unsafe_cascades']) . "\n");
        exit(2);
    }

    echo "Phạm vi dữ liệu phụ thuộc liên quan:\n";
    $hasDeps = false;
    foreach ($audit['dependents'] as $d) {
        if ($d['count'] > 0) {
            $hasDeps = true;
            echo sprintf("  - %-28s : %d bản ghi (cơ chế: %s)\n", $d['table'], $d['count'], $d['delete_rule']);
        }
    }
    if (!$hasDeps) {
        echo "  - Không có bản ghi phụ thuộc nào.\n";
    }

    if (!$execute) {
        echo "\n[DRY-RUN] Chưa thực hiện xóa dữ liệu. Để xóa kèm sao lưu, chạy:\n";
        echo "  php scripts/cleanup-grade-6-9-students.php --execute --backup-dir=/duong/dan/backup\n";
        exit(0);
    }

    if ($audit['target_count'] === 0) {
        echo "\nKhông có tài khoản lớp 6–9 nào phù hợp; bỏ qua bước xóa.\n";
        exit(0);
    }

    if ($backupDir === '') {
        fwrite(STDERR, "LỖI: Chế độ --execute bắt buộc truyền --backup-dir=<thu_muc_sao_luu> để sao lưu trước khi xóa.\n");
        exit(1);
    }

    $result = cleanup69_execute($pdo, $backupDir);
    echo "\n✓ Đã sao lưu trước khi xóa:\n";
    echo "  - JSON: {$result['backup']['json_path']}\n";
    echo "  - SQL khôi phục: {$result['backup']['sql_path']}\n";
    echo "✓ Đã xóa an toàn trong transaction: {$result['deleted_users']} tài khoản (ID: " . implode(', ', $result['deleted_user_ids']) . ")\n";
    exit(0);
}
