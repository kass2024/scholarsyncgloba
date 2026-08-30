<?php
declare(strict_types=1);

require_once __DIR__ . '/zoom_meeting_api.php';

function fm_meeting_gravatar_url(string $email, int $size = 200): string
{
    $email = strtolower(trim($email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return '';
    }

    return 'https://www.gravatar.com/avatar/' . md5($email) . '?s=' . $size . '&d=404';
}

function fm_meeting_uploads_avatar_url(string $publicBase, ?string $filename): string
{
    $filename = trim((string) $filename);
    if ($filename === '' || $filename === 'default_avatar.png') {
        return '';
    }

    return rtrim($publicBase, '/') . '/uploads/' . rawurlencode($filename);
}

function fm_meeting_admin_avatar_url(mysqli $conn, int $adminId, string $publicBase): string
{
    if ($adminId <= 0) {
        return '';
    }

    $st = $conn->prepare('SELECT profile_photo FROM admins WHERE id = ? LIMIT 1');
    $st->bind_param('i', $adminId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();

    return fm_meeting_uploads_avatar_url($publicBase, is_array($row) ? (string) ($row['profile_photo'] ?? '') : '');
}

function fm_meeting_zoom_profile_avatar_url(?array $profile = null): string
{
    if ($profile === null) {
        $profile = zoom_api_fetch_host_profile(false);
    }
    if (!is_array($profile)) {
        return '';
    }

    return trim((string) ($profile['pic_url'] ?? ''));
}

/**
 * @return array{self: array{name: string, avatar_url: string}, participants: list<array{name: string, avatar_url: string}>}
 */
function fm_meeting_host_avatar_branding(mysqli $conn, int $adminId, string $hostName, string $hostEmail, string $publicBase): array
{
    $adminPhoto = fm_meeting_admin_avatar_url($conn, $adminId, $publicBase);
    $zoomPhoto = fm_meeting_zoom_profile_avatar_url();
    $selfAvatar = $adminPhoto !== '' ? $adminPhoto : $zoomPhoto;
    if ($selfAvatar === '' && $hostEmail !== '') {
        $selfAvatar = fm_meeting_gravatar_url($hostEmail);
    }

    return [
        'self' => [
            'name' => $hostName,
            'avatar_url' => $selfAvatar,
        ],
        'participants' => [],
    ];
}

/**
 * @return array{self: array{name: string, avatar_url: string}, participants: list<array{name: string, avatar_url: string}>}
 */
function fm_meeting_participant_avatar_branding(
    mysqli $conn,
    int $invitationId,
    string $displayName,
    string $email,
    string $publicBase
): array {
    $selfAvatar = $email !== '' ? fm_meeting_gravatar_url($email) : '';

    $participants = [];
    if ($invitationId > 0) {
        $st = $conn->prepare(
            'SELECT recipient_name, recipient_email FROM francophonie_mobility_meeting_invitees WHERE invitation_id = ?'
        );
        $st->bind_param('i', $invitationId);
        $st->execute();
        $res = $st->get_result();
        while ($row = $res->fetch_assoc()) {
            $name = trim((string) ($row['recipient_name'] ?? ''));
            $invEmail = trim((string) ($row['recipient_email'] ?? ''));
            if ($name === '') {
                continue;
            }
            $participants[] = [
                'name' => $name,
                'avatar_url' => $invEmail !== '' ? fm_meeting_gravatar_url($invEmail) : '',
            ];
        }
        $st->close();
    }

    $hostProfile = zoom_api_fetch_host_profile(false);
    $hostName = zoom_api_host_display_name($hostProfile);
    if ($hostName === '') {
        $hostName = 'Host';
    }
    $hostAvatar = fm_meeting_zoom_profile_avatar_url($hostProfile);
    $hostEmail = is_array($hostProfile) ? trim((string) ($hostProfile['email'] ?? '')) : '';
    if ($hostAvatar === '' && $hostEmail !== '') {
        $hostAvatar = fm_meeting_gravatar_url($hostEmail);
    }

    $participants[] = [
        'name' => $hostName,
        'avatar_url' => $hostAvatar,
    ];

    return [
        'self' => [
            'name' => $displayName,
            'avatar_url' => $selfAvatar,
        ],
        'participants' => $participants,
    ];
}
