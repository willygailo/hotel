-- ============================================================
-- Migration 0009: KYC Verification Lifecycle & Adjudication
-- Marvelous Hotel & Dormitory Management System
-- ============================================================

ALTER TABLE `users` ADD COLUMN `verification_status` ENUM('unverified', 'pending', 'verified', 'rejected') NOT NULL DEFAULT 'unverified' AFTER `profile_verified`;
ALTER TABLE `users` ADD COLUMN `rejection_reason` TEXT DEFAULT NULL AFTER `verification_status`;
ALTER TABLE `users` ADD COLUMN `verified_by` INT(11) DEFAULT NULL AFTER `rejection_reason`;
ALTER TABLE `users` ADD COLUMN `verified_at` DATETIME DEFAULT NULL AFTER `verified_by`;

-- Backfill verification_status only for uninitialized rows based on current profile_verified and id_document
UPDATE `users` 
SET `verification_status` = CASE 
    WHEN `profile_verified` = 1 THEN 'verified'
    WHEN `id_document` IS NOT NULL AND `id_document` != '' THEN 'pending'
    ELSE 'unverified'
END
WHERE `verification_status` = 'unverified'
  AND (`profile_verified` = 1 OR (`id_document` IS NOT NULL AND `id_document` != ''));
