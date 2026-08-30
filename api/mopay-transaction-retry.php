<?php
declare(strict_types=1);

/**
 * Retry a failed commission MoMo payout using the same amount stored in the log row.
 * Superadmin only. Does not apply to payroll rows (avoid accidental double salary).
 */
session_start();
header('Content-Type: application/json; charset=UTF-8');

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/helpers/role.php';
require_once dirname(__DIR__) . '/helpers/commission_requests_schema.php';
require_once dirname(__DIR__) . '/helpers/mopay_wallet_transactions.php';
require_once dirname(__DIR__) . '/includes/momo_phone.php';
require_once dirname(__DIR__) . '/includes/company_branding.php';
require_once dirname(__DIR__) . '/payments/mopay/salary_payout.php';

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

$st = $conn->prepare('SELECT role, COALESCE(full_name, email, "") AS fn FROM admins WHERE id = ? LIMIT 1');
if (!$st) {
    respond(['ok' => false, 'error' => 'Database error.'], 500);
}
$st->bind_param('i', $adminId);
$st->execute();
$roleRow = $st->get_result()->fetch_assoc();
$st->close();
if (!pcvc_is_superadmin_role((string) ($roleRow['role'] ?? ''))) {
    respond(['ok' => false, 'error' => 'Forbidden.'], 403);
}

$raw = file_get_contents('php://input');
$input = json_decode(is_string($raw) ? $raw : '', true);
if (!is_array($input)) {
    respond(['ok' => false, 'error' => 'Invalid JSON.'], 400);
}

$csrf = isset($input['csrf']) && is_string($input['csrf']) ? $input['csrf'] : '';
$sess = $_SESSION['mopay_tx_csrf'] ?? '';
if ($sess === '' || !hash_equals((string) $sess, $csrf)) {
    respond(['ok' => false, 'error' => 'Invalid security token. Reload the page.'], 403);
}

$logId = isset($input['log_id']) ? (int) $input['log_id'] : 0;
if ($logId < 1) {
    respond(['ok' => false, 'error' => 'Invalid log.'], 400);
}

pcvc_ensure_mopay_wallet_transactions_schema($conn);
pcvc_ensure_commission_requests_schema($conn);

$q = $conn->prepare(
    'SELECT id, direction, context_type, status, amount_rwf, meta_json, gateway_transaction_id
     FROM mopay_wallet_transactions WHERE id = ? LIMIT 1'
);
$q->bind_param('i', $logId);
$q->execute();
$logRow = $q->get_result()->fetch_assoc();
$q->close();
if (!$logRow) {
    respond(['ok' => false, 'error' => 'Transaction log not found.'], 404);
}
if (($logRow['direction'] ?? '') !== 'outbound' || ($logRow['status'] ?? '') !== 'failed') {
    respond(['ok' => false, 'error' => 'Only failed outbound payouts can be retried from this log.'], 400);
}
if (($logRow['context_type'] ?? '') !== 'commission_momo') {
    respond(['ok' => false, 'error' => 'Retry from log is only enabled for commission MoMo rows. Use Payroll to resend salary.'], 400);
}

$meta = json_decode((string) ($logRow['meta_json'] ?? ''), true);
if (!is_array($meta) || empty($meta['commission_request_id'])) {
    respond(['ok' => false, 'error' => 'Log entry has no commission_request_id metadata.'], 400);
}
$commissionId = (int) $meta['commission_request_id'];
$amountRwf = (int) ($logRow['amount_rwf'] ?? 0);
if ($commissionId < 1 || $amountRwf < 1) {
    respond(['ok' => false, 'error' => 'Invalid logged amount or commission id.'], 400);
}

$cq = $conn->prepare(
    'SELECT id, phone, amount_rwf, paid_rwf_total, request_status
     FROM commission_requests WHERE id = ? LIMIT 1'
);
$cq->bind_param('i', $commissionId);
$cq->execute();
$crow = $cq->get_result()->fetch_assoc();
$cq->close();
if (!$crow) {
    respond(['ok' => false, 'error' => 'Commission request no longer exists.'], 404);
}

