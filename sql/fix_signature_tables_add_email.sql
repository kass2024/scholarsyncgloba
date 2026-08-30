-- Fix contract signing issues by adding missing student_email column
-- This resolves the "Unknown column 'student_email'" and bind parameter mismatches

USE visaeofi_mis;
SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

-- Add student_email column to student_signatures table
ALTER TABLE `student_signatures` 
ADD COLUMN `student_email` VARCHAR(190) NULL AFTER `student_name`;

-- Add student_email column to student_signatures_special table  
ALTER TABLE `student_signatures_special`
ADD COLUMN `student_email` VARCHAR(190) NULL AFTER `student_name`;

-- Add indexes for better performance on email lookups
ALTER TABLE `student_signatures` 
ADD INDEX `idx_student_signatures_email` (`student_email`);

ALTER TABLE `student_signatures_special`
ADD INDEX `idx_student_signatures_special_email` (`student_email`);

SET FOREIGN_KEY_CHECKS = 1;

-- Verify the schema changes
DESCRIBE `student_signatures`;
DESCRIBE `student_signatures_special`;
