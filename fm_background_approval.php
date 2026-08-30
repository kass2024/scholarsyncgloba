<?php
/**
 * Background worker — sends approved package to FRANCOPHONIE_MOBILITY_APPROVAL_EMAIL.
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/francophonie_mobility_schema.php';
require_once __DIR__ . '/helpers/fm_email_worker.php';

fm_ensure_schema($conn);

$applicationId = (int) ($argv[1] ?? $_POST['application_id'] ?? 0);
if ($applicationId <= 0) {
    exit(1);
}

set_time_limit(300);
$ok = fm_send_approval_package_job($conn, $applicationId);
exit($ok ? 0 : 1);
