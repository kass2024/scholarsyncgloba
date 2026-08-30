<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/env_load.php';
require_once __DIR__ . '/helpers/francophonie_meeting_attendance.php';

xander_load_env_file();
fm_meeting_ensure_schema($conn);

$raw = file_get_contents('php://input');
$payload = is_string($raw) && $raw !== '' ? json_decode($raw, true) : $_POST;
if (!is_array($payload)) {
    $payload = [];
}

$action = trim((string) ($payload['action'] ?? 'join'));
$invitationId = (int) ($payload['invitation_id'] ?? 0);
$attendanceId = (int) ($payload['attendance_id'] ?? 0);

if ($action === 'leave') {
    if ($attendanceId <= 0) {
        echo json_encode(['ok' => false, 'message' => 'Missing attendance id.']);
        exit;
    }
    echo json_encode(fm_meeting_record_leave($conn, $attendanceId));
    exit;
}

if ($invitationId <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Missing invitation id.']);
    exit;
}

$participantType = trim((string) ($payload['participant_type'] ?? 'guest'));
$name = trim((string) ($payload['participant_name'] ?? $payload['name'] ?? ''));
$email = trim((string) ($payload['participant_email'] ?? $payload['email'] ?? ''));
$inviteeId = (int) ($payload['invitee_id'] ?? 0);
$sourceType = trim((string) ($payload['source_type'] ?? ''));
$sourceId = (int) ($payload['source_id'] ?? 0);
$joinToken = trim((string) ($payload['join_token'] ?? ''));

if ($inviteeId <= 0 && $joinToken !== '') {
    $st = $conn->prepare(
        'SELECT id, source_type, source_id, recipient_name, recipient_email
         FROM francophonie_mobility_meeting_invitees
         WHERE invitation_id = ? AND join_token = ? LIMIT 1'
    );
    $st->bind_param('is', $invitationId, $joinToken);
    $st->execute();
    $inv = $st->get_result()->fetch_assoc();
    $st->close();
    if ($inv) {
        $inviteeId = (int) $inv['id'];
        if ($name === '') {
            $name = trim((string) ($inv['recipient_name'] ?? ''));
        }
        if ($email === '') {
            $email = trim((string) ($inv['recipient_email'] ?? ''));
        }
        $sourceType = (string) ($inv['source_type'] ?? '');
        $sourceId = (int) ($inv['source_id'] ?? 0);
        $participantType = 'invitee';
    }
}

echo json_encode(fm_meeting_record_join(
    $conn,
    $invitationId,
    $participantType,
    $name,
    $email !== '' ? $email : null,
    $inviteeId > 0 ? $inviteeId : null,
    $sourceType !== '' ? $sourceType : null,
    $sourceId > 0 ? $sourceId : null
));
