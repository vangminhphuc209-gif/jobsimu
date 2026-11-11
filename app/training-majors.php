<?php
declare(strict_types=1);

/**
 * Training majors & institution source catalog helpers (HNV dataset integration).
 * Additive schema compatible with MySQL/MariaDB and SQLite.
 * Strictly separates:
 * - source accessibility/content status
 * - JobSimu editorial description status (not_edited | draft | approved)
 * - official major code verification status with evidence (unverified | verified | conflict)
 * - mapping status (pending_review | approved | rejected)
 * - per-source/year admission status (historical_unverified | source_verified_year | outdated_historical | source_unavailable | disputed)
 * - initial source warnings vs currently open review issues (major_review_issues)
 */

function training_majors_table_names(): array
{
    return [
        'major_fields',
        'major_subgroups',
        'training_majors',
        'institution_source_records',
        'major_institution_source_links',
        'major_review_queue',
        'major_review_issues',
        'training_major_mappings',
        'dataset_import_runs',
    ];
}

function training_majors_tables_ready(?PDO $pdo = null): bool
{
    $db = $pdo ?? db();
    $tables = training_majors_table_names();
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $placeholders = implode(',', array_fill(0, count($tables), '?'));
    if ($driver === 'mysql') {
        $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ($placeholders)");
        $stmt->execute($tables);
        return (int)$stmt->fetchColumn() === count($tables);
    }
    if ($driver === 'sqlite') {
        $stmt = $db->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name IN ($placeholders)");
        $stmt->execute($tables);
        return (int)$stmt->fetchColumn() === count($tables);
    }
    return false;
}

function training_majors_columns_ready(PDO $db): bool
{
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $checks = [
        'training_majors' => ['description_status', 'code_verification_status', 'code_evidence_url', 'code_checked_at', 'code_effective_scope', 'code_effective_year', 'code_verified_by'],
        'major_institution_source_links' => ['admission_status', 'observed_table_rows_json', 'link_checked_at'],
        'major_review_queue' => ['source_warnings_json', 'open_issue_count'],
        'training_major_mappings' => ['evidence_json', 'conflict_notes', 'has_conflict', 'review_note'],
    ];
    foreach ($checks as $table => $reqCols) {
        $existing = $driver === 'mysql'
            ? $db->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN)
            : array_column($db->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC), 'name');
        foreach ($reqCols as $col) {
            if (!in_array($col, $existing, true)) {
                return false;
            }
        }
    }
    return true;
}

function training_majors_migrate(?PDO $pdo = null): void
{
    $db = $pdo ?? db();
    if (training_majors_tables_ready($db) && training_majors_columns_ready($db)) {
        return;
    }
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $path = ROOT_PATH . '/database/migrations/2026_13_training_majors_catalog.sql';
        if (!is_file($path)) {
            throw new RuntimeException('Migration file missing: 2026_13_training_majors_catalog.sql');
        }
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new RuntimeException('Cannot read migration file: 2026_13_training_majors_catalog.sql');
        }
        foreach (explode(';', $sql) as $statement) {
            $trimmed = trim($statement);
            if ($trimmed !== '') {
                $db->exec($trimmed);
            }
        }
    } elseif ($driver === 'sqlite') {
        foreach (training_majors_sqlite_ddl() as $statement) {
            $db->exec($statement);
        }
    } else {
        throw new RuntimeException('Unsupported database driver for training majors catalog: ' . $driver);
    }

    training_majors_ensure_columns($db);
}

/**
 * Safe additive column upgrade for pre-existing installations.
 * Never automatically promotes legacy data to verified/approved on migration.
 */
function training_majors_ensure_columns(PDO $db): void
{
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $mysql = $driver === 'mysql';
    $longText = $mysql ? 'LONGTEXT' : 'TEXT';
    $tinyInt = $mysql ? 'TINYINT(1)' : 'INTEGER';
    $dt = $mysql ? 'DATETIME' : 'TEXT';
    $uid = $mysql ? 'INT UNSIGNED' : 'INTEGER';

    $additions = [
        'training_majors' => [
            'description_status' => "VARCHAR(30) NOT NULL DEFAULT 'not_edited'",
            'description_updated_by' => "$uid NULL",
            'description_updated_at' => "$dt NULL",
            'code_verification_status' => "VARCHAR(30) NOT NULL DEFAULT 'unverified'",
            'code_evidence_url' => 'VARCHAR(500) NULL',
            'code_evidence_title' => 'VARCHAR(250) NULL',
            'code_checked_at' => "$dt NULL",
            'code_effective_scope' => 'VARCHAR(180) NULL',
            'code_effective_year' => 'INTEGER NULL',
            'code_verified_by' => "$uid NULL",
            'code_verification_note' => 'TEXT NULL',
        ],
        'major_institution_source_links' => [
            'observed_table_rows_json' => "$longText NULL",
            'admission_status' => "VARCHAR(40) NOT NULL DEFAULT 'historical_unverified'",
            'link_checked_at' => "$dt NULL",
            'link_checked_by' => "$uid NULL",
            'link_check_note' => 'TEXT NULL',
        ],
        'major_review_queue' => [
            'source_warnings_json' => "TEXT NOT NULL DEFAULT '[]'",
            'open_issue_count' => 'INTEGER NOT NULL DEFAULT 0',
        ],
        'training_major_mappings' => [
            'evidence_json' => 'TEXT NULL',
            'conflict_notes' => 'TEXT NULL',
            'has_conflict' => "$tinyInt NOT NULL DEFAULT 0",
            'review_note' => 'TEXT NULL',
        ],
    ];

    $addedColumns = [];
    foreach ($additions as $table => $cols) {
        $existing = $mysql
            ? $db->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN)
            : array_column($db->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC), 'name');
        foreach ($cols as $colName => $colDef) {
            if (!in_array($colName, $existing, true)) {
                $sqlDef = ($mysql && stripos($colDef, 'TEXT NOT NULL DEFAULT') !== false)
                    ? 'TEXT NULL'
                    : $colDef;
                $db->exec("ALTER TABLE `$table` ADD COLUMN `$colName` $sqlDef");
                $addedColumns[$table][$colName] = true;
            }
        }
    }

    // Only backfill newly added columns conservatively: never auto-verify or auto-approve old data
    if (!empty($addedColumns['training_majors']['description_status'])) {
        $db->exec("UPDATE training_majors SET description_status = 'draft' WHERE editorial_description IS NOT NULL AND TRIM(editorial_description) <> '' AND description_status = 'not_edited'");
    }
    if (!empty($addedColumns['major_review_queue']['source_warnings_json'])) {
        $db->exec("UPDATE major_review_queue SET source_warnings_json = issues_json WHERE source_warnings_json IS NULL OR TRIM(source_warnings_json) = ''");
    }
}

