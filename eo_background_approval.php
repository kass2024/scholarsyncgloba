<?php
/**
 * Background worker — sends approved EO package to office email.
 * Sends application details + documents only; applicant is not notified.
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/employment_opportunities_schema.php';
require_once __DIR__ . '/helpers/employment_opportunities_notify.php';

eo_ensure_schema($conn);

$applicationId = (int) ($argv[1] ?? $_POST['application_id'] ?? 0);
if ($applicationId <= 0) {
    exit(1);
}

set_time_limit(300);
$ok = eo_send_approval_package_job($conn, $applicationId);
exit($ok ? 0 : 1);
