-- Migration 0014: Auth Remember-Me columns, Rate Limits, and Admin Password Reset Requests

ALTER TABLE `users` ADD COLUMN `remember_token` VARCHAR(255) DEFAULT NULL AFTER `two_fa_enabled`;
ALTER TABLE `users` ADD COLUMN `remember_expires` DATETIME DEFAULT NULL AFTER `remember_token`;

CREATE TABLE IF NOT EXISTS `rate_limits` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `key`        VARCHAR(255) NOT NULL,
  `identifier` VARCHAR(191) NOT NULL,
  `action`     VARCHAR(100) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rate_key_created` (`key`(191), `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_reset_requests` (
  `id`                    INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`               INT(11) DEFAULT NULL,
  `email`                 VARCHAR(191) NOT NULL,
  `phone`                 VARCHAR(30) DEFAULT NULL,
  `status`                ENUM('pending','completed','dismissed') NOT NULL DEFAULT 'pending',
  `new_password_assigned` VARCHAR(255) DEFAULT NULL,
  `requested_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resolved_at`           DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_prr_status` (`status`),
  KEY `idx_prr_user` (`user_id`),
  CONSTRAINT `fk_prr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