function training_majors_sqlite_ddl(): array
{
    return [
        "CREATE TABLE IF NOT EXISTS major_fields (
            id TEXT PRIMARY KEY,
            name TEXT NOT NULL,
            normalized_name TEXT NOT NULL,
            source_system TEXT NOT NULL DEFAULT 'hnv',
            source_url TEXT NOT NULL,
            retrieved_at TEXT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE INDEX IF NOT EXISTS idx_major_fields_norm ON major_fields(normalized_name)",
        "CREATE TABLE IF NOT EXISTS major_subgroups (
            id TEXT PRIMARY KEY,
            field_id TEXT NOT NULL,
            name TEXT NOT NULL,
            normalized_name TEXT NOT NULL,
            source_system TEXT NOT NULL DEFAULT 'hnv',
            source_url TEXT NOT NULL,
            retrieved_at TEXT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(field_id) REFERENCES major_fields(id) ON DELETE CASCADE
        )",
        "CREATE INDEX IF NOT EXISTS idx_major_subgroups_field ON major_subgroups(field_id)",
        "CREATE INDEX IF NOT EXISTS idx_major_subgroups_norm ON major_subgroups(normalized_name)",
        "CREATE TABLE IF NOT EXISTS training_majors (
            id TEXT PRIMARY KEY,
            field_id TEXT NOT NULL,
            subgroup_id TEXT NOT NULL,
            name TEXT NOT NULL,
            normalized_name TEXT NOT NULL,
            source_label TEXT NOT NULL,
            entity_type TEXT NOT NULL DEFAULT 'training_major',
            source_system TEXT NOT NULL DEFAULT 'hnv',
            source_url TEXT NULL,
            catalogue_url TEXT NULL,
            final_url TEXT NULL,
            source_page_title TEXT NULL,
            detail_status TEXT NOT NULL,
            http_status INTEGER NULL,
            has_article_body INTEGER NOT NULL DEFAULT 0,
            body_sha256 TEXT NULL,
            content_topics_json TEXT NOT NULL,
            listed_school_count INTEGER NULL,
            listed_school_count_year INTEGER NULL,
            school_listing_count_extracted INTEGER NULL,
            source_major_code_candidate TEXT NULL,
            official_major_code TEXT NULL,
            major_code_basis TEXT NULL,
            major_code_requires_official_verification INTEGER NOT NULL DEFAULT 1,
            observed_major_codes_json TEXT NOT NULL,
            source_dom_code TEXT NULL,
            verification_status TEXT NOT NULL DEFAULT 'source_extracted_not_officially_verified',
            career_mapping_status TEXT NOT NULL DEFAULT 'not_inferred_from_training_major',
            assessment_mapping_status TEXT NOT NULL DEFAULT 'not_scored',
            normalization_note TEXT NULL,
            editorial_description TEXT NULL,
            description_status TEXT NOT NULL DEFAULT 'not_edited',
            description_updated_by INTEGER NULL,
            description_updated_at TEXT NULL,
            code_verification_status TEXT NOT NULL DEFAULT 'unverified',
            code_evidence_url TEXT NULL,
            code_evidence_title TEXT NULL,
            code_checked_at TEXT NULL,
            code_effective_scope TEXT NULL,
            code_effective_year INTEGER NULL,
            code_verified_by INTEGER NULL,
            code_verification_note TEXT NULL,
            editorial_status TEXT NOT NULL DEFAULT 'unverified',
            is_editorially_locked INTEGER NOT NULL DEFAULT 0,
            editorial_notes TEXT NULL,
            reviewed_by INTEGER NULL,
            reviewed_at TEXT NULL,
            retrieved_at TEXT NULL,
            raw_payload_json TEXT NOT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(field_id) REFERENCES major_fields(id),
            FOREIGN KEY(subgroup_id) REFERENCES major_subgroups(id)
        )",
        "CREATE INDEX IF NOT EXISTS idx_tm_field ON training_majors(field_id)",
        "CREATE INDEX IF NOT EXISTS idx_tm_subgroup ON training_majors(subgroup_id)",
        "CREATE INDEX IF NOT EXISTS idx_tm_detail_status ON training_majors(detail_status)",
        "CREATE INDEX IF NOT EXISTS idx_tm_desc_status ON training_majors(description_status)",
        "CREATE INDEX IF NOT EXISTS idx_tm_code_ver_status ON training_majors(code_verification_status)",
        "CREATE INDEX IF NOT EXISTS idx_tm_norm_name ON training_majors(normalized_name)",
        "CREATE INDEX IF NOT EXISTS idx_tm_code_cand ON training_majors(source_major_code_candidate)",
        "CREATE INDEX IF NOT EXISTS idx_tm_official_code ON training_majors(official_major_code)",
        "CREATE TABLE IF NOT EXISTS institution_source_records (
            id TEXT PRIMARY KEY,
            source_label TEXT NULL,
            normalized_label TEXT NULL,
            source_url TEXT NOT NULL UNIQUE,
            official_institution_id INTEGER NULL,
            source_system TEXT NOT NULL DEFAULT 'hnv',
            verification_status TEXT NOT NULL DEFAULT 'unmerged_source_url',
            retrieved_at TEXT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(official_institution_id) REFERENCES institutions(id) ON DELETE SET NULL
        )",
        "CREATE INDEX IF NOT EXISTS idx_isr_official ON institution_source_records(official_institution_id)",
        "CREATE TABLE IF NOT EXISTS major_institution_source_links (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            major_id TEXT NOT NULL,
            institution_source_id TEXT NOT NULL,
            source_news_year INTEGER NULL,
            source_news_year_key INTEGER NOT NULL DEFAULT 0,
            level_label TEXT NULL,
            observed_major_codes_json TEXT NOT NULL,
            observed_table_rows_json TEXT NULL,
            observed_subject_combination_codes_json TEXT NOT NULL,
            evidence_url TEXT NOT NULL,
            verification_status TEXT NOT NULL DEFAULT 'historical_source_listing_not_current_admission_confirmation',
            admission_status TEXT NOT NULL DEFAULT 'historical_unverified',
            link_checked_at TEXT NULL,
            link_checked_by INTEGER NULL,
            link_check_note TEXT NULL,
            retrieved_at TEXT NULL,
            raw_payload_json TEXT NOT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(major_id, institution_source_id, source_news_year_key),
            FOREIGN KEY(major_id) REFERENCES training_majors(id) ON DELETE CASCADE,
            FOREIGN KEY(institution_source_id) REFERENCES institution_source_records(id) ON DELETE CASCADE
        )",
        "CREATE INDEX IF NOT EXISTS idx_misl_major ON major_institution_source_links(major_id)",
        "CREATE INDEX IF NOT EXISTS idx_misl_inst ON major_institution_source_links(institution_source_id)",
        "CREATE INDEX IF NOT EXISTS idx_misl_year ON major_institution_source_links(source_news_year)",
        "CREATE INDEX IF NOT EXISTS idx_misl_adm_status ON major_institution_source_links(admission_status)",
        "CREATE TABLE IF NOT EXISTS major_review_queue (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            major_id TEXT NOT NULL UNIQUE,
            issues_json TEXT NOT NULL,
            source_warnings_json TEXT NOT NULL DEFAULT '[]',
            issue_count INTEGER NOT NULL DEFAULT 0,
            open_issue_count INTEGER NOT NULL DEFAULT 0,
            source_url TEXT NULL,
            review_status TEXT NOT NULL DEFAULT 'pending',
            reviewer_note TEXT NULL,
            reviewed_by INTEGER NULL,
            reviewed_at TEXT NULL,
            last_detected_at TEXT NOT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(major_id) REFERENCES training_majors(id) ON DELETE CASCADE
        )",
        "CREATE INDEX IF NOT EXISTS idx_mrq_status ON major_review_queue(review_status)",
        "CREATE TABLE IF NOT EXISTS major_review_issues (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            major_id TEXT NOT NULL,
            issue_code TEXT NOT NULL,
            issue_category TEXT NOT NULL,
            origin TEXT NOT NULL DEFAULT 'initial_source',
            status TEXT NOT NULL DEFAULT 'open',
            detail_note TEXT NULL,
            resolution_note TEXT NULL,
            resolved_by INTEGER NULL,
            resolved_at TEXT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(major_id, issue_code),
            FOREIGN KEY(major_id) REFERENCES training_majors(id) ON DELETE CASCADE
        )",
        "CREATE INDEX IF NOT EXISTS idx_mri_status_code ON major_review_issues(status, issue_code)",
        "CREATE TABLE IF NOT EXISTS training_major_mappings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            training_major_id TEXT NOT NULL,
            existing_major_id INTEGER NOT NULL,
            match_basis TEXT NOT NULL,
            mapping_status TEXT NOT NULL DEFAULT 'pending_review',
            confidence_note TEXT NULL,
            evidence_json TEXT NULL,
            conflict_notes TEXT NULL,
            has_conflict INTEGER NOT NULL DEFAULT 0,
            review_note TEXT NULL,
            reviewed_by INTEGER NULL,
            reviewed_at TEXT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(training_major_id, existing_major_id),
            FOREIGN KEY(training_major_id) REFERENCES training_majors(id) ON DELETE CASCADE,
            FOREIGN KEY(existing_major_id) REFERENCES majors(id) ON DELETE CASCADE
        )",
        "CREATE INDEX IF NOT EXISTS idx_tmm_status ON training_major_mappings(mapping_status)",
        "CREATE TABLE IF NOT EXISTS dataset_import_runs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            source_name TEXT NOT NULL,
            source_version TEXT NULL,
            source_retrieved_at TEXT NULL,
            mode TEXT NOT NULL,
            status TEXT NOT NULL,
            summary_json TEXT NOT NULL,
            initiated_by INTEGER NULL,
            started_at TEXT NOT NULL,
            finished_at TEXT NULL
        )",
        "CREATE INDEX IF NOT EXISTS idx_dir_started ON dataset_import_runs(started_at)",
    ];
}

