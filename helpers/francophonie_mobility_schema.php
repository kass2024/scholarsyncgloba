<?php
declare(strict_types=1);

/**
 * Francophonie Mobility — auto-create tables on first request (idempotent).
 */

/** @return array<string, string> slug => label */
function fm_job_offer_choices(): array
{
    return [
        'construction_helper'              => 'Construction Helper',
        'mechanical_helper'              => 'Mechanical Helper',
        'cook_kitchen_assistant'         => 'Cook / Kitchen Assistant',
        'restaurant_staff'               => 'Restaurant Staff',
        'customer_service_representative' => 'Customer Service Representative (Customer Care)',
    ];
}

function fm_job_offer_label(string $slug): string
{
    return fm_job_offer_choices()[$slug] ?? $slug;
}

function fm_ensure_schema(mysqli $conn): void
{
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;

    $uploadDir = dirname(__DIR__) . '/uploads/francophonie_mobility';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }

    $statements = [
        "CREATE TABLE IF NOT EXISTS `francophonie_mobility_applications` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` varchar(80) NOT NULL,
          `reference_id` varchar(24) NOT NULL,
          `first_name` varchar(100) NOT NULL,
          `last_name` varchar(100) NOT NULL,
          `email` varchar(150) NOT NULL,
          `phone_area_code` varchar(10) DEFAULT NULL,
          `phone_number` varchar(20) NOT NULL,
          `date_of_birth` date DEFAULT NULL,
          `passport_number` varchar(64) DEFAULT NULL,
          `address` text DEFAULT NULL,
          `age` tinyint(3) unsigned DEFAULT NULL,
          `nationality` varchar(100) NOT NULL,
          `country_of_residence` varchar(100) NOT NULL,
          `profession` varchar(200) NOT NULL,
          `years_experience` varchar(20) DEFAULT NULL,
          `highest_degree` varchar(200) NOT NULL,
          `field_of_study` varchar(200) NOT NULL,
          `university_name` varchar(255) NOT NULL,
          `country_of_study` varchar(100) NOT NULL,
          `graduation_year` varchar(10) DEFAULT NULL,
          `other_certifications` text DEFAULT NULL,
          `french_level` enum('beginner','intermediate','advanced','fluent') NOT NULL,
          `french_tef` tinyint(1) NOT NULL DEFAULT 0,
          `french_tcf` tinyint(1) NOT NULL DEFAULT 0,
          `french_professional` enum('yes','no') NOT NULL,
          `english_level` enum('beginner','intermediate','advanced','fluent') NOT NULL,
          `english_toefl` tinyint(1) NOT NULL DEFAULT 0,
          `english_ielts` tinyint(1) NOT NULL DEFAULT 0,
          `english_professional` enum('yes','no') NOT NULL,
          `has_wes` enum('yes','no') NOT NULL,
          `job_offer` varchar(120) DEFAULT NULL,
          `cv_file` varchar(255) NOT NULL,
          `french_cert_file` varchar(255) NOT NULL,
          `english_cert_file` varchar(255) DEFAULT NULL,
          `academic_docs_file` varchar(255) NOT NULL,
          `admin_notes` text DEFAULT NULL,
          `approval_package_sent_at` datetime DEFAULT NULL,
          `status` enum('pending','under_review','approved','rejected') NOT NULL DEFAULT 'pending',
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`id`),
          UNIQUE KEY `reference_id` (`reference_id`),
          KEY `user_id` (`user_id`),
          KEY `status` (`status`),
          KEY `email` (`email`),
          KEY `created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `francophonie_mobility_status_logs` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `application_id` int(11) NOT NULL,
          `old_status` varchar(32) DEFAULT NULL,
          `new_status` varchar(32) NOT NULL,
          `admin_id` int(11) DEFAULT NULL,
          `note` text DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`),
          KEY `application_id` (`application_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    foreach ($statements as $sql) {
        if (!$conn->query($sql)) {
            error_log('fm_ensure_schema failed: ' . $conn->error);
        }
    }

    // Idempotent column updates (optional documents + multiple academic files as JSON text).
    @mysqli_query($conn, 'ALTER TABLE francophonie_mobility_applications MODIFY cv_file varchar(255) NULL DEFAULT NULL');
    @mysqli_query($conn, 'ALTER TABLE francophonie_mobility_applications MODIFY french_cert_file varchar(255) NULL DEFAULT NULL');
    @mysqli_query($conn, 'ALTER TABLE francophonie_mobility_applications MODIFY academic_docs_file TEXT NULL DEFAULT NULL');

    $colCheck = $conn->query("SHOW COLUMNS FROM francophonie_mobility_applications LIKE 'submission_email_sent_at'");
    if ($colCheck && $colCheck->num_rows === 0) {
        $conn->query('ALTER TABLE francophonie_mobility_applications ADD COLUMN submission_email_sent_at datetime NULL DEFAULT NULL');
    }
    if ($colCheck) {
        $colCheck->free();
    }

    $contractFieldAlters = [
        'date_of_birth'   => 'ADD COLUMN `date_of_birth` date NULL DEFAULT NULL AFTER `phone_number`',
        'passport_number' => 'ADD COLUMN `passport_number` varchar(64) NULL DEFAULT NULL AFTER `date_of_birth`',
        'address'         => 'ADD COLUMN `address` text NULL DEFAULT NULL AFTER `passport_number`',
    ];
    foreach ($contractFieldAlters as $colName => $alterSql) {
        $chk = $conn->query("SHOW COLUMNS FROM francophonie_mobility_applications LIKE '{$colName}'");
        if ($chk && $chk->num_rows === 0) {
            $conn->query("ALTER TABLE francophonie_mobility_applications {$alterSql}");
        }
        if ($chk) {
            $chk->free();
        }
    }

    // Candidate intro video (local + pCloud public link).
    $videoCols = [
        'video_file'           => "ADD COLUMN `video_file` varchar(255) NULL DEFAULT NULL AFTER `academic_docs_file`",
        'video_source'         => "ADD COLUMN `video_source` varchar(16) NULL DEFAULT NULL AFTER `video_file`",
        'video_pcloud_fileid'  => "ADD COLUMN `video_pcloud_fileid` varchar(64) NULL DEFAULT NULL AFTER `video_source`",
        'video_pcloud_link'    => "ADD COLUMN `video_pcloud_link` text NULL DEFAULT NULL AFTER `video_pcloud_fileid`",
        'video_public_token'   => "ADD COLUMN `video_public_token` varchar(64) NULL DEFAULT NULL AFTER `video_pcloud_link`",
        'video_public_secret'  => "ADD COLUMN `video_public_secret` varchar(64) NULL DEFAULT NULL AFTER `video_public_token`",
    ];
    foreach ($videoCols as $colName => $alterSql) {
        $chk = $conn->query("SHOW COLUMNS FROM francophonie_mobility_applications LIKE '{$colName}'");
        if ($chk && $chk->num_rows === 0) {
            $conn->query("ALTER TABLE francophonie_mobility_applications {$alterSql}");
        }
        if ($chk) {
            $chk->free();
        }
    }

    $idx = $conn->query("SHOW INDEX FROM francophonie_mobility_applications WHERE Key_name = 'video_public_token'");
    if ($idx && $idx->num_rows === 0) {
        @$conn->query('ALTER TABLE francophonie_mobility_applications ADD KEY `video_public_token` (`video_public_token`)');
    }
    if ($idx) {
        $idx->free();
    }

    // One-time admin invite link for candidates who applied without a video.
    $inviteCols = [
        'video_invite_token'      => "ADD COLUMN `video_invite_token` varchar(64) NULL DEFAULT NULL AFTER `video_public_secret`",
        'video_invite_created_at' => "ADD COLUMN `video_invite_created_at` datetime NULL DEFAULT NULL AFTER `video_invite_token`",
        'video_invite_opened_at'  => "ADD COLUMN `video_invite_opened_at` datetime NULL DEFAULT NULL AFTER `video_invite_created_at`",
        'video_invite_used_at'    => "ADD COLUMN `video_invite_used_at` datetime NULL DEFAULT NULL AFTER `video_invite_opened_at`",
    ];
    foreach ($inviteCols as $colName => $alterSql) {
        $chk = $conn->query("SHOW COLUMNS FROM francophonie_mobility_applications LIKE '{$colName}'");
        if ($chk && $chk->num_rows === 0) {
            $conn->query("ALTER TABLE francophonie_mobility_applications {$alterSql}");
        }
        if ($chk) {
            $chk->free();
        }
    }

    $idxInvite = $conn->query("SHOW INDEX FROM francophonie_mobility_applications WHERE Key_name = 'video_invite_token'");
    if ($idxInvite && $idxInvite->num_rows === 0) {
        @$conn->query('ALTER TABLE francophonie_mobility_applications ADD UNIQUE KEY `video_invite_token` (`video_invite_token`)');
    }
    if ($idxInvite) {
        $idxInvite->free();
    }

    $jobOfferCol = $conn->query("SHOW COLUMNS FROM francophonie_mobility_applications LIKE 'job_offer'");
    if ($jobOfferCol && $jobOfferCol->num_rows === 0) {
        $conn->query("ALTER TABLE francophonie_mobility_applications ADD COLUMN `job_offer` varchar(120) NULL DEFAULT NULL AFTER `has_wes`");
    }
    if ($jobOfferCol) {
        $jobOfferCol->free();
    }
}
