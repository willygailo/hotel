-- Migration 0018: Track Admin-assigned initial / temporary passwords securely (AES-256-GCM ciphertext vault)
-- Users keep 100% one-way Bcrypt hashes in users.password; temp_password_enc only holds temporary/walk-in credentials
-- created or reset by Administration until the user updates their own password.

ALTER TABLE `users` ADD COLUMN `temp_password_enc` TEXT DEFAULT NULL AFTER `remember_expires`;
ALTER TABLE `users` ADD COLUMN `password_is_temp` TINYINT(1) NOT NULL DEFAULT 0 AFTER `temp_password_enc`;
ALTER TABLE `users` ADD COLUMN `password_updated_at` DATETIME DEFAULT NULL AFTER `password_is_temp`;
