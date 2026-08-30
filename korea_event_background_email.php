<?php
/**
 * Background worker — South Korea Event Participation confirmation emails.
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/korea_event_schema.php';
require_once __DIR__ . '/helpers/korea_event_notify.php';

kep_ensure_schema($conn);

$applicationId = (int) ($argv[1] ?? $_POST['application_id'] ?? 0);
if ($applicationId <= 0) {
    exit(1);
}

set_time_limit(180);
$ok = kep_send_new_application_email_job($conn, $applicationId);
exit($ok ? 0 : 1);
