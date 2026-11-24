-- JobSimu 4-Step High School Career Journey & Structured Admissions Migration
-- Additive and idempotent (safe to rerun without creating duplicates).

-- 1. Enrich admission_records with campus, round, score_type, conditions, source metadata & verification status (only if table exists)
SET @has_ar := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='admission_records');

SET @sql_ar1 := IF(@has_ar=1 AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='admission_records' AND COLUMN_NAME='campus')=0, 'ALTER TABLE `admission_records` ADD COLUMN `campus` VARCHAR(140) NULL AFTER `program_id`', 'SELECT 1');
PREPARE s1 FROM @sql_ar1; EXECUTE s1; DEALLOCATE PREPARE s1;

SET @sql_ar2 := IF(@has_ar=1 AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='admission_records' AND COLUMN_NAME='round_name')=0, 'ALTER TABLE `admission_records` ADD COLUMN `round_name` VARCHAR(80) NULL AFTER `method`', 'SELECT 1');
PREPARE s2 FROM @sql_ar2; EXECUTE s2; DEALLOCATE PREPARE s2;

SET @sql_ar3 := IF(@has_ar=1 AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='admission_records' AND COLUMN_NAME='score_type')=0, "ALTER TABLE `admission_records` ADD COLUMN `score_type` VARCHAR(40) NOT NULL DEFAULT 'cutoff' AFTER `candidate_group`", 'SELECT 1');
PREPARE s3 FROM @sql_ar3; EXECUTE s3; DEALLOCATE PREPARE s3;

SET @sql_ar4 := IF(@has_ar=1 AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='admission_records' AND COLUMN_NAME='conditions_note')=0, 'ALTER TABLE `admission_records` ADD COLUMN `conditions_note` TEXT NULL AFTER `score_scale`', 'SELECT 1');
PREPARE s4 FROM @sql_ar4; EXECUTE s4; DEALLOCATE PREPARE s4;

SET @sql_ar5 := IF(@has_ar=1 AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='admission_records' AND COLUMN_NAME='source_title')=0, 'ALTER TABLE `admission_records` ADD COLUMN `source_title` VARCHAR(240) NULL AFTER `source_url`', 'SELECT 1');
PREPARE s5 FROM @sql_ar5; EXECUTE s5; DEALLOCATE PREPARE s5;

SET @sql_ar6 := IF(@has_ar=1 AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='admission_records' AND COLUMN_NAME='publisher')=0, 'ALTER TABLE `admission_records` ADD COLUMN `publisher` VARCHAR(180) NULL AFTER `source_title`', 'SELECT 1');
PREPARE s6 FROM @sql_ar6; EXECUTE s6; DEALLOCATE PREPARE s6;

SET @sql_ar7 := IF(@has_ar=1 AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='admission_records' AND COLUMN_NAME='published_date')=0, 'ALTER TABLE `admission_records` ADD COLUMN `published_date` DATE NULL AFTER `publisher`', 'SELECT 1');
PREPARE s7 FROM @sql_ar7; EXECUTE s7; DEALLOCATE PREPARE s7;

SET @sql_ar8 := IF(@has_ar=1 AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='admission_records' AND COLUMN_NAME='verification_status')=0, "ALTER TABLE `admission_records` ADD COLUMN `verification_status` VARCHAR(30) NOT NULL DEFAULT 'unverified' AFTER `verified_at`", 'SELECT 1');
PREPARE s8 FROM @sql_ar8; EXECUTE s8; DEALLOCATE PREPARE s8;

SET @sql_ar9 := IF(@has_ar=1, "UPDATE `admission_records` SET `verification_status` = 'verified' WHERE `verification_status` = 'unverified' AND `source_url` IS NOT NULL AND (TRIM(`source_url`) LIKE 'https://%' OR TRIM(`source_url`) LIKE 'http://%')", 'SELECT 1');
PREPARE s9 FROM @sql_ar9; EXECUTE s9; DEALLOCATE PREPARE s9;

SET @sql_ar10 := IF(@has_ar=1, "UPDATE `admission_records` SET `verification_status` = 'unverified' WHERE `source_url` IS NULL OR (TRIM(`source_url`) NOT LIKE 'https://%' AND TRIM(`source_url`) NOT LIKE 'http://%')", 'SELECT 1');
PREPARE s10 FROM @sql_ar10; EXECUTE s10; DEALLOCATE PREPARE s10;

