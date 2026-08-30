<?php
/**
 * Zoom Meeting SDK signatures (ZOOM_EMBED_CLIENT_ID / ZOOM_EMBED_CLIENT_SECRET).
 * Same JWT logic as ScholarSync Learning ZoomMeetingSdkService.
 */
declare(strict_types=1);

require_once __DIR__ . '/env_load.php';
require_once __DIR__ . '/zoom_meeting_api.php';

function zoom_sdk_is_configured(): bool
{
    xander_load_env_file();

    return trim(xander_env_get('ZOOM_EMBED_CLIENT_ID')) !== ''
        && trim(xander_env_get('ZOOM_EMBED_CLIENT_SECRET')) !== '';
}

function zoom_sdk_base64_url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function zoom_sdk_generate_signature(string $meetingNumber, int $role): string
{
    $sdkKey = trim(xander_env_get('ZOOM_EMBED_CLIENT_ID'));
    $sdkSecret = trim(xander_env_get('ZOOM_EMBED_CLIENT_SECRET'));
    $meetingNumber = preg_replace('/\D+/', '', $meetingNumber) ?: $meetingNumber;

    $iat = time() - 30;
    $exp = $iat + 7200;

    $header = zoom_sdk_base64_url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
    $payload = zoom_sdk_base64_url_encode(json_encode([
        'appKey' => $sdkKey,
        'sdkKey' => $sdkKey,
        'mn' => $meetingNumber,
        'role' => $role,
        'iat' => $iat,
        'exp' => $exp,
        'tokenExp' => $exp,
    ], JSON_THROW_ON_ERROR));

    $hash = hash_hmac('sha256', $header . '.' . $payload, $sdkSecret, true);

    return $header . '.' . $payload . '.' . zoom_sdk_base64_url_encode($hash);
}

/**
 * @return array{ok: bool, message?: string, token?: string}
 */
function zoom_api_fetch_host_zak(): array
{
    $host = zoom_api_host_user_id();
    $result = zoom_api_request('GET', '/users/' . rawurlencode($host) . '/token?type=zak');
    if (!$result['ok']) {
        return $result;
    }

    $token = trim((string) ($result['data']['token'] ?? ''));
    if ($token === '') {
        return ['ok' => false, 'message' => 'Zoom host ZAK token was empty.'];
    }

    return ['ok' => true, 'token' => $token];
}

/**
 * @return array{ok: bool, message?: string, sdk?: array<string, mixed>}
 */
function zoom_sdk_build_join_payload(
    string $meetingNumber,
    string $userName,
    int $role = 0,
    ?string $password = null,
    ?string $userEmail = null,
    bool $withHostZak = false
): array {
    if (!zoom_sdk_is_configured()) {
        return [
            'ok' => false,
            'message' => 'Zoom Meeting SDK is not configured. Set ZOOM_EMBED_CLIENT_ID and ZOOM_EMBED_CLIENT_SECRET in .env.',
        ];
    }

    $meetingNumber = preg_replace('/\D+/', '', $meetingNumber) ?: $meetingNumber;
    if ($meetingNumber === '') {
        return ['ok' => false, 'message' => 'Meeting number is required.'];
    }

    $userName = trim($userName) !== '' ? trim($userName) : ($role === 1 ? 'Host' : 'Guest');
    $zak = null;
    if ($withHostZak && $role === 1) {
        $zakResult = zoom_api_fetch_host_zak();
        if ($zakResult['ok']) {
            $zak = $zakResult['token'];
        }
    }

    $sdkKey = trim(xander_env_get('ZOOM_EMBED_CLIENT_ID'));
    $sdk = [
        'signature' => zoom_sdk_generate_signature($meetingNumber, $role),
        'sdk_key' => $sdkKey,
        'meeting_number' => $meetingNumber,
        'password' => (string) ($password ?? ''),
        'user_name' => $userName,
        'role' => $role,
        'zak' => $zak,
    ];

    if ($userEmail !== null && trim($userEmail) !== '') {
        $sdk['user_email'] = trim($userEmail);
    }

    return ['ok' => true, 'sdk' => $sdk];
}

/**
 * Build in-browser embed room URL (ScholarSync Learning frontend) when configured.
 */
function fm_meeting_learning_frontend_base(): string
{
    xander_load_env_file();
    foreach (['SCHOLARSYNC_LEARNING_FRONTEND_URL', 'ELEARNING_FRONTEND_URL', 'FRONTEND_URL'] as $key) {
        $url = rtrim(trim(xander_env_get($key)), '/');
        if ($url !== '' && preg_match('#^https?://#i', $url)) {
            return $url;
        }
    }

    return '';
}

function fm_meeting_embed_room_path(
    string $meetingNumber,
    int $role = 1,
    ?string $password = null,
    ?string $userName = null,
    ?string $userEmail = null
): string {
    $params = [
        'meeting_number' => preg_replace('/\D+/', '', $meetingNumber),
        'role' => (string) $role,
    ];
    if ($password !== null && $password !== '') {
        $params['password'] = $password;
    }
    if ($userName !== null && trim($userName) !== '') {
        $params['user_name'] = trim($userName);
    }
    if ($userEmail !== null && trim($userEmail) !== '') {
        $params['user_email'] = trim($userEmail);
    }

    return '/meeting/room?' . http_build_query($params);
}

