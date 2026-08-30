<?php
/**
 * send_francophonie_meeting_invitation.php — Create Zoom meeting + email invitations.
 */
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/env_load.php';
require_once __DIR__ . '/helpers/role.php';
require_once __DIR__ . '/helpers/francophonie_meeting_invitation_schema.php';
require_once __DIR__ . '/helpers/francophonie_meeting_invite_notify.php';
require_once __DIR__ . '/helpers/zoom_meeting_sdk.php';

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
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Refresh the page and try again.']);
    exit;
}

$topic = trim((string) ($_POST['topic'] ?? ''));
$agenda = trim((string) ($_POST['agenda'] ?? ''));
$startLocal = trim((string) ($_POST['start_time'] ?? ''));
$duration = max(15, min(480, (int) ($_POST['duration'] ?? 60)));
$timezone = trim((string) ($_POST['timezone'] ?? 'America/Toronto')) ?: 'America/Toronto';
$customMessage = trim((string) ($_POST['custom_message'] ?? ''));

$fmIds = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['fm_ids'] ?? [])))));
$studentIds = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['student_ids'] ?? [])))));

if ($topic === '') {
    echo json_encode(['success' => false, 'message' => 'Meeting topic is required.']);
    exit;
}

if ($startLocal === '') {
    echo json_encode(['success' => false, 'message' => 'Meeting date and time are required.']);
    exit;
}

if ($fmIds === [] && $studentIds === []) {
    echo json_encode(['success' => false, 'message' => 'Select at least one recipient.']);
    exit;
}

try {
    $dt = new DateTime($startLocal, new DateTimeZone($timezone));
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Invalid date or timezone.']);
    exit;
}

$recipients = [];

