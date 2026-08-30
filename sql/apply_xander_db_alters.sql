-- =============================================================================
-- ScholarSync DB — schema alignment script (legacy migrations; ScholarSync Global)
-- Database: use your ScholarSync DB (e.g. visaeofi_db from .env)
--
-- Option A — Run plain ALTERs one-by-one in phpMyAdmin / mysql CLI.
--   If you get "Duplicate column name" (errno 1060), skip that line — already applied.
--
-- Option B — Idempotent block below (MySQL/MariaDB with information_schema).
-- =============================================================================

-- -----------------------------------------------------------------------------
-- A) Plain ALTER statements (same as runtime migrations in PHP)
-- -----------------------------------------------------------------------------

-- admins — admin_password_reset.php (forgot/reset password flow)
ALTER TABLE `admins` ADD COLUMN `password_reset_token` VARCHAR(64) NULL DEFAULT NULL;
ALTER TABLE `admins` ADD COLUMN `password_reset_expires` DATETIME NULL DEFAULT NULL;

-- job_applications — job_application_status.php + sql/job_applications_add_process_status.sql
ALTER TABLE `job_applications`
  ADD COLUMN `process_status` VARCHAR(64) NOT NULL DEFAULT 'submitted' COMMENT 'Workflow stage';

ALTER TABLE `job_applications` ADD COLUMN `rejection_reason` VARCHAR(2000) NULL DEFAULT NULL;

-- form_17_applications (visit & study visa) — form17_application_status.php
ALTER TABLE `form_17_applications`
  ADD COLUMN `process_status` VARCHAR(64) NOT NULL DEFAULT 'submitted' COMMENT 'Workflow stage';

ALTER TABLE `form_17_applications`
  ADD COLUMN `submitted_at` DATETIME NULL DEFAULT NULL COMMENT 'Set when applicant completes step 2';

ALTER TABLE `form_17_applications` ADD COLUMN `rejection_reason` VARCHAR(2000) NULL DEFAULT NULL;


-- =============================================================================
-- B) Idempotent version (run as one script; skips existing columns)
-- =============================================================================
/*
DELIMITER $$

DROP PROCEDURE IF EXISTS scholarsync_apply_xander_alters$$
CREATE PROCEDURE scholarsync_apply_xander_alters()
BEGIN
  DECLARE dbname VARCHAR(64);
  SET dbname = DATABASE();

  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = dbname AND TABLE_NAME = 'admins' AND COLUMN_NAME = 'password_reset_token') THEN
    ALTER TABLE `admins` ADD COLUMN `password_reset_token` VARCHAR(64) NULL DEFAULT NULL;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = dbname AND TABLE_NAME = 'admins' AND COLUMN_NAME = 'password_reset_expires') THEN
    ALTER TABLE `admins` ADD COLUMN `password_reset_expires` DATETIME NULL DEFAULT NULL;
  END IF;

  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = dbname AND TABLE_NAME = 'job_applications' AND COLUMN_NAME = 'process_status') THEN
    ALTER TABLE `job_applications` ADD COLUMN `process_status` VARCHAR(64) NOT NULL DEFAULT 'submitted' COMMENT 'Workflow stage';
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = dbname AND TABLE_NAME = 'job_applications' AND COLUMN_NAME = 'rejection_reason') THEN
    ALTER TABLE `job_applications` ADD COLUMN `rejection_reason` VARCHAR(2000) NULL DEFAULT NULL;
  END IF;

  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = dbname AND TABLE_NAME = 'form_17_applications' AND COLUMN_NAME = 'process_status') THEN
    ALTER TABLE `form_17_applications` ADD COLUMN `process_status` VARCHAR(64) NOT NULL DEFAULT 'submitted' COMMENT 'Workflow stage';
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = dbname AND TABLE_NAME = 'form_17_applications' AND COLUMN_NAME = 'submitted_at') THEN
    ALTER TABLE `form_17_applications` ADD COLUMN `submitted_at` DATETIME NULL DEFAULT NULL COMMENT 'Set when applicant completes step 2';
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = dbname AND TABLE_NAME = 'form_17_applications' AND COLUMN_NAME = 'rejection_reason') THEN
    ALTER TABLE `form_17_applications` ADD COLUMN `rejection_reason` VARCHAR(2000) NULL DEFAULT NULL;
  END IF;
END$$

DELIMITER ;

CALL scholarsync_apply_xander_alters();
DROP PROCEDURE IF EXISTS scholarsync_apply_xander_alters;
*/
