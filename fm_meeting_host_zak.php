<?php
/**
 * Lazy host ZAK token — fetched after boot screen so host page HTML loads fast.
 */
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/env_load.php';
require_once __DIR__ . '/helpers/role.php';
require_once __DIR__ . '/helpers/zoom_meeting_sdk.php';

xander_load_env_file();

if (empty($_SESSION['id'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}
pcvc_require_staff_or_superadmin($conn, true);

if (!zoom_sdk_is_configured()) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'message' => 'Zoom SDK not configured']);
    exit;
}

$result = zoom_api_fetch_host_zak();
if (!$result['ok']) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'message' => (string) ($result['message'] ?? 'ZAK fetch failed')]);
    exit;
}

echo json_encode(['ok' => true, 'zak' => (string) $result['token']], JSON_UNESCAPED_UNICODE);
