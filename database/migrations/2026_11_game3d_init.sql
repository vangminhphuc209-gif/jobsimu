-- ==============================================================================
-- JOBSIMU 3D MIGRATION: 2026_11_game3d_init.sql
-- Vertical Slice Day 1 - Teacher Simulation Persistence
-- Isolated from legacy schema.sql. Zero changes to legacy tables.
-- ==============================================================================

-- 1. Game Runs Table: Tracks overall simulation sessions
CREATE TABLE IF NOT EXISTS `game_runs` (
    `id` VARCHAR(64) NOT NULL,
    `user_id` INT UNSIGNED NULL,
    `career_id` VARCHAR(64) NOT NULL DEFAULT 'giao-vien',
    `content_version` VARCHAR(32) NOT NULL DEFAULT '1.0.0',
    `day_index` INT NOT NULL DEFAULT 1,
    `status` ENUM('active', 'completed', 'abandoned') NOT NULL DEFAULT 'active',
    `started_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_runs_user` (`user_id`),
    KEY `idx_runs_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Game Save Snapshots Table: Optimistic revision locking & idempotency
CREATE TABLE IF NOT EXISTS `game_save_snapshots` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `run_id` VARCHAR(64) NOT NULL,
    `revision` INT NOT NULL DEFAULT 1,
    `idempotency_key` VARCHAR(128) NULL,
    `snapshot_json` LONGTEXT NOT NULL,
    `saved_at` DATETIME NOT NULL,
    UNIQUE KEY `idx_run_revision` (`run_id`, `revision`),
    UNIQUE KEY `idx_run_idempotency` (`run_id`, `idempotency_key`),
    CONSTRAINT `fk_snapshots_run` FOREIGN KEY (`run_id`) REFERENCES `game_runs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Game Events Table: Sequential event log for behavioral telemetry
CREATE TABLE IF NOT EXISTS `game_events` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `run_id` VARCHAR(64) NOT NULL,
    `event_seq` INT NOT NULL,
    `event_id` VARCHAR(64) NOT NULL,
    `action_id` VARCHAR(64) NOT NULL,
    `payload_json` LONGTEXT NULL,
    `created_at` DATETIME NOT NULL,
    UNIQUE KEY `idx_run_seq` (`run_id`, `event_seq`),
    KEY `idx_events_action` (`event_id`, `action_id`),
    CONSTRAINT `fk_events_run` FOREIGN KEY (`run_id`) REFERENCES `game_runs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Game NPC States Table: Persists relationship metrics and memory flags
CREATE TABLE IF NOT EXISTS `game_npc_states` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `run_id` VARCHAR(64) NOT NULL,
    `npc_id` VARCHAR(64) NOT NULL,
    `state_json` LONGTEXT NOT NULL,
    `updated_at` DATETIME NOT NULL,
    UNIQUE KEY `idx_run_npc` (`run_id`, `npc_id`),
    CONSTRAINT `fk_npc_states_run` FOREIGN KEY (`run_id`) REFERENCES `game_runs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Game Quests Table: Tracks progress across CP-01 to CP-07
CREATE TABLE IF NOT EXISTS `game_quests` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `run_id` VARCHAR(64) NOT NULL,
    `quest_id` VARCHAR(64) NOT NULL,
    `status` ENUM('pending', 'active', 'completed', 'failed') NOT NULL DEFAULT 'pending',
    `progress_json` LONGTEXT NULL,
    `updated_at` DATETIME NOT NULL,
    UNIQUE KEY `idx_run_quest` (`run_id`, `quest_id`),
    CONSTRAINT `fk_quests_run` FOREIGN KEY (`run_id`) REFERENCES `game_runs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Game Reports Table: Multidimensional career evaluation reports
CREATE TABLE IF NOT EXISTS `game_reports` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `run_id` VARCHAR(64) NOT NULL,
    `report_version` VARCHAR(32) NOT NULL DEFAULT '1.0.0',
    `summary_json` LONGTEXT NOT NULL,
    `completed_at` DATETIME NOT NULL,
    UNIQUE KEY `idx_reports_run` (`run_id`),
    CONSTRAINT `fk_reports_run` FOREIGN KEY (`run_id`) REFERENCES `game_runs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
