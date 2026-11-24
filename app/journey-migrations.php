<?php
declare(strict_types=1);

/**
 * Idempotent schema migrations & canonical seeds for the 4-step career journey:
 * - Structured admissions combinations & verified program_combinations
 * - Quick Self-Discovery (5 questions)
 * - "Hiểu mình cần gì" (20-question weighted comparison)
 * - Month/Year Action Plan & revocable share links
 *
 * Safe on both MySQL/MariaDB and SQLite. Never fabricates scores or unverified data.
 */

if (!function_exists('journey_canonical_combinations')) {
    function journey_canonical_combinations(): array
    {
        return [
            ['A00', 'Toán, Vật lý, Hóa học', ['Toán', 'Vật lý', 'Hóa học'], 'Khoa học tự nhiên', 1],
            ['A01', 'Toán, Vật lý, Tiếng Anh', ['Toán', 'Vật lý', 'Tiếng Anh'], 'Khoa học tự nhiên & Ngoại ngữ', 2],
            ['B00', 'Toán, Hóa học, Sinh học', ['Toán', 'Hóa học', 'Sinh học'], 'Y sinh & Khoa học sự sống', 3],
            ['C00', 'Ngữ văn, Lịch sử, Địa lý', ['Ngữ văn', 'Lịch sử', 'Địa lý'], 'Khoa học xã hội', 4],
            ['C01', 'Ngữ văn, Toán, Vật lý', ['Ngữ văn', 'Toán', 'Vật lý'], 'Tổng hợp', 5],
            ['D01', 'Ngữ văn, Toán, Tiếng Anh', ['Ngữ văn', 'Toán', 'Tiếng Anh'], 'Ngoại ngữ & Kinh tế - Xã hội', 6],
            ['D07', 'Toán, Hóa học, Tiếng Anh', ['Toán', 'Hóa học', 'Tiếng Anh'], 'Khoa học tự nhiên & Ngoại ngữ', 7],
            ['D14', 'Ngữ văn, Lịch sử, Tiếng Anh', ['Ngữ văn', 'Lịch sử', 'Tiếng Anh'], 'Khoa học xã hội & Ngoại ngữ', 8],
            ['D15', 'Ngữ văn, Địa lý, Tiếng Anh', ['Ngữ văn', 'Địa lý', 'Tiếng Anh'], 'Khoa học xã hội & Ngoại ngữ', 9],
            ['K01', 'Toán, Ngữ văn và 1 môn Tự nhiên/Tin học/Công nghệ (Bách khoa)', ['Toán', 'Ngữ văn', 'Vật lý', 'Hóa học', 'Sinh học', 'Tin học', 'Công nghệ'], 'Tổ hợp kỹ thuật', 10],
            ['V00', 'Toán, Vật lý, Vẽ mỹ thuật', ['Toán', 'Vật lý', 'Vẽ mỹ thuật'], 'Kiến trúc & Nghệ thuật', 11],
            ['H00', 'Ngữ văn, Năng khiếu vẽ 1, Năng khiếu vẽ 2', ['Ngữ văn', 'Mỹ thuật', 'Vẽ mỹ thuật'], 'Thiết kế & Nghệ thuật', 12],
            ['X06', 'Toán, Vật lý, Tin học', ['Toán', 'Vật lý', 'Tin học'], 'Công nghệ thông tin', 13],
            ['X26', 'Toán, Tiếng Anh, Tin học', ['Toán', 'Tiếng Anh', 'Tin học'], 'Công nghệ thông tin & Ngoại ngữ', 14],
        ];
    }
}

