-- Migration 0013: Add 'dirty' housekeeping status to rooms.status ENUM
ALTER TABLE `rooms`
  MODIFY COLUMN `status` ENUM('available','occupied','dirty','maintenance','reserved') NOT NULL DEFAULT 'available';
