<?php
/**
 * Zoom Server-to-Server OAuth + meeting creation (reads ZOOM_* from .env).
 */
declare(strict_types=1);

require_once __DIR__ . '/env_load.php';

function zoom_api_is_configured(): bool
{
    xander_load_env_file();

    return trim(xander_env_get('ZOOM_ACCOUNT_ID')) !== ''
        && trim(xander_env_get('ZOOM_CLIENT_ID')) !== ''
        && trim(xander_env_get('ZOOM_CLIENT_SECRET')) !== '';
}

function zoom_api_host_user_id(): string
{
    $host = trim(xander_env_get('ZOOM_HOST_USER_ID'));
    if ($host !== '') {
        return $host;
    }

    return 'me';
}

function zoom_api_token_cache_path(): string
{
    $key = md5(
        trim(xander_env_get('ZOOM_ACCOUNT_ID')) . '|'
        . trim(xander_env_get('ZOOM_CLIENT_ID'))
    );

    return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pcvc_zoom_token_' . $key . '.json';
}

/**
 * @return array{ok: bool, token?: string, message?: string}
 */
function zoom_api_get_access_token(bool $forceRefresh = false): array
{
    if (!zoom_api_is_configured()) {
        return ['ok' => false, 'message' => 'Zoom API is not configured. Set ZOOM_ACCOUNT_ID, ZOOM_CLIENT_ID, and ZOOM_CLIENT_SECRET in .env.'];
    }

    $cacheFile = zoom_api_token_cache_path();
    if (!$forceRefresh && is_readable($cacheFile)) {
        $raw = @file_get_contents($cacheFile);
        if ($raw !== false && $raw !== '') {
            $cached = json_decode($raw, true);
            if (is_array($cached) && !empty($cached['access_token']) && !empty($cached['expires_at']) && (int) $cached['expires_at'] > time() + 60) {
                return ['ok' => true, 'token' => (string) $cached['access_token']];
            }
        }
    }

    $accountId = trim(xander_env_get('ZOOM_ACCOUNT_ID'));
    $clientId = trim(xander_env_get('ZOOM_CLIENT_ID'));
    $clientSecret = trim(xander_env_get('ZOOM_CLIENT_SECRET'));

    $ch = curl_init('https://zoom.us/oauth/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret),
            'Content-Type: application/x-www-form-urlencoded',
        ],
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'account_credentials',
            'account_id' => $accountId,
        ]),
    ]);

    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        return ['ok' => false, 'message' => 'Zoom OAuth request failed: ' . ($curlErr ?: 'network error')];
    }

    $json = json_decode((string) $body, true);
    if ($status < 200 || $status >= 300 || !is_array($json) || empty($json['access_token'])) {
        $detail = '';
        if (is_array($json)) {
            $detail = trim((string) ($json['message'] ?? $json['error'] ?? ''));
        }

        return [
            'ok' => false,
            'message' => $detail !== '' ? "Zoom OAuth failed: {$detail}" : 'Zoom OAuth token unavailable.',
        ];
    }

    $expiresIn = max(60, (int) ($json['expires_in'] ?? 3600));
    @file_put_contents($cacheFile, json_encode([
        'access_token' => $json['access_token'],
        'expires_at' => time() + $expiresIn - 120,
    ], JSON_UNESCAPED_SLASHES), LOCK_EX);

    return ['ok' => true, 'token' => (string) $json['access_token']];
}

/**
 * @return array{ok: bool, message?: string, data?: array<string, mixed>}
 */
function zoom_api_request(string $method, string $path, ?array $payload = null, bool $retry = true): array
{
    $tokenResult = zoom_api_get_access_token();
    if (!$tokenResult['ok']) {
        return $tokenResult;
    }

    $url = 'https://api.zoom.us/v2' . $path;
    $ch = curl_init($url);
    $headers = [
        'Authorization: Bearer ' . $tokenResult['token'],
        'Content-Type: application/json',
    ];

    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
    ];

    if ($payload !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        return ['ok' => false, 'message' => 'Zoom API request failed: ' . ($curlErr ?: 'network error')];
    }

    if ($status === 401 && $retry) {
        zoom_api_get_access_token(true);

        return zoom_api_request($method, $path, $payload, false);
    }

    $json = json_decode((string) $body, true);
    if ($status < 200 || $status >= 300) {
        $detail = is_array($json) ? trim((string) ($json['message'] ?? '')) : '';

        return [
            'ok' => false,
            'message' => $detail !== '' ? "Zoom API error: {$detail}" : "Zoom API error (HTTP {$status}).",
            'data' => is_array($json) ? $json : [],
        ];
    }

    return ['ok' => true, 'data' => is_array($json) ? $json : []];
}

