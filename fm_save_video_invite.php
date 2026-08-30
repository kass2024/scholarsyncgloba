<?php
declare(strict_types=1);

/**
 * fm_save_video_invite.php — attach pCloud video to application via one-time invite token.
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/francophonie_mobility_schema.php';
require_once __DIR__ . '/helpers/fm_public_share.php';

fm_ensure_schema($conn);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Invalid request');
    }

    $token = trim((string) ($_POST['invite_token'] ?? ''));
    if ($token === '' || !preg_match('/^[a-f0-9]{32}$/i', $token)) {
        throw new RuntimeException('Invalid invite link');
    }

    $source = strtolower(trim((string) ($_POST['video_source'] ?? 'upload')));
    if (!in_array($source, ['upload', 'record'], true)) {
        $source = 'upload';
    }

    $pcloudFileId = trim((string) ($_POST['video_pcloud_fileid'] ?? ''));
    $pcloudLink = trim((string) ($_POST['video_pcloud_link'] ?? ''));
    if ($pcloudFileId === '' || $pcloudLink === '') {
        throw new RuntimeException('Video upload to pCloud is required first');
    }

    $conn->begin_transaction();

    $st = $conn->prepare(
        'SELECT id, video_pcloud_link, video_file, video_invite_used_at
         FROM francophonie_mobility_applications
         WHERE video_invite_token = ? LIMIT 1 FOR UPDATE'
    );
    if (!$st) {
        throw new RuntimeException('Database error');
    }
    $st->bind_param('s', $token);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();

    if (!$row) {
        throw new RuntimeException('This invite link is invalid or expired');
    }

    if (!empty($row['video_invite_used_at'])) {
        throw new RuntimeException('This invite link was already used');
    }

    $hasVideo = trim((string) ($row['video_pcloud_link'] ?? '')) !== ''
        || trim((string) ($row['video_file'] ?? '')) !== '';
    if ($hasVideo) {
        throw new RuntimeException('A video is already on file for this application');
    }

    $id = (int) $row['id'];
    $share = fm_new_public_share_tokens();
    $publicToken = $share['token'];
    $publicSecret = $share['secret'];
    $emptyLocal = '';

    $upd = $conn->prepare(
        'UPDATE francophonie_mobility_applications SET
            video_file = ?,
            video_source = ?,
            video_pcloud_fileid = ?,
            video_pcloud_link = ?,
            video_public_token = ?,
            video_public_secret = ?,
            video_invite_used_at = NOW()
         WHERE id = ? AND video_invite_token = ? AND video_invite_used_at IS NULL
         LIMIT 1'
    );
    $upd->bind_param(
        'ssssssis',
        $emptyLocal,
        $source,
        $pcloudFileId,
        $pcloudLink,
        $publicToken,
        $publicSecret,
        $id,
        $token
    );
    if (!$upd->execute() || $upd->affected_rows < 1) {
        $upd->close();
        throw new RuntimeException('Could not save video (link may already be used)');
    }
    $upd->close();
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Video saved. This invite link is now closed.',
        'public_token' => $publicToken,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $ignored) {
    }
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
