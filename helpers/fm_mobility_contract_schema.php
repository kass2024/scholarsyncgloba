<?php
declare(strict_types=1);

/**
 * Francophonie Mobility E-Sign Contract — auto-create tables (idempotent).
 */
function fm_contract_ensure_schema(mysqli $conn): void
{
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;

    $uploadDir = dirname(__DIR__) . '/uploads/fm_contracts';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }

    $statements = [
        "CREATE TABLE IF NOT EXISTS `fm_mobility_contracts` (
          `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
          `contract_token` varchar(64) NOT NULL,
          `status` enum('draft','signed','cancelled') NOT NULL DEFAULT 'draft',
          `application_id` int(11) unsigned DEFAULT NULL,
          `external_client_name` varchar(255) DEFAULT NULL,
          `external_client_email` varchar(190) DEFAULT NULL,
          `external_client_phone` varchar(64) DEFAULT NULL,
          `external_client_dob` date DEFAULT NULL,
          `external_client_nationality` varchar(190) DEFAULT NULL,
          `external_client_passport` varchar(64) DEFAULT NULL,
          `external_client_address` text DEFAULT NULL,
          `agreement_date` date DEFAULT NULL,
          `signed_at` timestamp NULL DEFAULT NULL,
          `sent_at` timestamp NULL DEFAULT NULL,
          `pdf_path` varchar(500) DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`id`),
          UNIQUE KEY `contract_token` (`contract_token`),
          KEY `application_id` (`application_id`),
          KEY `status` (`status`),
          KEY `created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `fm_mobility_signatures` (
          `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
          `contract_id` int(11) unsigned NOT NULL,
          `client_name` varchar(255) NOT NULL,
          `client_email` varchar(190) DEFAULT NULL,
          `client_passport` varchar(64) DEFAULT NULL,
          `signed_date` varchar(32) NOT NULL,
          `signature_image` text DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`),
          KEY `contract_id` (`contract_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    foreach ($statements as $sql) {
        $conn->query($sql);
    }
}
