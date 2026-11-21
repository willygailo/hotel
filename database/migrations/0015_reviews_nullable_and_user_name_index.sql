-- Migration 0015: Allow nullable room_id/booking_id for General Stay reviews
-- and replace strict 2-column unique_user_fullname with non-unique index (full name+middle+suffix uniqueness is enforced by validatePersonName)

ALTER TABLE `reviews` DROP FOREIGN KEY `fk_review_room`;
ALTER TABLE `reviews` DROP FOREIGN KEY `fk_review_booking`;
ALTER TABLE `reviews` MODIFY COLUMN `room_id` INT(11) DEFAULT NULL;
ALTER TABLE `reviews` MODIFY COLUMN `booking_id` INT(11) DEFAULT NULL;
ALTER TABLE `reviews` ADD CONSTRAINT `fk_review_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;
ALTER TABLE `reviews` ADD CONSTRAINT `fk_review_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE;

ALTER TABLE `users` DROP INDEX `unique_user_fullname`;
ALTER TABLE `users` ADD KEY `idx_user_fullname` (`first_name`, `last_name`);
