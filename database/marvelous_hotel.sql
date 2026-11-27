-- ============================================================
-- MARVELOUS HOTEL AND DORMITORY
-- Booking and Reservation Management System
-- with AI-Based Room Recommendation
-- Database Schema v1.0
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+08:00";

CREATE DATABASE IF NOT EXISTS `marvelous_hotel` 
  DEFAULT CHARACTER SET utf8mb4 
  COLLATE utf8mb4_unicode_ci;

USE `marvelous_hotel`;

-- ============================================================
-- TABLE: users
-- ============================================================
CREATE TABLE `users` (
  `id`                INT(11) NOT NULL AUTO_INCREMENT,
  `first_name`        VARCHAR(100) NOT NULL,
  `middle_name`       VARCHAR(100) DEFAULT NULL,
  `last_name`         VARCHAR(100) NOT NULL,
  `suffix`            VARCHAR(20) DEFAULT NULL,
  `email`             VARCHAR(191) NOT NULL UNIQUE,
  `password`          VARCHAR(255) NOT NULL,
  `role`              ENUM('user','staff','admin') NOT NULL DEFAULT 'user',
  `phone`             VARCHAR(20) DEFAULT NULL,
  `address`           TEXT DEFAULT NULL,
  `location`          TEXT DEFAULT NULL,
  `avatar`            VARCHAR(255) DEFAULT 'default-avatar.png',
  `gender`            ENUM('male','female','other') DEFAULT NULL,
  `date_of_birth`     DATE DEFAULT NULL,
  `id_type`           VARCHAR(50) DEFAULT NULL,
  `id_number`         VARCHAR(100) DEFAULT NULL,
  `id_document`       VARCHAR(255) DEFAULT NULL,
  `status`            ENUM('active','inactive','banned') NOT NULL DEFAULT 'active',
  `email_verified`    TINYINT(1) NOT NULL DEFAULT 0,
  `profile_verified`  TINYINT(1) NOT NULL DEFAULT 0,
  `verification_status` ENUM('unverified','pending','verified','rejected') NOT NULL DEFAULT 'unverified',
  `rejection_reason`  TEXT DEFAULT NULL,
  `verified_by`       INT(11) DEFAULT NULL,
  `verified_at`       DATETIME DEFAULT NULL,
  `verification_submitted_at` DATETIME DEFAULT NULL,
  `verify_token`      VARCHAR(255) DEFAULT NULL,
  `reset_token`       VARCHAR(255) DEFAULT NULL,
  `reset_expires`     DATETIME DEFAULT NULL,
  `two_fa_secret`     VARCHAR(255) DEFAULT NULL,
  `two_fa_enabled`    TINYINT(1) NOT NULL DEFAULT 0,
  `remember_token`    VARCHAR(255) DEFAULT NULL,
  `remember_expires`  DATETIME DEFAULT NULL,
  `temp_password_enc` TEXT DEFAULT NULL,
  `password_is_temp`  TINYINT(1) NOT NULL DEFAULT 0,
  `password_updated_at` DATETIME DEFAULT NULL,
  `last_login`        DATETIME DEFAULT NULL,
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_role` (`role`),
  KEY `idx_status` (`status`),
  KEY `idx_user_fullname` (`first_name`, `last_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: room_types
-- ============================================================
CREATE TABLE `room_types` (
  `id`          INT(11) NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `icon`        VARCHAR(100) DEFAULT 'fa-bed',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: rooms
-- ============================================================
CREATE TABLE `rooms` (
  `id`              INT(11) NOT NULL AUTO_INCREMENT,
  `room_number`     VARCHAR(20) NOT NULL UNIQUE,
  `room_type_id`    INT(11) NOT NULL,
  `name`            VARCHAR(150) NOT NULL,
  `description`     TEXT DEFAULT NULL,
  `floor`           INT(5) NOT NULL DEFAULT 1,
  `capacity`        INT(5) NOT NULL DEFAULT 1,
  `price_per_night` DECIMAL(10,2) NOT NULL,
  `weekend_rate`    DECIMAL(10,2) DEFAULT NULL,
  `amenities`       JSON DEFAULT NULL,
  `tags`            JSON DEFAULT NULL,
  `images`          JSON DEFAULT NULL,
  `thumbnail`       VARCHAR(255) DEFAULT 'default-room.jpg',
  `status`          ENUM('available','occupied','dirty','maintenance','reserved') NOT NULL DEFAULT 'available',
  `is_dorm`         TINYINT(1) NOT NULL DEFAULT 0,
  `bed_count`       INT(5) DEFAULT NULL,
  `beds_available`  INT(5) DEFAULT NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_room_type` (`room_type_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_room_type` FOREIGN KEY (`room_type_id`) REFERENCES `room_types` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: bookings
-- ============================================================
CREATE TABLE `bookings` (
  `id`                INT(11) NOT NULL AUTO_INCREMENT,
  `booking_code`      VARCHAR(20) NOT NULL UNIQUE,
  `user_id`           INT(11) NOT NULL,
  `room_id`           INT(11) NOT NULL,
  `check_in`          DATE NOT NULL,
  `check_out`         DATE NOT NULL,
  `num_guests`        INT(5) NOT NULL DEFAULT 1,
  `nights`            INT(5) NOT NULL DEFAULT 1,
  `subtotal`          DECIMAL(10,2) NOT NULL,
  `discount`          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_amount`      DECIMAL(10,2) NOT NULL,
  `special_requests`  TEXT DEFAULT NULL,
  `payment_method`    ENUM('maya','gcash','cash') NOT NULL DEFAULT 'maya',
  `status`            ENUM('pending','confirmed','checked_in','checked_out','cancelled','rejected') NOT NULL DEFAULT 'pending',
  `accepted_by`       INT(11) DEFAULT NULL,
  `accepted_at`       DATETIME DEFAULT NULL,
  `checked_in_at`     DATETIME DEFAULT NULL,
  `checked_out_at`    DATETIME DEFAULT NULL,
  `cancelled_reason`  TEXT DEFAULT NULL,
  `cancelled_at`      DATETIME DEFAULT NULL,
  `ai_recommended`    TINYINT(1) NOT NULL DEFAULT 0,
  `source`            ENUM('user','staff','admin','ai') NOT NULL DEFAULT 'user',
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_room_id` (`room_id`),
  KEY `idx_status` (`status`),
  KEY `idx_booking_code` (`booking_code`),
  CONSTRAINT `fk_booking_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_booking_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: payments
-- ============================================================
CREATE TABLE `payments` (
  `id`              INT(11) NOT NULL AUTO_INCREMENT,
  `booking_id`      INT(11) NOT NULL,
  `user_id`         INT(11) NOT NULL,
  `amount`          DECIMAL(10,2) NOT NULL,
  `method`          ENUM('gcash','bank_transfer','cash','card','maya') NOT NULL,
  `reference_number` VARCHAR(100) DEFAULT NULL,
  `proof_image`     VARCHAR(255) DEFAULT NULL,
  `status`          ENUM('pending','verified','rejected','refunded') NOT NULL DEFAULT 'pending',
  `verified_by`     INT(11) DEFAULT NULL,
  `verified_at`     DATETIME DEFAULT NULL,
  `notes`           TEXT DEFAULT NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_booking_id` (`booking_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_payment_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: reviews
-- ============================================================
CREATE TABLE `reviews` (
  `id`          INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`     INT(11) NOT NULL,
  `room_id`     INT(11) DEFAULT NULL,
  `booking_id`  INT(11) DEFAULT NULL,
  `rating`      TINYINT(1) NOT NULL CHECK (`rating` BETWEEN 1 AND 5),
  `title`       VARCHAR(200) DEFAULT NULL,
  `comment`     TEXT NOT NULL,
  `status`      ENUM('pending','approved','hidden') NOT NULL DEFAULT 'pending',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_review` (`booking_id`),
  KEY `idx_room_id` (`room_id`),
  CONSTRAINT `fk_review_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_review_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_review_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: notifications
-- ============================================================
CREATE TABLE `notifications` (
  `id`          INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`     INT(11) NOT NULL,
  `title`       VARCHAR(200) NOT NULL,
  `message`     TEXT NOT NULL,
  `type`        ENUM('booking','payment','review','system','alert','general') NOT NULL DEFAULT 'system',
  `icon`        VARCHAR(100) DEFAULT 'fa-bell',
  `link`        VARCHAR(255) DEFAULT NULL,
  `is_read`     TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_is_read` (`is_read`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: audit_logs
-- ============================================================
CREATE TABLE `audit_logs` (
  `id`          INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`     INT(11) DEFAULT NULL,
  `action`      VARCHAR(200) NOT NULL,
  `module`      VARCHAR(100) NOT NULL,
  `details`     TEXT DEFAULT NULL,
  `ip_address`  VARCHAR(50) DEFAULT NULL,
  `user_agent`  VARCHAR(255) DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: ai_logs
-- ============================================================
CREATE TABLE `ai_logs` (
  `id`            INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`       INT(11) DEFAULT NULL,
  `session_id`    VARCHAR(100) DEFAULT NULL,
  `user_message`  TEXT NOT NULL,
  `ai_response`   TEXT NOT NULL,
  `intent`        VARCHAR(100) DEFAULT NULL,
  `flagged`       TINYINT(1) NOT NULL DEFAULT 0,
  `flag_reason`   VARCHAR(255) DEFAULT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_flagged` (`flagged`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: announcements
-- ============================================================
CREATE TABLE `announcements` (
  `id`          INT(11) NOT NULL AUTO_INCREMENT,
  `title`       VARCHAR(200) NOT NULL,
  `message`     TEXT NOT NULL,
  `target_role` ENUM('all','user','staff','admin') NOT NULL DEFAULT 'all',
  `priority`    ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  `created_by`  INT(11) NOT NULL,
  `expires_at`  DATETIME DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_target_role` (`target_role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: settings
-- ============================================================
CREATE TABLE `settings` (
  `id`          INT(11) NOT NULL AUTO_INCREMENT,
  `key`         VARCHAR(100) NOT NULL UNIQUE,
  `value`       TEXT NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: promos
-- ============================================================
CREATE TABLE `promos` (
  `id`            INT(11) NOT NULL AUTO_INCREMENT,
  `code`          VARCHAR(50) NOT NULL UNIQUE,
  `description`   VARCHAR(255) DEFAULT NULL,
  `discount_type` ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
  `discount_value` DECIMAL(10,2) NOT NULL,
  `min_nights`    INT(5) DEFAULT 1,
  `max_uses`      INT(11) DEFAULT NULL,
  `used_count`    INT(11) NOT NULL DEFAULT 0,
  `valid_from`    DATE DEFAULT NULL,
  `valid_until`   DATE DEFAULT NULL,
  `status`        ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: contact_messages
-- ============================================================
CREATE TABLE `contact_messages` (
  `id`          INT(11) NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100) NOT NULL,
  `email`       VARCHAR(191) NOT NULL,
  `message`     TEXT NOT NULL,
  `ip_address`  VARCHAR(45) DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: rate_limits
-- ============================================================
CREATE TABLE `rate_limits` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `key`        VARCHAR(255) NOT NULL,
  `identifier` VARCHAR(191) NOT NULL,
  `action`     VARCHAR(100) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rate_key_created` (`key`(191), `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: password_reset_requests
-- ============================================================
CREATE TABLE `password_reset_requests` (
  `id`                    INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`               INT(11) DEFAULT NULL,
  `email`                 VARCHAR(191) NOT NULL,
  `phone`                 VARCHAR(30) DEFAULT NULL,
  `request_token`         VARCHAR(64) DEFAULT NULL,
  `status`                ENUM('pending','completed','dismissed') NOT NULL DEFAULT 'pending',
  `dispatch_type`         ENUM('password','link') DEFAULT NULL,
  `new_password_assigned` VARCHAR(255) DEFAULT NULL,
  `requested_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resolved_at`           DATETIME DEFAULT NULL,
  `user_notified_at`      DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_prr_status` (`status`),
  KEY `idx_prr_user` (`user_id`),
  KEY `idx_prr_token` (`request_token`),
  CONSTRAINT `fk_prr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SEED DATA: Room Types
-- ============================================================
INSERT INTO `room_types` (`name`, `description`, `icon`) VALUES
('Standard Room', 'Basic comfortable room with essential amenities', 'fa-bed'),
('Deluxe Room', 'Spacious room with premium furnishings', 'fa-star'),
('Suite', 'Luxury suite with separate living area', 'fa-crown'),
('Family Room', 'Large room perfect for families', 'fa-people-roof'),
('Dormitory', 'Budget-friendly shared dormitory bed', 'fa-building'),
('Studio', 'Self-contained studio unit', 'fa-house');

-- ============================================================
-- SEED DATA: Rooms (6 Total: 3 Overnight Hotel Rooms + 3 Dormitory Rooms)
-- ============================================================
INSERT INTO `rooms` (`room_number`,`room_type_id`,`name`,`description`,`floor`,`capacity`,`price_per_night`,`weekend_rate`,`amenities`,`tags`,`thumbnail`,`status`) VALUES
('101',1,'Standard Hotel Room 101','Cozy overnight hotel room on the ground floor with tropical garden view, plush queen bed, and private bath.',1,2,800.00,950.00,'["WiFi","Air Con","TV","Hot Shower","Ref"]','["hotel room","overnight","budget","garden view"]','room-101.jpg','available'),
('201',2,'Deluxe Hotel Room 201','Spacious overnight deluxe hotel room with premium king bedding, Smart TV, Safe Box, and city skyline view.',2,2,1500.00,1800.00,'["WiFi","Air Con","Smart TV","Hot Shower","Ref","Safe Box","Breakfast"]','["hotel room","overnight","deluxe","city view"]','room-201.jpg','available'),
('301',3,'Executive Suite 301','Luxurious overnight hotel suite with separate living lounge, king bedroom, panoramic city view, and Hot Tub.',3,4,2500.00,3000.00,'["WiFi","Air Con","Smart TV","Hot Tub","Ref","Safe Box","Mini Bar","Balcony","Breakfast","Lounge"]','["hotel room","overnight","luxury","suite","family"]','suite-301.jpg','available'),
('D01',5,'Dormitory Room - Female Wing','Clean female-only boutique dormitory with light-wood capsule bunks, privacy curtains, personal lockers, and shared bath.',1,1,350.00,400.00,'["WiFi","Air Con","Locker","Privacy Curtain","Shared Bathroom","Charging Station"]','["dormitory","budget","female only","student"]','dorm-f01.jpg','available'),
('D02',5,'Dormitory Room - Male Wing','Modern male-only boutique dormitory with dark-wood & steel bunk pods, privacy curtains, lockers, and charging shelves.',1,1,350.00,400.00,'["WiFi","Air Con","Locker","Privacy Curtain","Shared Bathroom","Charging Station"]','["dormitory","budget","male only","student"]','dorm-m02.jpg','available'),
('D03',5,'Dormitory Room - Co-ed Deluxe Wing','Upscale co-ed boutique dormitory with timber sleeping pods, study desk nook, personal lockers, and high-speed WiFi.',2,1,450.00,500.00,'["WiFi","Air Con","Locker","Privacy Curtain","Study Nook","Shared Bathroom","Charging Station"]','["dormitory","budget","co-ed","study friendly"]','dorm-03.jpg','available');

-- ============================================================
-- SEED DATA: Default Admin Account
-- Password: Admin@1234 (bcrypt hashed)
-- ============================================================
INSERT INTO `users` (`first_name`,`last_name`,`email`,`password`,`role`,`status`,`email_verified`) VALUES
('Hotel','Admin','admin@marveloushotel.com','$2y$12$vF/81PmrhX3Eks9/DTcTuesDkNaG9tHGisNTdKARNVn7Rn/QlNJMS',
'admin','active',1),
('Maria','Santos','staff@marveloushotel.com','$2y$12$vF/81PmrhX3Eks9/DTcTuesDkNaG9tHGisNTdKARNVn7Rn/QlNJMS',
'staff','active',1);

-- ============================================================
-- SEED DATA: Settings
-- ============================================================
INSERT INTO `settings` (`key`,`value`,`description`) VALUES
('hotel_name','Marvelous Hotel and Dormitory','Name of the hotel'),
('hotel_tagline','Your Home Away From Home','Hotel tagline'),
('hotel_address','123 Marvelous St., Quezon City, Philippines','Hotel address'),
('hotel_phone','+63 912 345 6789','Hotel phone number'),
('hotel_email','info@marveloushotel.com','Hotel contact email'),
('hotel_gcash','09123456789','GCash number for payments'),
('hotel_maya','@willygailojr','PayMaya (Maya) handle or number'),
('hotel_maya_name','WILLY GAILO','PayMaya (Maya) Registered Account Name'),
('hotel_maya_handle','@willygailojr','PayMaya (Maya) Username / Handle'),
('hotel_maya_phone','+63 *** *** 6963','PayMaya (Maya) Phone Number'),
('hotel_maya_qr','assets/images/maya_qr.jpg','PayMaya (Maya) QR Code image path'),
('hotel_bank_name','BDO Unibank','Bank name for transfers'),
('hotel_bank_account','1234 5678 9012','Bank account number'),
('check_in_time','14:00','Standard check-in time'),
('check_out_time','12:00','Standard check-out time'),
('cancellation_hours','24','Hours before check-in to allow free cancellation'),
('tax_rate','0','VAT/Tax rate percentage'),
('ai_enabled','1','Enable AI chatbot'),
('ai_provider','nvidia','AI Engine provider: nvidia or local'),
('ai_nvidia_key','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','NVIDIA NIM API Key'),
('ai_nvidia_model','nvidia/nemotron-3.5-bolt-e4b','NVIDIA NIM Model ID'),
('ai_model','local','AI model type: local or openai'),
('openai_key','','OpenAI API key (if using)'),
('max_booking_days','30','Maximum days allowed per booking'),
('site_maintenance','0','Put site in maintenance mode'),
('mail_driver','smtp','Mail transport driver (smtp or mail)'),
('smtp_host','smtp.gmail.com','SMTP Server Host (e.g. smtp.gmail.com)'),
('smtp_port','587','SMTP Server Port (587 for TLS, 465 for SSL)'),
('smtp_encryption','tls','SMTP Encryption (tls or ssl)'),
('smtp_username','','SMTP Username / Sender Gmail (e.g. hotel@gmail.com)'),
('smtp_password','','SMTP Password / Google App Password (16 characters)'),
('mail_from_email','info@marveloushotel.com','Default From Email address'),
('mail_from_name','Marvelous Hotel & Dormitory','Default From Sender Name');

-- ============================================================
-- SEED DATA: Promos
-- ============================================================
INSERT INTO `promos` (`code`,`description`,`discount_type`,`discount_value`,`min_nights`,`max_uses`,`valid_until`,`status`) VALUES
('WELCOME10','10% off for new guests','percent',10.00,1,100,'2027-12-31','active'),
('LONGSTAY20','20% off for 5+ nights stay','percent',20.00,5,50,'2027-06-30','active'),
('SUMMER500','Flat PHP 500 off any room','fixed',500.00,2,30,'2026-12-31','active');

COMMIT;
