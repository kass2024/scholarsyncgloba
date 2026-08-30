<?php
declare(strict_types=1);

ob_start();
session_start();
header('Content-Type: application/json; charset=UTF-8');
@set_time_limit(90);

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/helpers/role.php';
require_once dirname(__DIR__) . '/helpers/refund_requests_schema.php';

function refund_admin_respond(array $data, int $code = 200): void
{
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $adminId = (int) ($_SESSION['admin_id'] ?? $_SESSION['id'] ?? 0);
    if ($adminId < 1) {
        refund_admin_respond(['ok' => false, 'error' => 'Not authenticated. Please reload the admin dashboard and try again.'], 401);
    }

    $st = $conn->prepare('SELECT role FROM admins WHERE id = ? LIMIT 1');
    if (!$st) {
        refund_admin_respond(['ok' => false, 'error' => 'Database error.'], 500);
    }
    $st->bind_param('i', $adminId);
    $st->execute();
    $roleRow = $st->get_result()->fetch_assoc();
    $st->close();
    $role = (string) ($roleRow['role'] ?? $_SESSION['role'] ?? '');
    if (!pcvc_is_superadmin_role($role)) {
        refund_admin_respond(['ok' => false, 'error' => 'Only superadmin can manage refund requests.'], 403);
    }

    $raw = file_get_contents('php://input');
    $input = json_decode(is_string($raw) ? $raw : '', true);
    if (!is_array($input)) {
        refund_admin_respond(['ok' => false, 'error' => 'Invalid request body.'], 400);
    }

    $csrf = isset($input['csrf']) && is_string($input['csrf']) ? $input['csrf'] : '';
    $sess = (string) ($_SESSION['refund_admin_csrf'] ?? '');
    if ($sess === '' || !hash_equals($sess, $csrf)) {
        refund_admin_respond(['ok' => false, 'error' => 'Security token expired. Close this panel, reopen Refund Requests, and try again.'], 403);
    }

    $allowedStatus = ['pending', 'under_review', 'approved', 'rejected', 'paid'];

    $id = isset($input['id']) ? (int) $input['id'] : 0;
    if ($id < 1) {
        refund_admin_respond(['ok' => false, 'error' => 'Invalid request id.'], 400);
    }

    pcvc_ensure_refund_requests_schema($conn);

    $notifyEmail = !empty($input['notify_email']);
    $notifyWhatsapp = !empty($input['notify_whatsapp']);
    $notifyMessage = isset($input['notify_message']) && is_string($input['notify_message'])
        ? trim($input['notify_message'])
        : '';
    $adminComment = isset($input['admin_comment']) && is_string($input['admin_comment'])
        ? trim($input['admin_comment'])
        : '';
    $internalNote = '';
    $touchInternal = array_key_exists('internal_note', $input);
    if ($touchInternal && is_string($input['internal_note'])) {
        $internalNote = trim($input['internal_note']);
    }

    $newStatus = isset($input['request_status']) && is_string($input['request_status'])
        ? trim($input['request_status'])
        : '';
    if ($newStatus === '' || !in_array($newStatus, $allowedStatus, true)) {
        refund_admin_respond(['ok' => false, 'error' => 'Invalid status.'], 400);
    }

    $labels = [
        'pending' => 'Pending',
        'under_review' => 'Under review',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'paid' => 'Refund paid',
    ];
    $label = $labels[$newStatus] ?? $newStatus;

    $commentForStudent = $adminComment !== '' ? $adminComment : $notifyMessage;
    if ($commentForStudent === '' && ($notifyEmail || $notifyWhatsapp)) {
        $commentForStudent = 'Your refund request status is now: ' . $label . '.';
    }

    if ($newStatus === 'rejected' && ($notifyEmail || $notifyWhatsapp) && trim($adminComment) === '') {
        refund_admin_respond(['ok' => false, 'error' => 'Enter a comment to the student before notifying about a rejection.'], 400);
    }

    $q = $conn->prepare('SELECT id, request_status, admin_comment, email, phone FROM refund_requests WHERE id = ? LIMIT 1');
    if (!$q) {
        refund_admin_respond(['ok' => false, 'error' => 'Database error.'], 500);
    }
    $q->bind_param('i', $id);
    $q->execute();
    $row = $q->get_result()->fetch_assoc();
    $q->close();
    if (!$row) {
        refund_admin_respond(['ok' => false, 'error' => 'Request not found.'], 404);
    }

    $oldStatus = (string) ($row['request_status'] ?? 'pending');
    $saveComment = $adminComment !== '' ? $adminComment : $commentForStudent;

    if ($touchInternal) {
        $upd = $conn->prepare(
            'UPDATE refund_requests SET request_status = ?, admin_comment = NULLIF(?, ""), internal_note = NULLIF(?, ""), updated_at = NOW() WHERE id = ? LIMIT 1'
        );
        if (!$upd) {
            refund_admin_respond(['ok' => false, 'error' => 'Database prepare failed: ' . $conn->error], 500);
        }
        $upd->bind_param('sssi', $newStatus, $saveComment, $internalNote, $id);
    } else {
        $upd = $conn->prepare(
            'UPDATE refund_requests SET request_status = ?, admin_comment = NULLIF(?, ""), updated_at = NOW() WHERE id = ? LIMIT 1'
        );
        if (!$upd) {
            refund_admin_respond(['ok' => false, 'error' => 'Database prepare failed: ' . $conn->error], 500);
        }
        $upd->bind_param('ssi', $newStatus, $saveComment, $id);
    }
    if (!$upd->execute()) {
        $err = $upd->error;
        $upd->close();
        refund_admin_respond(['ok' => false, 'error' => 'Update failed: ' . $err], 500);
    }
    $upd->close();

    $logComment = $commentForStudent !== '' ? $commentForStudent : ($internalNote !== '' ? $internalNote : '');
    $log = $conn->prepare(
        'INSERT INTO refund_request_status_logs (refund_request_id, old_status, new_status, admin_id, comment, created_at)
         VALUES (?, ?, ?, ?, NULLIF(?, ""), NOW())'
    );
    if ($log) {
        $log->bind_param('issis', $id, $oldStatus, $newStatus, $adminId, $logComment);
        if (!$log->execute()) {
            error_log('[refund-admin-update] log insert: ' . $log->error);
        }
        $log->close();
    }

    $notifyResult = null;
    if ($notifyEmail || $notifyWhatsapp) {
        require_once dirname(__DIR__) . '/helpers/refund_status_notify.php';
        try {
            $notifyResult = pcvc_notify_refund_request_change(
                $conn,
                $id,
                $label,
                $notifyEmail,
                $notifyWhatsapp,
                $commentForStudent
            );
        } catch (Throwable $notifyEx) {
            error_log('[refund-admin-update] notify: ' . $notifyEx->getMessage());
            $notifyResult = [
                'email' => [
                    'requested' => $notifyEmail,
                    'sent' => $notifyEmail ? false : null,
                    'error' => $notifyEmail ? ('Notify error: ' . $notifyEx->getMessage()) : '',
                    'to' => '',
                ],
                'whatsapp' => [
                    'requested' => $notifyWhatsapp,
                    'sent' => $notifyWhatsapp ? false : null,
                    'method' => '',
                    'error' => $notifyWhatsapp ? ('Notify error: ' . $notifyEx->getMessage()) : '',
                    'to' => '',
                ],
                'env' => function_exists('pcvc_refund_notify_env_diagnosis')
                    ? pcvc_refund_notify_env_diagnosis()
                    : [],
            ];
        }
    }

    $_SESSION['refund_admin_csrf'] = bin2hex(random_bytes(32));

    $out = [
        'ok' => true,
        'request_status' => $newStatus,
        'status_label' => $label,
        'csrf' => $_SESSION['refund_admin_csrf'],
        'message' => 'Refund request saved.',
    ];
    if ($notifyResult !== null) {
        $out['notify'] = $notifyResult;
        $emailOk = !$notifyEmail || !empty($notifyResult['email']['sent']);
        $waOk = !$notifyWhatsapp || !empty($notifyResult['whatsapp']['sent']);
        $out['notify_all_ok'] = $emailOk && $waOk;
        if (!$out['notify_all_ok']) {
            $out['message'] = 'Saved, but one or more notifications failed — see details below.';
        } else {
            $out['message'] = 'Saved and notifications sent successfully.';
        }
    }

    refund_admin_respond($out);
} catch (Throwable $e) {
    error_log('[refund-admin-update] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    refund_admin_respond([
        'ok' => false,
        'error' => 'Server error while saving: ' . $e->getMessage(),
    ], 500);
}