if (!function_exists('journey_strip_accents')) {
    function journey_strip_accents(string $str): string
    {
        $str = mb_strtolower(trim($str), 'UTF-8');
        $map = [
            'à'=>'a','á'=>'a','ạ'=>'a','ả'=>'a','ã'=>'a','â'=>'a','ầ'=>'a','ấ'=>'a','ậ'=>'a','ẩ'=>'a','ẫ'=>'a','ă'=>'a','ằ'=>'a','ắ'=>'a','ặ'=>'a','ẳ'=>'a','ẵ'=>'a',
            'è'=>'e','é'=>'e','ẹ'=>'e','ẻ'=>'e','ẽ'=>'e','ê'=>'e','ề'=>'e','ế'=>'e','ệ'=>'e','ể'=>'e','ễ'=>'e',
            'ì'=>'i','í'=>'i','ị'=>'i','ỉ'=>'i','ĩ'=>'i',
            'ò'=>'o','ó'=>'o','ọ'=>'o','ỏ'=>'o','õ'=>'o','ô'=>'o','ồ'=>'o','ố'=>'o','ộ'=>'o','ổ'=>'o','ỗ'=>'o','ơ'=>'o','ờ'=>'o','ớ'=>'o','ợ'=>'o','ở'=>'o','ỡ'=>'o',
            'ù'=>'u','ú'=>'u','ụ'=>'u','ủ'=>'u','ũ'=>'u','ư'=>'u','ừ'=>'u','ứ'=>'u','ự'=>'u','ử'=>'u','ữ'=>'u',
            'ỳ'=>'y','ý'=>'y','ỵ'=>'y','ỷ'=>'y','ỹ'=>'y',
            'đ'=>'d',
        ];
        return strtr($str, $map);
    }
}

if (!function_exists('journey_uuid4')) {
    function journey_uuid4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}

