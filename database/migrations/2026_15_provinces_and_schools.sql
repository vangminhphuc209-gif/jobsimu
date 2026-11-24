-- Provinces & High Schools Directory for Student Signup (Idempotent / Safe to rerun)
-- Preserves all legacy users.school values and historical records.
-- Deduplicates "Trường THPT Chu Văn An" (already one of the 4 legacy Lai Châu schools).

CREATE TABLE IF NOT EXISTS `provinces` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(64) NOT NULL,
    `name` VARCHAR(120) NOT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `signup_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_provinces_code` (`code`),
    INDEX `idx_provinces_signup` (`signup_enabled`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `schools` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add additive users.school_id column if missing
SET @has_school_id := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'school_id'
);
SET @sql_add_school_id := IF(
    @has_school_id = 0,
    'ALTER TABLE `users` ADD COLUMN `school_id` INT UNSIGNED NULL AFTER `school`',
    'SELECT 1'
);
PREPARE stmt_add_school_id FROM @sql_add_school_id;
EXECUTE stmt_add_school_id;
DEALLOCATE PREPARE stmt_add_school_id;

-- Add index idx_users_school_id if missing
SET @has_school_idx := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND INDEX_NAME = 'idx_users_school_id'
);
SET @sql_add_school_idx := IF(
    @has_school_idx = 0,
    'ALTER TABLE `users` ADD INDEX `idx_users_school_id` (`school_id`)',
    'SELECT 1'
);
PREPARE stmt_add_school_idx FROM @sql_add_school_idx;
EXECUTE stmt_add_school_idx;
DEALLOCATE PREPARE stmt_add_school_idx;

