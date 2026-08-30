<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/env_load.php';
require_once __DIR__ . '/helpers/role.php';
require_once __DIR__ . '/helpers/francophonie_meeting_attendance.php';
require_once __DIR__ . '/helpers/zoom_meeting_sdk.php';

xander_load_env_file();
fm_meeting_ensure_schema($conn);

if (empty($_SESSION['id'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Access denied']);
    exit;
}
pcvc_require_staff_or_superadmin($conn, true);

$invitationId = (int) ($_GET['invitation_id'] ?? 0);
if ($invitationId <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Missing invitation_id']);
    exit;
}

$st = $conn->prepare('SELECT id, topic, start_time, guest_join_token FROM francophonie_mobility_meeting_invitations WHERE id = ? LIMIT 1');
$st->bind_param('i', $invitationId);
$st->execute();
$meeting = $st->get_result()->fetch_assoc();
$st->close();

if (!$meeting) {
    echo json_encode(['ok' => false, 'message' => 'Meeting not found']);
    exit;
}

$guestToken = fm_meeting_ensure_guest_join_token($conn, $invitationId);

echo json_encode([
    'ok' => true,
    'meeting' => [
        'id' => (int) $meeting['id'],
        'topic' => (string) ($meeting['topic'] ?? ''),
        'start_time' => (string) ($meeting['start_time'] ?? ''),
        'guest_join_url' => fm_meeting_guest_join_url($invitationId, $guestToken),
    ],
    'invitees' => fm_meeting_invitee_attendance_summary($conn, $invitationId),
    'attendance_log' => fm_meeting_attendance_rows($conn, $invitationId),
]);
