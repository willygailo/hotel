-- Migration 0005: Add unique key on users(first_name, last_name)
-- Pinipigilan ang pag-register ng kaparehong first name at last name sa system
ALTER TABLE `users` ADD UNIQUE KEY `unique_user_fullname` (`first_name`, `last_name`);
