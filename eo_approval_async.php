<?php
/**
 * Fire-and-forget: send office approval package (application details + documents).
 * Does NOT email the applicant.
 *
 * Auth: HMAC token (eo_approval_async_token).
 */
declare(strict_types=1);

ignore_user_abort(true);
@set_time_limit(300);
ini_set('display_errors', '0');

function eo_approval_async_ack(): void
{
    $body = '{"ok":true}';
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Length: ' . (string) strlen($body));
    header('Connection: close');
    header('Cache-Control: no-store');
    echo $body;
    while (ob_get_level() > 0) {
        @ob_end_flush();
    }
    @flush();
    if (function_exists('fastcgi_finish_request')) {
        @fastcgi_finish_request();
    } elseif (function_exists('litespeed_finish_request')) {
        @litespeed_finish_request();
    }
}

require_once __DIR__ . '/helpers/env_load.php';
require_once __DIR__ . '/helpers/employment_opportunities_notify.php';

xander_load_env_file();

$appId = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['id']) ? (int) $_POST['id'] : 0);
$token = trim((string) ($_GET['t'] ?? $_POST['t'] ?? ''));

if ($appId <= 0 || $token === '') {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'bad request']);
    exit;
}

$expected = eo_approval_async_token($appId);
if (!hash_equals($expected, $token)) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
}

$officeTo = eo_notify_recipient_email();
if ($officeTo === '') {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'EMPLOYMENT_OPPORTUNITIES_APPROVAL_EMAIL / NOTIFY_EMAIL not set']);
    exit;
}

eo_approval_async_ack();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/employment_opportunities_schema.php';
require_once __DIR__ . '/helpers/employment_opportunities_files.php';

eo_ensure_schema($conn);

$stmt = $conn->prepare('SELECT * FROM employment_opportunities_applications WHERE id = ? LIMIT 1');
if (!$stmt) {
    error_log('EO approval async: prepare failed');
    exit;
}
$stmt->bind_param('i', $appId);
$stmt->execute();
$app = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$app) {
    error_log('EO approval async: application not found id=' . $appId);
    exit;
}

try {
    $ok = eo_notify_office_new_application($app);
    error_log('EO approval package [' . ($app['reference_id'] ?? $appId) . '] to ' . $officeTo . ': ' . ($ok ? 'OK' : 'FAILED'));
} catch (Throwable $e) {
    error_log('EO approval package exception [' . $appId . ']: ' . $e->getMessage());
}

exit;