-- 2. Canonical exam subject combinations catalog
CREATE TABLE IF NOT EXISTS `admission_combinations` (
    `code` VARCHAR(16) NOT NULL PRIMARY KEY,
    `subjects_text` VARCHAR(200) NOT NULL,
    `subjects_json` TEXT NOT NULL,
    `category` VARCHAR(60) NOT NULL DEFAULT 'Tổ hợp phổ biến',
    `sort_order` INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `admission_combinations` (`code`, `subjects_text`, `subjects_json`, `category`, `sort_order`) VALUES
    ('A00', 'Toán, Vật lý, Hóa học', '["Toán","Vật lý","Hóa học"]', 'Khoa học tự nhiên', 1),
    ('A01', 'Toán, Vật lý, Tiếng Anh', '["Toán","Vật lý","Tiếng Anh"]', 'Khoa học tự nhiên & Ngoại ngữ', 2),
    ('B00', 'Toán, Hóa học, Sinh học', '["Toán","Hóa học","Sinh học"]', 'Y sinh & Khoa học sự sống', 3),
    ('C00', 'Ngữ văn, Lịch sử, Địa lý', '["Ngữ văn","Lịch sử","Địa lý"]', 'Khoa học xã hội', 4),
    ('C01', 'Ngữ văn, Toán, Vật lý', '["Ngữ văn","Toán","Vật lý"]', 'Tổng hợp', 5),
    ('D01', 'Ngữ văn, Toán, Tiếng Anh', '["Ngữ văn","Toán","Tiếng Anh"]', 'Ngoại ngữ & Kinh tế - Xã hội', 6),
    ('D07', 'Toán, Hóa học, Tiếng Anh', '["Toán","Hóa học","Tiếng Anh"]', 'Khoa học tự nhiên & Ngoại ngữ', 7),
    ('D14', 'Ngữ văn, Lịch sử, Tiếng Anh', '["Ngữ văn","Lịch sử","Tiếng Anh"]', 'Khoa học xã hội & Ngoại ngữ', 8),
    ('D15', 'Ngữ văn, Địa lý, Tiếng Anh', '["Ngữ văn","Địa lý","Tiếng Anh"]', 'Khoa học xã hội & Ngoại ngữ', 9),
    ('K01', 'Toán, Ngữ văn và 1 môn Tự nhiên/Tin học/Công nghệ (Bách khoa)', '["Toán","Ngữ văn","Vật lý","Hóa học","Sinh học","Tin học","Công nghệ"]', 'Tổ hợp kỹ thuật', 10),
    ('V00', 'Toán, Vật lý, Vẽ mỹ thuật', '["Toán","Vật lý","Vẽ mỹ thuật"]', 'Kiến trúc & Nghệ thuật', 11),
    ('H00', 'Ngữ văn, Năng khiếu vẽ 1, Năng khiếu vẽ 2', '["Ngữ văn","Mỹ thuật","Vẽ mỹ thuật"]', 'Thiết kế & Nghệ thuật', 12),
    ('X06', 'Toán, Vật lý, Tin học', '["Toán","Vật lý","Tin học"]', 'Công nghệ thông tin', 13),
    ('X26', 'Toán, Tiếng Anh, Tin học', '["Toán","Tiếng Anh","Tin học"]', 'Công nghệ thông tin & Ngoại ngữ', 14)
ON DUPLICATE KEY UPDATE
    `subjects_text` = VALUES(`subjects_text`),
    `subjects_json` = VALUES(`subjects_json`),
    `category` = VALUES(`category`),
    `sort_order` = VALUES(`sort_order`);

-- 3. Program <-> Year <-> Method <-> Combination verified linkage
CREATE TABLE IF NOT EXISTS `program_combinations` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `program_id` INT UNSIGNED NOT NULL,
    `admission_year` INT NOT NULL,
    `method` VARCHAR(140) NOT NULL,
    `combination_code` VARCHAR(16) NOT NULL,
    `conditions_note` TEXT NULL,
    `source_url` VARCHAR(500) NOT NULL,
    `source_title` VARCHAR(240) NULL,
    `publisher` VARCHAR(180) NULL,
    `published_date` DATE NULL,
    `verified_on` DATE NULL,
    `verification_status` VARCHAR(30) NOT NULL DEFAULT 'verified',
    UNIQUE KEY `uk_prog_comb` (`program_id`, `admission_year`, `method`, `combination_code`),
    INDEX `idx_pc_comb_year` (`combination_code`, `admission_year`),
    CONSTRAINT `fk_pc_program` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pc_combination` FOREIGN KEY (`combination_code`) REFERENCES `admission_combinations` (`code`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Step 1: Quick Self-Discovery Sessions (5 questions)
CREATE TABLE IF NOT EXISTS `user_discovery_sessions` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `question_version` VARCHAR(32) NOT NULL DEFAULT 'discover_v1',
    `answers_json` TEXT NOT NULL,
    `summary_json` TEXT NULL,
    `completed` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_discovery_user` (`user_id`, `updated_at`),
    CONSTRAINT `fk_discovery_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Step 3: "Hiểu mình cần gì" 20-question weighted comparisons
CREATE TABLE IF NOT EXISTS `user_comparisons` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` VARCHAR(36) NOT NULL,
    `user_id` INT UNSIGNED NULL,
    `question_version` VARCHAR(32) NOT NULL DEFAULT 'compare_v1',
    `options_json` TEXT NOT NULL,
    `answers_json` TEXT NOT NULL,
    `research_notes_json` TEXT NULL,
    `result_json` TEXT NULL,
    `primary_option_id` VARCHAR(80) NULL,
    `backup_option_id` VARCHAR(80) NULL,
    `status` VARCHAR(24) NOT NULL DEFAULT 'draft',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_comparisons_uid` (`uid`),
    INDEX `idx_comparisons_user` (`user_id`, `updated_at`),
    CONSTRAINT `fk_comparisons_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Step 4: Month/Year Action Plans & Revocable Read-Only Share Tokens
CREATE TABLE IF NOT EXISTS `career_action_plans` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uid` VARCHAR(36) NOT NULL,
    `user_id` INT UNSIGNED NULL,
    `comparison_uid` VARCHAR(36) NULL,
    `primary_option_json` TEXT NOT NULL,
    `backup_option_json` TEXT NULL,
    `grade` VARCHAR(20) NULL,
    `start_month` VARCHAR(7) NOT NULL,
    `target_horizon` VARCHAR(40) NOT NULL DEFAULT '6_months',
    `hours_per_week` INT NULL,
    `research_items_json` TEXT NULL,
    `share_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_action_plans_uid` (`uid`),
    INDEX `idx_action_plans_user` (`user_id`, `updated_at`),
    CONSTRAINT `fk_action_plans_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `career_action_plan_items` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `plan_id` BIGINT UNSIGNED NOT NULL,
    `month_key` VARCHAR(7) NOT NULL,
    `due_date` DATE NULL,
    `deadline_kind` VARCHAR(24) NOT NULL DEFAULT 'self',
    `official_source_url` VARCHAR(500) NULL,
    `goal` VARCHAR(255) NOT NULL,
    `tasks` TEXT NOT NULL,
    `expected_outcome` TEXT NOT NULL,
    `status` VARCHAR(24) NOT NULL DEFAULT 'not_started',
    `source` VARCHAR(24) NOT NULL DEFAULT 'manual',
    `user_edited` TINYINT(1) NOT NULL DEFAULT 0,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_plan_items_plan_month` (`plan_id`, `month_key`, `sort_order`),
    CONSTRAINT `fk_plan_items_plan` FOREIGN KEY (`plan_id`) REFERENCES `career_action_plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `plan_share_tokens` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `plan_id` BIGINT UNSIGNED NOT NULL,
    `token` VARCHAR(64) NOT NULL,
    `revoked_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_plan_share_token` (`token`),
    INDEX `idx_plan_share_plan` (`plan_id`, `revoked_at`),
    CONSTRAINT `fk_plan_share_plan` FOREIGN KEY (`plan_id`) REFERENCES `career_action_plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
