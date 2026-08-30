<?php
declare(strict_types=1);

/**
 * Token-gated attachment download for secured view-details links.
 * Usage: fm-public-file.php?t=TOKEN&s=SECRET&doc=cv|french|english|academic_0[&inline=1]
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/francophonie_mobility_schema.php';
require_once __DIR__ . '/helpers/fm_public_share.php';
require_once __DIR__ . '/helpers/secure_file.php';

fm_ensure_schema($conn);
fm_public_noindex_headers();

$token = trim((string) ($_GET['t'] ?? ''));
$secret = trim((string) ($_GET['s'] ?? ''));
$docKey = trim((string) ($_GET['doc'] ?? ''));
$inline = !empty($_GET['inline']);

$row = fm_public_load_by_tokens($conn, $token, $secret);
if (!$row || $docKey === '') {
    http_response_code(404);
    exit('Not found');
}

$rel = fm_public_resolve_doc_path($row, $docKey);
$abs = $rel !== null ? fm_abs_upload_path($rel) : null;
if ($abs === null) {
    http_response_code(404);
    exit('File not found');
}

$mime = mime_content_type($abs) ?: 'application/octet-stream';
$filename = basename($abs);
$disposition = $inline ? 'inline' : 'attachment';

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($abs));
header('Content-Disposition: ' . $disposition . '; filename="' . str_replace('"', '', $filename) . '"');
header('X-Content-Type-Options: nosniff');
readfile($abs);
exit;
