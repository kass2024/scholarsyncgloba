<?php
declare(strict_types=1);

require_once __DIR__ . '/francophonie_meeting_invitation_schema.php';
require_once __DIR__ . '/zoom_meeting_api.php';

/**
 * @return array<string, array<string, mixed>>
 */
function fm_meeting_invitations_by_zoom_number(mysqli $conn): array
{
    fm_meeting_ensure_schema($conn);

    $map = [];
    $res = $conn->query(
        "SELECT id, topic, start_time, duration_minutes, zoom_meeting_number, zoom_password
         FROM francophonie_mobility_meeting_invitations
         WHERE zoom_meeting_number IS NOT NULL AND zoom_meeting_number <> ''"
    );
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $num = preg_replace('/\D+/', '', (string) ($row['zoom_meeting_number'] ?? ''));
            if ($num === '') {
                continue;
            }
            $map[$num] = $row;
        }
        $res->free();
    }

    return $map;
}

/**
 * Pick the best MP4 file for playback (completed, preferred layout, largest).
 *
 * @return array{download_url: string, play_url: string, status: string, file_size: int, recording_type: string}|null
 */
function fm_meeting_pick_best_mp4_from_files(array $files): ?array
{
    $mp4s = [];
    foreach ($files as $file) {
        if (!is_array($file)) {
            continue;
        }
        if (strtoupper((string) ($file['file_type'] ?? '')) !== 'MP4') {
            continue;
        }
        $mp4s[] = $file;
    }
    if ($mp4s === []) {
        return null;
    }

    $typePriority = [
        'shared_screen_with_speaker_view' => 100,
        'shared_screen_with_gallery_view' => 90,
        'active_speaker' => 80,
        'gallery_view' => 70,
        'shared_screen' => 60,
        'audio_only' => 10,
    ];

    usort($mp4s, static function (array $a, array $b) use ($typePriority): int {
        $sa = strtolower((string) ($a['status'] ?? 'completed'));
        $sb = strtolower((string) ($b['status'] ?? 'completed'));
        if ($sa === '') {
            $sa = 'completed';
        }
        if ($sb === '') {
            $sb = 'completed';
        }
        $ca = $sa === 'completed' ? 1 : 0;
        $cb = $sb === 'completed' ? 1 : 0;
        if ($ca !== $cb) {
            return $cb <=> $ca;
        }

        $ta = $typePriority[strtolower((string) ($a['recording_type'] ?? ''))] ?? 50;
        $tb = $typePriority[strtolower((string) ($b['recording_type'] ?? ''))] ?? 50;
        if ($ta !== $tb) {
            return $tb <=> $ta;
        }

        return (int) ($b['file_size'] ?? 0) <=> (int) ($a['file_size'] ?? 0);
    });

    $best = $mp4s[0];
    $status = strtolower((string) ($best['status'] ?? 'completed'));
    if ($status === '') {
        $status = 'completed';
    }

    return [
        'download_url' => (string) ($best['download_url'] ?? ''),
        'play_url' => (string) ($best['play_url'] ?? ''),
        'status' => $status,
        'file_size' => (int) ($best['file_size'] ?? 0),
        'recording_type' => (string) ($best['recording_type'] ?? ''),
    ];
}

/**
 * @return array{ok: bool, message?: string, ready?: bool, status?: string, download_url?: string, file_size?: int, recording_type?: string, stream_url?: string}
 */
function fm_meeting_recording_playback_info(string $meetingNumber): array
{
    $meetingNumber = preg_replace('/\D+/', '', $meetingNumber);
    if ($meetingNumber === '') {
        return ['ok' => false, 'message' => 'Invalid meeting number'];
    }

    $result = zoom_api_request('GET', '/meetings/' . rawurlencode($meetingNumber) . '/recordings');
    if (!$result['ok']) {
        return [
            'ok' => false,
            'message' => (string) ($result['message'] ?? 'Recording not found on Zoom'),
            'status' => 'missing',
        ];
    }

    $files = $result['data']['recording_files'] ?? [];
    $best = fm_meeting_pick_best_mp4_from_files(is_array($files) ? $files : []);
    if ($best === null || ($best['download_url'] ?? '') === '') {
        return ['ok' => false, 'message' => 'No MP4 recording file found', 'status' => 'missing'];
    }

    $status = (string) ($best['status'] ?? 'unknown');

    return [
        'ok' => true,
        'ready' => $status === 'completed',
        'status' => $status,
        'download_url' => $best['download_url'],
        'file_size' => (int) ($best['file_size'] ?? 0),
        'recording_type' => (string) ($best['recording_type'] ?? ''),
        'stream_url' => 'fm_meeting_recording_stream.php?meeting_number=' . rawurlencode($meetingNumber),
    ];
}

