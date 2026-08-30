-- Repair script for MySQL error #1932 ("doesn't exist in engine")
-- Target DB: visawgnz_scholarsyncglobal
--
-- How to use (phpMyAdmin):
-- 1) Select database `visawgnz_scholarsyncglobal`
-- 2) Import this file (or paste into SQL tab) and run
--
-- NOTE:
-- - This script DROPS the affected tables then recreates them.
-- - If you have important data in these tables, restore from backup first.

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- UPAFA tables
-- ----------------------------

DROP TABLE IF EXISTS `upafa_registration_files`;
DROP TABLE IF EXISTS `upafa_registrations`;

CREATE TABLE `upafa_registrations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `academic_year` VARCHAR(32) NOT NULL,
  `last_name` VARCHAR(120) NOT NULL,
  `first_name` VARCHAR(120) NOT NULL,
  `nationality` VARCHAR(120) NOT NULL,
  `birth_place` VARCHAR(190) NOT NULL,
  `birth_date` DATE NOT NULL,
  `highest_education` VARCHAR(190) NOT NULL,
  `department` VARCHAR(190) NOT NULL,
  `school_name_address` VARCHAR(255) NOT NULL,
  `year_from` SMALLINT UNSIGNED NOT NULL,
  `year_to` SMALLINT UNSIGNED NOT NULL,
  `intended_degree` VARCHAR(190) NOT NULL,
  `field_of_study` VARCHAR(190) NOT NULL,
  `registration_fees` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `tuition_fees` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `scholarship` ENUM('Yes','No') NOT NULL DEFAULT 'No',
  `scholarship_institution` VARCHAR(255) NULL,
  `referred_by_parrot` ENUM('Yes','No') NOT NULL DEFAULT 'No',
  `ref_institution` VARCHAR(255) NULL,
  `telephone` VARCHAR(64) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `commitment_name` VARCHAR(190) NOT NULL,
  `done_at` VARCHAR(190) NOT NULL,
  `done_date` DATE NOT NULL,
  `application_status` ENUM('pending','under_review','accepted','rejected') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_upafa_registrations_email` (`email`),
  KEY `idx_upafa_registrations_status` (`application_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `upafa_registration_files` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `registration_id` INT UNSIGNED NOT NULL,
  `file_type` ENUM('passport_photo','id_document','birth_certificate','degree_transcript','other_attachment') NOT NULL DEFAULT 'other_attachment',
  `original_name` VARCHAR(255) NOT NULL,
  `stored_name` VARCHAR(255) NOT NULL,
  `mime_type` VARCHAR(120) NOT NULL,
  `size_bytes` INT UNSIGNED NOT NULL DEFAULT 0,
  `storage_path` VARCHAR(500) NOT NULL,
  `uploaded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_upafa_files_registration_id` (`registration_id`),
  KEY `idx_upafa_files_type` (`file_type`),
  CONSTRAINT `fk_upafa_files_registration`
    FOREIGN KEY (`registration_id`) REFERENCES `upafa_registrations` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Main student applications table
-- ----------------------------

DROP TABLE IF EXISTS `student_applications`;

CREATE TABLE `student_applications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- identity / routing
  `application_id` VARCHAR(32) NULL,
  `session_id` VARCHAR(255) NULL,
  `user_id` VARCHAR(64) NULL,
  `form_url` VARCHAR(255) NULL,

  -- per-app foreign refs used by code
  `university_id` INT UNSIGNED NULL,
  `region_id` INT UNSIGNED NULL,

  -- personal info
  `first_name` VARCHAR(120) NULL,
  `middle_name` VARCHAR(120) NULL,
  `last_name` VARCHAR(120) NULL,
  `email` VARCHAR(190) NULL,
  `area_code` VARCHAR(16) NULL,
  `phone_number` VARCHAR(64) NULL,
  `gender` VARCHAR(32) NULL,
  `dob` DATE NULL,
  `country_of_birth` VARCHAR(190) NULL,
  `city_of_birth` VARCHAR(190) NULL,
  `nationality` VARCHAR(190) NULL,
  `second_nationality` VARCHAR(190) NULL,

  -- passport / IDs (variants exist across forms)
  `passport` VARCHAR(190) NULL,
  `passport_number` VARCHAR(64) NULL,
  `passport_issue_date` DATE NULL,
  `passport_expiry` DATE NULL,
  `passport_expiry_date` DATE NULL,
  `student_national_id` VARCHAR(120) NULL,

  -- address
  `address_line1` VARCHAR(255) NULL,
  `address_line2` VARCHAR(255) NULL,
  `city` VARCHAR(190) NULL,
  `state_province` VARCHAR(190) NULL,
  `postal_code` VARCHAR(64) NULL,

  -- destination / finance
  `destination` TEXT NULL,
  `other_destination` VARCHAR(255) NULL,
  `destination_loan` TEXT NULL,
  `other_destination_loan` VARCHAR(255) NULL,
  `paying_tuition_fees` VARCHAR(190) NULL,
  `paying_cost_living` VARCHAR(190) NULL,
  `paying_travel_expenses` VARCHAR(190) NULL,

  -- study level + program choices (used by multiple forms)
  `intended_study_level` TEXT NULL,
  `bachelor_program` VARCHAR(255) NULL,
  `masters_program` VARCHAR(255) NULL,
  `phd_program` VARCHAR(255) NULL,
  `advanced_diploma_program` VARCHAR(255) NULL,
  `college_diploma_program` VARCHAR(255) NULL,
  `college_certificate_program` VARCHAR(255) NULL,
  `graduate_certificate_program` VARCHAR(255) NULL,
  `field_of_study` VARCHAR(255) NULL,
  `education_language` VARCHAR(190) NULL,
  `language_of_instruction` VARCHAR(190) NULL,

  -- previous education (common)
  `previous_institution_name` VARCHAR(255) NULL,
  `previous_institution_street` VARCHAR(255) NULL,
  `previous_institution_city` VARCHAR(190) NULL,
  `previous_institution_province` VARCHAR(190) NULL,
  `previous_institution_country` VARCHAR(190) NULL,
  `previous_institution_post_code` VARCHAR(64) NULL,
  `previous_study_start` DATE NULL,
  `previous_study_graduation` DATE NULL,

  -- extra education (rusvuz-specific)
  `academic_session` VARCHAR(64) NULL,
  `marital_status` VARCHAR(64) NULL,
  `college_name` VARCHAR(255) NULL,
  `college_address` VARCHAR(255) NULL,
  `college_start_date` DATE NULL,
  `college_end_date` DATE NULL,
  `studied_in_russia` TINYINT(1) NOT NULL DEFAULT 0,
  `studied_in_russia_details` TEXT NULL,
  `studied_russian_language` TINYINT(1) NOT NULL DEFAULT 0,
  `studied_russian_language_details` TEXT NULL,
  `selected_universities` JSON NULL,
  `signed_application_form` VARCHAR(500) NULL,

  -- background questions
  `additional_secondary_school` VARCHAR(32) NULL,
  `additional_secondary_details` TEXT NULL,
  `study_gap` VARCHAR(32) NULL,
  `study_gap_details` TEXT NULL,
  `post_secondary` VARCHAR(32) NULL,
  `post_secondary_details` TEXT NULL,
  `criminal_history` VARCHAR(32) NULL,
  `criminal_history_details` TEXT NULL,
  `disability` VARCHAR(32) NULL,
  `disability_details` TEXT NULL,
  `visa_rejection` VARCHAR(32) NULL,
  `visa_rejection_details` TEXT NULL,

  -- emergency + parents
  `emergency_first_name` VARCHAR(120) NULL,
  `emergency_last_name` VARCHAR(120) NULL,
  `emergency_email` VARCHAR(190) NULL,
  `emergency_area_code` VARCHAR(16) NULL,
  `emergency_phone_number` VARCHAR(64) NULL,
  `emergency_relationship` VARCHAR(120) NULL,
  `emergency_same_address` VARCHAR(32) NULL,
  `father_first_name` VARCHAR(120) NULL,
  `father_last_name` VARCHAR(120) NULL,
  `mother_first_name` VARCHAR(120) NULL,
  `mother_last_name` VARCHAR(120) NULL,

  -- agent
  `agent_first_name` VARCHAR(120) NULL,
  `agent_last_name` VARCHAR(120) NULL,
  `agent_email` VARCHAR(190) NULL,

  -- documents (paths / JSON arrays)
  `degree_transcripts` JSON NULL,
  `high_school_degree` VARCHAR(500) NULL,
  `valid_passport` VARCHAR(500) NULL,
  `cv_resume` VARCHAR(500) NULL,
  `personal_statement` VARCHAR(500) NULL,
  `recommendation_letters` VARCHAR(500) NULL,
  `english_certificate` VARCHAR(500) NULL,
  `birth_certificate` VARCHAR(500) NULL,
  `payment_proof` VARCHAR(500) NULL,

  -- misc
  `comments` LONGTEXT NULL,
  `applicant_signature` TEXT NULL,

  -- workflow flags used across UI
  `app_start` TINYINT(1) NOT NULL DEFAULT 0,
  `incomplete_app` TINYINT(1) NOT NULL DEFAULT 1,
  `submitted` TINYINT(1) NOT NULL DEFAULT 0,
  `deny` TINYINT(1) NOT NULL DEFAULT 0,
  `admit` TINYINT(1) NOT NULL DEFAULT 0,
  `visa_scheduled` TINYINT(1) NOT NULL DEFAULT 0,
  `visa_approved` TINYINT(1) NOT NULL DEFAULT 0,
  `enrolled` TINYINT(1) NOT NULL DEFAULT 0,
  `app_paid` TINYINT(1) NOT NULL DEFAULT 0,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,

  -- timestamps
  `application_date` DATE NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_student_apps_email` (`email`),
  KEY `idx_student_apps_user_id` (`user_id`),
  KEY `idx_student_apps_submitted` (`submitted`),
  KEY `idx_student_apps_created_at` (`created_at`),
  KEY `idx_student_apps_university_id` (`university_id`),
  KEY `idx_student_apps_region_id` (`region_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

