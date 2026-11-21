-- Migration 0012: Add Gmail SMTP and Email Configuration Settings
-- Enables sending real transactional emails (password reset, confirmations, notifications)

INSERT IGNORE INTO `settings` (`key`, `value`, `description`, `updated_at`) VALUES
('mail_driver', 'smtp', 'Mail transport driver (smtp or mail)', NOW()),
('smtp_host', 'smtp.gmail.com', 'SMTP Server Host (e.g. smtp.gmail.com)', NOW()),
('smtp_port', '587', 'SMTP Server Port (587 for TLS, 465 for SSL)', NOW()),
('smtp_encryption', 'tls', 'SMTP Encryption (tls or ssl)', NOW()),
('smtp_username', '', 'SMTP Username / Sender Gmail (e.g. hotel@gmail.com)', NOW()),
('smtp_password', '', 'SMTP Password / Google App Password (16 characters)', NOW()),
('mail_from_email', 'info@marveloushotel.com', 'Default From Email address', NOW()),
('mail_from_name', 'Marvelous Hotel & Dormitory', 'Default From Sender Name', NOW());
