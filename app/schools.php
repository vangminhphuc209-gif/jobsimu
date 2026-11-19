<?php
declare(strict_types=1);

/**
 * Canonical Provinces & High Schools Directory for Student Signup & Profiles.
 *
 * Single source of truth shared between backend validation, migration/seeding,
 * JSON API (`index.php?page=schools-api`), signup form, profile settings, and admin views.
 */

function schools_canonical_catalog(): array
{
    return [
        'provinces' => [
            [
                'code' => 'hai-phong',
                'name' => 'Thành phố Hải Phòng',
                'sort_order' => 1,
            ],
            [
                'code' => 'dien-bien',
                'name' => 'Tỉnh Điện Biên',
                'sort_order' => 2,
            ],
            [
                'code' => 'lai-chau',
                'name' => 'Tỉnh Lai Châu',
                'sort_order' => 3,
            ],
            [
                'code' => 'hung-yen',
                'name' => 'Tỉnh Hưng Yên',
                'sort_order' => 4,
            ],
        ],
        'schools' => [
            // A. Thành phố Hải Phòng (1 trường)
            [
                'code' => 'hp-thpt-luong-the-vinh-hai-duong',
                'province_code' => 'hai-phong',
                'name' => 'Trường THPT Lương Thế Vinh Hải Dương',
                'sort_order' => 1,
                'is_legacy' => false,
            ],
            // B. Tỉnh Điện Biên (1 trường)
            [
                'code' => 'db-ptdtnt-thpt-tinh-dien-bien',
                'province_code' => 'dien-bien',
                'name' => 'Trường PTDTNT THPT tỉnh Điện Biên',
                'sort_order' => 1,
                'is_legacy' => false,
            ],
            // C. Tỉnh Lai Châu (4 trường cũ trên website; "Trường THPT Chu Văn An" đã tồn tại nên gộp thành 1 bản ghi, không tạo trùng)
            [
                'code' => 'lc-thpt-chu-van-an',
                'province_code' => 'lai-chau',
                'name' => 'Trường THPT Chu Văn An',
                'sort_order' => 1,
                'is_legacy' => true,
            ],
            [
                'code' => 'lc-ptdtnt-thpt-tinh',
                'province_code' => 'lai-chau',
                'name' => 'Trường PTDTNT THPT Tỉnh',
                'sort_order' => 2,
                'is_legacy' => true,
            ],
            [
                'code' => 'lc-thpt-quyet-thang',
                'province_code' => 'lai-chau',
                'name' => 'Trường THPT Quyết Thắng',
                'sort_order' => 3,
                'is_legacy' => true,
            ],
            [
                'code' => 'lc-ptdtnt-thpt-muong-te',
                'province_code' => 'lai-chau',
                'name' => 'Trường PTDTNT THPT Mường Tè',
                'sort_order' => 4,
                'is_legacy' => true,
            ],
            // D. Tỉnh Hưng Yên (1 trường)
            [
                'code' => 'hy-thpt-nguyen-du',
                'province_code' => 'hung-yen',
                'name' => 'Trường THPT Nguyễn Du',
                'sort_order' => 1,
                'is_legacy' => false,
            ],
        ],
    ];
}

function schools_grade_options(): array
{
    return ['Lớp 10', 'Lớp 11', 'Lớp 12'];
}

function schools_validate_grade($raw, bool $required = true): array
{
    $grade = is_scalar($raw) ? trim((string)$raw) : '';
    if ($grade === '') {
        if ($required) {
            return [
                'ok' => false,
                'grade' => '',
                'error' => 'Vui lòng chọn khối lớp (Lớp 10, Lớp 11 hoặc Lớp 12).',
            ];
        }
        return ['ok' => true, 'grade' => '', 'error' => ''];
    }
    if (!in_array($grade, schools_grade_options(), true)) {
        return [
            'ok' => false,
            'grade' => '',
            'error' => 'Hệ thống chỉ hỗ trợ đăng ký cho học sinh THPT (Lớp 10, Lớp 11, Lớp 12).',
        ];
    }
    return ['ok' => true, 'grade' => $grade, 'error' => ''];
}

function schools_pdo(?PDO $pdo = null): PDO
{
    return $pdo ?? db();
}

function schools_tables_available(?PDO $pdo = null): bool
{
    $conn = schools_pdo($pdo);
    try {
        $conn->query('SELECT 1 FROM provinces LIMIT 1');
        $conn->query('SELECT 1 FROM schools LIMIT 1');
        return true;
    } catch (Throwable $ignored) {
        return false;
    }
}