/** Pure-PHP Vietnamese NFC composition (handles NFD and mixed-NFD inputs without ext-intl). */
function hnv_to_nfc(string $text): string
{
    if ($text === '') {
        return '';
    }
    if (class_exists('Normalizer')) {
        $norm = Normalizer::normalize($text, Normalizer::FORM_C);
        if (is_string($norm)) {
            return $norm;
        }
    }
    static $precomposedBase = null;
    static $toneTable = null;
    if ($precomposedBase === null) {
        $precomposedBase = [
            'â' => ['a', "\u{0302}"], 'Â' => ['A', "\u{0302}"],
            'ă' => ['a', "\u{0306}"], 'Ă' => ['A', "\u{0306}"],
            'ê' => ['e', "\u{0302}"], 'Ê' => ['E', "\u{0302}"],
            'ô' => ['o', "\u{0302}"], 'Ô' => ['O', "\u{0302}"],
            'ơ' => ['o', "\u{031B}"], 'Ơ' => ['O', "\u{031B}"],
            'ư' => ['u', "\u{031B}"], 'Ư' => ['U', "\u{031B}"],
        ];
        $rows = [
            'a'  => ['a', 'à', 'á', 'ả', 'ã', 'ạ'],
            'A'  => ['A', 'À', 'Á', 'Ả', 'Ã', 'Ạ'],
            'a^' => ['â', 'ầ', 'ấ', 'ẩ', 'ẫ', 'ậ'],
            'A^' => ['Â', 'Ầ', 'Ấ', 'Ẩ', 'Ẫ', 'Ậ'],
            'a(' => ['ă', 'ằ', 'ắ', 'ẳ', 'ẵ', 'ặ'],
            'A(' => ['Ă', 'Ằ', 'Ắ', 'Ẳ', 'Ẵ', 'Ặ'],
            'e'  => ['e', 'è', 'é', 'ẻ', 'ẽ', 'ẹ'],
            'E'  => ['E', 'È', 'É', 'Ẻ', 'Ẽ', 'Ẹ'],
            'e^' => ['ê', 'ề', 'ế', 'ể', 'ễ', 'ệ'],
            'E^' => ['Ê', 'Ề', 'Ế', 'Ể', 'Ễ', 'Ệ'],
            'i'  => ['i', 'ì', 'í', 'ỉ', 'ĩ', 'ị'],
            'I'  => ['I', 'Ì', 'Í', 'Ỉ', 'Ĩ', 'Ị'],
            'o'  => ['o', 'ò', 'ó', 'ỏ', 'õ', 'ọ'],
            'O'  => ['O', 'Ò', 'Ó', 'Ỏ', 'Õ', 'Ọ'],
            'o^' => ['ô', 'ồ', 'ố', 'ổ', 'ỗ', 'ộ'],
            'O^' => ['Ô', 'Ồ', 'Ố', 'Ổ', 'Ỗ', 'Ộ'],
            'o+' => ['ơ', 'ờ', 'ớ', 'ở', 'ỡ', 'ợ'],
            'O+' => ['Ơ', 'Ờ', 'Ớ', 'Ở', 'Ỡ', 'Ợ'],
            'u'  => ['u', 'ù', 'ú', 'ủ', 'ũ', 'ụ'],
            'U'  => ['U', 'Ù', 'Ú', 'Ủ', 'Ũ', 'Ụ'],
            'u+' => ['ư', 'ừ', 'ứ', 'ử', 'ữ', 'ự'],
            'U+' => ['Ư', 'Ừ', 'Ứ', 'Ử', 'Ữ', 'Ự'],
            'y'  => ['y', 'ỳ', 'ý', 'ỷ', 'ỹ', 'ỵ'],
            'Y'  => ['Y', 'Ỳ', 'Ý', 'Ỷ', 'Ỹ', 'Ỵ'],
        ];
        $toneTable = [];
        foreach ($rows as $key => $forms) {
            $toneTable[$key] = [
                ''        => $forms[0],
                "\u{0300}" => $forms[1],
                "\u{0301}" => $forms[2],
                "\u{0309}" => $forms[3],
                "\u{0303}" => $forms[4],
                "\u{0323}" => $forms[5],
            ];
        }
    }

    return (string)preg_replace_callback(
        '/([AEIOUYaeiouyâÂăĂêÊôÔơƠưƯ])([\x{0300}\x{0301}\x{0302}\x{0303}\x{0306}\x{0309}\x{031B}\x{0323}]{1,3})/u',
        static function (array $m) use ($precomposedBase, $toneTable): string {
            $base = $m[1];
            $marks = $m[2];
            $mod = '';
            if (isset($precomposedBase[$base])) {
                [$base, $existingMod] = $precomposedBase[$base];
                $mod = $existingMod === "\u{0302}" ? '^' : ($existingMod === "\u{0306}" ? '(' : '+');
            }
            $tone = '';
            $chars = preg_split('//u', $marks, -1, PREG_SPLIT_NO_EMPTY);
            if ($chars === false) {
                return $m[0];
            }
            foreach ($chars as $ch) {
                if ($ch === "\u{0302}") {
                    $mod = '^';
                } elseif ($ch === "\u{0306}") {
                    $mod = '(';
                } elseif ($ch === "\u{031B}") {
                    $mod = '+';
                } elseif (in_array($ch, ["\u{0300}", "\u{0301}", "\u{0309}", "\u{0303}", "\u{0323}"], true)) {
                    $tone = $ch;
                }
            }
            $lookupKey = $base . $mod;
            return $toneTable[$lookupKey][$tone] ?? $m[0];
        },
        $text
    );
}

