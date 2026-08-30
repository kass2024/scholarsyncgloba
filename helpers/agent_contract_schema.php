<?php
declare(strict_types=1);

/**
 * Agent Referral & Commission Agreement — auto-create tables (idempotent).
 */
function agent_contract_table_names(): array
{
    return ['agent_contracts', 'agent_signatures'];
}

function agent_contract_table_exists(mysqli $conn, string $table): bool
{
    $esc = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$esc}'");

    return $res && $res->num_rows > 0;
}

/** @return array<string, bool> */
function agent_contract_schema_status(mysqli $conn): array
{
    $status = [];
    foreach (agent_contract_table_names() as $table) {
        $status[$table] = agent_contract_table_exists($conn, $table);
    }

    return $status;
}

function agent_contract_upload_dir(): string
{
    return dirname(__DIR__) . '/uploads/agent_contracts';
}

function agent_contract_ensure_schema(mysqli $conn): bool
{
    static $ran = false;
    if ($ran) {
        return true;
    }

    $uploadDir = agent_contract_upload_dir();
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }

    $statements = [
        "CREATE TABLE IF NOT EXISTS `agent_contracts` (
          `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
          `contract_token` varchar(64) NOT NULL,
          `status` enum('draft','signed','cancelled') NOT NULL DEFAULT 'draft',
          `admin_id` int(11) unsigned DEFAULT NULL,
          `agent_type` enum('staff','agent','external') NOT NULL DEFAULT 'external',
          `agent_name` varchar(255) DEFAULT NULL,
          `agent_email` varchar(190) DEFAULT NULL,
          `agent_phone` varchar(64) DEFAULT NULL,
          `agent_address` varchar(500) DEFAULT NULL,
          `agent_title` varchar(190) DEFAULT NULL,
          `effective_date` date DEFAULT NULL,
          `signed_at` timestamp NULL DEFAULT NULL,
          `invite_sent_at` timestamp NULL DEFAULT NULL,
          `sent_at` timestamp NULL DEFAULT NULL,
          `pdf_path` varchar(500) DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`id`),
          UNIQUE KEY `contract_token` (`contract_token`),
          KEY `admin_id` (`admin_id`),
          KEY `status` (`status`),
          KEY `agent_email` (`agent_email`),
          KEY `created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `agent_signatures` (
          `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
          `contract_id` int(11) unsigned NOT NULL,
          `agent_name` varchar(255) NOT NULL,
          `agent_email` varchar(190) DEFAULT NULL,
          `agent_title` varchar(190) DEFAULT NULL,
          `signed_date` varchar(32) NOT NULL,
          `signature_image` mediumtext DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`),
          KEY `contract_id` (`contract_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    $ok = true;
    foreach ($statements as $sql) {
        if (!$conn->query($sql)) {
            $ok = false;
            error_log('agent_contract_ensure_schema failed: ' . $conn->error);
        }
    }

    $ran = $ok;

    return $ok;
}
