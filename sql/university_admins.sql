-- University admins in charge (many-to-many)
-- Prefer: php scripts/seed_applyboard_canada_allocation.php

CREATE TABLE IF NOT EXISTS `university_admins` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `university_id` INT(11) NOT NULL,
  `admin_id` INT(11) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_university_admin` (`university_id`, `admin_id`),
  KEY `idx_ua_university` (`university_id`),
  KEY `idx_ua_admin` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
