<?php
declare(strict_types=1);

/**
 * Automated tests for Provinces & Schools migration, deduplication,
 * legacy data preservation, idempotency, and strict backend validation.
 *
 * Requires TEST_DB_DSN (e.g. local disposable MySQL) or pdo_sqlite.
 */

require_once __DIR__ . '/../app/schools.php';

function assert_eq($expected, $actual, string $msg): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$msg}\n  Expected: " . var_export($expected, true) . "\n  Actual:   " . var_export($actual, true) . "\n");
        exit(1);
    }
    echo "✓ {$msg}\n";
}

function assert_true(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
    echo "✓ {$msg}\n";
}

$dsn = getenv('TEST_DB_DSN') ?: '';
$user = getenv('TEST_DB_USER') ?: null;
$pass = getenv('TEST_DB_PASS') !== false ? getenv('TEST_DB_PASS') : null;

if ($dsn !== '') {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} elseif (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    $pdo = new PDO('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} else {
    fwrite(STDERR, "SKIP: Set TEST_DB_DSN (local disposable MySQL) or enable pdo_sqlite.\n");
    exit(2);
}

$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
echo "=== Running Signup & Schools Tests (driver={$driver}) ===\n";

if ($driver === 'mysql') {
    // Clean disposable schema and seed legacy users before migration
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach (['users', 'schools', 'provinces', 'settings'] as $tbl) {
        $pdo->exec("DROP TABLE IF EXISTS `{$tbl}`");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    $pdo->exec("CREATE TABLE `users` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `email` VARCHAR(190) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL,
        `role` ENUM('student','admin') NOT NULL DEFAULT 'student',
        `grade` VARCHAR(30) NULL,
        `school` VARCHAR(150) NULL,
        `avatar_color` VARCHAR(20) NOT NULL DEFAULT '#5B7CFA',
        `status` ENUM('active','locked') NOT NULL DEFAULT 'active',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE `settings` (`setting_key` VARCHAR(100) PRIMARY KEY, `setting_value` TEXT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} else {
    $pdo->exec("CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(190) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role TEXT NOT NULL DEFAULT 'student',
        grade VARCHAR(30) NULL,
        school VARCHAR(150) NULL,
        avatar_color VARCHAR(20) NOT NULL DEFAULT '#5B7CFA',
        status TEXT NOT NULL DEFAULT 'active',
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )");
}

// Seed legacy users covering all 4 existing schools + 1 historical out-of-scope school + 1 blank school
$insUser = $pdo->prepare('INSERT INTO users(name, email, password, role, grade, school) VALUES(?, ?, ?, ?, ?, ?)');
$insUser->execute(['Legacy 1', 'legacy1@example.test', 'hash', 'student', 'Lớp 11', 'Trường PTDTNT THPT Tỉnh']);
$insUser->execute(['Legacy 2', 'legacy2@example.test', 'hash', 'student', 'Lớp 12', 'Trường THPT Quyết Thắng']);
$insUser->execute(['Legacy 3', 'legacy3@example.test', 'hash', 'student', 'Lớp 10', 'Trường THPT Chu Văn An']);
$insUser->execute(['Legacy 4', 'legacy4@example.test', 'hash', 'student', 'Lớp 12', 'Trường PTDTNT THPT Mường Tè']);
$insUser->execute(['Historical OutOfScope', 'hist@example.test', 'hash', 'student', 'Lớp 8', 'THCS Bình Minh']);
$insUser->execute(['Legacy Blank', 'blank@example.test', 'hash', 'student', 'Lớp 11', '']);

// 1. Run migration #1
$res1 = schools_migrate($pdo, true);
assert_eq(3, $res1['provinces_inserted'], 'First migration inserts 3 provinces');
assert_eq(6, $res1['schools_inserted'], 'First migration inserts 6 deduplicated schools');
assert_eq(4, $res1['users_backfilled'], 'Backfills only the 4 legacy users matching known schools');

$provinces = schools_list_signup_provinces($pdo);
assert_eq(3, count($provinces), 'Signup province dropdown has exactly 3 provinces');
assert_eq(
    ['Thành phố Hải Phòng', 'Tỉnh Điện Biên', 'Tỉnh Lai Châu'],
    array_column($provinces, 'name'),
    'Province names match exact requirements in order'
);

$provByCode = [];
foreach ($provinces as $p) {
    $provByCode[$p['code']] = $p;
}

// 2. Verify schools per province
$hpSchools = schools_list_signup_by_province($provByCode['hai-phong']['id'], $pdo);
assert_eq(
    ['Trường THPT Lương Thế Vinh Hải Dương'],
    array_column($hpSchools, 'name'),
    'Thành phố Hải Phòng has exactly Trường THPT Lương Thế Vinh Hải Dương'
);

$dbSchools = schools_list_signup_by_province($provByCode['dien-bien']['id'], $pdo);
assert_eq(
    ['Trường PTDTNT THPT tỉnh Điện Biên'],
    array_column($dbSchools, 'name'),
    'Tỉnh Điện Biên has exactly Trường PTDTNT THPT tỉnh Điện Biên'
);

$lcSchools = schools_list_signup_by_province($provByCode['lai-chau']['id'], $pdo);
assert_eq(
    [
        'Trường THPT Chu Văn An',
        'Trường PTDTNT THPT Tỉnh',
        'Trường THPT Quyết Thắng',
        'Trường PTDTNT THPT Mường Tè',
    ],
    array_column($lcSchools, 'name'),
    'Tỉnh Lai Châu has exactly 4 deduplicated schools (Chu Văn An + 3 other legacy schools)'
);

// Record original IDs before rerun
$origProvIds = $pdo->query('SELECT code, id FROM provinces ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);
$origSchoolIds = $pdo->query('SELECT code, id FROM schools ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);

// Insert a historical out-of-scope province & school to verify migration preserves them without enabling signup
$pdo->exec("INSERT INTO provinces(code, name, sort_order, signup_enabled) VALUES('ha-noi-legacy', 'Thành phố Hà Nội (Lịch sử)', 99, 1)");
$histProvId = (int)$pdo->lastInsertId();
$stmtHist = $pdo->prepare("INSERT INTO schools(code, province_id, name, sort_order, signup_enabled) VALUES('hn-legacy-school', ?, 'Trường Lịch Sử Ngoài Phạm Vi', 99, 1)");
$stmtHist->execute([$histProvId]);
$histSchoolId = (int)$pdo->lastInsertId();

// 3. Run migration #2 (idempotency + preservation)
$res2 = schools_migrate($pdo, true);
assert_eq(0, $res2['provinces_inserted'], 'Second migration inserts 0 duplicate provinces');
assert_eq(0, $res2['schools_inserted'], 'Second migration inserts 0 duplicate schools');
assert_eq(0, $res2['users_backfilled'], 'Second migration does not re-backfill already linked users');

assert_eq(
    $origProvIds,
    $pdo->query("SELECT code, id FROM provinces WHERE code IN ('hai-phong','dien-bien','lai-chau') ORDER BY id")->fetchAll(PDO::FETCH_KEY_PAIR),
    'Province IDs remain unchanged after rerun'
);
assert_eq(
    $origSchoolIds,
    $pdo->query("SELECT code, id FROM schools WHERE signup_enabled = 1 ORDER BY id")->fetchAll(PDO::FETCH_KEY_PAIR),
    'School IDs remain unchanged after rerun'
);
assert_eq(3, count(schools_list_signup_provinces($pdo)), 'Out-of-scope historical province is excluded from signup');
assert_eq(1, (int)$pdo->query("SELECT COUNT(*) FROM schools WHERE id = {$histSchoolId} AND signup_enabled = 0")->fetchColumn(), 'Out-of-scope historical school is preserved with signup_enabled=0');

// 4. Verify legacy user links & preservation
$legacyRows = $pdo->query('SELECT email, school, school_id FROM users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
foreach ($legacyRows as $row) {
    $enriched = schools_enrich_user($row + ['role' => 'student'], $pdo);
    if (str_starts_with($row['email'], 'legacy')) {
        assert_true($enriched['school_id'] !== null, "Legacy user {$row['email']} linked to school_id");
        assert_eq('Tỉnh Lai Châu', $enriched['province_name'], "Legacy user {$row['email']} derives province Tỉnh Lai Châu");
        assert_eq(false, $enriched['school_needs_completion'], "Legacy user {$row['email']} does not need completion");
    } elseif ($row['email'] === 'hist@example.test') {
        assert_eq(null, $enriched['school_id'], 'Historical THCS Bình Minh user is NOT auto-assigned school_id');
        assert_eq('THCS Bình Minh', $enriched['school'], 'Historical THCS Bình Minh text is preserved');
        assert_eq(null, $enriched['province_name'], 'Historical THCS Bình Minh user has no guessed province');
        assert_eq(true, $enriched['school_needs_completion'], 'Historical user flagged as needing profile completion');
    } elseif ($row['email'] === 'blank@example.test') {
        assert_eq(null, $enriched['school_id'], 'Blank-school user is NOT auto-assigned school_id');
        assert_eq('', $enriched['school'], 'Blank-school user remains blank');
        assert_eq(true, $enriched['school_needs_completion'], 'Blank-school user flagged as needing profile completion');
    }
}

// 5. Strict backend validation checks
$vMissing = schools_validate_selection('', '', $pdo);
assert_eq(false, $vMissing['ok'], 'Rejects empty province and school');
assert_true(isset($vMissing['errors']['province_id'], $vMissing['errors']['school_id']), 'Returns inline errors for both province and school');

$vMissingSchool = schools_validate_selection((string)$provByCode['hai-phong']['id'], '', $pdo);
assert_eq(false, $vMissingSchool['ok'], 'Rejects missing school');
assert_true(!isset($vMissingSchool['errors']['province_id']) && isset($vMissingSchool['errors']['school_id']), 'Returns inline error specifically for missing school');

$vMismatch = schools_validate_selection((string)$provByCode['hai-phong']['id'], (string)$lcSchools[0]['id'], $pdo);
assert_eq(false, $vMismatch['ok'], 'Rejects cross-province pair (Hải Phòng + Chu Văn An Lai Châu)');
assert_true(isset($vMismatch['errors']['school_id']), 'Returns inline error for cross-province mismatch');

$vOutOfList = schools_validate_selection('99999', '99999', $pdo);
assert_eq(false, $vOutOfList['ok'], 'Rejects unknown province/school IDs');

$vHistorical = schools_validate_selection((string)$histProvId, (string)$histSchoolId, $pdo);
assert_eq(false, $vHistorical['ok'], 'Rejects historical out-of-scope province/school IDs (signup_enabled=0)');

$vNonNumeric = schools_validate_selection('hai-phong', 'Trường THPT Chu Văn An', $pdo);
assert_eq(false, $vNonNumeric['ok'], 'Rejects raw names/strings instead of numeric IDs');

$vValidHp = schools_validate_selection((string)$provByCode['hai-phong']['id'], (string)$hpSchools[0]['id'], $pdo);
assert_eq(true, $vValidHp['ok'], 'Accepts valid Hải Phòng + Lương Thế Vinh Hải Dương pair');
assert_eq('Thành phố Hải Phòng', $vValidHp['school']['province_name'], 'Validated Hải Phòng school resolves province Thành phố Hải Phòng');

$vValidDb = schools_validate_selection((string)$provByCode['dien-bien']['id'], (string)$dbSchools[0]['id'], $pdo);
assert_eq(true, $vValidDb['ok'], 'Accepts valid Điện Biên + PTDTNT THPT tỉnh Điện Biên pair');

$vValidLc = schools_validate_selection((string)$provByCode['lai-chau']['id'], (string)$lcSchools[1]['id'], $pdo);
assert_eq(true, $vValidLc['ok'], 'Accepts valid Lai Châu + PTDTNT THPT Tỉnh pair');

// 6. Grades 10-12 only validation & legacy/admin preservation
assert_eq(['Lớp 10', 'Lớp 11', 'Lớp 12'], schools_grade_options(), 'Grade options strictly contain Lớp 10, Lớp 11, Lớp 12');
assert_eq(true, schools_validate_grade('Lớp 10', true)['ok'], 'Accepts Lớp 10');
assert_eq(true, schools_validate_grade('Lớp 11', true)['ok'], 'Accepts Lớp 11');
assert_eq(true, schools_validate_grade('Lớp 12', true)['ok'], 'Accepts Lớp 12');
foreach (['Lớp 6', 'Lớp 7', 'Lớp 8', 'Lớp 9', 'Chọn lớp', 'Khác', ''] as $badGrade) {
    assert_eq(false, schools_validate_grade($badGrade, true)['ok'], "Rejects invalid/out-of-range grade '{$badGrade}' when required");
}
assert_eq(true, schools_validate_grade('', false)['ok'], 'Allows blank grade when optional (admin profile)');

// Verify legacy Lớp 8 student was NOT auto-converted to Lớp 10
$histFull = $pdo->query("SELECT * FROM users WHERE email = 'hist@example.test'")->fetch(PDO::FETCH_ASSOC);
assert_eq('Lớp 8', $histFull['grade'], 'Legacy Lớp 8 user grade is preserved (NOT auto-converted to Lớp 10)');
$histEnriched = schools_enrich_user($histFull, $pdo);
assert_eq(true, $histEnriched['grade_needs_completion'], 'Legacy Lớp 8 student is flagged grade_needs_completion=true');

// Verify admin with blank grade & school is NOT flagged for completion
$adminEnriched = schools_enrich_user(['role' => 'admin', 'grade' => '', 'school' => '', 'school_id' => null], $pdo);
assert_eq(false, $adminEnriched['school_needs_completion'], 'Admin is not blocked by school_needs_completion');
assert_eq(false, $adminEnriched['grade_needs_completion'], 'Admin is not blocked by grade_needs_completion');

// 7. Instant client-side lookup grouped catalog
$grouped = schools_grouped_by_province($pdo);
assert_eq(3, count($grouped), 'Grouped catalog contains exactly 3 provinces');
$hpGroup = $grouped[(string)$provByCode['hai-phong']['id']] ?? [];
$dbGroup = $grouped[(string)$provByCode['dien-bien']['id']] ?? [];
$lcGroup = $grouped[(string)$provByCode['lai-chau']['id']] ?? [];
assert_eq(1, count($hpGroup), 'Grouped Hải Phòng has exactly 1 school');
assert_eq('Trường THPT Lương Thế Vinh Hải Dương', $hpGroup[0]['name'], 'Hải Phòng school name is exact');
assert_eq(1, count($dbGroup), 'Grouped Điện Biên has exactly 1 school');
assert_eq('Trường PTDTNT THPT tỉnh Điện Biên', $dbGroup[0]['name'], 'Điện Biên school name is exact');
assert_eq(4, count($lcGroup), 'Grouped Lai Châu has exactly 4 deduplicated schools');

// Grouped catalog static fallback
$groupedFallback = schools_grouped_by_province(null);
assert_true(!empty($groupedFallback), 'Grouped catalog fallback returns valid catalog');

echo "=== All Signup & Schools Unit/Migration Tests Passed! ===\n";