/**
 * @param array<string, mixed> $data
 * @return array{ok: bool, message?: string, meeting?: array<string, mixed>}
 */
function zoom_api_create_scheduled_meeting(array $data): array
{
    $startTime = trim((string) ($data['start_time'] ?? ''));
    if ($startTime === '') {
        return ['ok' => false, 'message' => 'Meeting start time is required.'];
    }

    try {
        $dt = new DateTime($startTime);
        $formattedStart = $dt->format('Y-m-d\TH:i:s');
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => 'Invalid meeting start time.'];
    }

    $host = zoom_api_host_user_id();
    $payload = [
        'topic' => trim((string) ($data['topic'] ?? 'Francophonie Mobility Meeting')) ?: 'Francophonie Mobility Meeting',
        'type' => 2,
        'start_time' => $formattedStart,
        'duration' => max(15, min(480, (int) ($data['duration'] ?? 60))),
        'timezone' => trim((string) ($data['timezone'] ?? 'America/Toronto')) ?: 'America/Toronto',
        'agenda' => trim((string) ($data['agenda'] ?? '')),
        'settings' => [
            'join_before_host' => (bool) ($data['join_before_host'] ?? true),
            'waiting_room' => (bool) ($data['waiting_room'] ?? false),
            'mute_upon_entry' => true,
            'host_video' => true,
            'participant_video' => false,
            'auto_recording' => 'cloud',
            'approval_type' => 2,
            'registrants_email_notification' => false,
        ],
    ];

    $path = '/users/' . rawurlencode($host) . '/meetings';
    $result = zoom_api_request('POST', $path, $payload);
    if (!$result['ok']) {
        return $result;
    }

    $meeting = $result['data'] ?? [];
    if (empty($meeting['join_url'])) {
        return ['ok' => false, 'message' => 'Zoom meeting was created but no join URL was returned.'];
    }

    return ['ok' => true, 'meeting' => $meeting];
}

/**
 * @return array{ok: bool, message?: string}
 */
function zoom_api_connection_status(): array
{
    if (!zoom_api_is_configured()) {
        return ['ok' => false, 'message' => 'Zoom credentials missing in .env.'];
    }

    $token = zoom_api_get_access_token();
    if (!$token['ok']) {
        return $token;
    }

    $host = zoom_api_host_user_id();
    $probe = zoom_api_request('GET', '/users/' . rawurlencode($host), null);
    if (!$probe['ok']) {
        return $probe;
    }

    return ['ok' => true, 'message' => 'Connected as ' . ($probe['data']['email'] ?? $host)];
}

/**
 * @return array{ok: bool, message?: string}
 */
function zoom_api_delete_meeting(string $meetingNumber): array
{
    $meetingNumber = preg_replace('/\D+/', '', $meetingNumber);
    if ($meetingNumber === '') {
        return ['ok' => false, 'message' => 'Invalid meeting number'];
    }

    $result = zoom_api_request('DELETE', '/meetings/' . rawurlencode($meetingNumber));
    if (!$result['ok']) {
        $msg = (string) ($result['message'] ?? '');
        if (stripos($msg, 'not found') !== false || stripos($msg, '404') !== false) {
            return ['ok' => true, 'message' => 'Meeting already removed from Zoom'];
        }

        return $result;
    }

    return ['ok' => true];
}

function zoom_api_host_profile_cache_path(): string
{
    return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pcvc_zoom_host_profile_'
        . md5(zoom_api_host_user_id()) . '.json';
}

/**
 * @return array<string, mixed>|null
 */
function zoom_api_fetch_host_profile(bool $forceRefresh = false): ?array
{
    $cacheFile = zoom_api_host_profile_cache_path();
    if (!$forceRefresh && is_readable($cacheFile)) {
        $raw = @file_get_contents($cacheFile);
        if ($raw !== false && $raw !== '') {
            $cached = json_decode($raw, true);
            if (
                is_array($cached)
                && isset($cached['expires'])
                && (int) $cached['expires'] > time()
                && is_array($cached['profile'] ?? null)
            ) {
                return $cached['profile'];
            }
        }
    }

    $host = zoom_api_host_user_id();
    $result = zoom_api_request('GET', '/users/' . rawurlencode($host), null);
    if (!$result['ok']) {
        $result = zoom_api_request('GET', '/users/me', null);
        if (!$result['ok']) {
            return null;
        }
    }

    $profile = $result['data'] ?? null;
    if (!is_array($profile)) {
        return null;
    }

    @file_put_contents($cacheFile, json_encode([
        'expires' => time() + 3600,
        'profile' => $profile,
    ], JSON_UNESCAPED_UNICODE));

    return $profile;
}

