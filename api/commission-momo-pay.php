<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=UTF-8');

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/helpers/role.php';
require_once dirname(__DIR__) . '/helpers/commission_requests_schema.php';
require_once dirname(__DIR__) . '/includes/momo_phone.php';
require_once dirname(__DIR__) . '/includes/company_branding.php';
require_once dirname(__DIR__) . '/payments/mopay/salary_payout.php';
require_once dirname(__DIR__) . '/helpers/mopay_wallet_transactions.php';

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
if (!pcvc_is_superadmin_role((string) ($roleRow['role'] ?? ''))) {
    respond(['ok' => false, 'error' => 'Only superadmin can send commission MoMo payments.'], 403);
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

$id = isset($input['id']) ? (int) $input['id'] : 0;
$amountRwf = isset($input['amount_rwf']) ? (int) round((float) $input['amount_rwf']) : 0;
if ($id < 1 || $amountRwf < 1) {
    respond(['ok' => false, 'error' => 'Invalid request or amount.'], 400);
}

pcvc_ensure_commission_requests_schema($conn);
pcvc_ensure_mopay_wallet_transactions_schema($conn);

$q = $conn->prepare(
    'SELECT id, phone, email, first_name, last_name, amount_rwf, paid_rwf_total, request_status
     FROM commission_requests WHERE id = ? LIMIT 1'
);
$q->bind_param('i', $id);
$q->execute();
$row = $q->get_result()->fetch_assoc();
$q->close();
if (!$row) {
    respond(['ok' => false, 'error' => 'Commission request not found.'], 404);
}

$totalDue = (float) ($row['amount_rwf'] ?? 0);
$paidSoFar = (float) ($row['paid_rwf_total'] ?? 0);
$remaining = max(0.0, $totalDue - $paidSoFar);
if ($remaining < 1) {
    respond(['ok' => false, 'error' => 'Nothing left to pay for this request.'], 400);
}
if ($amountRwf > $remaining) {
    respond(['ok' => false, 'error' => 'Amount exceeds remaining balance (RWF ' . number_format($remaining, 0) . ').'], 400);
}

$phoneRaw = trim((string) ($row['phone'] ?? ''));
$msisdn = pcvc_normalize_rw_momo_msisdn($phoneRaw);
if ($msisdn === null) {
    respond(['ok' => false, 'error' => 'Agent phone is not a valid Rwanda MoMo number. Update the profile or pay manually.'], 400);
}

$paymentsCfg = require dirname(__DIR__) . '/payments/config.php';
$cfg = $paymentsCfg['mopay'] ?? [];
if (!is_array($cfg)) {
    respond(['ok' => false, 'error' => 'MoPay is not configured.'], 500);
}
$merchant = trim((string) ($cfg['receiver_account_no'] ?? ''));
if ($merchant === '') {
    respond(['ok' => false, 'error' => 'MOPAY_RECEIVER_ACCOUNT_NO is not set.'], 500);
}

$company = PCVC_COMPANY_DISPLAY_NAME;
$tid = mopay_salary_sanitize_transaction_id('COMM_' . $id . '_' . time() . '_' . random_int(10000, 99999));
$msg = mopay_salary_sanitize_message($company . ' Commission #' . $id);

$payout = mopay_salary_payout_single($cfg, $merchant, $msisdn, $amountRwf, $tid, $msg);
if (!($payout['ok'] ?? false)) {
    pcvc_mopay_wallet_tx_insert($conn, [
        'context_type' => 'commission_momo',
        'context_id' => (string) $id,
        'context_label' => 'Commission #' . $id,
        'initiated_by_admin_id' => $adminId,
        'recipient_msisdn' => $msisdn,
        'amount_rwf' => $amountRwf,
        'status' => 'failed',
        'gateway_transaction_id' => $tid,
        'mopay_flow' => isset($payout['flow']) ? (string) $payout['flow'] : null,
        'http_status' => isset($payout['http']) ? (int) $payout['http'] : null,
        'error_message' => (string) ($payout['error'] ?? 'MoMo payment failed'),
        'gateway_response_json' => json_encode($payout, JSON_UNESCAPED_UNICODE),
        'meta' => ['commission_request_id' => $id],
    ]);
    respond([
        'ok' => false,
        'error' => (string) ($payout['error'] ?? 'MoMo payment failed'),
    ], 502);
}

$effectiveTid = isset($payout['transactionId']) && is_string($payout['transactionId'])
    ? $payout['transactionId']
    : $tid;

$newPaid = $paidSoFar + $amountRwf;
$newStatus = 'paid_partial';
if ($newPaid + 0.5 >= $totalDue) {
    $newStatus = 'paid_full';
    $newPaid = $totalDue;
}

$u = $conn->prepare(
    'UPDATE commission_requests SET paid_rwf_total = ?, request_status = ?, last_momo_transaction_id = ? WHERE id = ? LIMIT 1'
);
$u->bind_param('dssi', $newPaid, $newStatus, $effectiveTid, $id);
if (!$u->execute()) {
    $u->close();
    respond(['ok' => false, 'error' => 'Payment succeeded but database update failed. Reconcile manually.', 'transactionId' => $effectiveTid], 500);
}
$u->close();

pcvc_mopay_wallet_tx_insert($conn, [
    'context_type' => 'commission_momo',
    'context_id' => (string) $id,
    'context_label' => 'Commission #' . $id,
    'initiated_by_admin_id' => $adminId,
    'recipient_msisdn' => $msisdn,
    'amount_rwf' => $amountRwf,
    'status' => 'success',
    'gateway_transaction_id' => $effectiveTid,
    'mopay_flow' => isset($payout['flow']) ? (string) $payout['flow'] : null,
    'http_status' => isset($payout['http']) ? (int) $payout['http'] : null,
    'error_message' => null,
    'gateway_response_json' => json_encode($payout, JSON_UNESCAPED_UNICODE),
    'meta' => ['commission_request_id' => $id],
]);

respond([
    'ok' => true,
    'paid_rwf_total' => $newPaid,
    'request_status' => $newStatus,
    'transactionId' => $effectiveTid,
    'flow' => $payout['flow'] ?? null,
]);
