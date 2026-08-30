<?php
declare(strict_types=1);

/**
 * South Korea Event Attendance (Invitation) E-Sign Contract — auto-create tables (idempotent).
 * Tables are created automatically on first admin/contract request (cPanel-safe).
 */
function kic_contract_table_names(): array
{
    return ['korea_invitation_contracts', 'korea_invitation_signatures'];
}

function kic_contract_table_exists(mysqli $conn, string $table): bool
{
    $esc = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$esc}'");

    return $res && $res->num_rows > 0;
}

/** @return array<string, bool> */
function kic_contract_schema_status(mysqli $conn): array
{
    $status = [];
    foreach (kic_contract_table_names() as $table) {
        $status[$table] = kic_contract_table_exists($conn, $table);
    }

    return $status;
}

function kic_contract_ensure_schema(mysqli $conn): bool
{
    static $ran = false;
    if ($ran) {
        return true;
    }

    $uploadDir = dirname(__DIR__) . '/uploads/korea_invitation_contracts';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }

    $statements = [
        "CREATE TABLE IF NOT EXISTS `korea_invitation_contracts` (
          `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
          `contract_token` varchar(64) NOT NULL,
          `status` enum('draft','signed','cancelled') NOT NULL DEFAULT 'draft',
          `student_id` int(11) unsigned DEFAULT NULL,
          `external_client_name` varchar(255) DEFAULT NULL,
          `external_client_email` varchar(190) DEFAULT NULL,
          `external_client_phone` varchar(64) DEFAULT NULL,
          `external_client_passport` varchar(64) DEFAULT NULL,
          `event_name` varchar(255) DEFAULT NULL,
          `event_location_dates` varchar(500) DEFAULT NULL,
          `agreement_date` date DEFAULT NULL,
          `signed_at` timestamp NULL DEFAULT NULL,
          `sent_at` timestamp NULL DEFAULT NULL,
          `pdf_path` varchar(500) DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`id`),
          UNIQUE KEY `contract_token` (`contract_token`),
          KEY `student_id` (`student_id`),
          KEY `status` (`status`),
          KEY `created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `korea_invitation_signatures` (
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

    $ok = true;
    foreach ($statements as $sql) {
        if (!$conn->query($sql)) {
            $ok = false;
            error_log('kic_contract_ensure_schema failed: ' . $conn->error);
        }
    }

    $ran = $ok;

    return $ok;
}
