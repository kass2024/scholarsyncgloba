<?php
declare(strict_types=1);

/**
 * Run idempotent SQL migration files (CREATE IF NOT EXISTS, etc.).
 * Strips -- line comments; splits on semicolon + newline.
 */
function pcvc_sql_run_migration_file(mysqli $conn, string $absolutePath): void
{
    if (!is_readable($absolutePath)) {
        error_log('[pcvc_sql_run_migration_file] File not readable: ' . $absolutePath);

        return;
    }

    $raw = file_get_contents($absolutePath);
    if ($raw === false || trim($raw) === '') {
        return;
    }

    // Remove block comments
    $raw = preg_replace('!/\*.*?\*/!s', '', $raw) ?? $raw;

    // Remove -- line comments
    $lines = explode("\n", $raw);
    $clean = [];
    foreach ($lines as $line) {
        if (preg_match('/^\s*--/', $line)) {
            continue;
        }
        $clean[] = $line;
    }
    $sql = implode("\n", $clean);

    $statements = preg_split('/;\s*[\r\n]+/', $sql) ?: [];
    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '') {
            continue;
        }
        if (!$conn->query($stmt)) {
            error_log(
                '[pcvc_sql_run_migration_file] ' . $conn->error
                . ' (' . basename($absolutePath) . ')'
            );
        }
    }
}
