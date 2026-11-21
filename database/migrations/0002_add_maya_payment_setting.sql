-- Migration 0002: Add PayMaya (Maya) setting for GCash & PayMaya ONLY payment model
INSERT INTO `settings` (`key`, `value`, `description`)
SELECT 'hotel_maya', '09123456789', 'PayMaya (Maya) number for payments'
WHERE NOT EXISTS (SELECT 1 FROM `settings` WHERE `key` = 'hotel_maya');
