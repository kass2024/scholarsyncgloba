-- ScholarSync Global clean-system reset.
-- Destructive: clears transactional/application data and keeps reusable catalogs.
-- Password hashes are preserved for the single retained superadmin.

USE `visawgnz_scholarsyncglobal`;
SET FOREIGN_KEY_CHECKS = 0;

DROP PROCEDURE IF EXISTS `reset_scholarsync_transactional_data`;
DELIMITER $$

CREATE PROCEDURE `reset_scholarsync_transactional_data`()
BEGIN
    DECLARE finished INT DEFAULT 0;
    DECLARE table_name VARCHAR(255);
    DECLARE reset_tables CURSOR FOR
        SELECT TABLE_NAME
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_TYPE = 'BASE TABLE'
          AND TABLE_NAME NOT IN (
              'abroad_courses',
              'ad_subtopics',
              'ad_topics',
              'campaigns',
              'contract_templates',
              'countries',
              'fee_items',
              'fee_packages',
              'full_scholarships',
              'influencers',
              'loan_providers',
              'marketing_brochures',
              'offices',
              'packages',
              'payment_packages',
              'platforms',
              'program_levels',
              'programs',
              'regions',
              'schools',
              'site_testimonials',
              'universities',
              'websites',
              'website_email_accounts',
              'youtube_advertisements',
              'admins'
          );
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET finished = 1;

    OPEN reset_tables;
    reset_loop: LOOP
        FETCH reset_tables INTO table_name;
        IF finished = 1 THEN
            LEAVE reset_loop;
        END IF;
        SET @reset_sql = CONCAT('TRUNCATE TABLE `', REPLACE(table_name, '`', '``'), '`');
        PREPARE reset_statement FROM @reset_sql;
        EXECUTE reset_statement;
        DEALLOCATE PREPARE reset_statement;
    END LOOP;
    CLOSE reset_tables;

    DELETE FROM `admins` WHERE `id` <> 1;
    UPDATE `admins`
    SET
        `username` = 'scholarsync',
        `first_name` = 'ScholarSync',
        `last_name` = 'Global',
        `full_name` = 'ScholarSync Global',
        `email` = 'infos@scholarsyncglobal.ca',
        `role` = 'superadmin'
    WHERE `id` = 1;
    ALTER TABLE `admins` AUTO_INCREMENT = 2;
END$$

DELIMITER ;
CALL `reset_scholarsync_transactional_data`();
DROP PROCEDURE IF EXISTS `reset_scholarsync_transactional_data`;
SET FOREIGN_KEY_CHECKS = 1;
