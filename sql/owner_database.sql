-- ScholarSync Global owner database bootstrap.
-- Create the database in the hosting panel as:
--   visawgnz_scholarsyncglobal
-- Then import this file, scholarsyncglobal_schema.sql, and
-- repair_scholarsync_missing_tables.sql.

USE `visawgnz_scholarsyncglobal`;

CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(190) NOT NULL,
  `first_name` VARCHAR(120) NOT NULL DEFAULT '',
  `last_name` VARCHAR(120) NOT NULL DEFAULT '',
  `full_name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(190) NULL,
  `phone_number` VARCHAR(64) NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` VARCHAR(32) NOT NULL DEFAULT 'staff',
  `status` VARCHAR(32) NOT NULL DEFAULT 'active',
  `position` VARCHAR(190) NULL,
  `employment_type` VARCHAR(120) NULL,
  `employment_start_date` DATE NULL,
  `national_id` VARCHAR(120) NULL,
  `date_of_birth` DATE NULL,
  `marital_status` VARCHAR(64) NULL,
  `nationality` VARCHAR(190) NULL,
  `place_of_birth` VARCHAR(190) NULL,
  `address` TEXT NULL,
  `salary_per_minute` DECIMAL(12,4) NOT NULL DEFAULT 0,
  `monthly_salary` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `salary_currency` VARCHAR(8) NOT NULL DEFAULT 'RWF',
  `allowed_break_minutes` INT NOT NULL DEFAULT 0,
  `work_days_per_week` INT NOT NULL DEFAULT 5,
  `sheet_id` VARCHAR(190) NULL,
  `sheet_link` VARCHAR(500) NULL,
  `office_id` INT NULL,
  `profile_photo` VARCHAR(500) NULL,
  `password_reset_token` VARCHAR(255) NULL,
  `password_reset_expires` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admins_username_unique` (`username`),
  KEY `admins_email_index` (`email`),
  KEY `admins_role_index` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