$totalDue = (float) ($crow['amount_rwf'] ?? 0);
$paidSoFar = (float) ($crow['paid_rwf_total'] ?? 0);
$remaining = max(0.0, $totalDue - $paidSoFar);
if ($remaining < 1) {
    respond(['ok' => false, 'error' => 'Nothing left to pay for this commission request.'], 400);
}
$sendAmount = min($amountRwf, (int) floor($remaining));
if ($sendAmount < 1) {
    respond(['ok' => false, 'error' => 'Remaining balance is too small.'], 400);
}

$phoneRaw = trim((string) ($crow['phone'] ?? ''));
$msisdn = pcvc_normalize_rw_momo_msisdn($phoneRaw);
if ($msisdn === null) {
    respond(['ok' => false, 'error' => 'Agent phone is not a valid Rwanda MoMo number.'], 400);
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
$tid = mopay_salary_sanitize_transaction_id('COMM_RETRY_' . $commissionId . '_' . time() . '_' . random_int(10000, 99999));
$msg = mopay_salary_sanitize_message($company . ' Commission #' . $commissionId . ' (retry)');

$payout = mopay_salary_payout_single($cfg, $merchant, $msisdn, $sendAmount, $tid, $msg);
if (!($payout['ok'] ?? false)) {
    pcvc_mopay_wallet_tx_insert($conn, [
        'context_type' => 'commission_momo',
        'context_id' => (string) $commissionId,
        'context_label' => 'Commission #' . $commissionId . ' (retry)',
        'initiated_by_admin_id' => $adminId,
        'recipient_msisdn' => $msisdn,
        'amount_rwf' => $sendAmount,
        'status' => 'failed',
        'gateway_transaction_id' => $tid,
        'mopay_flow' => isset($payout['flow']) ? (string) $payout['flow'] : null,
        'http_status' => isset($payout['http']) ? (int) $payout['http'] : null,
        'error_message' => (string) ($payout['error'] ?? 'MoMo payment failed'),
        'gateway_response_json' => json_encode($payout, JSON_UNESCAPED_UNICODE),
        'meta' => ['commission_request_id' => $commissionId, 'retry_of_log_id' => $logId],
        'retry_of_id' => $logId,
    ]);
    respond([
        'ok' => false,
        'error' => (string) ($payout['error'] ?? 'MoMo payment failed'),
    ], 502);
}

$effectiveTid = isset($payout['transactionId']) && is_string($payout['transactionId'])
    ? $payout['transactionId']
    : $tid;

$newPaid = $paidSoFar + $sendAmount;
$newStatus = 'paid_partial';
if ($newPaid + 0.5 >= $totalDue) {
    $newStatus = 'paid_full';
    $newPaid = $totalDue;
}

$u = $conn->prepare(
    'UPDATE commission_requests SET paid_rwf_total = ?, request_status = ?, last_momo_transaction_id = ? WHERE id = ? LIMIT 1'
);
$u->bind_param('dssi', $newPaid, $newStatus, $effectiveTid, $commissionId);
if (!$u->execute()) {
    $u->close();
    respond(['ok' => false, 'error' => 'Payment succeeded but database update failed. Reconcile manually.', 'transactionId' => $effectiveTid], 500);
}
$u->close();

pcvc_mopay_wallet_tx_insert($conn, [
    'context_type' => 'commission_momo',
    'context_id' => (string) $commissionId,
    'context_label' => 'Commission #' . $commissionId . ' (retry)',
    'initiated_by_admin_id' => $adminId,
    'recipient_msisdn' => $msisdn,
    'amount_rwf' => $sendAmount,
    'status' => 'success',
    'gateway_transaction_id' => $effectiveTid,
    'mopay_flow' => isset($payout['flow']) ? (string) $payout['flow'] : null,
    'http_status' => isset($payout['http']) ? (int) $payout['http'] : null,
    'gateway_response_json' => json_encode($payout, JSON_UNESCAPED_UNICODE),
    'meta' => ['commission_request_id' => $commissionId, 'retry_of_log_id' => $logId],
    'retry_of_id' => $logId,
]);

respond([
    'ok' => true,
    'paid_rwf_total' => $newPaid,
    'request_status' => $newStatus,
    'transactionId' => $effectiveTid,
    'flow' => $payout['flow'] ?? null,
    'amount_rwf_sent' => $sendAmount,
]);
