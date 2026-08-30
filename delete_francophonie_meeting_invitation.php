<?php
/**
 * delete_francophonie_meeting_invitation.php — Remove invitation record (+ optional Zoom meeting).
 */
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/env_load.php';
require_once __DIR__ . '/helpers/role.php';
require_once __DIR__ . '/helpers/francophonie_meeting_invitation_schema.php';
require_once __DIR__ . '/helpers/zoom_meeting_api.php';

xander_load_env_file();
fm_meeting_ensure_schema($conn);

if (empty($_SESSION['id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}
pcvc_require_staff_or_superadmin($conn, true);

if (empty($_POST['csrf_token']) || !hash_equals((string) ($_SESSION['csrf_token'] ?? ''), (string) $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

$id = (int) ($_POST['invitation_id'] ?? 0);
$deleteZoom = !empty($_POST['delete_zoom']);

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid invitation']);
    exit;
}

$st = $conn->prepare(
    'SELECT id, topic, zoom_meeting_number FROM francophonie_mobility_meeting_invitations WHERE id = ? LIMIT 1'
);
$st->bind_param('i', $id);
$st->execute();
$row = $st->get_result()->fetch_assoc();
$st->close();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Meeting not found']);
    exit;
}

$zoomNote = '';
if ($deleteZoom && trim((string) ($row['zoom_meeting_number'] ?? '')) !== '') {
    $zoomDel = zoom_api_delete_meeting((string) $row['zoom_meeting_number']);
    if (!$zoomDel['ok']) {
        echo json_encode([
            'success' => false,
            'message' => 'Could not delete Zoom meeting: ' . ($zoomDel['message'] ?? 'Zoom API error'),
        ]);
        exit;
    }
    $zoomNote = ' Zoom meeting removed.';
}

$delInvitees = $conn->prepare('DELETE FROM francophonie_mobility_meeting_invitees WHERE invitation_id = ?');
$delInvitees->bind_param('i', $id);
$delInvitees->execute();
$delInvitees->close();

$del = $conn->prepare('DELETE FROM francophonie_mobility_meeting_invitations WHERE id = ?');
$del->bind_param('i', $id);
$del->execute();
$del->close();

echo json_encode([
    'success' => true,
    'message' => 'Meeting invitation deleted.' . $zoomNote,
]);