-- Add foreign key fk_users_school if missing
SET @has_school_fk := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND CONSTRAINT_NAME = 'fk_users_school'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql_add_school_fk := IF(
    @has_school_fk = 0,
    'ALTER TABLE `users` ADD CONSTRAINT `fk_users_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt_add_school_fk FROM @sql_add_school_fk;
EXECUTE stmt_add_school_fk;
DEALLOCATE PREPARE stmt_add_school_fk;

-- Seed 3 allowed signup provinces (preserve IDs on rerun; never delete out-of-scope historical rows)
INSERT INTO `provinces` (`code`, `name`, `sort_order`, `signup_enabled`) VALUES
    ('hai-phong', 'Thành phố Hải Phòng', 1, 1),
    ('dien-bien', 'Tỉnh Điện Biên', 2, 1),
    ('lai-chau', 'Tỉnh Lai Châu', 3, 1),
    ('hung-yen', 'Tỉnh Hưng Yên', 4, 1)
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `sort_order` = VALUES(`sort_order`),
    `signup_enabled` = VALUES(`signup_enabled`);

UPDATE `provinces`
SET `signup_enabled` = 0
WHERE `code` NOT IN ('hai-phong', 'dien-bien', 'lai-chau', 'hung-yen');

-- Seed 7 canonical signup schools (preserve IDs on rerun; "Trường THPT Chu Văn An" deduplicated)
INSERT INTO `schools` (`code`, `province_id`, `name`, `sort_order`, `signup_enabled`)
SELECT 'hp-thpt-luong-the-vinh-hai-duong', p.id, 'Trường THPT Lương Thế Vinh Hải Dương', 1, 1
FROM `provinces` p WHERE p.code = 'hai-phong'
ON DUPLICATE KEY UPDATE
    `province_id` = VALUES(`province_id`),
    `name` = VALUES(`name`),
    `sort_order` = VALUES(`sort_order`),
    `signup_enabled` = VALUES(`signup_enabled`);

INSERT INTO `schools` (`code`, `province_id`, `name`, `sort_order`, `signup_enabled`)
SELECT 'db-ptdtnt-thpt-tinh-dien-bien', p.id, 'Trường PTDTNT THPT tỉnh Điện Biên', 1, 1
FROM `provinces` p WHERE p.code = 'dien-bien'
ON DUPLICATE KEY UPDATE
    `province_id` = VALUES(`province_id`),
    `name` = VALUES(`name`),
    `sort_order` = VALUES(`sort_order`),
    `signup_enabled` = VALUES(`signup_enabled`);

INSERT INTO `schools` (`code`, `province_id`, `name`, `sort_order`, `signup_enabled`)
SELECT 'lc-thpt-chu-van-an', p.id, 'Trường THPT Chu Văn An', 1, 1
FROM `provinces` p WHERE p.code = 'lai-chau'
ON DUPLICATE KEY UPDATE
    `province_id` = VALUES(`province_id`),
    `name` = VALUES(`name`),
    `sort_order` = VALUES(`sort_order`),
    `signup_enabled` = VALUES(`signup_enabled`);

INSERT INTO `schools` (`code`, `province_id`, `name`, `sort_order`, `signup_enabled`)
SELECT 'lc-ptdtnt-thpt-tinh', p.id, 'Trường PTDTNT THPT Tỉnh', 2, 1
FROM `provinces` p WHERE p.code = 'lai-chau'
ON DUPLICATE KEY UPDATE
    `province_id` = VALUES(`province_id`),
    `name` = VALUES(`name`),
    `sort_order` = VALUES(`sort_order`),
    `signup_enabled` = VALUES(`signup_enabled`);

INSERT INTO `schools` (`code`, `province_id`, `name`, `sort_order`, `signup_enabled`)
SELECT 'lc-thpt-quyet-thang', p.id, 'Trường THPT Quyết Thắng', 3, 1
FROM `provinces` p WHERE p.code = 'lai-chau'
ON DUPLICATE KEY UPDATE
    `province_id` = VALUES(`province_id`),
    `name` = VALUES(`name`),
    `sort_order` = VALUES(`sort_order`),
    `signup_enabled` = VALUES(`signup_enabled`);

INSERT INTO `schools` (`code`, `province_id`, `name`, `sort_order`, `signup_enabled`)
SELECT 'lc-ptdtnt-thpt-muong-te', p.id, 'Trường PTDTNT THPT Mường Tè', 4, 1
FROM `provinces` p WHERE p.code = 'lai-chau'
ON DUPLICATE KEY UPDATE
    `province_id` = VALUES(`province_id`),
    `name` = VALUES(`name`),
    `sort_order` = VALUES(`sort_order`),
    `signup_enabled` = VALUES(`signup_enabled`);

INSERT INTO `schools` (`code`, `province_id`, `name`, `sort_order`, `signup_enabled`)
SELECT 'hy-thpt-nguyen-du', p.id, 'Trường THPT Nguyễn Du', 1, 1
FROM `provinces` p WHERE p.code = 'hung-yen'
ON DUPLICATE KEY UPDATE
    `province_id` = VALUES(`province_id`),
    `name` = VALUES(`name`),
    `sort_order` = VALUES(`sort_order`),
    `signup_enabled` = VALUES(`signup_enabled`);

UPDATE `schools`
SET `signup_enabled` = 0
WHERE `code` NOT IN (
    'hp-thpt-luong-the-vinh-hai-duong',
    'db-ptdtnt-thpt-tinh-dien-bien',
    'lc-thpt-chu-van-an',
    'lc-ptdtnt-thpt-tinh',
    'lc-thpt-quyet-thang',
    'lc-ptdtnt-thpt-muong-te',
    'hy-thpt-nguyen-du'
);

-- Backfill users.school_id ONLY for exact matches with known schools; never overwrite or guess unknown/blank schools
UPDATE `users` u
JOIN `schools` s ON TRIM(u.`school`) = s.`name`
SET u.`school_id` = s.`id`
WHERE u.`school_id` IS NULL
  AND u.`school` IS NOT NULL
  AND TRIM(u.`school`) <> '';
