-- Migration 0020: Restructure catalog to 6 rooms (3 Overnight Hotel Rooms + 3 Dormitory Rooms)
-- Safe for existing databases: preserves any legacy room with booking history by archiving it to 'maintenance',
-- and only deletes legacy seed rooms that have zero bookings.

-- 1. Update the 3 Overnight Hotel Rooms (101, 201, 301)
INSERT INTO `rooms` (`room_number`,`room_type_id`,`name`,`description`,`floor`,`capacity`,`price_per_night`,`weekend_rate`,`amenities`,`tags`,`thumbnail`,`status`) VALUES
('101', 1, 'Standard Hotel Room 101', 'Cozy overnight hotel room on the ground floor with tropical garden view, plush queen bed, and private bath.', 1, 2, 800.00, 950.00, '["WiFi","Air Con","TV","Hot Shower","Ref"]', '["hotel room","overnight","budget","garden view"]', 'room-101.jpg', 'available'),
('201', 2, 'Deluxe Hotel Room 201', 'Spacious overnight deluxe hotel room with premium king bedding, Smart TV, Safe Box, and city skyline view.', 2, 2, 1500.00, 1800.00, '["WiFi","Air Con","Smart TV","Hot Shower","Ref","Safe Box","Breakfast"]', '["hotel room","overnight","deluxe","city view"]', 'room-201.jpg', 'available'),
('301', 3, 'Executive Suite 301', 'Luxurious overnight hotel suite with separate living lounge, king bedroom, panoramic city view, and Hot Tub.', 3, 4, 2500.00, 3000.00, '["WiFi","Air Con","Smart TV","Hot Tub","Ref","Safe Box","Mini Bar","Balcony","Breakfast","Lounge"]', '["hotel room","overnight","luxury","suite","family"]', 'suite-301.jpg', 'available')
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `description` = VALUES(`description`),
  `floor` = VALUES(`floor`),
  `capacity` = VALUES(`capacity`),
  `price_per_night` = VALUES(`price_per_night`),
  `weekend_rate` = VALUES(`weekend_rate`),
  `amenities` = VALUES(`amenities`),
  `tags` = VALUES(`tags`),
  `thumbnail` = IF(`thumbnail` LIKE '%/%', `thumbnail`, VALUES(`thumbnail`)),
  `status` = IF(`status` IN ('occupied','reserved'), `status`, 'available');

-- 2. Update / Insert the 3 Dormitory Rooms (D01, D02, D03)
INSERT INTO `rooms` (`room_number`,`room_type_id`,`name`,`description`,`floor`,`capacity`,`price_per_night`,`weekend_rate`,`amenities`,`tags`,`thumbnail`,`status`) VALUES
('D01', 5, 'Dormitory Room - Female Wing', 'Clean female-only boutique dormitory with light-wood capsule bunks, privacy curtains, personal lockers, and shared bath.', 1, 1, 350.00, 400.00, '["WiFi","Air Con","Locker","Privacy Curtain","Shared Bathroom","Charging Station"]', '["dormitory","budget","female only","student"]', 'dorm-f01.jpg', 'available'),
('D02', 5, 'Dormitory Room - Male Wing', 'Modern male-only boutique dormitory with dark-wood & steel bunk pods, privacy curtains, lockers, and charging shelves.', 1, 1, 350.00, 400.00, '["WiFi","Air Con","Locker","Privacy Curtain","Shared Bathroom","Charging Station"]', '["dormitory","budget","male only","student"]', 'dorm-m02.jpg', 'available'),
('D03', 5, 'Dormitory Room - Co-ed Deluxe Wing', 'Upscale co-ed boutique dormitory with timber sleeping pods, study desk nook, personal lockers, and high-speed WiFi.', 2, 1, 450.00, 500.00, '["WiFi","Air Con","Locker","Privacy Curtain","Study Nook","Shared Bathroom","Charging Station"]', '["dormitory","budget","co-ed","study friendly"]', 'dorm-03.jpg', 'available')
ON DUPLICATE KEY UPDATE
  `room_type_id` = VALUES(`room_type_id`),
  `name` = VALUES(`name`),
  `description` = VALUES(`description`),
  `floor` = VALUES(`floor`),
  `capacity` = VALUES(`capacity`),
  `price_per_night` = VALUES(`price_per_night`),
  `weekend_rate` = VALUES(`weekend_rate`),
  `amenities` = VALUES(`amenities`),
  `tags` = VALUES(`tags`),
  `thumbnail` = IF(`thumbnail` LIKE '%/%', `thumbnail`, VALUES(`thumbnail`)),
  `status` = IF(`status` IN ('occupied','reserved'), `status`, 'available');

-- 3. Safely remove unused extra seed rooms (102, 202, 401, S01) with zero booking history
DELETE FROM `rooms`
WHERE `room_number` IN ('102', '202', '401', 'S01')
  AND `id` NOT IN (SELECT DISTINCT `room_id` FROM `bookings`);

-- 4. Archive any remaining legacy seed rooms that still have historical bookings so they stay hidden from public catalog
UPDATE `rooms`
SET `status` = 'maintenance'
WHERE `room_number` IN ('102', '202', '401', 'S01');
