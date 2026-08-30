-- South Korea Event Participation Form
-- Also auto-created in PHP via helpers/korea_event_schema.php (kep_ensure_schema)
-- on form, save, admin, dashboard, and retrieve — no manual phpMyAdmin step required.

CREATE TABLE IF NOT EXISTS `korea_event_applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(80) NOT NULL,
  `reference_id` varchar(24) NOT NULL,
  `full_name` varchar(200) NOT NULL,
  `first_name` varchar(100) NOT NULL DEFAULT '',
  `last_name` varchar(100) NOT NULL DEFAULT '',
  `date_of_birth` date DEFAULT NULL,
  `gender` varchar(20) NOT NULL DEFAULT '',
  `nationality` varchar(100) NOT NULL DEFAULT '',
  `country_of_residence` varchar(100) NOT NULL DEFAULT '',
  `passport_number` varchar(64) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone_area_code` varchar(10) DEFAULT NULL,
  `phone_number` varchar(40) NOT NULL,
  `messaging_app` enum('whatsapp','telegram') NOT NULL DEFAULT 'whatsapp',
  `occupation` varchar(150) DEFAULT NULL,
  `organization` varchar(150) DEFAULT NULL,
  `event_name` varchar(200) NOT NULL DEFAULT 'South Korea Event',
  `participation_purpose` text DEFAULT NULL,
  `passport_file` varchar(255) NOT NULL,
  `cv_file` varchar(255) NOT NULL,
  `status` enum('pending','under_review','approved','rejected') NOT NULL DEFAULT 'pending',
  `source` varchar(20) NOT NULL DEFAULT 'public',
  `created_by_admin_id` int(11) DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference_id` (`reference_id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `status` (`status`),
  KEY `email` (`email`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
