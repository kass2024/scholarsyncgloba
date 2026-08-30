<?php
/**
 * Adds rejection_reason VARCHAR for staff notes when status is Rejected (student deny flag or job/visa rejected).
 * Idempotent; table name must be caller-whitelisted.
 */
function xander_ensure_rejection_reason_column(mysqli $conn, string $table): void
{
    static $cache = [];
    $table = preg_replace('/[^A-Za-z0-9_]/', '', $table);
    if ($table === '' || isset($cache[$table])) {
        return;
    }
    $cache[$table] = true;

    $res = @$conn->query("SHOW COLUMNS FROM `{$table}` LIKE 'rejection_reason'");
    if ($res && $res->num_rows > 0) {
        return;
    }

    $sql = "ALTER TABLE `{$table}` ADD COLUMN `rejection_reason` VARCHAR(2000) NULL DEFAULT NULL";
    if (!@$conn->query($sql)) {
        error_log('[rejection_reason_column] ' . $table . ': ' . $conn->error);
    }
}
