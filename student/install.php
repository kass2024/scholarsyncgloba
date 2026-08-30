<?php
declare(strict_types=1);

/**
 * One-time installer for student portal tables.
 * Visit: http://localhost/scholarsyncglobal/student/install.php
 *
 * After success, DELETE this file (recommended).
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers/student_portal_schema.php';

header('Content-Type: text/plain; charset=UTF-8');

try {
    pcvc_student_portal_ensure_schema($conn);

    $db = $conn->query('SELECT DATABASE() AS db')->fetch_assoc()['db'] ?? '';
    echo "OK: Student portal tables ensured.\n";
    echo "Database: " . $db . "\n";
    echo "Created/ensured tables:\n";
    echo "- student_portal_accounts\n";
    echo "- student_portal_uploads\n";
    echo "\nNext step: delete this file: student/install.php\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "ERROR: " . $e->getMessage() . "\n";
}