if ($fmIds !== []) {
    $placeholders = implode(',', array_fill(0, count($fmIds), '?'));
    $types = str_repeat('i', count($fmIds));
    $sql = "SELECT id, first_name, last_name, email, status FROM francophonie_mobility_applications WHERE id IN ({$placeholders})";
    $st = $conn->prepare($sql);
    $st->bind_param($types, ...$fmIds);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();

    foreach ($rows as $row) {
        $email = trim((string) ($row['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }
        $recipients[] = [
            'source_type' => 'francophonie_mobility',
            'source_id' => (int) $row['id'],
            'name' => trim((string) $row['first_name'] . ' ' . (string) $row['last_name']),
            'email' => $email,
            'meta' => (string) ($row['status'] ?? ''),
        ];
    }
}

if ($studentIds !== []) {
    $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
    $types = str_repeat('i', count($studentIds));
    $sql = "SELECT sa.id, sa.first_name, sa.last_name, sa.email,
            COALESCE(u.name, '') AS university_name
            FROM student_applications sa
            LEFT JOIN universities u ON u.id = sa.university_id
            WHERE sa.id IN ({$placeholders})";
    $st = $conn->prepare($sql);
    $st->bind_param($types, ...$studentIds);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();

    foreach ($rows as $row) {
        $email = trim((string) ($row['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }
        $recipients[] = [
            'source_type' => 'student_application',
            'source_id' => (int) $row['id'],
            'name' => trim((string) $row['first_name'] . ' ' . (string) $row['last_name']),
            'email' => $email,
            'meta' => (string) ($row['university_name'] ?? ''),
        ];
    }
}

// Deduplicate by email (keep first)
$seenEmails = [];
$uniqueRecipients = [];
foreach ($recipients as $r) {
    $key = strtolower($r['email']);
    if (isset($seenEmails[$key])) {
        continue;
    }
    $seenEmails[$key] = true;
    $uniqueRecipients[] = $r;
}

if ($uniqueRecipients === []) {
    echo json_encode(['success' => false, 'message' => 'No valid email addresses found for the selected recipients.']);
    exit;
}

if (!fm_meeting_public_url_configured()) {
    echo json_encode([
        'success' => false,
        'message' => 'Set app.baseURL=https://scholarsyncglobal.ca/ in .env so invitation emails contain a working domain link.',
    ]);
    exit;
}

$zoomResult = zoom_api_create_scheduled_meeting([
    'topic' => $topic,
    'agenda' => $agenda,
    'start_time' => $dt->format('Y-m-d H:i:s'),
    'duration' => $duration,
    'timezone' => $timezone,
    'join_before_host' => true,
    'waiting_room' => false,
]);

if (!$zoomResult['ok']) {
    echo json_encode(['success' => false, 'message' => $zoomResult['message'] ?? 'Failed to create Zoom meeting.']);
    exit;
}

$zoom = $zoomResult['meeting'] ?? [];
$meetingPayload = [
    'topic' => $topic,
    'join_url' => (string) ($zoom['join_url'] ?? ''),
    'password' => (string) ($zoom['password'] ?? ''),
    'meeting_number' => (string) ($zoom['id'] ?? ''),
    'duration' => $duration,
    'start_time_display' => $dt->format('l, F j, Y \a\t g:i A T'),
];

$adminId = (int) ($_SESSION['id'] ?? 0);
$zoomMeetingUuid = (string) ($zoom['uuid'] ?? '');
$zoomMeetingNumber = (string) ($zoom['id'] ?? '');
$joinUrl = (string) ($zoom['join_url'] ?? '');
$startUrl = (string) ($zoom['start_url'] ?? '');
$password = (string) ($zoom['password'] ?? '');
$startMysql = $dt->format('Y-m-d H:i:s');
$guestJoinToken = fm_meeting_generate_join_token();

$ins = $conn->prepare(
    'INSERT INTO francophonie_mobility_meeting_invitations
    (topic, agenda, start_time, duration_minutes, timezone, zoom_meeting_id, zoom_meeting_number,
     zoom_join_url, zoom_password, zoom_start_url, guest_join_token, recipient_count, created_by_admin_id)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$recipientCount = count($uniqueRecipients);
$ins->bind_param(
    'sssisssssssii',
    $topic,
    $agenda,
    $startMysql,
    $duration,
    $timezone,
    $zoomMeetingUuid,
    $zoomMeetingNumber,
    $joinUrl,
    $password,
    $startUrl,
    $guestJoinToken,
    $recipientCount,
    $adminId
);
$ins->execute();
$invitationId = (int) $ins->insert_id;
$ins->close();

$sent = 0;
$failed = 0;
$errors = [];

$inviteeStmt = $conn->prepare(
    'INSERT INTO francophonie_mobility_meeting_invitees
    (invitation_id, source_type, source_id, recipient_name, recipient_email, join_token, email_sent, email_error, sent_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

foreach ($uniqueRecipients as $recipient) {
    $joinToken = fm_meeting_generate_join_token();
    $webJoinUrl = fm_meeting_participant_join_url($invitationId, $joinToken);
    $recipientMeetingPayload = array_merge($meetingPayload, ['join_url' => $webJoinUrl]);

    $sendResult = fm_send_meeting_invitation_email(
        $recipient['email'],
        $recipient['name'],
        $recipientMeetingPayload,
        $customMessage
    );
    $ok = !empty($sendResult['ok']);

    $emailSent = $ok ? 1 : 0;
    $errMsg = $ok ? null : (string) ($sendResult['error'] ?? 'SMTP send failed');
    $sentAt = $ok ? date('Y-m-d H:i:s') : null;

    if ($ok) {
        $sent++;
    } else {
        $failed++;
        $errors[] = $recipient['email'] . ($errMsg !== '' ? ' (' . $errMsg . ')' : '');
    }

    $sourceType = $recipient['source_type'];
    $sourceId = $recipient['source_id'];
    $name = $recipient['name'];
    $email = $recipient['email'];

    $inviteeStmt->bind_param(
        'isisssiss',
        $invitationId,
        $sourceType,
        $sourceId,
        $name,
        $email,
        $joinToken,
        $emailSent,
        $errMsg,
        $sentAt
    );
    $inviteeStmt->execute();
}
$inviteeStmt->close();

$upd = $conn->prepare('UPDATE francophonie_mobility_meeting_invitations SET emails_sent = ?, emails_failed = ? WHERE id = ?');
$upd->bind_param('iii', $sent, $failed, $invitationId);
$upd->execute();
$upd->close();

$message = "Zoom meeting created. {$sent} invitation email(s) sent";
if ($failed > 0) {
    $message .= ", {$failed} failed";
}
$message .= '.';

$historyRow = fm_meeting_invitation_history_payload($conn, [
    'id' => $invitationId,
    'topic' => $topic,
    'start_time' => $startMysql,
    'duration_minutes' => $duration,
    'recipient_count' => $recipientCount,
    'emails_sent' => $sent,
    'emails_failed' => $failed,
    'zoom_meeting_number' => $zoomMeetingNumber,
    'zoom_password' => $password,
    'guest_join_token' => $guestJoinToken,
]);

echo json_encode([
    'success' => true,
    'message' => $message,
    'invitation_id' => $invitationId,
    'meeting' => $historyRow,
    'host_room_url' => fm_meeting_host_room_url($invitationId),
    'participant_join_url' => fm_meeting_participant_join_url($invitationId),
    'guest_join_url' => fm_meeting_guest_join_url($invitationId, $guestJoinToken),
    'emails_ok' => $failed === 0 && $sent > 0,
    'zoom' => [
        'join_url' => fm_meeting_guest_join_url($invitationId, $guestJoinToken),
        'start_url' => $startUrl,
        'meeting_number' => $zoomMeetingNumber,
        'password' => $password,
    ],
    'stats' => [
        'recipients' => $recipientCount,
        'sent' => $sent,
        'failed' => $failed,
    ],
    'failed_emails' => $errors,
]);
