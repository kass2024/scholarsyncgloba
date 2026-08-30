<?php
/**
 * Job application workflow statuses (stored in job_applications.process_status)
 */
require_once __DIR__ . '/rejection_reason_column.php';

function xander_job_application_process_statuses(): array
{
    return [
        'submitted' => 'Submitted',
        'under_review' => 'Under review',
        'waiting_decision' => 'Waiting for decision',
        'final_decision' => 'Final decision available',
        'closed' => 'Closed',
        'rejected' => 'Rejected',
    ];
}

function xander_job_application_status_keys_in_order(): array
{
    return array_keys(xander_job_application_process_statuses());
}

function xander_is_valid_job_process_status(string $key): bool
{
    return array_key_exists($key, xander_job_application_process_statuses());
}

function xander_normalize_job_process_status(?string $raw): string
{
    $s = strtolower(trim((string) $raw));
    return xander_is_valid_job_process_status($s) ? $s : 'submitted';
}

/**
 * Ensures job_applications.process_status exists (idempotent).
 */
function xander_ensure_job_applications_process_status_column(mysqli $conn): void
{
    $r = @$conn->query("SHOW COLUMNS FROM `job_applications` LIKE 'process_status'");
    if ($r && $r->num_rows > 0) {
        xander_ensure_rejection_reason_column($conn, 'job_applications');

        return;
    }
    $sql = "ALTER TABLE `job_applications` 
            ADD COLUMN `process_status` VARCHAR(64) NOT NULL DEFAULT 'submitted' 
            COMMENT 'Workflow stage'";
    if (!@$conn->query($sql)) {
        error_log('job_applications process_status migration: ' . $conn->error);
    }
    xander_ensure_rejection_reason_column($conn, 'job_applications');
}