/** Accent-stripped lowercase ASCII Vietnamese for accentless search and indexing. */
function hnv_strip_vi_accents(string $text): string
{
    if ($text === '') {
        return '';
    }
    $clean = preg_replace('/\p{Mn}+/u', '', $text);
    if ($clean === null) {
        $clean = $text;
    }
    static $map = [
        'à'=>'a','á'=>'a','ả'=>'a','ã'=>'a','ạ'=>'a','ă'=>'a','ằ'=>'a','ắ'=>'a','ẳ'=>'a','ẵ'=>'a','ặ'=>'a',
        'â'=>'a','ầ'=>'a','ấ'=>'a','ẩ'=>'a','ẫ'=>'a','ậ'=>'a',
        'À'=>'a','Á'=>'a','Ả'=>'a','Ã'=>'a','Ạ'=>'a','Ă'=>'a','Ằ'=>'a','Ắ'=>'a','Ẳ'=>'a','Ẵ'=>'a','Ặ'=>'a',
        'Â'=>'a','Ầ'=>'a','Ấ'=>'a','Ẩ'=>'a','Ẫ'=>'a','Ậ'=>'a',
        'đ'=>'d','Đ'=>'d',
        'è'=>'e','é'=>'e','ẻ'=>'e','ẽ'=>'e','ẹ'=>'e','ê'=>'e','ề'=>'e','ế'=>'e','ể'=>'e','ễ'=>'e','ệ'=>'e',
        'È'=>'e','É'=>'e','Ẻ'=>'e','Ẽ'=>'e','Ẹ'=>'e','Ê'=>'e','Ề'=>'e','Ế'=>'e','Ể'=>'e','Ễ'=>'e','Ệ'=>'e',
        'ì'=>'i','í'=>'i','ỉ'=>'i','ĩ'=>'i','ị'=>'i','Ì'=>'i','Í'=>'i','Ỉ'=>'i','Ĩ'=>'i','Ị'=>'i',
        'ò'=>'o','ó'=>'o','ỏ'=>'o','õ'=>'o','ọ'=>'o','ô'=>'o','ồ'=>'o','ố'=>'o','ổ'=>'o','ỗ'=>'o','ộ'=>'o',
        'ơ'=>'o','ờ'=>'o','ớ'=>'o','ở'=>'o','ỡ'=>'o','ợ'=>'o',
        'Ò'=>'o','Ó'=>'o','Ỏ'=>'o','Õ'=>'o','Ọ'=>'o','Ô'=>'o','Ồ'=>'o','Ố'=>'o','Ổ'=>'o','Ỗ'=>'o','Ộ'=>'o',
        'Ơ'=>'o','Ờ'=>'o','Ớ'=>'o','Ở'=>'o','Ỡ'=>'o','Ợ'=>'o',
        'ù'=>'u','ú'=>'u','ủ'=>'u','ũ'=>'u','ụ'=>'u','ư'=>'u','ừ'=>'u','ứ'=>'u','ử'=>'u','ữ'=>'u','ự'=>'u',
        'Ù'=>'u','Ú'=>'u','Ủ'=>'u','Ũ'=>'u','Ụ'=>'u','Ư'=>'u','Ừ'=>'u','Ứ'=>'u','Ử'=>'u','Ữ'=>'u','Ự'=>'u',
        'ỳ'=>'y','ý'=>'y','ỷ'=>'y','ỹ'=>'y','ỵ'=>'y','Ỳ'=>'y','Ý'=>'y','Ỷ'=>'y','Ỹ'=>'y','Ỵ'=>'y',
        '–'=>' ','—'=>' ','-'=>' ','/'=>' ',','=>' ',
    ];
    $clean = strtr($clean, $map);
    $clean = mb_strtolower($clean, 'UTF-8');
    $clean = preg_replace('/\s+/u', ' ', $clean);
    return trim((string)$clean);
}