function schools_users_has_school_id(?PDO $pdo = null): bool
{
    $conn = schools_pdo($pdo);
    $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
    try {
        if ($driver === 'mysql') {
            $cols = $conn->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
            return in_array('school_id', $cols, true);
        }
        $cols = $conn->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $col) {
            if (($col['name'] ?? '') === 'school_id') {
                return true;
            }
        }
        return false;
    } catch (Throwable $ignored) {
        return false;
    }
}

/**
 * Idempotent migration + seed + safe backfill for provinces, schools, and users.school_id.
 * Supports both MySQL/MariaDB and SQLite. Safe to run multiple times without creating duplicates
 * or altering IDs of existing records.
 */
function schools_migrate(?PDO $pdo = null, bool $force = false): array
{
    static $migratedConnections = [];

    $conn = schools_pdo($pdo);
    $connKey = spl_object_id($conn);
    $schemaVersion = '1.1.0';

    if (!$force && !empty($migratedConnections[$connKey])) {
        return ['skipped' => true];
    }

    $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);

    // Fast path for MySQL when schema version is already up to date and tables exist
    if (!$force && $driver === 'mysql' && function_exists('setting')) {
        $currentVer = setting('schools_schema_version', '');
        if ($currentVer !== '' && version_compare($currentVer, $schemaVersion, '>=') && schools_tables_available($conn)) {
            $migratedConnections[$connKey] = true;
            return ['skipped' => true];
        }
    }

    if ($driver === 'mysql') {
        $conn->exec("CREATE TABLE IF NOT EXISTS `provinces` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `code` VARCHAR(64) NOT NULL,
            `name` VARCHAR(120) NOT NULL,
            `sort_order` INT NOT NULL DEFAULT 0,
            `signup_enabled` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_provinces_code` (`code`),
            INDEX `idx_provinces_signup` (`signup_enabled`, `sort_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $conn->exec("CREATE TABLE IF NOT EXISTS `schools` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `code` VARCHAR(80) NOT NULL,
            `province_id` INT UNSIGNED NOT NULL,
            `name` VARCHAR(150) NOT NULL,
            `sort_order` INT NOT NULL DEFAULT 0,
            `signup_enabled` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_schools_code` (`code`),
            UNIQUE KEY `uk_schools_province_name` (`province_id`, `name`),
            INDEX `idx_schools_province_signup` (`province_id`, `signup_enabled`, `sort_order`),
            CONSTRAINT `fk_schools_province` FOREIGN KEY (`province_id`) REFERENCES `provinces` (`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $userTableExists = !empty($conn->query("SHOW TABLES LIKE 'users'")->fetchAll());
        if ($userTableExists) {
            $userCols = $conn->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('school_id', $userCols, true)) {
                $afterClause = in_array('school', $userCols, true) ? ' AFTER `school`' : '';
                $conn->exec("ALTER TABLE `users` ADD COLUMN `school_id` INT UNSIGNED NULL{$afterClause}");
            }
            $indexes = $conn->query("SHOW INDEX FROM `users` WHERE Key_name = 'idx_users_school_id'")->fetchAll();
            if (empty($indexes)) {
                $conn->exec("ALTER TABLE `users` ADD INDEX `idx_users_school_id` (`school_id`)");
            }
            $fkStmt = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND CONSTRAINT_NAME = 'fk_users_school' AND CONSTRAINT_TYPE = 'FOREIGN KEY'");
            if (!$fkStmt->fetchColumn()) {
                $conn->exec("ALTER TABLE `users` ADD CONSTRAINT `fk_users_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE SET NULL");
            }
        }
    } else {
        $conn->exec("CREATE TABLE IF NOT EXISTS provinces (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code VARCHAR(64) NOT NULL UNIQUE,
            name VARCHAR(120) NOT NULL,
            sort_order INTEGER NOT NULL DEFAULT 0,
            signup_enabled INTEGER NOT NULL DEFAULT 1,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )");

        $conn->exec("CREATE TABLE IF NOT EXISTS schools (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code VARCHAR(80) NOT NULL UNIQUE,
            province_id INTEGER NOT NULL,
            name VARCHAR(150) NOT NULL,
            sort_order INTEGER NOT NULL DEFAULT 0,
            signup_enabled INTEGER NOT NULL DEFAULT 1,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(province_id, name),
            FOREIGN KEY(province_id) REFERENCES provinces(id) ON DELETE RESTRICT
        )");
        $conn->exec('CREATE INDEX IF NOT EXISTS idx_schools_province_signup ON schools(province_id, signup_enabled, sort_order)');

        $userTableExists = (bool)$conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'")->fetchColumn();
        if ($userTableExists && !schools_users_has_school_id($conn)) {
            $conn->exec('ALTER TABLE users ADD COLUMN school_id INTEGER NULL');
            $conn->exec('CREATE INDEX IF NOT EXISTS idx_users_school_id ON users(school_id)');
        }
    }

    $catalog = schools_canonical_catalog();
    $provinceIdByCode = [];
    $provincesInserted = 0;
    $provincesUpdated = 0;

    $findProvByCode = $conn->prepare('SELECT id FROM provinces WHERE code = ?');
    $findProvByName = $conn->prepare('SELECT id FROM provinces WHERE name = ?');
    $insertProv = $conn->prepare('INSERT INTO provinces(code, name, sort_order, signup_enabled) VALUES(?, ?, ?, 1)');
    $updateProv = $conn->prepare('UPDATE provinces SET code = ?, name = ?, sort_order = ?, signup_enabled = 1 WHERE id = ?');

    foreach ($catalog['provinces'] as $prov) {
        $findProvByCode->execute([$prov['code']]);
        $existingId = $findProvByCode->fetchColumn();
        if (!$existingId) {
            $findProvByName->execute([$prov['name']]);
            $existingId = $findProvByName->fetchColumn();
        }
        if ($existingId) {
            $updateProv->execute([$prov['code'], $prov['name'], $prov['sort_order'], (int)$existingId]);
            $provinceIdByCode[$prov['code']] = (int)$existingId;
            $provincesUpdated++;
        } else {
            $insertProv->execute([$prov['code'], $prov['name'], $prov['sort_order']]);
            $provinceIdByCode[$prov['code']] = (int)$conn->lastInsertId();
            $provincesInserted++;
        }
    }

    // Preserve any historical out-of-scope provinces, but disable them for signup
    $allowedProvCodes = array_column($catalog['provinces'], 'code');
    $provPh = implode(',', array_fill(0, count($allowedProvCodes), '?'));
    $conn->prepare("UPDATE provinces SET signup_enabled = 0 WHERE code NOT IN ({$provPh})")->execute($allowedProvCodes);

    $schoolsInserted = 0;
    $schoolsUpdated = 0;
    $findSchoolByCode = $conn->prepare('SELECT id FROM schools WHERE code = ?');
    $findSchoolByName = $conn->prepare('SELECT id FROM schools WHERE name = ?');
    $insertSchool = $conn->prepare('INSERT INTO schools(code, province_id, name, sort_order, signup_enabled) VALUES(?, ?, ?, ?, 1)');
    $updateSchool = $conn->prepare('UPDATE schools SET code = ?, province_id = ?, name = ?, sort_order = ?, signup_enabled = 1 WHERE id = ?');

    foreach ($catalog['schools'] as $sch) {
        $provId = $provinceIdByCode[$sch['province_code']];
        $findSchoolByCode->execute([$sch['code']]);
        $existingId = $findSchoolByCode->fetchColumn();
        if (!$existingId) {
            // Preserve existing record ID if school already exists by exact name
            $findSchoolByName->execute([$sch['name']]);
            $existingId = $findSchoolByName->fetchColumn();
        }
        if ($existingId) {
            $updateSchool->execute([$sch['code'], $provId, $sch['name'], $sch['sort_order'], (int)$existingId]);
            $schoolsUpdated++;
        } else {
            $insertSchool->execute([$sch['code'], $provId, $sch['name'], $sch['sort_order']]);
            $schoolsInserted++;
        }
    }

    // Preserve any historical out-of-scope schools, but disable them for signup
    $allowedSchoolCodes = array_column($catalog['schools'], 'code');
    $schoolPh = implode(',', array_fill(0, count($allowedSchoolCodes), '?'));
    $conn->prepare("UPDATE schools SET signup_enabled = 0 WHERE code NOT IN ({$schoolPh})")->execute($allowedSchoolCodes);

    // Safe backfill for existing users whose legacy users.school matches a known school name exactly
    $usersBackfilled = 0;
    if (schools_users_has_school_id($conn)) {
        $hasSchoolTextCol = false;
        if ($driver === 'mysql') {
            $uCols = $conn->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
            $hasSchoolTextCol = in_array('school', $uCols, true);
        } else {
            $uCols = $conn->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($uCols as $c) {
                if (($c['name'] ?? '') === 'school') {
                    $hasSchoolTextCol = true;
                    break;
                }
            }
        }

        if ($hasSchoolTextCol) {
            $allSchools = $conn->query('SELECT id, name FROM schools')->fetchAll(PDO::FETCH_ASSOC);
            $schoolByNameLower = [];
            foreach ($allSchools as $sRow) {
                $schoolByNameLower[mb_strtolower(trim((string)$sRow['name']))] = (int)$sRow['id'];
            }

            $unlinkedUsers = $conn->query("SELECT id, school FROM users WHERE school_id IS NULL AND school IS NOT NULL AND TRIM(school) <> ''")->fetchAll(PDO::FETCH_ASSOC);
            $linkStmt = $conn->prepare('UPDATE users SET school_id = ? WHERE id = ? AND school_id IS NULL');
            foreach ($unlinkedUsers as $uRow) {
                $normalized = mb_strtolower(trim((string)$uRow['school']));
                if (isset($schoolByNameLower[$normalized])) {
                    $linkStmt->execute([$schoolByNameLower[$normalized], (int)$uRow['id']]);
                    $usersBackfilled += $linkStmt->rowCount();
                }
            }
        }
    }

    if ($driver === 'mysql' && function_exists('setting')) {
        try {
            $stmt = $conn->prepare("INSERT INTO settings(setting_key, setting_value) VALUES('schools_schema_version', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([$schemaVersion]);
            setting('schools_schema_version', $schemaVersion, true);
        } catch (Throwable $ignored) {
            // settings table might not exist in isolated test fixtures
        }
    }

    $migratedConnections[$connKey] = true;

    return [
        'skipped' => false,
        'provinces_inserted' => $provincesInserted,
        'provinces_updated' => $provincesUpdated,
        'schools_inserted' => $schoolsInserted,
        'schools_updated' => $schoolsUpdated,
        'users_backfilled' => $usersBackfilled,
    ];
}

function schools_list_signup_provinces(?PDO $pdo = null): array
{
    $conn = schools_pdo($pdo);
    $rows = $conn->query('SELECT id, code, name FROM provinces WHERE signup_enabled = 1 ORDER BY sort_order ASC, id ASC')->fetchAll(PDO::FETCH_ASSOC);
    return array_map(static function (array $r): array {
        return [
            'id' => (int)$r['id'],
            'code' => (string)$r['code'],
            'name' => (string)$r['name'],
        ];
    }, $rows);
}

function schools_list_signup_by_province(int $provinceId, ?PDO $pdo = null): array
{
    if ($provinceId <= 0) {
        return [];
    }
    $conn = schools_pdo($pdo);
    $stmt = $conn->prepare(
        'SELECT s.id, s.code, s.name, s.province_id
         FROM schools s
         JOIN provinces p ON p.id = s.province_id
         WHERE s.province_id = ? AND p.signup_enabled = 1 AND s.signup_enabled = 1
         ORDER BY s.sort_order ASC, s.id ASC'
    );
    $stmt->execute([$provinceId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return array_map(static function (array $r): array {
        return [
            'id' => (int)$r['id'],
            'code' => (string)$r['code'],
            'name' => (string)$r['name'],
            'province_id' => (int)$r['province_id'],
        ];
    }, $rows);
}

/**
 * Get all active signup schools grouped by province_id for instant client-side lookup.
 * Returns array of [string $provinceId => array<array{id: int, code: string, name: string, province_id: int}>].
 */
function schools_grouped_by_province(?PDO $pdo = null): array
{
    try {
        $conn = schools_pdo($pdo);
        if (schools_tables_available($conn)) {
            $stmt = $conn->query(
                'SELECT s.id, s.code, s.name, s.province_id
                 FROM schools s
                 JOIN provinces p ON p.id = s.province_id
                 WHERE p.signup_enabled = 1 AND s.signup_enabled = 1
                 ORDER BY p.sort_order ASC, s.sort_order ASC, s.id ASC'
            );
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $grouped = [];
            foreach ($rows as $r) {
                $pid = (string)$r['province_id'];
                if (!isset($grouped[$pid])) {
                    $grouped[$pid] = [];
                }
                $grouped[$pid][] = [
                    'id' => (int)$r['id'],
                    'code' => (string)$r['code'],
                    'name' => (string)$r['name'],
                    'province_id' => (int)$r['province_id'],
                ];
            }
            if (!empty($grouped)) {
                return $grouped;
            }
        }
    } catch (Throwable $ignored) {
    }

    // Static fallback from canonical catalog
    $catalog = schools_canonical_catalog();
    $grouped = [];
    $provCodeToId = ['hai-phong' => 1, 'dien-bien' => 2, 'lai-chau' => 3, 'hung-yen' => 4];
    $counter = 1;
    foreach ($catalog['schools'] as $s) {
        $pId = (string)($provCodeToId[$s['province_code']] ?? 1);
        if (!isset($grouped[$pId])) {
            $grouped[$pId] = [];
        }
        $grouped[$pId][] = [
            'id' => $counter++,
            'code' => $s['code'],
            'name' => $s['name'],
            'province_id' => (int)$pId,
        ];
    }
    return $grouped;
}

function schools_list_signup_names(?PDO $pdo = null): array
{
    try {
        $conn = schools_pdo($pdo);
        if (schools_tables_available($conn)) {
            $rows = $conn->query(
                'SELECT s.name
                 FROM schools s
                 JOIN provinces p ON p.id = s.province_id
                 WHERE p.signup_enabled = 1 AND s.signup_enabled = 1
                 ORDER BY p.sort_order ASC, s.sort_order ASC, s.id ASC'
            )->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($rows)) {
                return array_values(array_map('strval', $rows));
            }
        }
    } catch (Throwable $ignored) {
    }
    return array_column(schools_canonical_catalog()['schools'], 'name');
}

function schools_find_province_by_id(int $provinceId, ?PDO $pdo = null): ?array
{
    if ($provinceId <= 0) {
        return null;
    }
    $conn = schools_pdo($pdo);
    $stmt = $conn->prepare('SELECT id, code, name, signup_enabled FROM provinces WHERE id = ?');
    $stmt->execute([$provinceId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    return [
        'id' => (int)$row['id'],
        'code' => (string)$row['code'],
        'name' => (string)$row['name'],
        'signup_enabled' => (int)$row['signup_enabled'] === 1,
    ];
}

function schools_find_by_id(int $schoolId, ?PDO $pdo = null): ?array
{
    if ($schoolId <= 0) {
        return null;
    }
    $conn = schools_pdo($pdo);
    $stmt = $conn->prepare(
        'SELECT s.id, s.code, s.name, s.province_id, s.signup_enabled,
                p.code AS province_code, p.name AS province_name, p.signup_enabled AS province_signup_enabled
         FROM schools s
         JOIN provinces p ON p.id = s.province_id
         WHERE s.id = ?'
    );
    $stmt->execute([$schoolId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    return [
        'id' => (int)$row['id'],
        'code' => (string)$row['code'],
        'name' => (string)$row['name'],
        'province_id' => (int)$row['province_id'],
        'province_code' => (string)$row['province_code'],
        'province_name' => (string)$row['province_name'],
        'signup_enabled' => (int)$row['signup_enabled'] === 1,
        'province_signup_enabled' => (int)$row['province_signup_enabled'] === 1,
    ];
}

function schools_parse_positive_id($raw): ?int
{
    if (is_int($raw)) {
        return $raw > 0 ? $raw : null;
    }
    if (!is_string($raw)) {
        return null;
    }
    $trimmed = trim($raw);
    if ($trimmed === '' || !preg_match('/^[1-9][0-9]*$/', $trimmed)) {
        return null;
    }
    return (int)$trimmed;
}

/**
 * Strictly validate a submitted (province_id, school_id) pair against the DB.
 * Rejects missing values, non-numeric values, out-of-scope IDs, disabled historical rows,
 * and cross-province mismatches.
 */
function schools_validate_selection($provinceIdRaw, $schoolIdRaw, ?PDO $pdo = null): array
{
    $errors = [];
    $province = null;
    $school = null;

    $rawProvinceStr = is_scalar($provinceIdRaw) ? trim((string)$provinceIdRaw) : '';
    $rawSchoolStr = is_scalar($schoolIdRaw) ? trim((string)$schoolIdRaw) : '';

    if ($rawProvinceStr === '') {
        $errors['province_id'] = 'Vui lòng chọn tỉnh/thành phố.';
    } else {
        $provinceId = schools_parse_positive_id($rawProvinceStr);
        $province = $provinceId !== null ? schools_find_province_by_id($provinceId, $pdo) : null;
        if (!$province || !$province['signup_enabled']) {
            $errors['province_id'] = 'Tỉnh/Thành phố không nằm trong danh sách hỗ trợ.';
            $province = null;
        }
    }

    if ($rawSchoolStr === '') {
        $errors['school_id'] = $province
            ? 'Vui lòng chọn trường học.'
            : 'Vui lòng chọn tỉnh/thành phố và trường học.';
    } else {
        $schoolId = schools_parse_positive_id($rawSchoolStr);
        $school = $schoolId !== null ? schools_find_by_id($schoolId, $pdo) : null;
        if (!$school || !$school['signup_enabled'] || !$school['province_signup_enabled']) {
            $errors['school_id'] = 'Trường học không nằm trong danh sách được phép đăng ký.';
            $school = null;
        } elseif ($province && $school['province_id'] !== $province['id']) {
            $errors['school_id'] = 'Trường học đã chọn không thuộc tỉnh/thành phố tương ứng.';
            $school = null;
        }
    }

    return [
        'ok' => empty($errors) && $province !== null && $school !== null,
        'errors' => $errors,
        'province' => $province,
        'school' => $school,
    ];
}

/**
 * Derive canonical school & province metadata for a user row from school_id -> schools -> provinces.
 * Never auto-assigns a school or guesses a province for unlinked historical values.
 */
function schools_enrich_user(array $user, ?PDO $pdo = null): array
{
    $schoolId = isset($user['school_id']) && $user['school_id'] !== null && $user['school_id'] !== ''
        ? (int)$user['school_id']
        : null;
    $legacySchoolText = trim((string)($user['school'] ?? ''));

    $schoolInfo = null;
    if ($schoolId !== null && $schoolId > 0) {
        try {
            if (schools_tables_available($pdo)) {
                $schoolInfo = schools_find_by_id($schoolId, $pdo);
            }
        } catch (Throwable $ignored) {
            $schoolInfo = null;
        }
    }

    if ($schoolInfo !== null) {
        $user['school_id'] = $schoolInfo['id'];
        $user['school_code'] = $schoolInfo['code'];
        $user['school_name'] = $schoolInfo['name'];
        $user['school'] = $schoolInfo['name'];
        $user['province_id'] = $schoolInfo['province_id'];
        $user['province_code'] = $schoolInfo['province_code'];
        $user['province_name'] = $schoolInfo['province_name'];
        $user['school_needs_completion'] = false;
    } else {
        $user['school_id'] = null;
        $user['school_code'] = null;
        $user['school_name'] = $legacySchoolText;
        $user['school'] = $legacySchoolText;
        $user['province_id'] = null;
        $user['province_code'] = null;
        $user['province_name'] = null;
        $user['school_needs_completion'] = (($user['role'] ?? 'student') === 'student');
    }

    $role = (string)($user['role'] ?? 'student');
    $currentGrade = trim((string)($user['grade'] ?? ''));
    $user['grade_needs_completion'] = ($role === 'student') && !in_array($currentGrade, schools_grade_options(), true);

    return $user;
}

/**
 * JSON API endpoint for `index.php?page=schools-api`.
 * - GET ?page=schools-api -> returns allowed signup provinces
 * - GET ?page=schools-api&province_id=<id> -> returns allowed signup schools in that province
 */
function schools_handle_api_request(?PDO $pdo = null): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: public, max-age=300, stale-while-revalidate=600');

    $rawProvince = $_GET['province_id'] ?? null;
    if ($rawProvince === null || $rawProvince === '') {
        echo json_encode([
            'ok' => true,
            'provinces' => schools_list_signup_provinces($pdo),
            'catalog' => schools_grouped_by_province($pdo),
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    $provinceId = schools_parse_positive_id($rawProvince);
    $province = $provinceId !== null ? schools_find_province_by_id($provinceId, $pdo) : null;
    if (!$province || !$province['signup_enabled']) {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'error' => 'Tỉnh/Thành phố không nằm trong danh sách hỗ trợ.',
            'schools' => [],
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    echo json_encode([
        'ok' => true,
        'province' => [
            'id' => $province['id'],
            'code' => $province['code'],
            'name' => $province['name'],
        ],
        'schools' => schools_list_signup_by_province($province['id'], $pdo),
    ], JSON_UNESCAPED_UNICODE);
}
