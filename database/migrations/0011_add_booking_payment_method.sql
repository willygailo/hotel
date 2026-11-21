-- Migration 0011: Add payment_method to bookings table
-- Enables tracking of chosen payment channel (maya, gcash, cash) during room booking

ALTER TABLE `bookings` 
ADD COLUMN `payment_method` ENUM('maya', 'gcash', 'cash') NOT NULL DEFAULT 'maya' 
AFTER `special_requests`;

