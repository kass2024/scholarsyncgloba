<?php
/**
 * Fire-and-forget HTTP endpoint: send applicant confirmation email.
 * Called after form save so SMTP never blocks the browser JSON response.
 *
 * Auth: HMAC token derived from app secrets (no session required).
 */
declare(strict_types=1);

ignore_user_abort(true);
@set_time_limit(180);
ini_set('display_errors', '0');

// Close the HTTP connection to the caller ASAP (Apache/mod_php + FastCGI).
function eo_async_ack_and_continue(): void
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

$referenceId = trim((string) ($_GET['ref'] ?? $_POST['ref'] ?? ''));
$token = trim((string) ($_GET['t'] ?? $_POST['t'] ?? ''));

if ($referenceId === '' || !preg_match('/^EO[A-Z0-9]+$/i', $referenceId) || $token === '') {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'bad request']);
    exit;
}

$expected = eo_notify_async_token($referenceId);
if (!hash_equals($expected, $token)) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
}

eo_async_ack_and_continue();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/employment_opportunities_schema.php';

eo_ensure_schema($conn);

$stmt = $conn->prepare('SELECT * FROM employment_opportunities_applications WHERE reference_id = ? LIMIT 1');
if (!$stmt) {
    error_log('EO async notify: prepare failed');
    exit;
}
$stmt->bind_param('s', $referenceId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    error_log('EO async notify: application not found ' . $referenceId);
    exit;
}

try {
    $ok = eo_notify_applicant_received($row);
    error_log('EO async applicant notify [' . $referenceId . ']: ' . ($ok ? 'OK' : 'FAILED'));
} catch (Throwable $e) {
    error_log('EO async applicant notify exception [' . $referenceId . ']: ' . $e->getMessage());
}

exit;
