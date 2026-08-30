<?php
declare(strict_types=1);

/**
 * Backward-compatible alias for legacy commission workflows.
 * The application now uses one isolated ScholarSync Global database.
 */
if (!isset($conn) || !($conn instanceof mysqli)) {
    require_once __DIR__ . '/db.php';
}

$conn2 = $conn;
