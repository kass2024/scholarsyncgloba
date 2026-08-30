<?php
declare(strict_types=1);

/**
 * South Korea Event Participation — auto-create tables on first request (idempotent).
 * Safe on every page load, including cPanel / production.
 */
function kep_ensure_schema(mysqli $conn): bool
{
    static $ran = false;
    static $ok = false;
    if ($ran) {
        return $ok;
    }
    $ran = true;

    $uploadDir = dirname(__DIR__) . '/uploads/korea_event';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }

    $sql = "CREATE TABLE IF NOT EXISTS `korea_event_applications` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` varchar(80) NOT NULL,
      `reference_id` varchar(24) NOT NULL,
      `full_name` varchar(200) NOT NULL,
      `first_name` varchar(100) NOT NULL DEFAULT '',
      `last_name` varchar(100) NOT NULL DEFAULT '',
      `date_of_birth` date DEFAULT NULL,
      `gender` varchar(20) NOT NULL DEFAULT '',
      `nationality` varchar(100) NOT NULL DEFAULT '',
      `country_of_residence` varchar(100) NOT NULL DEFAULT '',
      `passport_number` varchar(64) NOT NULL,
      `email` varchar(150) DEFAULT NULL,
      `phone_area_code` varchar(10) DEFAULT NULL,
      `phone_number` varchar(40) NOT NULL,
      `messaging_app` enum('whatsapp','telegram') NOT NULL DEFAULT 'whatsapp',
      `occupation` varchar(150) DEFAULT NULL,
      `organization` varchar(150) DEFAULT NULL,
      `event_name` varchar(200) NOT NULL DEFAULT 'South Korea Event',
      `participation_purpose` text DEFAULT NULL,
      `passport_file` varchar(255) NOT NULL,
      `cv_file` varchar(255) NOT NULL,
      `status` enum('pending','under_review','approved','rejected') NOT NULL DEFAULT 'pending',
      `source` varchar(20) NOT NULL DEFAULT 'public',
      `created_by_admin_id` int(11) DEFAULT NULL,
      `admin_notes` text DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `reference_id` (`reference_id`),
      UNIQUE KEY `user_id` (`user_id`),
      KEY `status` (`status`),
      KEY `email` (`email`),
      KEY `created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    try {
        if (!$conn->query($sql)) {
            error_log('kep_ensure_schema failed: ' . $conn->error);
            $ok = false;
            return false;
        }

        kep_ensure_column($conn, 'korea_event_applications', 'date_of_birth', "ALTER TABLE `korea_event_applications` ADD COLUMN `date_of_birth` date DEFAULT NULL AFTER `last_name`");
        kep_ensure_column($conn, 'korea_event_applications', 'gender', "ALTER TABLE `korea_event_applications` ADD COLUMN `gender` varchar(20) NOT NULL DEFAULT '' AFTER `date_of_birth`");
        kep_ensure_column($conn, 'korea_event_applications', 'nationality', "ALTER TABLE `korea_event_applications` ADD COLUMN `nationality` varchar(100) NOT NULL DEFAULT '' AFTER `gender`");
        kep_ensure_column($conn, 'korea_event_applications', 'country_of_residence', "ALTER TABLE `korea_event_applications` ADD COLUMN `country_of_residence` varchar(100) NOT NULL DEFAULT '' AFTER `nationality`");
        kep_ensure_column($conn, 'korea_event_applications', 'occupation', "ALTER TABLE `korea_event_applications` ADD COLUMN `occupation` varchar(150) DEFAULT NULL AFTER `messaging_app`");
        kep_ensure_column($conn, 'korea_event_applications', 'organization', "ALTER TABLE `korea_event_applications` ADD COLUMN `organization` varchar(150) DEFAULT NULL AFTER `occupation`");
        kep_ensure_column($conn, 'korea_event_applications', 'event_name', "ALTER TABLE `korea_event_applications` ADD COLUMN `event_name` varchar(200) NOT NULL DEFAULT 'South Korea Event' AFTER `organization`");
        kep_ensure_column($conn, 'korea_event_applications', 'participation_purpose', "ALTER TABLE `korea_event_applications` ADD COLUMN `participation_purpose` text DEFAULT NULL AFTER `event_name`");
        kep_ensure_column($conn, 'korea_event_applications', 'cv_file', "ALTER TABLE `korea_event_applications` ADD COLUMN `cv_file` varchar(255) NOT NULL DEFAULT '' AFTER `passport_file`");
        kep_ensure_column($conn, 'korea_event_applications', 'admin_notes', "ALTER TABLE `korea_event_applications` ADD COLUMN `admin_notes` text DEFAULT NULL AFTER `status`");
        kep_ensure_column($conn, 'korea_event_applications', 'source', "ALTER TABLE `korea_event_applications` ADD COLUMN `source` varchar(20) NOT NULL DEFAULT 'public' AFTER `status`");
        kep_ensure_column($conn, 'korea_event_applications', 'created_by_admin_id', "ALTER TABLE `korea_event_applications` ADD COLUMN `created_by_admin_id` int(11) DEFAULT NULL AFTER `source`");
    } catch (Throwable $e) {
        error_log('kep_ensure_schema exception: ' . $e->getMessage());
        $ok = false;
        return false;
    }

    $ok = true;
    return true;
}

function kep_ensure_column(mysqli $conn, string $table, string $column, string $alterSql): void
{
    $tableSafe = $conn->real_escape_string($table);
    $colSafe = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `{$tableSafe}` LIKE '{$colSafe}'");
    if ($res && $res->num_rows === 0) {
        if (!$conn->query($alterSql)) {
            error_log("kep_ensure_column {$table}.{$column} failed: " . $conn->error);
        }
    }
    if ($res) {
        $res->free();
    }
}

/** @return array<string,string> */
function kep_gender_options(): array
{
    return [
        'male' => 'Male',
        'female' => 'Female',
        'other' => 'Other / Prefer not to say',
    ];
}

function kep_gender_label(string $key): string
{
    $map = kep_gender_options();
    return $map[$key] ?? ($key !== '' ? $key : '—');
}
