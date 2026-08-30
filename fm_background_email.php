<?php
/**
 * Background email worker — CLI or fire-and-forget HTTP (sendBeacon).
 * Uses SMTP_* from project .env via helpers/mail_smtp.php.
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/francophonie_mobility_schema.php';
require_once __DIR__ . '/helpers/fm_email_worker.php';

fm_ensure_schema($conn);

$applicationId = 0;

if (PHP_SAPI === 'cli') {
    $applicationId = (int) ($argv[1] ?? 0);
} else {
    header('Content-Type: application/json; charset=utf-8');
    ignore_user_abort(true);
    set_time_limit(120);

    $applicationId = (int) ($_POST['application_id'] ?? $_GET['application_id'] ?? 0);
    $token = trim((string) ($_POST['token'] ?? $_GET['token'] ?? ''));

    if ($applicationId <= 0 || $token === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }

    $st = $conn->prepare('SELECT reference_id FROM francophonie_mobility_applications WHERE id = ? LIMIT 1');
    $st->bind_param('i', $applicationId);
    $st->execute();
    $refRow = $st->get_result()->fetch_assoc();
    $st->close();

    if (!$refRow || !fm_verify_email_token($applicationId, (string) $refRow['reference_id'], $token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden']);
        exit;
    }

    if (function_exists('fastcgi_finish_request')) {
        echo json_encode(['success' => true, 'message' => 'queued']);
        fastcgi_finish_request();
    }
}

if ($applicationId <= 0) {
    if (PHP_SAPI !== 'cli') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid id']);
    }
    exit(1);
}

$ok = fm_send_new_application_emails($conn, $applicationId);

if (PHP_SAPI === 'cli') {
    exit($ok ? 0 : 1);
}

echo json_encode(['success' => $ok]);