function hnv_has_vi_diacritics(string $text): bool
{
    $nfc = hnv_to_nfc($text);
    $stripped = hnv_strip_vi_accents($nfc);
    $plain = preg_replace('/\s+/u', ' ', mb_strtolower(strtr($nfc, ['–'=>' ','—'=>' ','-'=>' ','/'=>' ',','=>' ']), 'UTF-8'));
    return trim((string)$plain) !== $stripped;
}

/** Exact title key for mapping (keeps Vietnamese accents exact, unifies dashes & spacing). */
function hnv_normalize_exact_title(string $text): string
{
    $nfc = hnv_to_nfc($text);
    $nfc = str_replace(['–', '—'], '-', $nfc);
    $nfc = (string)preg_replace('/\s*-\s*/u', ' - ', $nfc);
    $nfc = (string)preg_replace('/\s+/u', ' ', $nfc);
    return mb_strtolower(trim($nfc), 'UTF-8');
}

function hnv_review_issue_labels(): array
{
    return [
        'SOURCE_HAS_NO_DETAIL_LINK' => ['label' => 'Nguồn không có trang chi tiết', 'category' => 'source', 'severity' => 'warn'],
        'DETAIL_FETCH_NOT_SUCCESSFUL' => ['label' => 'Không đọc được trang nguồn chi tiết', 'category' => 'source', 'severity' => 'error'],
        'EMPTY_ARTICLE_BODY' => ['label' => 'Trang nguồn thiếu bài giới thiệu', 'category' => 'source', 'severity' => 'warn'],
        'NO_SINGLE_MAJOR_CODE_CANDIDATE' => ['label' => 'Nguồn chưa có mã ứng viên đơn nhất', 'category' => 'code', 'severity' => 'info'],
        'MULTIPLE_CODES_IN_SOURCE_TABLES' => ['label' => 'Nhiều mã xuất hiện trong bảng nguồn', 'category' => 'code', 'severity' => 'info'],
        'NO_SCHOOL_LISTINGS_EXTRACTED' => ['label' => 'Chưa trích xuất được liên kết trường', 'category' => 'admission_links', 'severity' => 'warn'],
        'DUPLICATE_CANDIDATE_CODE' => ['label' => 'Mã ứng viên bị trùng với ngành khác tại nguồn', 'category' => 'code', 'severity' => 'error'],
        'LEVEL_CODE_MISMATCH' => ['label' => 'Nhãn trình độ và đầu mã ngành trong bảng có sai khác', 'category' => 'code', 'severity' => 'error'],
        'CODE_CONFLICT' => ['label' => 'Mã ngành có xung đột cần đối chiếu', 'category' => 'code', 'severity' => 'error'],
        'MISSING_JOBSIMU_DESCRIPTION' => ['label' => 'Chưa có mô tả JobSimu được duyệt', 'category' => 'description', 'severity' => 'warn'],
        'UNVERIFIED_OFFICIAL_CODE' => ['label' => 'Chưa xác minh mã ngành chính thức', 'category' => 'code', 'severity' => 'warn'],
    ];
}

function hnv_topic_labels(): array
{
    return [
        'employment' => 'Vị trí / việc làm',
        'curriculum' => 'Chương trình / môn học',
        'skills' => 'Kỹ năng / tố chất',
        'duration' => 'Thời gian đào tạo',
        'admission' => 'Xét tuyển / tổ hợp',
    ];
}

function hnv_detail_status_meta(string $detailStatus, bool $hasArticleBody): array
{
    return hnv_source_access_meta($detailStatus, $hasArticleBody);
}

function hnv_source_access_meta(string $detailStatus, bool $hasArticleBody): array
{
    if ($detailStatus === 'not_linked_by_source') {
        return ['label' => 'Nguồn chỉ liệt kê tên (chưa có trang chi tiết)', 'tone' => 'warn'];
    }
    if ($detailStatus !== 'fetched') {
        return ['label' => 'Trang nguồn không đọc được đúng cấu trúc', 'tone' => 'error'];
    }
    if (!$hasArticleBody) {
        return ['label' => 'Đọc được trang nguồn · thiếu bài giới thiệu', 'tone' => 'warn'];
    }
    return ['label' => 'Đọc được trang nguồn tham khảo', 'tone' => 'ok'];
}

function hnv_description_status_meta(string $status): array
{
    return match ($status) {
        'approved' => ['label' => 'Đã duyệt mô tả JobSimu', 'tone' => 'ok'],
        'draft' => ['label' => 'Bản nháp mô tả JobSimu (chưa công khai)', 'tone' => 'info'],
        default => ['label' => 'Chưa biên tập mô tả JobSimu', 'tone' => 'warn'],
    };
}

function hnv_code_verification_meta(string $status): array
{
    return match ($status) {
        'verified' => ['label' => 'Đã xác minh mã ngành', 'tone' => 'ok'],
        'conflict' => ['label' => 'Mã ngành có xung đột cần đối chiếu', 'tone' => 'error'],
        default => ['label' => 'Chưa xác minh mã ngành chính thức', 'tone' => 'warn'],
    };
}

function hnv_code_basis_label(?string $basis): string
{
    return match (trim((string)$basis)) {
        'catalogue_dom_id' => 'ID danh mục trên trang nguồn',
        'single_code_in_school_listings' => 'Mã duy nhất quan sát trong bảng trường',
        'exact_row_match' => 'Khớp dòng tên ngành trong bảng nguồn',
        default => 'Thông tin trích xuất từ nguồn tham khảo',
    };
}

function hnv_mapping_status_meta(string $status): array
{
    return match ($status) {
        'approved', 'confirmed' => ['label' => 'Đã duyệt ánh xạ', 'tone' => 'ok'],
        'rejected' => ['label' => 'Đã từ chối ánh xạ', 'tone' => 'error'],
        default => ['label' => 'Chờ duyệt ánh xạ', 'tone' => 'warn'],
    };
}

function hnv_admission_link_status_meta(string $status): array
{
    return match ($status) {
        'source_verified_year' => ['label' => 'Đã đối chiếu tại nguồn theo đúng năm tin', 'tone' => 'ok'],
        'outdated_historical' => ['label' => 'Tin lịch sử · đã hết hiệu lực tuyển sinh hiện hành', 'tone' => 'warn'],
        'source_unavailable' => ['label' => 'URL nguồn hỏng hoặc nội dung đã thay đổi', 'tone' => 'error'],
        'disputed' => ['label' => 'Thông tin tại nguồn có sai khác cần kiểm tra', 'tone' => 'error'],
        default => ['label' => 'Tin lịch sử · chưa kiểm chứng lại tại nguồn', 'tone' => 'warn'],
    };
}

