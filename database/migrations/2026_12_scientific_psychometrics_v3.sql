-- Scientific Psychometrics & SOKA Mirror upgrade (v3.0.0)
-- Additive migration safe for MySQL 5.7+ / MariaDB 10.x. Column additions are guarded in run_migrations().

CREATE TABLE IF NOT EXISTS `soka_mirror_requests` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `invite_token` VARCHAR(64) NOT NULL UNIQUE,
    `rater_relation` ENUM('peer', 'teacher', 'parent', 'mentor') NOT NULL DEFAULT 'peer',
    `status` ENUM('open', 'closed') NOT NULL DEFAULT 'open',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME NULL,
    INDEX `idx_soka_user` (`user_id`),
    CONSTRAINT `fk_soka_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `soka_mirror_responses` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `request_id` BIGINT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `rater_role` ENUM('peer', 'teacher', 'parent', 'mentor') NOT NULL DEFAULT 'peer',
    `ratings_json` JSON NOT NULL,
    `strength_note` VARCHAR(300) NULL,
    `blindspot_note` VARCHAR(300) NULL,
    `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_soka_resp_user` (`user_id`, `submitted_at`),
    CONSTRAINT `fk_soka_resp_req` FOREIGN KEY (`request_id`) REFERENCES `soka_mirror_requests` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_soka_resp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
