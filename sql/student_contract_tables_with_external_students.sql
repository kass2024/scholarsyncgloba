-- Enhanced student contract tables to support external students (not in MIS system)
-- This allows students who are not in the student_applications table to have contracts

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

-- Drop existing tables if they exist (for recreation)
DROP TABLE IF EXISTS `student_signatures_special`;
DROP TABLE IF EXISTS `student_contracts_special`;
DROP TABLE IF EXISTS `student_signatures`;
DROP TABLE IF EXISTS `student_contracts`;

-- Enhanced regular student contracts table
CREATE TABLE `student_contracts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  
  -- Contract identification
  `contract_token` VARCHAR(64) NOT NULL,
  `status` ENUM('draft','signed','cancelled') NOT NULL DEFAULT 'draft',
  
  -- Student reference (can be NULL for external students)
  `student_id` INT UNSIGNED NULL,
  
  -- External student information (filled when student_id is NULL)
  `external_student_name` VARCHAR(255) NULL,
  `external_student_email` VARCHAR(190) NULL,
  `external_student_phone` VARCHAR(64) NULL,
  `external_student_dob` DATE NULL,
  `external_student_nationality` VARCHAR(190) NULL,
  `external_student_passport` VARCHAR(64) NULL,
  
  -- Contract metadata
  `selected_package` VARCHAR(20) NULL,
  `signed_at` TIMESTAMP NULL,
  `sent_at` TIMESTAMP NULL,
  `pdf_path` VARCHAR(500) NULL,
  
  -- Timestamps
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_contract_token` (`contract_token`),
  KEY `idx_student_contracts_student_id` (`student_id`),
  KEY `idx_student_contracts_status` (`status`),
  KEY `idx_student_contracts_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Enhanced special student contracts table
CREATE TABLE `student_contracts_special` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  
  -- Contract identification
  `contract_token` VARCHAR(64) NOT NULL,
  `status` ENUM('draft','signed','cancelled') NOT NULL DEFAULT 'draft',
  
  -- Student reference (can be NULL for external students)
  `student_id` INT UNSIGNED NULL,
  
  -- External student information (filled when student_id is NULL)
  `external_student_name` VARCHAR(255) NULL,
  `external_student_email` VARCHAR(190) NULL,
  `external_student_phone` VARCHAR(64) NULL,
  `external_student_dob` DATE NULL,
  `external_student_nationality` VARCHAR(190) NULL,
  `external_student_passport` VARCHAR(64) NULL,
  
  -- Contract metadata
  `selected_package` VARCHAR(20) NULL,
  `signed_at` TIMESTAMP NULL,
  `sent_at` TIMESTAMP NULL,
  `pdf_path` VARCHAR(500) NULL,
  
  -- Timestamps
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_contract_token_special` (`contract_token`),
  KEY `idx_student_contracts_special_student_id` (`student_id`),
  KEY `idx_student_contracts_special_status` (`status`),
  KEY `idx_student_contracts_special_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Student signatures table for regular contracts
CREATE TABLE `student_signatures` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `contract_id` INT UNSIGNED NOT NULL,
  `student_name` VARCHAR(255) NOT NULL,
  `signature_image` TEXT NULL,
  `signed_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  KEY `idx_student_signatures_contract_id` (`contract_id`),
  CONSTRAINT `fk_student_signatures_contract`
    FOREIGN KEY (`contract_id`) REFERENCES `student_contracts` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Student signatures table for special contracts
CREATE TABLE `student_signatures_special` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `contract_id` INT UNSIGNED NOT NULL,
  `student_name` VARCHAR(255) NOT NULL,
  `signature_image` TEXT NULL,
  `signed_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  KEY `idx_student_signatures_special_contract_id` (`contract_id`),
  CONSTRAINT `fk_student_signatures_special_contract`
    FOREIGN KEY (`contract_id`) REFERENCES `student_contracts_special` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
