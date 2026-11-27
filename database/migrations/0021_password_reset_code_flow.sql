-- Migration 0021: Code-based Password Reset Workflow (Admin dispatches 1-time Reset Code, User sets own password)
-- Safe & idempotent: keeps legacy 'password','link' ENUM values intact for historical rows.

ALTER TABLE `password_reset_requests`
    MODIFY COLUMN `dispatch_type` ENUM('code','password','link') DEFAULT NULL;

ALTER TABLE `password_reset_requests`
    ADD COLUMN `reset_code` VARCHAR(16) DEFAULT NULL AFTER `new_password_assigned`,
    ADD COLUMN `reset_code_hash` VARCHAR(64) DEFAULT NULL AFTER `reset_code`,
    ADD COLUMN `reset_code_expires` DATETIME DEFAULT NULL AFTER `reset_code_hash`,
    ADD COLUMN `reset_code_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `reset_code_expires`,
    ADD COLUMN `code_used_at` DATETIME DEFAULT NULL AFTER `user_notified_at`,
    ADD INDEX `idx_prr_user_code` (`user_id`, `status`, `code_used_at`);
