-- Migration 0004: Update Maya (PayMaya) QR code and account details
INSERT INTO `settings` (`key`, `value`, `description`)
SELECT 'hotel_maya_name', 'WILLY GAILO', 'PayMaya (Maya) Registered Account Name'
WHERE NOT EXISTS (SELECT 1 FROM `settings` WHERE `key` = 'hotel_maya_name');

INSERT INTO `settings` (`key`, `value`, `description`)
SELECT 'hotel_maya_handle', '@willygailojr', 'PayMaya (Maya) Handle / Username'
WHERE NOT EXISTS (SELECT 1 FROM `settings` WHERE `key` = 'hotel_maya_handle');

INSERT INTO `settings` (`key`, `value`, `description`)
SELECT 'hotel_maya_phone', '+63 *** *** 6963', 'PayMaya (Maya) Phone Number'
WHERE NOT EXISTS (SELECT 1 FROM `settings` WHERE `key` = 'hotel_maya_phone');

INSERT INTO `settings` (`key`, `value`, `description`)
SELECT 'hotel_maya_qr', 'assets/images/maya_qr.jpg', 'Path to Maya / InstaPay QR Code Image'
WHERE NOT EXISTS (SELECT 1 FROM `settings` WHERE `key` = 'hotel_maya_qr');

UPDATE `settings` SET `value` = '@willygailojr (WILLY GAILO)' WHERE `key` = 'hotel_maya';
