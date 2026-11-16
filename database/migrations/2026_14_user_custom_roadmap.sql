-- User Custom Roadmap & Dynamic Milestones (Hub 4 Upgrade)
-- Additive and safe to rerun.

CREATE TABLE IF NOT EXISTS `user_roadmaps` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `target_career_id` INT UNSIGNED NULL,
    `target_career_title` VARCHAR(200) NULL,
    `goal_title` VARCHAR(255) NOT NULL DEFAULT 'Khám phá và định hình hướng đi tương lai',
    `target_timeframe` VARCHAR(100) NOT NULL DEFAULT 'Lớp 10 - 12',
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_roadmap_user` (`user_id`),
    CONSTRAINT `fk_roadmap_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_roadmap_milestones` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `roadmap_id` BIGINT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `stage` ENUM('discovery', 'academic', 'skills', 'decision') NOT NULL DEFAULT 'discovery',
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `action_url` VARCHAR(255) NULL,
    `is_completed` TINYINT(1) NOT NULL DEFAULT 0,
    `completed_at` DATETIME NULL,
    `source` ENUM('suggested', 'assessment', 'custom', 'ai') NOT NULL DEFAULT 'suggested',
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_milestone_user_stage` (`user_id`, `stage`, `is_completed`),
    CONSTRAINT `fk_milestone_roadmap` FOREIGN KEY (`roadmap_id`) REFERENCES `user_roadmaps` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_milestone_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
