<?php
/**
 * Background worker — sends Employment Opportunities submission email.
 * Same CLI dispatch pattern used by Francophonie Mobility.
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

set_time_limit(180);
$ok = eo_send_new_application_email_job($conn, $applicationId);
exit($ok ? 0 : 1);
