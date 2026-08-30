<?php
/**
 * Visit & study visa (form_17_applications) — same workflow labels as job applications.
 */
require_once __DIR__ . '/job_application_status.php';
require_once __DIR__ . '/rejection_reason_column.php';

function xander_ensure_form17_process_status_column(mysqli $conn): void
{
    $r = @$conn->query("SHOW COLUMNS FROM `form_17_applications` LIKE 'process_status'");
    if ($r && $r->num_rows > 0) {
        xander_ensure_rejection_reason_column($conn, 'form_17_applications');

        return;
    }
    $sql = "ALTER TABLE `form_17_applications` 
            ADD COLUMN `process_status` VARCHAR(64) NOT NULL DEFAULT 'submitted' 
            COMMENT 'Workflow stage'";
    if (!@$conn->query($sql)) {
        error_log('form_17_applications process_status migration: ' . $conn->error);
    }
    xander_ensure_rejection_reason_column($conn, 'form_17_applications');
}

/**
 * Final visa form submit sets submitted_at (draft rows stay NULL until step 2).
 */
function xander_ensure_form17_submitted_at_column(mysqli $conn): void
{
    $r = @$conn->query("SHOW COLUMNS FROM `form_17_applications` LIKE 'submitted_at'");
    if ($r && $r->num_rows > 0) {
        return;
    }
    $sql = "ALTER TABLE `form_17_applications` 
            ADD COLUMN `submitted_at` DATETIME NULL DEFAULT NULL 
            COMMENT 'Set when applicant completes step 2'";
    if (!@$conn->query($sql)) {
        error_log('form_17_applications submitted_at migration: ' . $conn->error);
    }
}
