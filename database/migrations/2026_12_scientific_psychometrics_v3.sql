-- JOBSIMU v3.0 Scientific Psychometrics & Stealth Assessment Migration
-- Compatible with MySQL 5.7+ / MariaDB 10.3+
-- Additive and idempotent where supported.

-- 1. Mở rộng bảng assessments để lưu Ma trận SCCT (Interest x Self-Efficacy), Z-scores và Cờ độ tin cậy
ALTER TABLE `assessments`
  ADD COLUMN IF NOT EXISTS `efficacy_scores` JSON NULL AFTER `scores`,
  ADD COLUMN IF NOT EXISTS `ipsative_z_scores` JSON NULL AFTER `efficacy_scores`,
  ADD COLUMN IF NOT EXISTS `validity_metrics` JSON NULL AFTER `constraint_answers`,
  ADD COLUMN IF NOT EXISTS `discrepancy_report` JSON NULL AFTER `validity_metrics`;

-- 2. Mở rộng bảng user_psychometrics để hỗ trợ chuẩn Big Five (Mini-IPIP), CAAS và TEIQue-SF
ALTER TABLE `user_psychometrics`
  MODIFY COLUMN `test_type` ENUM('mbti', 'eq', 'big5', 'caas', 'teique') NOT NULL,
  ADD COLUMN IF NOT EXISTS `duration_seconds` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `summary_label`,
  ADD COLUMN IF NOT EXISTS `validity_flag` ENUM('valid', 'rushed', 'flatline', 'inconsistent') NOT NULL DEFAULT 'valid' AFTER `duration_seconds`;

-- 3. Mở rộng bảng simulation_attempts để lưu Telemetry Hành vi (ECD Process Data)
ALTER TABLE `simulation_attempts`
  ADD COLUMN IF NOT EXISTS `process_telemetry` JSON NULL AFTER `skill_scores`,
  ADD COLUMN IF NOT EXISTS `tradeoff_profile` JSON NULL AFTER `process_telemetry`;

-- 4. Bảng Token Gương Phản Chiếu 360 Độ (SOKA Peer/Mentor Mirror Requests)
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

-- 5. Bảng Phản hồi Gương Phản Chiếu 360 Độ (SOKA Peer/Mentor Mirror Responses)
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