function hnv_match_basis_label(string $basis): string
{
    return match ($basis) {
        'exact_name_and_candidate_code' => 'Khớp chính xác tên ngành và mã ứng viên nguồn',
        'exact_name_without_candidate_code' => 'Khớp chính xác tên ngành (nguồn chưa có mã ứng viên đơn nhất)',
        'unique_candidate_code_name_differs' => 'Khớp mã ứng viên đơn nhất nhưng tên khác nhau',
        default => 'Đối chiếu thủ công bởi quản trị viên',
    };
}

function hnv_format_utc_datetime(?string $iso): string
{
    $iso = trim((string)$iso);
    if ($iso === '') {
        return 'Chưa rõ';
    }
    $ts = strtotime($iso);
    if ($ts === false) {
        return $iso;
    }
    return gmdate('d/m/Y H:i', $ts) . ' UTC';
}

function hnv_format_vn_datetime(?string $dt): string
{
    $dt = trim((string)$dt);
    if ($dt === '') {
        return 'Chưa rõ';
    }
    $ts = strtotime($dt);
    if ($ts === false) {
        return $dt;
    }
    return date('d/m/Y H:i', $ts);
}

function hnv_decode_json_list(?string $json): array
{
    if ($json === null || trim($json) === '') {
        return [];
    }
    $decoded = json_decode($json, true);
    return is_array($decoded) ? array_values($decoded) : [];
}

/** Recompute major_review_queue open count & status strictly from major_review_issues rows. */
function hnv_recompute_queue_status(PDO $db, string $majorId): void
{
    $rows = $db->prepare('SELECT status FROM major_review_issues WHERE major_id = ?');
    $rows->execute([$majorId]);
    $statuses = $rows->fetchAll(PDO::FETCH_COLUMN);
    $openCount = 0;
    $deferredCount = 0;
    $resolvedCount = 0;
    foreach ($statuses as $st) {
        if ($st === 'open') {
            $openCount++;
        } elseif ($st === 'deferred') {
            $deferredCount++;
        } elseif ($st === 'resolved') {
            $resolvedCount++;
        }
    }
    if ($openCount === 0 && $deferredCount === 0 && count($statuses) > 0) {
        $queueStatus = 'verified';
    } elseif ($openCount === 0 && $deferredCount > 0) {
        $queueStatus = 'deferred';
    } elseif ($resolvedCount > 0 || $deferredCount > 0) {
        $queueStatus = 'in_review';
    } else {
        $queueStatus = 'pending';
    }
    $upd = $db->prepare('UPDATE major_review_queue SET open_issue_count = ?, review_status = ? WHERE major_id = ?');
    $upd->execute([$openCount, $queueStatus, $majorId]);
}

