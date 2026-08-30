<?php
/**
 * fm_meeting_recording_stream.php — Inline MP4 stream for Francophonie meeting recordings.
 */
declare(strict_types=1);

session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/env_load.php';
require_once __DIR__ . '/helpers/role.php';
require_once __DIR__ . '/helpers/francophonie_meeting_recordings.php';
require_once __DIR__ . '/helpers/zoom_meeting_api.php';

xander_load_env_file();
fm_meeting_ensure_schema($conn);

if (empty($_SESSION['id'])) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Access denied';
    exit;
}
pcvc_require_staff_or_superadmin($conn);

$meetingNumber = preg_replace('/\D+/', '', (string) ($_GET['meeting_number'] ?? ''));
if ($meetingNumber === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Invalid meeting number';
    exit;
}

$known = fm_meeting_invitations_by_zoom_number($conn);
if (!isset($known[$meetingNumber])) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Recording not linked to a Francophonie meeting';
    exit;
}

if (!zoom_api_is_configured()) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Zoom API not configured';
    exit;
}

$mp4 = fm_meeting_fetch_meeting_mp4($meetingNumber);
if ($mp4 === null || ($mp4['download_url'] ?? '') === '') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'No MP4 recording found';
    exit;
}

if (($mp4['status'] ?? '') !== 'completed') {
    http_response_code(409);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Recording is still processing on Zoom. Try again later.';
    exit;
}

$tokenResult = zoom_api_get_access_token();
if (!$tokenResult['ok']) {
    http_response_code(502);
    header('Content-Type: text/plain; charset=utf-8');
    echo (string) ($tokenResult['message'] ?? 'Zoom token unavailable');
    exit;
}

if (!headers_sent()) {
    header('Content-Type: video/mp4');
    header('Accept-Ranges: bytes');
    header('Cache-Control: private, no-store');
    if (isset($_GET['download']) && (string) $_GET['download'] === '1') {
        $safeName = 'meeting-' . $meetingNumber . '-recording.mp4';
        header('Content-Disposition: attachment; filename="' . $safeName . '"');
    }
}

fm_meeting_proxy_zoom_recording_download((string) $mp4['download_url'], (string) $tokenResult['token']);
