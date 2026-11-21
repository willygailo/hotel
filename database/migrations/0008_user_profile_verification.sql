-- Migration 0008: Add User Profile Verification Fields
ALTER TABLE `users` ADD COLUMN `id_document` VARCHAR(255) DEFAULT NULL AFTER `id_number`;
ALTER TABLE `users` ADD COLUMN `location` TEXT DEFAULT NULL AFTER `address`;
ALTER TABLE `users` ADD COLUMN `profile_verified` TINYINT(1) NOT NULL DEFAULT 0 AFTER `email_verified`;
ALTER TABLE `users` ADD COLUMN `verification_submitted_at` DATETIME DEFAULT NULL AFTER `profile_verified`;
