<?php
/**
 * francophonie_meeting_sdk_auth.php — Meeting SDK join payload for host/participant embed.
 */
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/env_load.php';
require_once __DIR__ . '/helpers/role.php';
require_once __DIR__ . '/helpers/francophonie_meeting_invitation_schema.php';
require_once __DIR__ . '/helpers/zoom_meeting_sdk.php';

xander_load_env_file();
fm_meeting_ensure_schema($conn);

if (empty($_SESSION['id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}
pcvc_require_staff_or_superadmin($conn, true);

$invitationId = (int) ($_GET['invitation_id'] ?? $_POST['invitation_id'] ?? 0);
$role = (int) ($_GET['role'] ?? $_POST['role'] ?? 1) === 1 ? 1 : 0;

if ($invitationId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid invitation']);
    exit;
}

$st = $conn->prepare(
    'SELECT id, topic, zoom_meeting_number, zoom_password FROM francophonie_mobility_meeting_invitations WHERE id = ? LIMIT 1'
);
$st->bind_param('i', $invitationId);
$st->execute();
$row = $st->get_result()->fetch_assoc();
$st->close();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Meeting not found']);
    exit;
}

$adminName = trim((string) (($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')));
if ($adminName === '') {
    $adminName = trim((string) ($_SESSION['username'] ?? 'Host'));
}
$adminEmail = trim((string) ($_SESSION['email'] ?? ''));

$sdkResult = zoom_sdk_build_join_payload(
    (string) ($row['zoom_meeting_number'] ?? ''),
    $adminName,
    $role,
    (string) ($row['zoom_password'] ?? ''),
    $adminEmail !== '' ? $adminEmail : null,
    $role === 1
);

if (!$sdkResult['ok']) {
    echo json_encode(['success' => false, 'message' => $sdkResult['message'] ?? 'SDK auth failed']);
    exit;
}

echo json_encode([
    'success' => true,
    'topic' => (string) ($row['topic'] ?? 'Meeting'),
    'sdk' => $sdkResult['sdk'],
    'scholarsync_learning_room' => fm_meeting_learning_frontend_base() !== ''
        ? fm_meeting_learning_frontend_base() . fm_meeting_embed_room_path(
            (string) ($row['zoom_meeting_number'] ?? ''),
            $role,
            (string) ($row['zoom_password'] ?? ''),
            $adminName,
            $adminEmail !== '' ? $adminEmail : null
        )
        : null,
]);
