<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=UTF-8');

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/helpers/role.php';
require_once dirname(__DIR__) . '/helpers/commission_requests_schema.php';

function respond(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$adminId = (int) ($_SESSION['admin_id'] ?? $_SESSION['id'] ?? 0);
if ($adminId < 1) {
    respond(['ok' => false, 'error' => 'Not authenticated.'], 401);
}

$st = $conn->prepare('SELECT role FROM admins WHERE id = ? LIMIT 1');
if (!$st) {
    respond(['ok' => false, 'error' => 'Database error.'], 500);
}
$st->bind_param('i', $adminId);
$st->execute();
$roleRow = $st->get_result()->fetch_assoc();
$st->close();
$role = (string) ($roleRow['role'] ?? '');
if (!pcvc_is_superadmin_role($role)) {
    respond(['ok' => false, 'error' => 'Only superadmin can update commission requests.'], 403);
}

$raw = file_get_contents('php://input');
$input = json_decode(is_string($raw) ? $raw : '', true);
if (!is_array($input)) {
    respond(['ok' => false, 'error' => 'Invalid JSON.'], 400);
}

$csrf = isset($input['csrf']) && is_string($input['csrf']) ? $input['csrf'] : '';
$sess = $_SESSION['commission_admin_csrf'] ?? '';
if ($sess === '' || !hash_equals((string) $sess, $csrf)) {
    respond(['ok' => false, 'error' => 'Invalid security token. Reload the page.'], 403);
}

$allowedStatus = [
    'pending', 'under_review', 'approved', 'paid_partial', 'paid_full', 'rejected',
];

$id = isset($input['id']) ? (int) $input['id'] : 0;
if ($id < 1) {
    respond(['ok' => false, 'error' => 'Invalid request id.'], 400);
}

pcvc_ensure_commission_requests_schema($conn);

$notifyEmail = !empty($input['notify_email']);
$notifyWhatsapp = !empty($input['notify_whatsapp']);
if (!empty($input['notify_requester']) && !isset($input['notify_email']) && !isset($input['notify_whatsapp'])) {
    $notifyEmail = true;
}
$notifyMessage = isset($input['notify_message']) && is_string($input['notify_message'])
    ? trim($input['notify_message'])
    : '';
if ($notifyMessage === '' && isset($input['rejection_reason']) && is_string($input['rejection_reason'])) {
    $notifyMessage = trim($input['rejection_reason']);
}

$newStatus = isset($input['request_status']) && is_string($input['request_status'])
    ? trim($input['request_status'])
    : '';
if ($newStatus === '' || !in_array($newStatus, $allowedStatus, true)) {
    respond(['ok' => false, 'error' => 'Invalid status.'], 400);
}

$internal = '';
$touchInternal = array_key_exists('internal_note', $input);
if ($touchInternal && is_string($input['internal_note'])) {
    $internal = trim($input['internal_note']);
}

if ($newStatus === 'rejected' && ($notifyEmail || $notifyWhatsapp) && $notifyMessage === '') {
    respond(['ok' => false, 'error' => 'Enter a rejection reason before sending email or WhatsApp.'], 400);
}

$q = $conn->prepare(
    'SELECT id, email, first_name, last_name, phone, recruited_name, request_status FROM commission_requests WHERE id = ? LIMIT 1'
);
$q->bind_param('i', $id);
$q->execute();
$row = $q->get_result()->fetch_assoc();
$q->close();
if (!$row) {
    respond(['ok' => false, 'error' => 'Request not found.'], 404);
}

if ($touchInternal) {
    if ($newStatus === 'rejected') {
        $upd = $conn->prepare(
            'UPDATE commission_requests SET request_status = ?, internal_note = NULLIF(?, ""), rejection_reason = NULLIF(?, "") WHERE id = ? LIMIT 1'
        );
        if (!$upd) {
            respond(['ok' => false, 'error' => 'Database prepare failed.'], 500);
        }
        $upd->bind_param('sssi', $newStatus, $internal, $notifyMessage, $id);
    } else {
        $upd = $conn->prepare(
            'UPDATE commission_requests SET request_status = ?, internal_note = NULLIF(?, ""), rejection_reason = NULL WHERE id = ? LIMIT 1'
        );
        if (!$upd) {
            respond(['ok' => false, 'error' => 'Database prepare failed.'], 500);
        }
        $upd->bind_param('ssi', $newStatus, $internal, $id);
    }
} else {
    if ($newStatus === 'rejected') {
        $upd = $conn->prepare(
            'UPDATE commission_requests SET request_status = ?, rejection_reason = NULLIF(?, "") WHERE id = ? LIMIT 1'
        );
        if (!$upd) {
            respond(['ok' => false, 'error' => 'Database prepare failed.'], 500);
        }
        $upd->bind_param('ssi', $newStatus, $notifyMessage, $id);
    } else {
        $upd = $conn->prepare(
            'UPDATE commission_requests SET request_status = ?, rejection_reason = NULL WHERE id = ? LIMIT 1'
        );
        if (!$upd) {
            respond(['ok' => false, 'error' => 'Database prepare failed.'], 500);
        }
        $upd->bind_param('si', $newStatus, $id);
    }
}
if (!$upd->execute()) {
    $upd->close();
    respond(['ok' => false, 'error' => 'Update failed.'], 500);
}
$upd->close();

$labels = [
    'pending' => 'Pending',
    'under_review' => 'Under review',
    'approved' => 'Approved',
    'paid_partial' => 'Paid (partial)',
    'paid_full' => 'Paid in full',
    'rejected' => 'Rejected',
];
$label = $labels[$newStatus] ?? $newStatus;

$notifyResult = null;
if ($notifyEmail || $notifyWhatsapp) {
    try {
        require_once dirname(__DIR__) . '/helpers/commission_status_notify.php';
        $notifyResult = pcvc_notify_commission_request_change(
            $conn,
            $id,
            $newStatus,
            $label,
            $notifyEmail,
            $notifyWhatsapp,
            $notifyMessage
        );
    } catch (Throwable $e) {
        error_log('[commission-admin-update] notify: ' . $e->getMessage());
    }
}

$out = ['ok' => true, 'request_status' => $newStatus];
if ($notifyResult !== null) {
    $out['notify'] = $notifyResult;
}
respond($out);
