<?php
declare(strict_types=1);

/**
 * Employment Opportunities — auto-create tables on first request (idempotent).
 * Only touches employment_opportunities_* objects / upload folder.
 */
function eo_ensure_schema(mysqli $conn): bool
{
    static $ran = false;
    static $ok = false;
    if ($ran) {
        return $ok;
    }
    $ran = true;

    $uploadDir = dirname(__DIR__) . '/uploads/employment_opportunities';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }

    $sql = "CREATE TABLE IF NOT EXISTS `employment_opportunities_applications` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` varchar(80) NOT NULL,
      `reference_id` varchar(24) NOT NULL,
      `full_name` varchar(200) NOT NULL,
      `first_name` varchar(100) NOT NULL DEFAULT '',
      `last_name` varchar(100) NOT NULL DEFAULT '',
      `email` varchar(150) DEFAULT NULL,
      `phone_area_code` varchar(10) DEFAULT NULL,
      `phone_number` varchar(40) NOT NULL,
      `messaging_app` enum('whatsapp','telegram') NOT NULL DEFAULT 'whatsapp',
      `messaging_username` varchar(100) DEFAULT NULL,
      `passport_number` varchar(64) NOT NULL,
      `training_field` varchar(80) NOT NULL,
      `passport_file` varchar(255) NOT NULL,
      `academic_docs_file` text NOT NULL,
      `status` enum('pending','under_review','approved','rejected') NOT NULL DEFAULT 'pending',
      `admin_notes` text DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `reference_id` (`reference_id`),
      UNIQUE KEY `user_id` (`user_id`),
      KEY `status` (`status`),
      KEY `email` (`email`),
      KEY `created_at` (`created_at`),
      KEY `training_field` (`training_field`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (!$conn->query($sql)) {
        error_log('eo_ensure_schema failed: ' . $conn->error);
        $ok = false;
        return false;
    }

    // Lightweight column upgrades if an older EO table already exists (EO-only).
    eo_ensure_column($conn, 'employment_opportunities_applications', 'messaging_username', "ALTER TABLE `employment_opportunities_applications` ADD COLUMN `messaging_username` varchar(100) DEFAULT NULL AFTER `messaging_app`");
    eo_ensure_column($conn, 'employment_opportunities_applications', 'admin_notes', "ALTER TABLE `employment_opportunities_applications` ADD COLUMN `admin_notes` text DEFAULT NULL AFTER `status`");
    eo_ensure_index($conn, 'employment_opportunities_applications', 'training_field', "ALTER TABLE `employment_opportunities_applications` ADD KEY `training_field` (`training_field`)");

    $ok = true;
    return true;
}

function eo_ensure_column(mysqli $conn, string $table, string $column, string $alterSql): void
{
    $tableSafe = $conn->real_escape_string($table);
    $colSafe = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `{$tableSafe}` LIKE '{$colSafe}'");
    if ($res && $res->num_rows === 0) {
        if (!$conn->query($alterSql)) {
            error_log("eo_ensure_column {$table}.{$column} failed: " . $conn->error);
        }
    }
    if ($res) {
        $res->free();
    }
}

function eo_ensure_index(mysqli $conn, string $table, string $indexName, string $alterSql): void
{
    $tableSafe = $conn->real_escape_string($table);
    $idxSafe = $conn->real_escape_string($indexName);
    $res = $conn->query("SHOW INDEX FROM `{$tableSafe}` WHERE Key_name = '{$idxSafe}'");
    if ($res && $res->num_rows === 0) {
        if (!$conn->query($alterSql)) {
            error_log("eo_ensure_index {$table}.{$indexName} failed: " . $conn->error);
        }
    }
    if ($res) {
        $res->free();
    }
}

/** @return array<string,string> value => label */
function eo_training_fields(): array
{
    return [
        'road_transport' => '🚚 Road Transport Shop (Driver)',
        'service_hospitality' => '🏨 Service & Hospitality',
        'production_operator' => '🏭 Production Operator',
        'catering' => '🍽️ Catering Industry (Food Service)',
        'logistics' => '📦 Logistics',
        'installation' => '🔧 Installation Works',
        'tiling' => '🧱 Tiling Works',
    ];
}

function eo_training_field_label(string $key): string
{
    $map = eo_training_fields();
    return $map[$key] ?? $key;
}
