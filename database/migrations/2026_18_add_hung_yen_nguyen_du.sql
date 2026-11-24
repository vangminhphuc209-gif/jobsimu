-- Migration 2026_18: Add Tỉnh Hưng Yên & Trường THPT Nguyễn Du (Idempotent / Safe to rerun)
-- Preserves existing provinces, schools, IDs, and users.school_id linkages.

INSERT INTO `provinces` (`code`, `name`, `sort_order`, `signup_enabled`) VALUES
    ('hung-yen', 'Tỉnh Hưng Yên', 4, 1)
ON DUPLICATE KEY UPDATE
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

-- Keep signup_enabled strictly restricted to the 4 allowed provinces
UPDATE `provinces`
SET `signup_enabled` = 0
WHERE `code` NOT IN ('hai-phong', 'dien-bien', 'lai-chau', 'hung-yen');

-- Keep signup_enabled strictly restricted to the 7 allowed schools
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

-- Backfill any existing users who entered 'Trường THPT Nguyễn Du' as plain text
UPDATE `users` u
JOIN `schools` s ON TRIM(u.`school`) = s.`name` AND s.`code` = 'hy-thpt-nguyen-du'
SET u.`school_id` = s.`id`
WHERE u.`school_id` IS NULL
  AND u.`school` IS NOT NULL
  AND TRIM(u.`school`) <> '';
