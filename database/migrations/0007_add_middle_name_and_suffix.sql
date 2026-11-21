-- Migration 0007: Add middle_name and suffix to users table
ALTER TABLE `users` ADD COLUMN `middle_name` VARCHAR(100) DEFAULT NULL AFTER `first_name`;
ALTER TABLE `users` ADD COLUMN `suffix` VARCHAR(20) DEFAULT NULL AFTER `last_name`;
