-- Migration 0016: Real-time password reset status tracking & one-time popup notification

ALTER TABLE `password_reset_requests` ADD COLUMN `request_token` VARCHAR(64) DEFAULT NULL AFTER `phone`;
ALTER TABLE `password_reset_requests` ADD COLUMN `dispatch_type` ENUM('password','link') DEFAULT NULL AFTER `status`;
ALTER TABLE `password_reset_requests` ADD COLUMN `user_notified_at` DATETIME DEFAULT NULL AFTER `resolved_at`;
ALTER TABLE `password_reset_requests` ADD INDEX `idx_prr_token` (`request_token`);
