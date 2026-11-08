-- Four-Hub upgrade: additive and safe to rerun. Seed data lives in app/four-hubs-data.php.
-- 1. Bảng Tin tức tuyển sinh & Xu hướng nghề nghiệp (Hub 1)
CREATE TABLE IF NOT EXISTS `knowledge_articles` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(200) NOT NULL,
    `slug` VARCHAR(220) NOT NULL UNIQUE,
    `category` ENUM('admissions', 'trend', 'guide') NOT NULL DEFAULT 'admissions',
    `summary` VARCHAR(400) NOT NULL,
    `content` TEXT NOT NULL,
    `source_name` VARCHAR(120) NULL,
    `source_url` VARCHAR(500) NULL,
    `related_career_slug` VARCHAR(180) NULL,
    `featured` TINYINT(1) NOT NULL DEFAULT 0,
    `status` ENUM('published', 'draft') NOT NULL DEFAULT 'published',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_articles_category_status` (`category`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Bảng Liên kết chuyển hướng tới Trang Hướng Nghiệp Uy Tín (Hub 1)
CREATE TABLE IF NOT EXISTS `external_portals` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(150) NOT NULL,
    `organization` VARCHAR(150) NOT NULL,
    `icon` VARCHAR(20) NOT NULL DEFAULT '🌐',
    `description` VARCHAR(300) NOT NULL,
    `target_url` VARCHAR(500) NOT NULL,
    `category` ENUM('official_admissions', 'career_directory', 'scholarship', 'skills') NOT NULL DEFAULT 'career_directory',
    `sort_order` INT NOT NULL DEFAULT 0,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Bảng Kết quả Đánh giá Mở rộng MBTI & EQ (Hub 2 - Độc lập với bảng assessments để bảo toàn FK)
CREATE TABLE IF NOT EXISTS `user_psychometrics` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `test_type` ENUM('mbti', 'eq') NOT NULL,
    `answers_json` JSON NOT NULL,
    `scores_json` JSON NOT NULL,
    `result_code` VARCHAR(32) NOT NULL,
    `summary_label` VARCHAR(150) NOT NULL,
    `completed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_psych_user_type` (`user_id`, `test_type`, `completed_at`),
    CONSTRAINT `fk_psych_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Bảng Hội thoại Chatbot Tư vấn Chuyên sâu (Hub 4)
CREATE TABLE IF NOT EXISTS `ai_chat_messages` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `role` ENUM('user', 'assistant') NOT NULL,
    `message` TEXT NOT NULL,
    `context_snapshot` JSON NULL,
    `model` VARCHAR(100) NULL,
    `latency_ms` INT UNSIGNED NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_chat_user_time` (`user_id`, `created_at`),
    CONSTRAINT `fk_chat_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
