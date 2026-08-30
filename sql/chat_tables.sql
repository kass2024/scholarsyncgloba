-- ScholarSync live chat persistence (used by chat-api.php)
-- Run once against the ScholarSync Global database:
-- mysql -u <user> <database> < sql/chat_tables.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `chat_sessions` (
  `session_id` varchar(128) NOT NULL,
  `mode` varchar(16) NOT NULL DEFAULT 'ai',
  `status` varchar(32) NOT NULL DEFAULT 'open',
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`session_id`),
  KEY `idx_chat_sessions_updated` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_messages` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id` varchar(128) NOT NULL,
  `sender` varchar(16) NOT NULL,
  `message` longtext NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `delivered` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_chat_messages_session` (`session_id`),
  KEY `idx_chat_messages_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