function zoom_api_profile_full_name(?array $profile): string
{
    if (!is_array($profile)) {
        return '';
    }

    $first = trim((string) ($profile['first_name'] ?? ''));
    $last = trim((string) ($profile['last_name'] ?? ''));
    $full = trim($first . ' ' . $last);
    if ($full !== '') {
        return $full;
    }

    return trim((string) ($profile['display_name'] ?? ''));
}

/**
 * Name shown in meetings for the Zoom host account (display_name / brand, not legal name).
 */
function zoom_api_host_display_name(?array $profile): string
{
    xander_load_env_file();
    $override = trim(xander_env_get('ZOOM_HOST_DISPLAY_NAME'));
    if ($override !== '') {
        return $override;
    }

    if (!is_array($profile)) {
        return '';
    }

    $display = trim((string) ($profile['display_name'] ?? ''));
    if ($display !== '') {
        return $display;
    }

    return zoom_api_profile_full_name($profile);
}

/**
 * Real Zoom host account name/email for SDK join (not MIS login username).
 *
 * @return array{name: string, email: string}
 */
function zoom_api_resolve_host_join_identity(bool $forceRefresh = false): array
{
    xander_load_env_file();
    $override = trim(xander_env_get('ZOOM_HOST_DISPLAY_NAME'));
    $configured = trim(zoom_api_host_user_id());
    if (!$forceRefresh && $override !== '' && str_contains($configured, '@')) {
        return [
            'name' => $override,
            'email' => $configured,
        ];
    }

    $profile = zoom_api_fetch_host_profile($forceRefresh);
    $name = zoom_api_host_display_name($profile);
    $email = is_array($profile) ? trim((string) ($profile['email'] ?? '')) : '';

    if ($email === '' && str_contains($configured, '@')) {
        $email = $configured;
    }

    if ($name === '' && $email !== '') {
        $local = strstr($email, '@', true);
        if (is_string($local) && $local !== '') {
            $name = ucwords(str_replace(['.', '_', '-'], ' ', $local));
        }
    }

    return [
        'name' => $name !== '' ? $name : 'Host',
        'email' => $email,
    ];
}

/**
 * List cloud recordings for the configured Zoom host user.
 *
 * @return array{meetings: list<array<string, mixed>>, total_records: int, next_page_token: string|null}
 */
function zoom_api_list_user_recordings(string $from, string $to, ?string $searchKey = null, ?string $pageToken = null, int $pageSize = 50): array
{
    $userId = zoom_api_host_user_id();
    $query = [
        'from' => $from,
        'to' => $to,
        'page_size' => max(1, min(300, $pageSize)),
    ];
    if ($searchKey !== null && trim($searchKey) !== '') {
        $query['search_key'] = trim($searchKey);
    }
    if ($pageToken !== null && trim($pageToken) !== '') {
        $query['next_page_token'] = trim($pageToken);
    }

    $path = '/users/' . rawurlencode($userId) . '/recordings?' . http_build_query($query);
    $result = zoom_api_request('GET', $path, null);
    if (!$result['ok']) {
        throw new RuntimeException((string) ($result['message'] ?? 'Failed to list Zoom recordings.'));
    }

    $data = $result['data'] ?? [];

    return [
        'meetings' => is_array($data['meetings'] ?? null) ? $data['meetings'] : [],
        'total_records' => (int) ($data['total_records'] ?? 0),
        'next_page_token' => isset($data['next_page_token']) && $data['next_page_token'] !== ''
            ? (string) $data['next_page_token']
            : null,
    ];
}

/**
 * Delete all cloud recording files for a meeting.
 *
 * @param string $meetingId Zoom meeting ID (numeric) or UUID
 */
function zoom_api_delete_meeting_recordings(string $meetingId, string $action = 'delete'): void
{
    $numeric = preg_replace('/\D+/', '', $meetingId);
    $encoded = $numeric !== ''
        ? rawurlencode($numeric)
        : rawurlencode(rawurlencode($meetingId));

    $path = '/meetings/' . $encoded . '/recordings?action=' . rawurlencode($action);
    $result = zoom_api_request('DELETE', $path, null);
    if (!$result['ok']) {
        throw new RuntimeException((string) ($result['message'] ?? 'Failed to delete Zoom recording.'));
    }
}
