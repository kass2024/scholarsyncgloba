-- Agent Referral and Commission Agreement E-Sign tables
-- Auto-applied via helpers/agent_contract_schema.php on first request
-- Manual (cPanel phpMyAdmin): run this file OR open migrate_agent_contract.php once

CREATE TABLE IF NOT EXISTS `agent_contracts` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `contract_token` varchar(64) NOT NULL,
  `status` enum('draft','signed','cancelled') NOT NULL DEFAULT 'draft',
  `admin_id` int(11) unsigned DEFAULT NULL,
  `agent_type` enum('staff','agent','external') NOT NULL DEFAULT 'external',
  `agent_name` varchar(255) DEFAULT NULL,
  `agent_email` varchar(190) DEFAULT NULL,
  `agent_phone` varchar(64) DEFAULT NULL,
  `agent_address` varchar(500) DEFAULT NULL,
  `agent_title` varchar(190) DEFAULT NULL,
  `effective_date` date DEFAULT NULL,
  `signed_at` timestamp NULL DEFAULT NULL,
  `invite_sent_at` timestamp NULL DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `pdf_path` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `contract_token` (`contract_token`),
  KEY `admin_id` (`admin_id`),
  KEY `status` (`status`),
  KEY `agent_email` (`agent_email`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `agent_signatures` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `contract_id` int(11) unsigned NOT NULL,
  `agent_name` varchar(255) NOT NULL,
  `agent_email` varchar(190) DEFAULT NULL,
  `agent_title` varchar(190) DEFAULT NULL,
  `signed_date` varchar(32) NOT NULL,
  `signature_image` mediumtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `contract_id` (`contract_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
