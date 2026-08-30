<?php
/**
 * francophonie_mobility_admin_action.php
 * Admin JSON actions: create_video_invite | delete_application
 */
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/francophonie_mobility_schema.php';
require_once __DIR__ . '/helpers/francophonie_mobility_files.php';
require_once __DIR__ . '/helpers/fm_mobility_contract_schema.php';

fm_ensure_schema($conn);

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['id']) || empty($_POST['csrf_token'])
    || !hash_equals((string) ($_SESSION['csrf_token'] ?? ''), (string) $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$action = trim((string) ($_POST['action'] ?? ''));
$applicationId = (int) ($_POST['application_id'] ?? 0);

if ($applicationId <= 0 || $action === '') {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$st = $conn->prepare('SELECT * FROM francophonie_mobility_applications WHERE id = ? LIMIT 1');
$st->bind_param('i', $applicationId);
$st->execute();
$row = $st->get_result()->fetch_assoc();
$st->close();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Application not found']);
    exit;
}

function fm_admin_absolute_url(string $scriptRelative): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    return $scheme . '://' . $host . $basePath . '/' . ltrim($scriptRelative, '/');
}

function fm_admin_whatsapp_digits(array $row): string
{
    $digits = preg_replace('/\D+/', '', (string) (($row['phone_area_code'] ?? '') . ($row['phone_number'] ?? ''))) ?: '';
    return $digits;
}

function fm_admin_unlink_stored(string $rel): void
{
    $abs = fm_abs_upload_path($rel);
    if ($abs && is_file($abs)) {
        @unlink($abs);
    }
}

if ($action === 'create_video_invite') {
    $hasVideo = trim((string) ($row['video_pcloud_link'] ?? '')) !== ''
        || trim((string) ($row['video_file'] ?? '')) !== '';
    if ($hasVideo) {
        echo json_encode(['success' => false, 'message' => 'This application already has a video.']);
        exit;
    }

    $token = bin2hex(random_bytes(16));
    $upd = $conn->prepare(
        'UPDATE francophonie_mobility_applications
         SET video_invite_token = ?, video_invite_created_at = NOW(),
             video_invite_opened_at = NULL, video_invite_used_at = NULL
         WHERE id = ? LIMIT 1'
    );
    $upd->bind_param('si', $token, $applicationId);
    if (!$upd->execute()) {
        echo json_encode(['success' => false, 'message' => 'Could not create invite link']);
        exit;
    }
    $upd->close();

    $inviteUrl = fm_admin_absolute_url('fm-video-invite.php?t=' . rawurlencode($token));
    $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
    $ref = (string) ($row['reference_id'] ?? '');
    $waText = "Hello {$name},\n\n"
        . "Please upload or record your introduction video for Canada Francophonie Mobility.\n"
        . "Reference: {$ref}\n"
        . "One-time link (upload/record only): {$inviteUrl}\n\n"
        . "This link can be used once. Thank you.";
    $phoneDigits = fm_admin_whatsapp_digits($row);
    $waUrl = $phoneDigits !== ''
        ? 'https://wa.me/' . $phoneDigits . '?text=' . rawurlencode($waText)
        : 'https://wa.me/?text=' . rawurlencode($waText);

    echo json_encode([
        'success' => true,
        'message' => 'Video invite link created (one-time use after upload).',
        'invite_url' => $inviteUrl,
        'whatsapp_url' => $waUrl,
        'whatsapp_text' => $waText,
        'reference_id' => $ref,
        'phone_digits' => $phoneDigits,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'delete_application') {
    $confirm = trim((string) ($_POST['confirm_reference'] ?? ''));
    $ref = (string) ($row['reference_id'] ?? '');
    if ($confirm === '' || strcasecmp($confirm, $ref) !== 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Type the exact reference ID to confirm delete: ' . $ref,
        ]);
        exit;
    }

    $conn->begin_transaction();
    try {
        // Local document files (video is pCloud-only).
        foreach (['cv_file', 'french_cert_file', 'english_cert_file'] as $col) {
            $rel = trim((string) ($row[$col] ?? ''));
            if ($rel !== '') {
                fm_admin_unlink_stored($rel);
            }
        }
        foreach (fm_parse_stored_files((string) ($row['academic_docs_file'] ?? '')) as $apath) {
            fm_admin_unlink_stored($apath);
        }
        $videoLocal = trim((string) ($row['video_file'] ?? ''));
        if ($videoLocal !== '') {
            fm_admin_unlink_stored($videoLocal);
        }

        $logDel = $conn->prepare('DELETE FROM francophonie_mobility_status_logs WHERE application_id = ?');
        $logDel->bind_param('i', $applicationId);
        $logDel->execute();
        $logDel->close();

        // Linked e-sign contracts (best-effort).
        fm_contract_ensure_schema($conn);
        $cSt = $conn->prepare('SELECT id, pdf_path FROM fm_mobility_contracts WHERE application_id = ?');
        $cSt->bind_param('i', $applicationId);
        $cSt->execute();
        $contracts = $cSt->get_result()->fetch_all(MYSQLI_ASSOC);
        $cSt->close();
        foreach ($contracts as $c) {
            $cid = (int) ($c['id'] ?? 0);
            if ($cid <= 0) {
                continue;
            }
            if (!empty($c['pdf_path'])) {
                $filePath = realpath(__DIR__ . '/' . $c['pdf_path']);
                $baseDir = realpath(__DIR__ . '/uploads/fm_contracts');
                if ($filePath && $baseDir && strpos($filePath, $baseDir) === 0 && is_file($filePath)) {
                    @unlink($filePath);
                }
            }
            $sDel = $conn->prepare('DELETE FROM fm_mobility_signatures WHERE contract_id = ?');
            $sDel->bind_param('i', $cid);
            $sDel->execute();
            $sDel->close();
            $cDel = $conn->prepare('DELETE FROM fm_mobility_contracts WHERE id = ? LIMIT 1');
            $cDel->bind_param('i', $cid);
            $cDel->execute();
            $cDel->close();
        }

        $del = $conn->prepare('DELETE FROM francophonie_mobility_applications WHERE id = ? LIMIT 1');
        $del->bind_param('i', $applicationId);
        if (!$del->execute() || $del->affected_rows < 1) {
            throw new RuntimeException('Delete failed');
        }
        $del->close();

        $conn->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Application ' . $ref . ' deleted.',
        ]);
    } catch (Throwable $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);
