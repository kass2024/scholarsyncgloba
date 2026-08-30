<?php
declare(strict_types=1);

/**
 * Francophonie Mobility — secured public share links (view details).
 * URLs require both t (token) and s (secret). pCloud URLs are never shared.
 */

require_once __DIR__ . '/env_load.php';
require_once __DIR__ . '/francophonie_mobility_files.php';

function fm_public_base_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    return $scheme . '://' . $host . $basePath;
}

/** True when t + s look valid and match a row. */
function fm_public_tokens_valid_shape(string $token, string $secret): bool
{
    return (bool) preg_match('/^[a-f0-9]{32}$/i', $token)
        && (bool) preg_match('/^[a-f0-9]{32}$/i', $secret);
}

/**
 * Load application by dual share tokens.
 * @return array<string,mixed>|null
 */
function fm_public_load_by_tokens(mysqli $conn, string $token, string $secret): ?array
{
    if (!fm_public_tokens_valid_shape($token, $secret)) {
        return null;
    }
    $st = $conn->prepare(
        'SELECT * FROM francophonie_mobility_applications
         WHERE video_public_token = ? AND video_public_secret = ? LIMIT 1'
    );
    if (!$st) {
        return null;
    }
    $st->bind_param('ss', $token, $secret);
    $st->execute();
    $row = $st->get_result()->fetch_assoc() ?: null;
    $st->close();
    return $row;
}

/** Build secured view-details URL (never includes pCloud). */
function fm_public_details_url(string $token, string $secret): string
{
    return fm_public_base_url() . '/fm-view-details.php?t=' . rawurlencode($token)
        . '&s=' . rawurlencode($secret);
}

function fm_public_video_url(string $token, string $secret): string
{
    return fm_public_base_url() . '/fm-video-stream.php?t=' . rawurlencode($token)
        . '&s=' . rawurlencode($secret);
}

function fm_public_file_url(string $token, string $secret, string $docKey, bool $inline = false): string
{
    $url = fm_public_base_url() . '/fm-public-file.php?t=' . rawurlencode($token)
        . '&s=' . rawurlencode($secret)
        . '&doc=' . rawurlencode($docKey);
    if ($inline) {
        $url .= '&inline=1';
    }
    return $url;
}

/**
 * Ensure share token + secret exist for an application that has (or will have) a video/share.
 * Updates DB when missing. Returns [token, secret] or ['',''].
 *
 * @param array<string,mixed> $row
 * @return array{0:string,1:string}
 */
function fm_ensure_public_share_tokens(mysqli $conn, array &$row): array
{
    $id = (int) ($row['id'] ?? 0);
    if ($id <= 0) {
        return ['', ''];
    }

    $token = trim((string) ($row['video_public_token'] ?? ''));
    $secret = trim((string) ($row['video_public_secret'] ?? ''));
    // Always issue share credentials once admin wants a details link (even before video).
    $needsToken = $token === '' || !preg_match('/^[a-f0-9]{32}$/i', $token);
    $needsSecret = $secret === '' || !preg_match('/^[a-f0-9]{32}$/i', $secret);

    if (!$needsToken && !$needsSecret) {
        return [$token, $secret];
    }

    if ($needsToken) {
        $token = bin2hex(random_bytes(16));
    }
    if ($needsSecret) {
        $secret = bin2hex(random_bytes(16));
    }

    $upd = $conn->prepare(
        'UPDATE francophonie_mobility_applications
         SET video_public_token = ?, video_public_secret = ?
         WHERE id = ? LIMIT 1'
    );
    $upd->bind_param('ssi', $token, $secret, $id);
    $upd->execute();
    $upd->close();

    $row['video_public_token'] = $token;
    $row['video_public_secret'] = $secret;

    return [$token, $secret];
}

/**
 * Create fresh share tokens (used when video is first attached).
 * @return array{token:string,secret:string}
 */
function fm_new_public_share_tokens(): array
{
    return [
        'token' => bin2hex(random_bytes(16)),
        'secret' => bin2hex(random_bytes(16)),
    ];
}

/**
 * Resolve attachment list for public share page.
 *
 * @param array<string,mixed> $row
 * @return list<array{key:string,label:string,path:string}>
 */
function fm_public_attachment_list(array $row): array
{
    $list = [];
    $map = [
        'cv' => ['col' => 'cv_file', 'label' => 'CV'],
        'french' => ['col' => 'french_cert_file', 'label' => 'French Certificate'],
        'english' => ['col' => 'english_cert_file', 'label' => 'English Certificate'],
    ];
    foreach ($map as $key => $meta) {
        $rel = trim((string) ($row[$meta['col']] ?? ''));
        if ($rel === '' || fm_abs_upload_path($rel) === null) {
            continue;
        }
        $list[] = ['key' => $key, 'label' => $meta['label'], 'path' => $rel];
    }

    $academic = fm_parse_stored_files((string) ($row['academic_docs_file'] ?? ''));
    foreach ($academic as $i => $rel) {
        if (fm_abs_upload_path($rel) === null) {
            continue;
        }
        $label = count($academic) > 1 ? 'Academic Document ' . ($i + 1) : 'Academic Documents';
        $list[] = ['key' => 'academic_' . $i, 'label' => $label, 'path' => $rel];
    }

    return $list;
}

/**
 * Map doc key → relative path for a row, or null.
 *
 * @param array<string,mixed> $row
 */
function fm_public_resolve_doc_path(array $row, string $docKey): ?string
{
    $docKey = strtolower(trim($docKey));
    if ($docKey === 'cv') {
        $rel = trim((string) ($row['cv_file'] ?? ''));
        return $rel !== '' ? $rel : null;
    }
    if ($docKey === 'french') {
        $rel = trim((string) ($row['french_cert_file'] ?? ''));
        return $rel !== '' ? $rel : null;
    }
    if ($docKey === 'english') {
        $rel = trim((string) ($row['english_cert_file'] ?? ''));
        return $rel !== '' ? $rel : null;
    }
    if (preg_match('/^academic_(\d+)$/', $docKey, $m)) {
        $list = fm_parse_stored_files((string) ($row['academic_docs_file'] ?? ''));
        $i = (int) $m[1];
        return isset($list[$i]) ? $list[$i] : null;
    }
    return null;
}

/**
 * Plain-text share bundle for WhatsApp / clipboard (no pCloud).
 *
 * @param array<string,mixed> $row
 */
function fm_public_copy_bundle(array $row, string $detailsUrl): string
{
    $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
    $phone = '+' . trim(($row['phone_area_code'] ?? '') . ' ' . ($row['phone_number'] ?? ''));
    $lines = [
        'Francophonie Mobility — Candidate details',
        'Owner: ' . $name,
        'Reference: ' . ($row['reference_id'] ?? ''),
        'Email: ' . ($row['email'] ?? ''),
        'Phone: ' . $phone,
        'Nationality: ' . ($row['nationality'] ?? ''),
        'Profession: ' . ($row['profession'] ?? ''),
        'View details: ' . $detailsUrl,
    ];
    return implode("\n", $lines) . "\n";
}

function fm_public_noindex_headers(): void
{
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('Referrer-Policy: no-referrer');
    header('Cache-Control: no-store, no-cache, must-revalidate, private');
}
