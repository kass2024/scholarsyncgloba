-- South Korea Event Attendance (Invitation) E-Sign Contract tables
-- Auto-applied via helpers/korea_invitation_contract_schema.php on first request
-- Manual (cPanel phpMyAdmin): run this file OR open migrate_korea_invitation_contract.php once

CREATE TABLE IF NOT EXISTS `korea_invitation_contracts` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `contract_token` varchar(64) NOT NULL,
  `status` enum('draft','signed','cancelled') NOT NULL DEFAULT 'draft',
  `student_id` int(11) unsigned DEFAULT NULL,
  `external_client_name` varchar(255) DEFAULT NULL,
  `external_client_email` varchar(190) DEFAULT NULL,
  `external_client_phone` varchar(64) DEFAULT NULL,
  `external_client_passport` varchar(64) DEFAULT NULL,
  `event_name` varchar(255) DEFAULT NULL,
  `event_location_dates` varchar(500) DEFAULT NULL,
  `agreement_date` date DEFAULT NULL,
  `signed_at` timestamp NULL DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `pdf_path` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `contract_token` (`contract_token`),
  KEY `student_id` (`student_id`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `korea_invitation_signatures` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `contract_id` int(11) unsigned NOT NULL,
  `client_name` varchar(255) NOT NULL,
  `client_email` varchar(190) DEFAULT NULL,
  `client_passport` varchar(64) DEFAULT NULL,
  `signed_date` varchar(32) NOT NULL,
  `signature_image` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `contract_id` (`contract_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
