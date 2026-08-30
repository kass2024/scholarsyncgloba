<?php
declare(strict_types=1);

/**
 * Francophonie Mobility — upload/record candidate video to pCloud only.
 * No permanent local copy (saves server disk space). Uses PHP upload tmp, then deletes it.
 * Folder: 32332888671
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/helpers/fm_pcloud.php';

$tmpPath = '';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Invalid request');
    }

    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $code = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
        throw new RuntimeException('Video upload failed (code ' . $code . ')');
    }

    $source = strtolower(trim((string) ($_POST['source'] ?? 'upload')));
    if (!in_array($source, ['upload', 'record'], true)) {
        $source = 'upload';
    }

    $file = $_FILES['file'];
    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0) {
        throw new RuntimeException('Empty video file');
    }
    if ($size > 200 * 1024 * 1024) {
        throw new RuntimeException('Video is too large (max 200 MB)');
    }

    $tmpPath = (string) $file['tmp_name'];
    if ($tmpPath === '' || !is_file($tmpPath)) {
        throw new RuntimeException('Temporary upload missing');
    }

    $mime = (string) (mime_content_type($tmpPath) ?: ($file['type'] ?? ''));
    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    if ($ext === '' || $ext === 'blob') {
        if (str_contains($mime, 'webm')) {
            $ext = 'webm';
        } elseif (str_contains($mime, 'mp4') || str_contains($mime, 'quicktime')) {
            $ext = 'mp4';
        } else {
            $ext = 'webm';
        }
    }

    $allowedExt = ['mp4', 'webm', 'mov', 'm4v', 'avi', 'mkv', '3gp'];
    if (!in_array($ext, $allowedExt, true)) {
        throw new RuntimeException('Unsupported video type. Use MP4, WebM or MOV.');
    }

    $remoteName = 'fm_video_' . date('Ymd') . '_' . bin2hex(random_bytes(6)) . '_' . $source . '.' . $ext;
    $upload = fm_pcloud_upload_file($tmpPath, $remoteName);

    // Always remove PHP temp as soon as upload attempt finishes.
    if ($tmpPath !== '' && is_file($tmpPath)) {
        @unlink($tmpPath);
        $tmpPath = '';
    }

    if (empty($upload['ok'])) {
        throw new RuntimeException((string) ($upload['error'] ?? 'pCloud upload failed'));
    }

    $pcloudFileId = (string) ($upload['fileid'] ?? '');
    if ($pcloudFileId === '') {
        throw new RuntimeException('pCloud upload succeeded but file id is missing');
    }

    $pub = fm_pcloud_public_link($pcloudFileId);
    if (empty($pub['ok']) || trim((string) ($pub['link'] ?? '')) === '') {
        throw new RuntimeException((string) ($pub['error'] ?? 'Could not create pCloud public link'));
    }

    $pcloudLink = (string) $pub['link'];
    $direct = '';
    if (!empty($pub['code'])) {
        $direct = fm_pcloud_direct_download((string) $pub['code']);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Video uploaded to pCloud (not stored on server disk)',
        'file_path' => '', // intentionally empty — no local copy
        'remote_name' => (string) ($upload['name'] ?? $remoteName),
        'source' => $source,
        'pcloud_fileid' => $pcloudFileId,
        'pcloud_link' => $pcloudLink,
        'pcloud_direct' => $direct,
        'pcloud_ok' => true,
        'original_name' => (string) $file['name'],
        'size' => $size,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if ($tmpPath !== '' && is_file($tmpPath)) {
        @unlink($tmpPath);
    }
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
