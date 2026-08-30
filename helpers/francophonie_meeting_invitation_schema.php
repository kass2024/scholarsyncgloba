<?php
declare(strict_types=1);

require_once __DIR__ . '/francophonie_mobility_schema.php';

function fm_meeting_ensure_schema(mysqli $conn): void
{
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;

    fm_ensure_schema($conn);

    $sql = "CREATE TABLE IF NOT EXISTS `francophonie_mobility_meeting_invitations` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `topic` varchar(255) NOT NULL,
      `agenda` text DEFAULT NULL,
      `start_time` datetime NOT NULL,
      `duration_minutes` smallint unsigned NOT NULL DEFAULT 60,
      `timezone` varchar(64) NOT NULL DEFAULT 'America/Toronto',
      `zoom_meeting_id` varchar(64) DEFAULT NULL,
      `zoom_meeting_number` varchar(32) DEFAULT NULL,
      `zoom_join_url` text NOT NULL,
      `zoom_password` varchar(32) DEFAULT NULL,
      `zoom_start_url` text DEFAULT NULL,
      `guest_join_token` varchar(64) DEFAULT NULL,
      `recipient_count` int unsigned NOT NULL DEFAULT 0,
      `emails_sent` int unsigned NOT NULL DEFAULT 0,
      `emails_failed` int unsigned NOT NULL DEFAULT 0,
      `created_by_admin_id` int(11) DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `start_time` (`start_time`),
      KEY `created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $conn->query($sql);

    $sql2 = "CREATE TABLE IF NOT EXISTS `francophonie_mobility_meeting_invitees` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `invitation_id` int(11) NOT NULL,
      `source_type` enum('francophonie_mobility','student_application') NOT NULL,
      `source_id` int(11) NOT NULL,
      `recipient_name` varchar(200) NOT NULL,
      `recipient_email` varchar(190) NOT NULL,
      `join_token` varchar(64) DEFAULT NULL,
      `joined_at` datetime DEFAULT NULL,
      `join_count` int unsigned NOT NULL DEFAULT 0,
      `email_sent` tinyint(1) NOT NULL DEFAULT 0,
      `email_error` varchar(255) DEFAULT NULL,
      `sent_at` datetime DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `invitation_id` (`invitation_id`),
      KEY `source_lookup` (`source_type`,`source_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $conn->query($sql2);

    $col = $conn->query("SHOW COLUMNS FROM `francophonie_mobility_meeting_invitees` LIKE 'join_token'");
    if ($col && $col->num_rows === 0) {
        $conn->query(
            "ALTER TABLE `francophonie_mobility_meeting_invitees`
             ADD COLUMN `join_token` varchar(64) DEFAULT NULL AFTER `recipient_email`,
             ADD UNIQUE KEY `join_token` (`join_token`)"
        );
    }
    if ($col) {
        $col->free();
    }

    $colGuest = $conn->query("SHOW COLUMNS FROM `francophonie_mobility_meeting_invitations` LIKE 'guest_join_token'");
    if ($colGuest && $colGuest->num_rows === 0) {
        $conn->query(
            "ALTER TABLE `francophonie_mobility_meeting_invitations`
             ADD COLUMN `guest_join_token` varchar(64) DEFAULT NULL AFTER `zoom_start_url`"
        );
    }
    if ($colGuest) {
        $colGuest->free();
    }

    foreach (['joined_at' => 'datetime DEFAULT NULL', 'join_count' => 'int unsigned NOT NULL DEFAULT 0'] as $colName => $def) {
        $chk = $conn->query("SHOW COLUMNS FROM `francophonie_mobility_meeting_invitees` LIKE '{$colName}'");
        if ($chk && $chk->num_rows === 0) {
            $after = $colName === 'joined_at' ? 'join_token' : 'joined_at';
            $conn->query("ALTER TABLE `francophonie_mobility_meeting_invitees` ADD COLUMN `{$colName}` {$def} AFTER `{$after}`");
        }
        if ($chk) {
            $chk->free();
        }
    }

    $conn->query("CREATE TABLE IF NOT EXISTS `francophonie_mobility_meeting_attendance` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `invitation_id` int(11) NOT NULL,
      `invitee_id` int(11) DEFAULT NULL,
      `participant_type` enum('invitee','guest','host') NOT NULL DEFAULT 'guest',
      `participant_name` varchar(200) NOT NULL,
      `participant_email` varchar(190) DEFAULT NULL,
      `source_type` varchar(50) DEFAULT NULL,
      `source_id` int(11) DEFAULT NULL,
      `joined_at` datetime NOT NULL,
      `left_at` datetime DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `invitation_id` (`invitation_id`),
      KEY `invitee_id` (`invitee_id`),
      KEY `joined_at` (`joined_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query(
        "UPDATE francophonie_mobility_meeting_invitees
         SET join_token = MD5(CONCAT('fm-inv-', id, '-', UNIX_TIMESTAMP(), '-', RAND()))
         WHERE join_token IS NULL OR join_token = ''"
    );

    $conn->query(
        "UPDATE francophonie_mobility_meeting_invitations
         SET guest_join_token = MD5(CONCAT('fm-guest-', id, '-', UNIX_TIMESTAMP(), '-', RAND()))
         WHERE guest_join_token IS NULL OR guest_join_token = ''"
    );
}

/**
 * @param array<string, mixed> $row DB row from francophonie_mobility_meeting_invitations
 * @return array<string, mixed>
 */
function fm_meeting_invitation_history_payload(mysqli $conn, array $row): array
{
    require_once __DIR__ . '/zoom_meeting_sdk.php';

    $hid = (int) ($row['id'] ?? 0);
    $guestTok = trim((string) ($row['guest_join_token'] ?? ''));
    if ($guestTok === '' && $hid > 0) {
        $guestTok = fm_meeting_ensure_guest_join_token($conn, $hid);
    }

    $startRaw = (string) ($row['start_time'] ?? '');
    $startTs = strtotime($startRaw);

    return [
        'id' => $hid,
        'topic' => (string) ($row['topic'] ?? ''),
        'start_time' => $startRaw,
        'start_time_display' => $startTs ? date('M j, Y g:i A', $startTs) : $startRaw,
        'duration_minutes' => (int) ($row['duration_minutes'] ?? 60),
        'recipient_count' => (int) ($row['recipient_count'] ?? 0),
        'emails_sent' => (int) ($row['emails_sent'] ?? 0),
        'emails_failed' => (int) ($row['emails_failed'] ?? 0),
        'zoom_meeting_number' => (string) ($row['zoom_meeting_number'] ?? ''),
        'zoom_password' => (string) ($row['zoom_password'] ?? ''),
        'guest_join_url' => $hid > 0 ? fm_meeting_guest_join_url($hid, $guestTok) : '',
        'host_room_url' => $hid > 0 ? fm_meeting_host_room_url($hid) : '',
    ];
}
