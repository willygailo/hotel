-- ============================================================
-- MIGRATION: 0001_contact_messages
-- Tumanggap ng mensahe mula sa contact form (contact.php).
-- ============================================================
CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id`          INT(11) NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100) NOT NULL,
  `email`       VARCHAR(191) NOT NULL,
  `message`     TEXT NOT NULL,
  `ip_address`  VARCHAR(45) DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
