<?php
declare(strict_types=1);

/**
 * Admin-only: backfill student portal accounts for ALL existing students.
 * Default password: ScholarSync@2026
 *
 * Visit while logged in as admin:
 *   http://localhost/scholarsyncglobal/admin/student-portal-backfill.php
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers/student_portal_accounts.php';

header('Content-Type: text/plain; charset=UTF-8');

[$created, $linked, $skipped] = pcvc_student_portal_backfill_accounts($conn);

echo "OK\n";
echo "student portal backfill completed\n";
echo "default password used for NEW accounts: " . PCVC_STUDENT_DEFAULT_PASSWORD . "\n";
echo "created: $created\n";
echo "linked(existing accounts updated to latest application): $linked\n";
echo "skipped(invalid email/prepare errors): $skipped\n";
echo "\nNext: students can login at /scholarsyncglobal/student-login.php\n";

