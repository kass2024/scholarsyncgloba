<?php
declare(strict_types=1);

/**
 * Smart Brochure Sharing — schema helper (idempotent).
 *
 * Auto-creates the two brochure tables on first request so cPanel deployments
 * don't require a manual SQL import. Also makes sure the uploads/brochures
 * directory exists and is writable.
 *
 * Safe to call on every page load — uses CREATE TABLE IF NOT EXISTS.
 */
function pcvc_marketing_brochure_ensure_schema(mysqli $conn): void
{
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;

    $sqlBrochures = "
        CREATE TABLE IF NOT EXISTS `marketing_brochures` (
            `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `region_id`        INT UNSIGNED NULL,
            `title`            VARCHAR(255) NOT NULL,
            `slug`             VARCHAR(190) NOT NULL,
            `description`      TEXT NULL,
            `pdf_filename`     VARCHAR(255) NOT NULL,
            `pdf_path`         VARCHAR(500) NOT NULL,
            `pdf_size_bytes`   INT UNSIGNED NOT NULL DEFAULT 0,
            `cover_image`      VARCHAR(500) NULL,
            `extracted_text`   LONGTEXT NULL,
            `view_count`       INT UNSIGNED NOT NULL DEFAULT 0,
            `share_count`      INT UNSIGNED NOT NULL DEFAULT 0,
            `is_active`        TINYINT(1) NOT NULL DEFAULT 1,
            `created_by`       INT UNSIGNED NULL,
            `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_brochure_slug` (`slug`),
            KEY `idx_brochure_region` (`region_id`),
            KEY `idx_brochure_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    if (!$conn->query($sqlBrochures)) {
        error_log('marketing_brochures create failed: ' . $conn->error);
    }

    // Add columns for already-existing installs (idempotent).
    $hasCol = static function (string $col) use ($conn): bool {
        $col = $conn->real_escape_string($col);
        $r = $conn->query("SHOW COLUMNS FROM `marketing_brochures` LIKE '$col'");
        $ok = $r && $r->num_rows > 0;
        if ($r) $r->free();
        return $ok;
    };
    if (!$hasCol('attach_pdf')) {
        @$conn->query("ALTER TABLE `marketing_brochures` ADD COLUMN `attach_pdf` TINYINT(1) NOT NULL DEFAULT 1 AFTER `pdf_size_bytes`");
    }
    if (!$hasCol('html_content')) {
        @$conn->query("ALTER TABLE `marketing_brochures` ADD COLUMN `html_content` LONGTEXT NULL AFTER `extracted_text`");
    }
    if (!$hasCol('extraction_status')) {
        @$conn->query("ALTER TABLE `marketing_brochures` ADD COLUMN `extraction_status` VARCHAR(32) NOT NULL DEFAULT 'pending' AFTER `html_content`");
    }
    if (!$hasCol('university_id')) {
        @$conn->query("ALTER TABLE `marketing_brochures` ADD COLUMN `university_id` INT UNSIGNED NULL DEFAULT NULL AFTER `region_id`");
        @$conn->query("ALTER TABLE `marketing_brochures` ADD INDEX `idx_brochure_university` (`university_id`)");
    }

    $sqlShares = "
        CREATE TABLE IF NOT EXISTS `marketing_brochure_shares` (
            `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `brochure_id`      INT UNSIGNED NOT NULL,
            `share_token`      VARCHAR(64) NOT NULL,
            `recipient_name`   VARCHAR(190) NULL,
            `recipient_phone`  VARCHAR(40) NULL,
            `recipient_email`  VARCHAR(190) NULL,
            `channel`          ENUM('copy','whatsapp','email','sms','other') NOT NULL DEFAULT 'copy',
            `matched_table`    VARCHAR(80) NULL,
            `matched_row_id`   INT UNSIGNED NULL,
            `is_new_contact`   TINYINT(1) NOT NULL DEFAULT 0,
            `shared_by`        INT UNSIGNED NULL,
            `notes`            VARCHAR(255) NULL,
            `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `last_opened_at`   TIMESTAMP NULL DEFAULT NULL,
            `open_count`       INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `idx_share_brochure` (`brochure_id`),
            KEY `idx_share_phone` (`recipient_phone`),
            KEY `idx_share_token` (`share_token`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    if (!$conn->query($sqlShares)) {
        error_log('marketing_brochure_shares create failed: ' . $conn->error);
    }

    // Older installs that already created the tables without a foreign key
    // are fine — we deliberately skip ADD CONSTRAINT to keep things idempotent
    // across hosts that may disallow inline FKs.

    // Region table is expected to already exist in this project, but guard for
    // brand new deployments anyway (matches the existing structure: id, name).
    $sqlRegions = "
        CREATE TABLE IF NOT EXISTS `regions` (
            `id`   INT(11) NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(100) NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    @$conn->query($sqlRegions);

    pcvc_marketing_brochure_ensure_uploads_dir();
}

/**
 * Make sure uploads/brochures exists and is writable on every host.
 */
function pcvc_marketing_brochure_ensure_uploads_dir(): string
{
    $root = dirname(__DIR__);
    $dir  = $root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'brochures';

    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    // Drop a small .htaccess so direct PDF hits still work via Apache (most
    // shared cPanel hosts), and prevent PHP execution inside the folder.
    $ht = $dir . DIRECTORY_SEPARATOR . '.htaccess';
    if (!file_exists($ht)) {
        @file_put_contents(
            $ht,
            "Options -Indexes\n" .
            "<FilesMatch \"\\.(php|phtml|phar)$\">\n" .
            "    Require all denied\n" .
            "</FilesMatch>\n"
        );
    }

    return $dir;
}