function hnv_get_catalog_stats(?PDO $pdo = null): array
{
    $db = $pdo ?? db();
    if (!training_majors_tables_ready($db)) {
        return [
            'fields' => 0, 'subgroups' => 0, 'majors' => 0,
            'institution_sources' => 0, 'links' => 0,
            'review_queue_total' => 0, 'review_pending' => 0,
            'fetched_count' => 0, 'not_linked_count' => 0, 'unexpected_page_count' => 0,
            'with_article_body' => 0, 'with_school_links' => 0, 'without_school_links' => 0,
            'with_code_candidates' => 0, 'officially_verified_codes' => 0,
            'code_unverified_count' => 0, 'code_verified_count' => 0, 'code_conflict_count' => 0,
            'with_editorial_description' => 0, 'without_approved_description' => 0,
            'description_not_edited_count' => 0, 'description_draft_count' => 0, 'description_approved_count' => 0,
            'pending_mappings' => 0, 'confirmed_mappings' => 0, 'rejected_mappings' => 0,
            'open_issues_total' => 0, 'resolved_issues_total' => 0,
            'years' => [], 'source_warning_counts' => [], 'open_issue_counts' => [], 'issue_counts' => [],
            'admission_status_counts' => [],
        ];
    }

    $majorsCount = (int)$db->query('SELECT COUNT(*) FROM training_majors')->fetchColumn();
    $descApproved = (int)$db->query("SELECT COUNT(*) FROM training_majors WHERE description_status = 'approved'")->fetchColumn();
    $descDraft = (int)$db->query("SELECT COUNT(*) FROM training_majors WHERE description_status = 'draft'")->fetchColumn();
    $descNotEdited = (int)$db->query("SELECT COUNT(*) FROM training_majors WHERE description_status = 'not_edited'")->fetchColumn();
    $codeVerified = (int)$db->query("SELECT COUNT(*) FROM training_majors WHERE code_verification_status = 'verified' AND official_major_code IS NOT NULL AND official_major_code <> ''")->fetchColumn();
    $codeConflict = (int)$db->query("SELECT COUNT(*) FROM training_majors WHERE code_verification_status = 'conflict'")->fetchColumn();
    $codeUnverified = max(0, $majorsCount - $codeVerified - $codeConflict);

    $stats = [
        'fields' => (int)$db->query('SELECT COUNT(*) FROM major_fields')->fetchColumn(),
        'subgroups' => (int)$db->query('SELECT COUNT(*) FROM major_subgroups')->fetchColumn(),
        'majors' => $majorsCount,
        'institution_sources' => (int)$db->query('SELECT COUNT(*) FROM institution_source_records')->fetchColumn(),
        'links' => (int)$db->query('SELECT COUNT(*) FROM major_institution_source_links')->fetchColumn(),
        'review_queue_total' => (int)$db->query('SELECT COUNT(*) FROM major_review_queue')->fetchColumn(),
        'review_pending' => (int)$db->query("SELECT COUNT(*) FROM major_review_queue WHERE open_issue_count > 0")->fetchColumn(),
        'fetched_count' => (int)$db->query("SELECT COUNT(*) FROM training_majors WHERE detail_status = 'fetched'")->fetchColumn(),
        'not_linked_count' => (int)$db->query("SELECT COUNT(*) FROM training_majors WHERE detail_status = 'not_linked_by_source'")->fetchColumn(),
        'unexpected_page_count' => (int)$db->query("SELECT COUNT(*) FROM training_majors WHERE detail_status NOT IN ('fetched','not_linked_by_source')")->fetchColumn(),
        'with_article_body' => (int)$db->query('SELECT COUNT(*) FROM training_majors WHERE has_article_body = 1')->fetchColumn(),
        'with_school_links' => (int)$db->query('SELECT COUNT(DISTINCT major_id) FROM major_institution_source_links')->fetchColumn(),
        'with_code_candidates' => (int)$db->query("SELECT COUNT(*) FROM training_majors WHERE source_major_code_candidate IS NOT NULL AND source_major_code_candidate <> ''")->fetchColumn(),
        'officially_verified_codes' => $codeVerified,
        'code_verified_count' => $codeVerified,
        'code_conflict_count' => $codeConflict,
        'code_unverified_count' => $codeUnverified,
        'with_editorial_description' => $descApproved,
        'description_approved_count' => $descApproved,
        'description_draft_count' => $descDraft,
        'description_not_edited_count' => $descNotEdited,
        'without_approved_description' => max(0, $majorsCount - $descApproved),
        'pending_mappings' => (int)$db->query("SELECT COUNT(*) FROM training_major_mappings WHERE mapping_status = 'pending_review'")->fetchColumn(),
        'confirmed_mappings' => (int)$db->query("SELECT COUNT(*) FROM training_major_mappings WHERE mapping_status IN ('approved','confirmed')")->fetchColumn(),
        'rejected_mappings' => (int)$db->query("SELECT COUNT(*) FROM training_major_mappings WHERE mapping_status = 'rejected'")->fetchColumn(),
        'open_issues_total' => (int)$db->query("SELECT COUNT(*) FROM major_review_issues WHERE status = 'open'")->fetchColumn(),
        'resolved_issues_total' => (int)$db->query("SELECT COUNT(*) FROM major_review_issues WHERE status = 'resolved'")->fetchColumn(),
        'years' => [],
        'source_warning_counts' => [],
        'open_issue_counts' => [],
        'issue_counts' => [],
        'admission_status_counts' => [],
    ];
    $stats['without_school_links'] = max(0, $stats['majors'] - $stats['with_school_links']);

    $yearRows = $db->query('SELECT source_news_year, COUNT(*) AS cnt FROM major_institution_source_links GROUP BY source_news_year ORDER BY source_news_year DESC')->fetchAll();
    foreach ($yearRows as $yr) {
        $label = $yr['source_news_year'] === null ? 'unknown' : (string)$yr['source_news_year'];
        $stats['years'][$label] = (int)$yr['cnt'];
    }

    $admRows = $db->query('SELECT admission_status, COUNT(*) AS cnt FROM major_institution_source_links GROUP BY admission_status')->fetchAll();
    foreach ($admRows as $ar) {
        $stats['admission_status_counts'][(string)$ar['admission_status']] = (int)$ar['cnt'];
    }

    // Initial source warnings (from immutable source_warnings_json)
    $warnRows = $db->query('SELECT source_warnings_json FROM major_review_queue')->fetchAll(PDO::FETCH_COLUMN);
    $sourceWarningCounts = [];
    foreach ($warnRows as $json) {
        foreach (hnv_decode_json_list((string)$json) as $issueCode) {
            $sourceWarningCounts[$issueCode] = ($sourceWarningCounts[$issueCode] ?? 0) + 1;
        }
    }
    arsort($sourceWarningCounts);
    $stats['source_warning_counts'] = $sourceWarningCounts;

    // Live open issues (from major_review_issues WHERE status='open')
    $openRows = $db->query("SELECT issue_code, COUNT(*) AS cnt FROM major_review_issues WHERE status = 'open' GROUP BY issue_code ORDER BY cnt DESC")->fetchAll();
    $openCounts = [];
    foreach ($openRows as $or) {
        $openCounts[(string)$or['issue_code']] = (int)$or['cnt'];
    }
    $stats['open_issue_counts'] = $openCounts;
    $stats['issue_counts'] = $openCounts ?: $sourceWarningCounts;

    return $stats;
}

function hnv_get_fields_with_counts(?PDO $pdo = null): array
{
    $db = $pdo ?? db();
    $sql = 'SELECT f.id, f.name, f.source_url, COUNT(m.id) AS major_count
            FROM major_fields f
            LEFT JOIN training_majors m ON m.field_id = f.id
            GROUP BY f.id, f.name, f.source_url
            ORDER BY f.id ASC';
    return $db->query($sql)->fetchAll();
}

function hnv_get_subgroups_with_counts(?PDO $pdo = null, string $fieldId = ''): array
{
    $db = $pdo ?? db();
    $sql = 'SELECT s.id, s.field_id, s.name, f.name AS field_name, COUNT(m.id) AS major_count
            FROM major_subgroups s
            JOIN major_fields f ON f.id = s.field_id
            LEFT JOIN training_majors m ON m.subgroup_id = s.id';
    $args = [];
    if ($fieldId !== '') {
        $sql .= ' WHERE s.field_id = ?';
        $args[] = $fieldId;
    }
    $sql .= ' GROUP BY s.id, s.field_id, s.name, f.name ORDER BY f.id ASC, s.id ASC';
    $stmt = $db->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll();
}

/**
 * Search and paginate training majors.
 * Supports Vietnamese accented AND accentless queries, NFC/NFD inputs, field/subgroup filters, and data-status filters.
 */