function fm_meeting_host_room_path(int $invitationId): string
{
    return 'francophonie-meeting-host.php?invitation_id=' . $invitationId;
}

/** Full https URL for host room (admin + emails). */
function fm_meeting_host_room_url(int $invitationId): string
{
    return fm_meeting_absolute_url(fm_meeting_host_room_path($invitationId));
}

function fm_meeting_generate_join_token(): string
{
    return bin2hex(random_bytes(16));
}

/** Ensure invitation links always include scheme + domain (never relative). */
function fm_meeting_normalize_public_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $url)) {
        return $url;
    }

    return fm_meeting_absolute_url($url);
}

function fm_meeting_absolute_url(string $relativePath): string
{
    $base = rtrim(fm_zoom_public_base_url(), '/');
    $path = ltrim(str_replace('\\', '/', $relativePath), '/');

    return $base . '/' . $path;
}

function fm_meeting_participant_join_url(int $invitationId, ?string $joinToken = null): string
{
    return fm_meeting_normalize_public_url(fm_meeting_participant_join_path($invitationId, $joinToken));
}

function fm_meeting_participant_join_path(int $invitationId, ?string $joinToken = null): string
{
    $path = 'francophonie-meeting-join.php?invitation_id=' . $invitationId;
    if ($joinToken !== null && $joinToken !== '') {
        $path .= '&token=' . rawurlencode($joinToken);
    }

    return $path;
}

function fm_meeting_guest_join_path(int $invitationId, string $guestToken): string
{
    return 'francophonie-meeting-join.php?invitation_id=' . $invitationId
        . '&guest=1&guest_token=' . rawurlencode($guestToken);
}

function fm_meeting_guest_join_url(int $invitationId, string $guestToken): string
{
    return fm_meeting_normalize_public_url(fm_meeting_guest_join_path($invitationId, $guestToken));
}

function fm_zoom_public_base_url(): string
{
    if (!function_exists('pcvc_public_base_url')) {
        require_once __DIR__ . '/../includes/company_branding.php';
    }

    return pcvc_public_base_url();
}

/**
 * Current browser request origin (MIS host). Use for SDK assets + meeting leave URLs.
 * app.baseURL may point at the marketing site — never use that for in-page Zoom assets.
 */
function fm_zoom_request_base_url(): string
{
    $scheme = 'http';
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $scheme = 'https';
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $forwarded = strtolower(trim((string) strtok((string) $_SERVER['HTTP_X_FORWARDED_PROTO'], ',')));
        if ($forwarded === 'https') {
            $scheme = 'https';
        }
    }

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');

    return rtrim($scheme . '://' . $host . $dir, '/');
}

function fm_zoom_asset_base_url(): string
{
    return fm_zoom_request_base_url();
}

/** True when invitation links will include a usable public domain. */
function fm_meeting_public_url_configured(): bool
{
    $base = fm_zoom_public_base_url();
    if ($base === '' || !preg_match('#^https?://#i', $base)) {
        return false;
    }
    if (stripos($base, 'localhost') !== false || stripos($base, '127.0.0.1') !== false) {
        return true;
    }

    return (bool) preg_match('#^https://#i', $base);
}

function fm_zoom_meeting_js_file(): string
{
    $manifestPath = dirname(__DIR__) . '/assets/zoom-meetingsdk/manifest.json';
    if (is_readable($manifestPath)) {
        $raw = @file_get_contents($manifestPath);
        if ($raw !== false) {
            $m = json_decode($raw, true);
            if (is_array($m) && !empty($m['meetingJs'])) {
                return (string) $m['meetingJs'];
            }
        }
    }

    return 'zoom-meeting-6.2.0.min.js';
}

function fm_zoom_sdk_assets_installed(): bool
{
    $root = dirname(__DIR__) . '/assets/zoom-meetingsdk';
    $meetingJs = $root . '/dist/' . fm_zoom_meeting_js_file();

    return is_readable($meetingJs)
        && is_readable($root . '/vendor/react.min.js')
        && is_readable($root . '/dist/lib');
}

function fm_admin_session_display_name(?mysqli $conn = null): string
{
    $name = trim((string) ($_SESSION['name'] ?? ''));
    if ($name !== '') {
        return $name;
    }

    $firstLast = trim(trim((string) ($_SESSION['first_name'] ?? '')) . ' ' . trim((string) ($_SESSION['last_name'] ?? '')));
    if ($firstLast !== '') {
        return $firstLast;
    }

    $adminId = (int) ($_SESSION['id'] ?? $_SESSION['admin_id'] ?? 0);
    if ($adminId > 0 && $conn instanceof mysqli) {
        $st = $conn->prepare(
            'SELECT full_name, first_name, last_name FROM admins WHERE id = ? LIMIT 1'
        );
        if ($st) {
            $st->bind_param('i', $adminId);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            $st->close();
            if ($row) {
                $full = trim((string) ($row['full_name'] ?? ''));
                if ($full !== '') {
                    return $full;
                }
                $fl = trim(trim((string) ($row['first_name'] ?? '')) . ' ' . trim((string) ($row['last_name'] ?? '')));
                if ($fl !== '') {
                    return $fl;
                }
            }
        }
    }

    return '';
}
