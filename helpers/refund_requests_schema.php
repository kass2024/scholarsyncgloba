<?php
declare(strict_types=1);

require_once __DIR__ . '/sql_run_file.php';

/**
 * Ensure refund_requests tables exist (idempotent).
 * DDL source of truth: sql/refund_requests.sql (auto-executed here).
 */
function pcvc_ensure_refund_requests_schema(mysqli $conn): void
{
    $sqlFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR . 'refund_requests.sql';
    pcvc_sql_run_migration_file($conn, $sqlFile);

    $tbl = 'refund_requests';
    $chk = $conn->query("SHOW TABLES LIKE '{$tbl}'");
    if (!$chk || $chk->num_rows === 0) {
        return;
    }
    $chk->free();

    $have = [];
    $cols = $conn->query('SHOW COLUMNS FROM `' . $tbl . '`');
    if ($cols) {
        while ($r = $cols->fetch_assoc()) {
            $have[strtolower((string) ($r['Field'] ?? ''))] = true;
        }
        $cols->free();
    }

    $alters = [];
    if (empty($have['student_application_id'])) {
        $alters[] = 'ADD COLUMN `student_application_id` INT UNSIGNED NULL DEFAULT NULL';
    }
    if (empty($have['student_portal_account_id'])) {
        $alters[] = 'ADD COLUMN `student_portal_account_id` INT UNSIGNED NULL DEFAULT NULL';
    }
    if (empty($have['application_id'])) {
        $alters[] = 'ADD COLUMN `application_id` VARCHAR(64) DEFAULT NULL';
    }
    if (empty($have['is_existing_student'])) {
        $alters[] = 'ADD COLUMN `is_existing_student` TINYINT(1) NOT NULL DEFAULT 0';
    }
    if (empty($have['admin_comment'])) {
        $alters[] = 'ADD COLUMN `admin_comment` TEXT NULL';
    }
    if (empty($have['internal_note'])) {
        $alters[] = 'ADD COLUMN `internal_note` TEXT NULL';
    }
    if (empty($have['submitted_by'])) {
        $alters[] = "ADD COLUMN `submitted_by` VARCHAR(16) NOT NULL DEFAULT 'public'";
    }
    if (empty($have['updated_at'])) {
        $alters[] = 'ADD COLUMN `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP';
    }

    foreach ($alters as $fragment) {
        @$conn->query("ALTER TABLE `{$tbl}` {$fragment}");
    }
}

function pcvc_refund_generate_reference(): string
{
    return 'REF' . date('Y') . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
}

function pcvc_refund_upload_dir(): string
{
    $root = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'refund_requests';
    if (!is_dir($root)) {
        @mkdir($root, 0755, true);
    }

    return $root;
}

function pcvc_refund_safe_filename(string $name): string
{
    $base = basename($name);
    $base = preg_replace('/[^a-zA-Z0-9._-]/', '_', $base) ?? 'file';

    return substr($base, 0, 180);
}
