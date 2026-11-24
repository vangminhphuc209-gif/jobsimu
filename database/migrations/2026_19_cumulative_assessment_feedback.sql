-- Cumulative Assessment Feedback & Academic Condition Mapping (v3.1.0)
-- Additive migration safe for MySQL 5.7+ / MariaDB 10.x and SQLite.

CREATE TABLE IF NOT EXISTS `user_academic_profiles` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `grade_level` VARCHAR(20) NOT NULL DEFAULT 'Lớp 12',
    `academic_year` VARCHAR(20) NOT NULL DEFAULT '2024-2025',
    `semester` VARCHAR(20) NOT NULL DEFAULT 'sem1',
    `subject_scores_json` JSON NOT NULL,
    `target_region` VARCHAR(50) NOT NULL DEFAULT 'all',
    `budget_yearly` VARCHAR(50) NOT NULL DEFAULT 'unknown',
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_academic_user` (`user_id`),
    CONSTRAINT `fk_academic_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_assessment_reflections` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `test_type` VARCHAR(32) NOT NULL,
    `assessment_id` INT UNSIGNED NULL,
    `fit_feeling` ENUM('agree', 'partially_agree', 'disagree') NOT NULL DEFAULT 'agree',
    `student_notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_reflection_user_test` (`user_id`, `test_type`),
    CONSTRAINT `fk_reflection_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
