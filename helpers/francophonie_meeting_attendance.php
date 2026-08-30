<?php
declare(strict_types=1);

require_once __DIR__ . '/francophonie_meeting_invitation_schema.php';

/**
 * @return array{ok: bool, attendance_id?: int, message?: string}
 */
function fm_meeting_record_join(
    mysqli $conn,
    int $invitationId,
    string $participantType,
    string $participantName,
    ?string $participantEmail = null,
    ?int $inviteeId = null,
    ?string $sourceType = null,
    ?int $sourceId = null
): array {
    fm_meeting_ensure_schema($conn);

    $participantType = in_array($participantType, ['invitee', 'guest', 'host'], true)
        ? $participantType
        : 'guest';
    $participantName = trim($participantName);
    if ($participantName === '') {
        return ['ok' => false, 'message' => 'Participant name is required.'];
    }

    $email = $participantEmail !== null ? trim($participantEmail) : null;
    if ($email === '') {
        $email = null;
    }

    $now = date('Y-m-d H:i:s');
    $ins = $conn->prepare(
        'INSERT INTO francophonie_mobility_meeting_attendance
        (invitation_id, invitee_id, participant_type, participant_name, participant_email, source_type, source_id, joined_at)
        VALUES (?, NULLIF(?, 0), ?, ?, NULLIF(?, \'\'), NULLIF(?, \'\'), NULLIF(?, 0), ?)'
    );
    $inviteeParam = $inviteeId !== null && $inviteeId > 0 ? $inviteeId : 0;
    $sourceIdParam = $sourceId !== null && $sourceId > 0 ? $sourceId : 0;
    $sourceTypeParam = $sourceType !== null && $sourceType !== '' ? $sourceType : '';
    $emailVal = $email ?? '';
    $ins->bind_param(
        'iissssis',
        $invitationId,
        $inviteeParam,
        $participantType,
        $participantName,
        $emailVal,
        $sourceTypeParam,
        $sourceIdParam,
        $now
    );
    if (!$ins->execute()) {
        $ins->close();

        return ['ok' => false, 'message' => 'Could not record attendance.'];
    }
    $attendanceId = (int) $ins->insert_id;
    $ins->close();

    if ($inviteeParam !== null) {
        $upd = $conn->prepare(
            'UPDATE francophonie_mobility_meeting_invitees
             SET joined_at = COALESCE(joined_at, ?), join_count = join_count + 1
             WHERE id = ? AND invitation_id = ?'
        );
        $upd->bind_param('sii', $now, $inviteeParam, $invitationId);
        $upd->execute();
        $upd->close();
    }

    return ['ok' => true, 'attendance_id' => $attendanceId];
}

/**
 * @return array{ok: bool, message?: string}
 */
function fm_meeting_record_leave(mysqli $conn, int $attendanceId): array
{
    fm_meeting_ensure_schema($conn);

    if ($attendanceId <= 0) {
        return ['ok' => false, 'message' => 'Invalid attendance id.'];
    }

    $now = date('Y-m-d H:i:s');
    $upd = $conn->prepare(
        'UPDATE francophonie_mobility_meeting_attendance
         SET left_at = ?
         WHERE id = ? AND left_at IS NULL'
    );
    $upd->bind_param('si', $now, $attendanceId);
    $upd->execute();
    $upd->close();

    return ['ok' => true];
}

/**
 * @return list<array<string, mixed>>
 */
function fm_meeting_attendance_rows(mysqli $conn, int $invitationId): array
{
    fm_meeting_ensure_schema($conn);

    $rows = [];
    $st = $conn->prepare(
        'SELECT a.id, a.participant_type, a.participant_name, a.participant_email,
                a.source_type, a.source_id, a.joined_at, a.left_at,
                i.recipient_name, i.recipient_email, i.email_sent, i.join_count
         FROM francophonie_mobility_meeting_attendance a
         LEFT JOIN francophonie_mobility_meeting_invitees i ON i.id = a.invitee_id
         WHERE a.invitation_id = ?
         ORDER BY a.joined_at ASC'
    );
    $st->bind_param('i', $invitationId);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $st->close();

    return $rows;
}

/**
 * @return list<array<string, mixed>>
 */
function fm_meeting_invitee_attendance_summary(mysqli $conn, int $invitationId): array
{
    fm_meeting_ensure_schema($conn);

    $st = $conn->prepare(
        'SELECT id, source_type, source_id, recipient_name, recipient_email,
                email_sent, joined_at, join_count, sent_at
         FROM francophonie_mobility_meeting_invitees
         WHERE invitation_id = ?
         ORDER BY recipient_name ASC'
    );
    $st->bind_param('i', $invitationId);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();

    return $rows;
}

function fm_meeting_ensure_guest_join_token(mysqli $conn, int $invitationId): string
{
    fm_meeting_ensure_schema($conn);

    $st = $conn->prepare('SELECT guest_join_token FROM francophonie_mobility_meeting_invitations WHERE id = ? LIMIT 1');
    $st->bind_param('i', $invitationId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();

    $token = trim((string) ($row['guest_join_token'] ?? ''));
    if ($token !== '') {
        return $token;
    }

    $token = fm_meeting_generate_join_token();
    $upd = $conn->prepare('UPDATE francophonie_mobility_meeting_invitations SET guest_join_token = ? WHERE id = ?');
    $upd->bind_param('si', $token, $invitationId);
    $upd->execute();
    $upd->close();

    return $token;
}