function hnv_search_majors(PDO $db, array $filters, int $page = 1, int $perPage = 24): array
{
    $page = max(1, $page);
    $perPage = max(1, min(100, $perPage));
    $q = trim(mb_substr((string)($filters['q'] ?? ''), 0, 120));
    $fieldId = trim((string)($filters['field'] ?? ''));
    $subgroupId = trim((string)($filters['subgroup'] ?? ''));
    $status = trim((string)($filters['status'] ?? 'all'));

    $where = [];
    $args = [];
    if ($fieldId !== '') {
        $where[] = 'm.field_id = ?';
        $args[] = $fieldId;
    }
    if ($subgroupId !== '') {
        $where[] = 'm.subgroup_id = ?';
        $args[] = $subgroupId;
    }
    if ($status === 'ready' || $status === 'description_approved') {
        $where[] = "m.description_status = 'approved'";
    } elseif ($status === 'source_ready') {
        $where[] = "m.detail_status = 'fetched' AND m.has_article_body = 1";
    } elseif ($status === 'has_school_links') {
        $where[] = 'EXISTS (SELECT 1 FROM major_institution_source_links l WHERE l.major_id = m.id)';
    } elseif ($status === 'missing_detail') {
        $where[] = "(m.detail_status <> 'fetched' OR m.has_article_body = 0)";
    } elseif ($status === 'missing_description') {
        $where[] = "m.description_status <> 'approved'";
    } elseif ($status === 'code_verified') {
        $where[] = "m.code_verification_status = 'verified' AND m.official_major_code IS NOT NULL";
    } elseif ($status === 'code_conflict') {
        $where[] = "m.code_verification_status = 'conflict'";
    } elseif ($status === 'needs_review') {
        $where[] = "EXISTS (SELECT 1 FROM major_review_issues i WHERE i.major_id = m.id AND i.status = 'open')";
    }

    $qAscii = hnv_strip_vi_accents($q);
    $hasDiacritics = $q !== '' && hnv_has_vi_diacritics($q);
    if ($qAscii !== '') {
        $likeAscii = '%' . $qAscii . '%';
        $likeRaw = '%' . $q . '%';
        $where[] = '(m.normalized_name LIKE ? OR COALESCE(m.source_major_code_candidate, \'\') LIKE ? OR COALESCE(m.official_major_code, \'\') LIKE ? OR m.observed_major_codes_json LIKE ?)';
        array_push($args, $likeAscii, $likeRaw, $likeRaw, $likeRaw);
    }

    $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

    $selectBase = 'SELECT m.id, m.name, m.field_id, m.subgroup_id, f.name AS field_name, s.name AS subgroup_name,
                          m.source_url, m.catalogue_url, m.detail_status, m.has_article_body,
                          m.source_major_code_candidate, m.official_major_code, m.observed_major_codes_json,
                          m.editorial_description, m.description_status, m.code_verification_status,
                          m.editorial_status, m.verification_status, m.retrieved_at,
                          (SELECT COUNT(*) FROM major_institution_source_links l WHERE l.major_id = m.id) AS link_count,
                          (SELECT COUNT(DISTINCT l.institution_source_id) FROM major_institution_source_links l WHERE l.major_id = m.id) AS source_url_count,
                          (SELECT MIN(l.source_news_year) FROM major_institution_source_links l WHERE l.major_id = m.id) AS min_year,
                          (SELECT MAX(l.source_news_year) FROM major_institution_source_links l WHERE l.major_id = m.id) AS max_year,
                          COALESCE(q.open_issue_count, 0) AS open_issue_count,
                          q.review_status
                   FROM training_majors m
                   JOIN major_fields f ON f.id = m.field_id
                   JOIN major_subgroups s ON s.id = m.subgroup_id
                   LEFT JOIN major_review_queue q ON q.major_id = m.id'
                   . $whereSql . ' ORDER BY m.name ASC, m.id ASC';

    if ($hasDiacritics) {
        $stmt = $db->prepare($selectBase);
        $stmt->execute($args);
        $candidates = $stmt->fetchAll();
        $qNfcLower = mb_strtolower(hnv_to_nfc($q), 'UTF-8');
        $filtered = [];
        foreach ($candidates as $row) {
            $nameNfcLower = mb_strtolower(hnv_to_nfc((string)$row['name']), 'UTF-8');
            $candCode = (string)($row['source_major_code_candidate'] ?? '');
            $offCode = (string)($row['official_major_code'] ?? '');
            $obsJson = (string)($row['observed_major_codes_json'] ?? '');
            if (
                mb_strpos($nameNfcLower, $qNfcLower, 0, 'UTF-8') !== false
                || ($candCode !== '' && mb_stripos($candCode, $q, 0, 'UTF-8') !== false)
                || ($offCode !== '' && mb_stripos($offCode, $q, 0, 'UTF-8') !== false)
                || ($obsJson !== '' && mb_stripos($obsJson, $q, 0, 'UTF-8') !== false)
            ) {
                $filtered[] = $row;
            }
        }
        $total = count($filtered);
        $totalPages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;
        $items = array_slice($filtered, $offset, $perPage);
        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    $countStmt = $db->prepare('SELECT COUNT(*) FROM training_majors m' . $whereSql);
    $countStmt->execute($args);
    $total = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $pagedSql = $selectBase . ' LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;
    $stmt = $db->prepare($pagedSql);
    $stmt->execute($args);
    $items = $stmt->fetchAll();

    return [
        'items' => $items,
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => $totalPages,
    ];
}

function hnv_get_major_detail(PDO $db, string $majorId): ?array
{
    $stmt = $db->prepare(
        'SELECT m.*, f.name AS field_name, f.source_url AS field_source_url,
                s.name AS subgroup_name, s.source_url AS subgroup_source_url,
                q.issues_json AS review_issues_json, q.source_warnings_json,
                q.open_issue_count, q.review_status, q.reviewer_note, q.reviewed_at AS queue_reviewed_at
         FROM training_majors m
         JOIN major_fields f ON f.id = m.field_id
         JOIN major_subgroups s ON s.id = m.subgroup_id
         LEFT JOIN major_review_queue q ON q.major_id = m.id
         WHERE m.id = ?'
    );
    $stmt->execute([$majorId]);
    $major = $stmt->fetch();
    if (!$major) {
        return null;
    }

    $linkStmt = $db->prepare(
        'SELECT l.*, isr.source_label, isr.source_url AS institution_source_url, isr.official_institution_id,
                isr.verification_status AS institution_verification_status,
                i.name AS official_institution_name, i.code AS official_institution_code
         FROM major_institution_source_links l
         JOIN institution_source_records isr ON isr.id = l.institution_source_id
         LEFT JOIN institutions i ON i.id = isr.official_institution_id
         WHERE l.major_id = ?
         ORDER BY l.source_news_year DESC, isr.source_label ASC, l.id ASC'
    );
    $linkStmt->execute([$majorId]);
    $major['links'] = $linkStmt->fetchAll();

    $mapStmt = $db->prepare(
        'SELECT mm.*, em.code AS existing_code, em.name AS existing_name, em.description AS existing_description
         FROM training_major_mappings mm
         JOIN majors em ON em.id = mm.existing_major_id
         WHERE mm.training_major_id = ?
         ORDER BY mm.id ASC'
    );
    $mapStmt->execute([$majorId]);
    $major['mappings'] = $mapStmt->fetchAll();

    $issStmt = $db->prepare('SELECT * FROM major_review_issues WHERE major_id = ? ORDER BY CASE status WHEN \'open\' THEN 0 WHEN \'deferred\' THEN 1 ELSE 2 END, id ASC');
    $issStmt->execute([$majorId]);
    $major['issues'] = $issStmt->fetchAll();

    return $major;
}