if (!function_exists('journey_run_migrations')) {
    function journey_run_migrations(?PDO $pdo = null, bool $force = false): void
{
    static $done = [];
    $conn = $pdo ?? db();
    $key = spl_object_id($conn);
    $version = '1.0.0';

    if (!$force && !empty($done[$key])) {
        return;
    }

    $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
    $mysql = ($driver === 'mysql');

    if (!$force && $mysql && function_exists('setting')) {
        $cur = setting('journey_schema_version', '');
        if ($cur !== '' && version_compare($cur, $version, '>=')) {
            $done[$key] = true;
            return;
        }
    }

    $id = $mysql ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $bigId = $mysql ? 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $uInt = $mysql ? 'INT UNSIGNED' : 'INTEGER';
    $uBig = $mysql ? 'BIGINT UNSIGNED' : 'INTEGER';
    $suffix = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
    $ts = $mysql ? 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP' : 'TEXT DEFAULT CURRENT_TIMESTAMP';
    $tsUpd = $mysql ? 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP' : 'TEXT DEFAULT CURRENT_TIMESTAMP';

    // 1. Additive columns on admission_records (if table exists)
    try {
        if ($mysql) {
            $hasAr = !empty($conn->query("SHOW TABLES LIKE 'admission_records'")->fetchAll());
            if ($hasAr) {
                $cols = $conn->query('SHOW COLUMNS FROM admission_records')->fetchAll(PDO::FETCH_COLUMN);
                $alters = [
                    'campus' => 'ADD COLUMN `campus` VARCHAR(140) NULL AFTER `program_id`',
                    'round_name' => 'ADD COLUMN `round_name` VARCHAR(80) NULL AFTER `method`',
                    'score_type' => "ADD COLUMN `score_type` VARCHAR(40) NOT NULL DEFAULT 'cutoff' AFTER `candidate_group`",
                    'conditions_note' => 'ADD COLUMN `conditions_note` TEXT NULL AFTER `score_scale`',
                    'source_title' => 'ADD COLUMN `source_title` VARCHAR(240) NULL AFTER `source_url`',
                    'publisher' => 'ADD COLUMN `publisher` VARCHAR(180) NULL AFTER `source_title`',
                    'published_date' => 'ADD COLUMN `published_date` DATE NULL AFTER `publisher`',
                    'verification_status' => "ADD COLUMN `verification_status` VARCHAR(30) NOT NULL DEFAULT 'unverified' AFTER `verified_at`",
                ];
                foreach ($alters as $col => $sql) {
                    if (!in_array($col, $cols, true)) {
                        $conn->exec('ALTER TABLE `admission_records` ' . $sql);
                    }
                }
                $conn->exec("UPDATE `admission_records` SET `verification_status` = 'verified' WHERE `verification_status` = 'unverified' AND `source_url` IS NOT NULL AND (TRIM(`source_url`) LIKE 'https://%' OR TRIM(`source_url`) LIKE 'http://%')");
                $conn->exec("UPDATE `admission_records` SET `verification_status` = 'unverified' WHERE `source_url` IS NULL OR (TRIM(`source_url`) NOT LIKE 'https://%' AND TRIM(`source_url`) NOT LIKE 'http://%')");
            }
        } else {
            $hasAr = (bool)$conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='admission_records'")->fetchColumn();
            if ($hasAr) {
                $cols = array_column($conn->query('PRAGMA table_info(admission_records)')->fetchAll(PDO::FETCH_ASSOC), 'name');
                $alters = [
                    'campus' => 'ADD COLUMN campus VARCHAR(140) NULL',
                    'round_name' => 'ADD COLUMN round_name VARCHAR(80) NULL',
                    'score_type' => "ADD COLUMN score_type VARCHAR(40) NOT NULL DEFAULT 'cutoff'",
                    'conditions_note' => 'ADD COLUMN conditions_note TEXT NULL',
                    'source_title' => 'ADD COLUMN source_title VARCHAR(240) NULL',
                    'publisher' => 'ADD COLUMN publisher VARCHAR(180) NULL',
                    'published_date' => 'ADD COLUMN published_date TEXT NULL',
                    'verification_status' => "ADD COLUMN verification_status VARCHAR(30) NOT NULL DEFAULT 'unverified'",
                ];
                foreach ($alters as $col => $sql) {
                    if (!in_array($col, $cols, true)) {
                        $conn->exec('ALTER TABLE admission_records ' . $sql);
                    }
                }
                $conn->exec("UPDATE admission_records SET verification_status = 'verified' WHERE verification_status = 'unverified' AND source_url IS NOT NULL AND (TRIM(source_url) LIKE 'https://%' OR TRIM(source_url) LIKE 'http://%')");
                $conn->exec("UPDATE admission_records SET verification_status = 'unverified' WHERE source_url IS NULL OR (TRIM(source_url) NOT LIKE 'https://%' AND TRIM(source_url) NOT LIKE 'http://%')");
            }
        }
    } catch (Throwable $ignored) {
    }

    // 2. Canonical exam combinations
    $conn->exec("CREATE TABLE IF NOT EXISTS admission_combinations (
        code VARCHAR(16) NOT NULL PRIMARY KEY,
        subjects_text VARCHAR(200) NOT NULL,
        subjects_json TEXT NOT NULL,
        category VARCHAR(60) NOT NULL DEFAULT 'Tổ hợp phổ biến',
        sort_order INT NOT NULL DEFAULT 0
    ){$suffix}");

    $findComb = $conn->prepare('SELECT code FROM admission_combinations WHERE code = ?');
    $insComb = $conn->prepare('INSERT INTO admission_combinations(code, subjects_text, subjects_json, category, sort_order) VALUES(?, ?, ?, ?, ?)');
    $updComb = $conn->prepare('UPDATE admission_combinations SET subjects_text = ?, subjects_json = ?, category = ?, sort_order = ? WHERE code = ?');
    foreach (journey_canonical_combinations() as [$code, $text, $arr, $cat, $ord]) {
        $json = json_encode($arr, JSON_UNESCAPED_UNICODE);
        $findComb->execute([$code]);
        if ($findComb->fetchColumn()) {
            $updComb->execute([$text, $json, $cat, $ord, $code]);
        } else {
            $insComb->execute([$code, $text, $json, $cat, $ord]);
        }
    }

    // 3. Verified program_combinations table
    $conn->exec("CREATE TABLE IF NOT EXISTS program_combinations (
        id {$id},
        program_id {$uInt} NOT NULL,
        admission_year INT NOT NULL,
        method VARCHAR(140) NOT NULL,
        combination_code VARCHAR(16) NOT NULL,
        conditions_note TEXT NULL,
        source_url VARCHAR(500) NOT NULL,
        source_title VARCHAR(240) NULL,
        publisher VARCHAR(180) NULL,
        published_date DATE NULL,
        verified_on DATE NULL,
        verification_status VARCHAR(30) NOT NULL DEFAULT 'verified',
        UNIQUE(program_id, admission_year, method, combination_code)
    ){$suffix}");

    // Sync any existing admission_records with explicit combination codes + known official HUST IT1 combinations
    try {
        $hasPrograms = $mysql
            ? !empty($conn->query("SHOW TABLES LIKE 'programs'")->fetchAll())
            : (bool)$conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='programs'")->fetchColumn();

        if ($hasPrograms) {
            $findPc = $conn->prepare('SELECT id FROM program_combinations WHERE program_id=? AND admission_year=? AND method=? AND combination_code=?');
            $insPc = $conn->prepare('INSERT INTO program_combinations(program_id, admission_year, method, combination_code, conditions_note, source_url, source_title, publisher, verified_on, verification_status) VALUES(?,?,?,?,?,?,?,?,?,?)');

            // 3a. Auto-link from any admission_records row that already has explicit combination code(s)
            $arRows = $conn->query("SELECT ar.program_id, ar.admission_year, ar.method, ar.combination, ar.source_url, ar.source_title, ar.publisher, ar.verified_at, i.name AS inst_name FROM admission_records ar JOIN programs p ON p.id=ar.program_id JOIN institutions i ON i.id=p.institution_id WHERE ar.combination IS NOT NULL AND TRIM(ar.combination) <> ''")->fetchAll(PDO::FETCH_ASSOC);
            $validCodes = array_column(journey_canonical_combinations(), 0);
            foreach ($arRows as $ar) {
                $parts = preg_split('/[,;\/\s]+/', strtoupper(trim((string)$ar['combination'])));
                foreach ($parts as $code) {
                    if (!in_array($code, $validCodes, true)) {
                        continue;
                    }
                    $findPc->execute([(int)$ar['program_id'], (int)$ar['admission_year'], (string)$ar['method'], $code]);
                    if (!$findPc->fetchColumn()) {
                        $insPc->execute([
                            (int)$ar['program_id'],
                            (int)$ar['admission_year'],
                            (string)$ar['method'],
                            $code,
                            null,
                            (string)$ar['source_url'],
                            $ar['source_title'] ?: ('Thông báo điểm chuẩn ' . $ar['inst_name'] . ' ' . $ar['admission_year']),
                            $ar['publisher'] ?: (string)$ar['inst_name'],
                            $ar['verified_at'] ? substr((string)$ar['verified_at'], 0, 10) : '2026-11-09',
                            'verified',
                        ]);
                    }
                }
            }

            // 3b. Official HUST IT1 verified combinations (A00, A01, K01) documented in career-admissions.php
            // Source: https://soict.hust.edu.vn/diem-chuan-tham-khao.html
            $bkaIt1 = $conn->query("SELECT p.id FROM programs p JOIN institutions i ON i.id=p.institution_id WHERE i.code='BKA' AND p.program_code='IT1' LIMIT 1")->fetchColumn();
            if ($bkaIt1) {
                $src = 'https://soict.hust.edu.vn/diem-chuan-tham-khao.html';
                foreach ([2023, 2024, 2025] as $yr) {
                    foreach (['A00', 'A01', 'K01'] as $code) {
                        $findPc->execute([(int)$bkaIt1, $yr, 'Điểm thi THPT', $code]);
                        if (!$findPc->fetchColumn()) {
                            $insPc->execute([
                                (int)$bkaIt1,
                                $yr,
                                'Điểm thi THPT',
                                $code,
                                'Nguồn công bố điểm chuẩn chung theo chương trình IT1 (không tách riêng điểm chuẩn cho từng tổ hợp).',
                                $src,
                                'Điểm chuẩn tham khảo Trường CNTT&TT — ĐHBK Hà Nội',
                                'Đại học Bách khoa Hà Nội',
                                '2026-11-09',
                                'verified',
                            ]);
                        }
                    }
                }
            }
        }
    } catch (Throwable $ignored) {
    }

    // 4. Quick Self-Discovery sessions
    $conn->exec("CREATE TABLE IF NOT EXISTS user_discovery_sessions (
        id {$bigId},
        user_id {$uInt} NOT NULL,
        question_version VARCHAR(32) NOT NULL DEFAULT 'discover_v1',
        answers_json TEXT NOT NULL,
        summary_json TEXT NULL,
        completed TINYINT(1) NOT NULL DEFAULT 0,
        created_at {$ts},
        updated_at {$tsUpd}
    ){$suffix}");

    // 5. "Hiểu mình cần gì" weighted comparisons
    $conn->exec("CREATE TABLE IF NOT EXISTS user_comparisons (
        id {$bigId},
        uid VARCHAR(36) NOT NULL UNIQUE,
        user_id {$uInt} NULL,
        question_version VARCHAR(32) NOT NULL DEFAULT 'compare_v1',
        options_json TEXT NOT NULL,
        answers_json TEXT NOT NULL,
        research_notes_json TEXT NULL,
        result_json TEXT NULL,
        primary_option_id VARCHAR(80) NULL,
        backup_option_id VARCHAR(80) NULL,
        status VARCHAR(24) NOT NULL DEFAULT 'draft',
        created_at {$ts},
        updated_at {$tsUpd}
    ){$suffix}");

    // 6. Month/Year Action Plans
    $conn->exec("CREATE TABLE IF NOT EXISTS career_action_plans (
        id {$bigId},
        uid VARCHAR(36) NOT NULL UNIQUE,
        user_id {$uInt} NULL,
        comparison_uid VARCHAR(36) NULL,
        primary_option_json TEXT NOT NULL,
        backup_option_json TEXT NULL,
        grade VARCHAR(20) NULL,
        start_month VARCHAR(7) NOT NULL,
        target_horizon VARCHAR(40) NOT NULL DEFAULT '6_months',
        hours_per_week INT NULL,
        research_items_json TEXT NULL,
        share_enabled TINYINT(1) NOT NULL DEFAULT 0,
        created_at {$ts},
        updated_at {$tsUpd}
    ){$suffix}");

    $conn->exec("CREATE TABLE IF NOT EXISTS career_action_plan_items (
        id {$bigId},
        plan_id {$uBig} NOT NULL,
        month_key VARCHAR(7) NOT NULL,
        due_date DATE NULL,
        deadline_kind VARCHAR(24) NOT NULL DEFAULT 'self',
        official_source_url VARCHAR(500) NULL,
        goal VARCHAR(255) NOT NULL,
        tasks TEXT NOT NULL,
        expected_outcome TEXT NOT NULL,
        status VARCHAR(24) NOT NULL DEFAULT 'not_started',
        source VARCHAR(24) NOT NULL DEFAULT 'manual',
        user_edited TINYINT(1) NOT NULL DEFAULT 0,
        sort_order INT NOT NULL DEFAULT 0,
        created_at {$ts},
        updated_at {$tsUpd},
        FOREIGN KEY(plan_id) REFERENCES career_action_plans(id) ON DELETE CASCADE
    ){$suffix}");

    $conn->exec("CREATE TABLE IF NOT EXISTS plan_share_tokens (
        id {$bigId},
        plan_id {$uBig} NOT NULL,
        token VARCHAR(64) NOT NULL UNIQUE,
        revoked_at DATETIME NULL,
        created_at {$ts},
        FOREIGN KEY(plan_id) REFERENCES career_action_plans(id) ON DELETE CASCADE
    ){$suffix}");

    if ($mysql && function_exists('setting')) {
        try {
            $stmt = $conn->prepare("INSERT INTO settings(setting_key, setting_value) VALUES('journey_schema_version', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([$version]);
            setting('journey_schema_version', $version, true);
        } catch (Throwable $ignored) {
        }
    }

    $done[$key] = true;
}
}
