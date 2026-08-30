<?php
declare(strict_types=1);

/**
 * Secured video open endpoint — requires t + s; does not expose pCloud URL in share text.
 * Usage: fm-video-stream.php?t=TOKEN&s=SECRET
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/francophonie_mobility_schema.php';
require_once __DIR__ . '/helpers/fm_public_share.php';

fm_ensure_schema($conn);
fm_public_noindex_headers();

$token = trim((string) ($_GET['t'] ?? ''));
$secret = trim((string) ($_GET['s'] ?? ''));
$row = fm_public_load_by_tokens($conn, $token, $secret);

if (!$row) {
    http_response_code(404);
    exit('Not found');
}

$link = trim((string) ($row['video_pcloud_link'] ?? ''));
if ($link !== '') {
    header('Location: ' . $link, true, 302);
    exit;
}

header('Location: ' . fm_public_details_url($token, $secret), true, 302);
exit;