/**
 * @return list<array<string, mixed>>
 */
function fm_meeting_pick_recording_play_url(array $recordingFiles): array
{
    $playUrl = '';
    $downloadUrl = '';
    $fileTypes = [];
    $status = 'unknown';

    foreach ($recordingFiles as $file) {
        if (!is_array($file)) {
            continue;
        }
        $type = strtoupper((string) ($file['file_type'] ?? ''));
        if ($type !== '') {
            $fileTypes[] = $type;
        }
        if ($playUrl === '' && !empty($file['play_url'])) {
            $playUrl = (string) $file['play_url'];
        }
    }

    $best = fm_meeting_pick_best_mp4_from_files($recordingFiles);
    if ($best !== null) {
        $status = (string) ($best['status'] ?? 'unknown');
        $downloadUrl = (string) ($best['download_url'] ?? '');
        if ($playUrl === '' && ($best['play_url'] ?? '') !== '') {
            $playUrl = (string) $best['play_url'];
        }
    }

    return [
        'play_url' => $playUrl,
        'download_url' => $downloadUrl,
        'file_types' => array_values(array_unique($fileTypes)),
        'recording_status' => $status,
        'can_play_inline' => $status === 'completed' && $downloadUrl !== '',
    ];
}

/**
 * @return array{download_url: string, status: string, file_size: int}|null
 */
function fm_meeting_fetch_meeting_mp4(string $meetingNumber): ?array
{
    $info = fm_meeting_recording_playback_info($meetingNumber);
    if (empty($info['ok']) || ($info['download_url'] ?? '') === '') {
        return null;
    }

    return [
        'download_url' => (string) $info['download_url'],
        'status' => (string) ($info['status'] ?? 'unknown'),
        'file_size' => (int) ($info['file_size'] ?? 0),
    ];
}

/**
 * Stream a Zoom cloud MP4 through this server (keeps OAuth token server-side).
 */
function fm_meeting_proxy_zoom_recording_download(string $downloadUrl, string $accessToken): void
{
    $url = $downloadUrl;
    if (stripos($url, 'access_token=') === false) {
        $url .= (str_contains($url, '?') ? '&' : '?') . 'access_token=' . rawurlencode($accessToken);
    }

    $forwardHeaders = [];
    if (!empty($_SERVER['HTTP_RANGE'])) {
        $forwardHeaders[] = 'Range: ' . $_SERVER['HTTP_RANGE'];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_HTTPHEADER => $forwardHeaders,
        CURLOPT_HEADER => false,
        CURLOPT_HEADERFUNCTION => static function ($curl, string $headerLine): int {
            $len = strlen($headerLine);
            $trim = trim($headerLine);
            if ($trim === '') {
                return $len;
            }
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $trim, $m)) {
                http_response_code((int) $m[1]);

                return $len;
            }
            if (preg_match('/^(Content-Type|Content-Length|Content-Range|Accept-Ranges):/i', $trim)) {
                header($trim, false);
            }

            return $len;
        },
        CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk): int {
            echo $chunk;

            return strlen($chunk);
        },
    ]);

    $ok = curl_exec($ch);
    if ($ok === false) {
        if (!headers_sent()) {
            http_response_code(502);
            header('Content-Type: text/plain; charset=utf-8');
        }
        echo 'Could not stream recording from Zoom.';
    }
    curl_close($ch);
}

/**
 * @return array{items: list<array<string, mixed>>, total: int, next_page_token: string|null}
 */
