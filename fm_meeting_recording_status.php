<?php
/**
 * fm_meeting_recording_status.php — Fresh Zoom recording readiness for inline player.
 */
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/env_load.php';
require_once __DIR__ . '/helpers/role.php';
require_once __DIR__ . '/helpers/francophonie_meeting_recordings.php';
require_once __DIR__ . '/helpers/zoom_meeting_api.php';

xander_load_env_file();
fm_meeting_ensure_schema($conn);

if (empty($_SESSION['id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}
pcvc_require_staff_or_superadmin($conn, true);

$meetingNumber = preg_replace('/\D+/', '', (string) ($_GET['meeting_number'] ?? ''));
if ($meetingNumber === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid meeting number']);
    exit;
}

$known = fm_meeting_invitations_by_zoom_number($conn);
if (!isset($known[$meetingNumber])) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Recording not linked to a Francophonie meeting']);
    exit;
}

if (!zoom_api_is_configured()) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Zoom API not configured']);
    exit;
}

$info = fm_meeting_recording_playback_info($meetingNumber);
if (empty($info['ok'])) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'ready' => false,
        'status' => (string) ($info['status'] ?? 'missing'),
        'message' => (string) ($info['message'] ?? 'Recording not found'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'success' => true,
    'ready' => !empty($info['ready']),
    'status' => (string) ($info['status'] ?? 'unknown'),
    'stream_url' => (string) ($info['stream_url'] ?? ''),
    'file_size' => (int) ($info['file_size'] ?? 0),
    'recording_type' => (string) ($info['recording_type'] ?? ''),
    'message' => !empty($info['ready'])
        ? 'Recording is ready to play.'
        : 'Zoom is still processing the MP4 file.',
], JSON_UNESCAPED_UNICODE);
