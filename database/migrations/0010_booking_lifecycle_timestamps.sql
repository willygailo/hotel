-- ============================================================
-- Migration: 0010_booking_lifecycle_timestamps.sql
-- Add checked_in_at and checked_out_at for full audit tracking
-- ============================================================

ALTER TABLE `bookings` ADD COLUMN `checked_in_at` DATETIME DEFAULT NULL AFTER `accepted_at`;
ALTER TABLE `bookings` ADD COLUMN `checked_out_at` DATETIME DEFAULT NULL AFTER `checked_in_at`;