function fm_meeting_fetch_cloud_recordings(
    mysqli $conn,
    string $dateFrom,
    string $dateTo,
    string $search = '',
    ?string $pageToken = null
): array {
    $known = fm_meeting_invitations_by_zoom_number($conn);
    if ($known === []) {
        return ['items' => [], 'total' => 0, 'next_page_token' => null];
    }

    $searchTrim = trim($search);
    $zoomSearch = $searchTrim !== '' ? $searchTrim : null;

    $allMeetings = [];
    $pageToken = $pageToken;
    $pages = 0;
    do {
        $cloud = zoom_api_list_user_recordings($dateFrom, $dateTo, $zoomSearch, $pageToken);
        foreach ($cloud['meetings'] as $meeting) {
            if (is_array($meeting)) {
                $allMeetings[] = $meeting;
            }
        }
        $pageToken = $cloud['next_page_token'];
        $pages++;
    } while ($pageToken !== null && $pages < 12);

    $items = [];
    $searchLower = mb_strtolower($searchTrim);

    foreach ($allMeetings as $meeting) {
        if (!is_array($meeting)) {
            continue;
        }

        $meetingNumber = preg_replace('/\D+/', '', (string) ($meeting['id'] ?? ''));
        if ($meetingNumber === '' || !isset($known[$meetingNumber])) {
            continue;
        }

        $inv = $known[$meetingNumber];
        $topic = trim((string) ($inv['topic'] ?? ''));
        if ($topic === '') {
            $topic = trim((string) ($meeting['topic'] ?? ''));
        }

        $startTime = (string) ($meeting['start_time'] ?? ($inv['start_time'] ?? ''));
        $startTs = strtotime($startTime);
        $startDisplay = $startTs ? date('M j, Y g:i A', $startTs) : $startTime;
        $startDate = $startTs ? date('Y-m-d', $startTs) : '';

        if ($searchTrim !== '') {
            $haystack = mb_strtolower(implode(' ', [
                $topic,
                $meetingNumber,
                (string) ($inv['id'] ?? ''),
                $startDisplay,
                $startDate,
            ]));
            if (!str_contains($haystack, $searchLower)) {
                continue;
            }
        }

        $files = is_array($meeting['recording_files'] ?? null) ? $meeting['recording_files'] : [];
        $media = fm_meeting_pick_recording_play_url($files);
        $totalSize = 0;
        foreach ($files as $file) {
            if (is_array($file)) {
                $totalSize += (int) ($file['file_size'] ?? 0);
            }
        }

        $items[] = [
            'invitation_id' => (int) ($inv['id'] ?? 0),
            'topic' => $topic,
            'meeting_number' => $meetingNumber,
            'meeting_uuid' => (string) ($meeting['uuid'] ?? ''),
            'start_time' => $startTime,
            'start_time_display' => $startDisplay,
            'start_date' => $startDate,
            'duration_minutes' => (int) ($meeting['duration'] ?? ($inv['duration_minutes'] ?? 0)),
            'recording_files_count' => count($files),
            'total_size_bytes' => $totalSize,
            'total_size_label' => fm_meeting_format_bytes($totalSize),
            'play_url' => $media['play_url'],
            'download_url' => $media['download_url'],
            'file_types' => $media['file_types'],
            'recording_status' => $media['recording_status'],
            'can_play_inline' => !empty($media['can_play_inline']),
            'stream_url' => 'fm_meeting_recording_stream.php?meeting_number=' . rawurlencode($meetingNumber),
        ];
    }

    usort($items, static function (array $a, array $b): int {
        return strcmp((string) ($b['start_time'] ?? ''), (string) ($a['start_time'] ?? ''));
    });

    return [
        'items' => $items,
        'total' => count($items),
        'next_page_token' => null,
    ];
}

function fm_meeting_format_bytes(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1048576) {
        return round($bytes / 1024, 1) . ' KB';
    }
    if ($bytes < 1073741824) {
        return round($bytes / 1048576, 1) . ' MB';
    }

    return round($bytes / 1073741824, 2) . ' GB';
}
