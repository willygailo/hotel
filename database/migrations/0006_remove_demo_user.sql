-- Migration 0006: Delete demo user account (user@demo.com)
-- Inalis ang demo user account para maging purong tunay na rehistrasyon ang mga user
DELETE FROM `users` WHERE `email` = 'user@demo.com';
