<?php
/**
 * Ensures university_admins (many-to-many: universities ↔ admins in charge).
 */
declare(strict_types=1);

function pcvc_ensure_university_admins_schema(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $conn->query("
        CREATE TABLE IF NOT EXISTS `university_admins` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `university_id` INT(11) NOT NULL,
            `admin_id` INT(11) NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_university_admin` (`university_id`, `admin_id`),
            KEY `idx_ua_university` (`university_id`),
            KEY `idx_ua_admin` (`admin_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}
