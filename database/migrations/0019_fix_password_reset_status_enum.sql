-- Migration 0019: Ensure password_reset_requests.status supports 'dismissed' (superseded requests)
-- Fixes: SQLSTATE[01000] 1265 Data truncated for column 'status' on forgot-password resubmission.

ALTER TABLE `password_reset_requests` MODIFY COLUMN `status` ENUM('pending','completed','dismissed') NOT NULL DEFAULT 'pending';
