<?php
declare(strict_types=1);

session_start();

header('Content-Type: application/json; charset=UTF-8');

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/includes/company_branding.php';
require_once dirname(__DIR__) . '/includes/momo_phone.php';
require_once dirname(__DIR__) . '/helpers/role.php';
require_once dirname(__DIR__) . '/payments/mopay/salary_payout.php';
require_once dirname(__DIR__) . '/helpers/mopay_wallet_transactions.php';

function respond(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$adminId = $_SESSION['admin_id'] ?? $_SESSION['id'] ?? null;
if (!$adminId) {
    respond(['ok' => false, 'error' => 'Not authenticated.'], 401);
}

$stmt = $conn->prepare('SELECT role FROM admins WHERE id = ? LIMIT 1');
if (!$stmt) {
    respond(['ok' => false, 'error' => 'Database error.'], 500);
}
$aid = (int) $adminId;
$stmt->bind_param('i', $aid);
$stmt->execute();
$resRole = $stmt->get_result();
$row = $resRole ? $resRole->fetch_assoc() : null;
$stmt->close();

if (!$row || !pcvc_is_superadmin_role($row['role'] ?? '')) {
    respond(['ok' => false, 'error' => 'Only superadmin can send MoMo salary payments.'], 403);
}

$raw = file_get_contents('php://input');
$input = json_decode(is_string($raw) ? $raw : '', true);
if (!is_array($input)) {
    respond(['ok' => false, 'error' => 'Invalid JSON body.'], 400);
}

$csrf = isset($input['csrf']) && is_string($input['csrf']) ? $input['csrf'] : '';
$sess = $_SESSION['payroll_momo_csrf'] ?? '';
if ($sess === '' || !is_string($sess) || !hash_equals($sess, $csrf)) {
    respond(['ok' => false, 'error' => 'Invalid or expired security token. Reload the payroll page and try again.'], 403);
}

$month = isset($input['month']) && is_string($input['month']) ? trim($input['month']) : '';
if ($month === '' || !preg_match('/^\d{4}-\d{2}$/', $month)) {
    respond(['ok' => false, 'error' => 'Invalid month (expected YYYY-MM).'], 400);
}

$items = $input['items'] ?? null;
if (!is_array($items) || $items === []) {
    respond(['ok' => false, 'error' => 'No payment items.'], 400);
}

$paymentsCfg = require dirname(__DIR__) . '/payments/config.php';
$cfg = $paymentsCfg['mopay'] ?? [];
if (!is_array($cfg)) {
    respond(['ok' => false, 'error' => 'MoPay is not configured.'], 500);
}

$merchant = trim((string) ($cfg['receiver_account_no'] ?? ''));
if ($merchant === '') {
    respond(['ok' => false, 'error' => 'MOPAY_RECEIVER_ACCOUNT_NO is not set (merchant / received wallet).'], 500);
}

$ac = $conn->query('SHOW COLUMNS FROM `admins`');
$phoneFields = ['phone', 'mobile', 'phone_number'];
$have = [];
if ($ac instanceof mysqli_result) {
    while ($r = $ac->fetch_assoc()) {
        $f = (string) ($r['Field'] ?? '');
        if ($f !== '') {
            $have[$f] = true;
        }
    }
    $ac->free();
}
$coalesceParts = [];
foreach ($phoneFields as $f) {
    if (isset($have[$f])) {
        $coalesceParts[] = "NULLIF(TRIM(`" . str_replace('`', '', $f) . "`),'')";
    }
}
$phoneSelect = $coalesceParts !== []
    ? 'COALESCE(' . implode(',', $coalesceParts) . ') AS phone_raw'
    : "'' AS phone_raw";

$company = PCVC_COMPANY_DISPLAY_NAME;
$monthLabel = date('M Y', strtotime($month . '-01'));
$baseMessage = $company . ' — Salary ' . $monthLabel;

pcvc_ensure_mopay_wallet_transactions_schema($conn);

$results = [];
$okCount = 0;

foreach ($items as $it) {
    if (!is_array($it)) {
        $results[] = ['ok' => false, 'error' => 'Invalid item'];
        continue;
    }
    $empId = isset($it['admin_id']) ? (int) $it['admin_id'] : 0;
    $amount = isset($it['amount']) ? (int) round((float) $it['amount']) : 0;

    if ($empId < 1 || $amount < 1) {
        pcvc_mopay_wallet_tx_insert($conn, [
            'context_type' => 'payroll_staff',
            'context_id' => $month . '_emp_' . $empId,
            'context_label' => 'Payroll ' . $monthLabel . ' — invalid item',
            'initiated_by_admin_id' => $aid,
            'recipient_msisdn' => null,
            'amount_rwf' => max(0, $amount),
            'status' => 'failed',
            'error_message' => 'Invalid employee or amount.',
            'meta' => ['payroll_month' => $month, 'employee_admin_id' => $empId],
        ]);
        $results[] = ['admin_id' => $empId, 'ok' => false, 'error' => 'Invalid employee or amount.'];
        continue;
    }

    $q = 'SELECT id, full_name, role, ' . $phoneSelect . ' FROM admins WHERE id = ? AND role IN (\'staff\',\'superadmin\') LIMIT 1';

    $st = $conn->prepare($q);
    if (!$st) {
        $results[] = ['admin_id' => $empId, 'ok' => false, 'error' => 'Database error.'];
        continue;
    }
    $st->bind_param('i', $empId);
    $st->execute();
    $er = $st->get_result();
    $emp = $er ? $er->fetch_assoc() : null;
    $st->close();

    if (!$emp) {
        pcvc_mopay_wallet_tx_insert($conn, [
            'context_type' => 'payroll_staff',
            'context_id' => $month . '_emp_' . $empId,
            'context_label' => 'Payroll ' . $monthLabel,
            'initiated_by_admin_id' => $aid,
            'recipient_msisdn' => null,
            'amount_rwf' => $amount,
            'status' => 'failed',
            'error_message' => 'Employee not found or not eligible.',
            'meta' => ['payroll_month' => $month, 'employee_admin_id' => $empId],
        ]);
        $results[] = ['admin_id' => $empId, 'ok' => false, 'error' => 'Employee not found or not eligible.'];
        continue;
    }

    $phoneRaw = trim((string) ($emp['phone_raw'] ?? ''));
    $msisdn = pcvc_normalize_rw_momo_msisdn($phoneRaw);
    if ($msisdn === null) {
        pcvc_mopay_wallet_tx_insert($conn, [
            'context_type' => 'payroll_staff',
            'context_id' => $month . '_emp_' . $empId,
            'context_label' => 'Payroll ' . $monthLabel . ' — ' . ($emp['full_name'] ?? 'Staff'),
            'initiated_by_admin_id' => $aid,
            'recipient_msisdn' => null,
            'amount_rwf' => $amount,
            'status' => 'failed',
            'error_message' => 'No valid Rwanda MoMo number on file.',
            'meta' => [
                'payroll_month' => $month,
                'employee_admin_id' => $empId,
                'employee_name' => (string) ($emp['full_name'] ?? ''),
            ],
        ]);
        $results[] = [
            'admin_id' => $empId,
            'ok' => false,
            'error' => 'No valid Rwanda MoMo number on file for ' . ($emp['full_name'] ?? 'employee') . '.',
        ];
        continue;
    }

    $tid = 'PAYROLL_' . $empId . '_' . preg_replace('/\D+/', '', $month) . '_' . time() . '_' . random_int(1000, 9999);

    $payout = mopay_salary_payout_single($cfg, $merchant, $msisdn, $amount, $tid, $baseMessage);

    if ($payout['ok'] ?? false) {
        $okCount++;
        $effTid = isset($payout['transactionId']) && is_string($payout['transactionId'])
            ? $payout['transactionId']
            : $tid;
        pcvc_mopay_wallet_tx_insert($conn, [
            'context_type' => 'payroll_staff',
            'context_id' => $month . '_emp_' . $empId,
            'context_label' => 'Payroll ' . $monthLabel . ' — ' . ($emp['full_name'] ?? 'Staff'),
            'initiated_by_admin_id' => $aid,
            'recipient_msisdn' => $msisdn,
            'amount_rwf' => $amount,
            'status' => 'success',
            'gateway_transaction_id' => $effTid,
            'mopay_flow' => isset($payout['flow']) ? (string) $payout['flow'] : null,
            'http_status' => isset($payout['http']) ? (int) $payout['http'] : null,
            'gateway_response_json' => json_encode($payout, JSON_UNESCAPED_UNICODE),
            'meta' => [
                'payroll_month' => $month,
                'employee_admin_id' => $empId,
                'employee_name' => (string) ($emp['full_name'] ?? ''),
            ],
        ]);
        $results[] = [
            'admin_id' => $empId,
            'name' => $emp['full_name'] ?? '',
            'ok' => true,
            'amount' => $amount,
            'transactionId' => $effTid,
            'msisdn' => $msisdn,
            'flow' => $payout['flow'] ?? null,
        ];
    } else {
        pcvc_mopay_wallet_tx_insert($conn, [
            'context_type' => 'payroll_staff',
            'context_id' => $month . '_emp_' . $empId,
            'context_label' => 'Payroll ' . $monthLabel . ' — ' . ($emp['full_name'] ?? 'Staff'),
            'initiated_by_admin_id' => $aid,
            'recipient_msisdn' => $msisdn,
            'amount_rwf' => $amount,
            'status' => 'failed',
            'gateway_transaction_id' => $tid,
            'mopay_flow' => isset($payout['flow']) ? (string) $payout['flow'] : null,
            'http_status' => isset($payout['http']) ? (int) $payout['http'] : null,
            'error_message' => (string) ($payout['error'] ?? 'Payment failed'),
            'gateway_response_json' => json_encode($payout, JSON_UNESCAPED_UNICODE),
            'meta' => [
                'payroll_month' => $month,
                'employee_admin_id' => $empId,
                'employee_name' => (string) ($emp['full_name'] ?? ''),
            ],
        ]);
        $results[] = [
            'admin_id' => $empId,
            'name' => $emp['full_name'] ?? '',
            'ok' => false,
            'error' => (string) ($payout['error'] ?? 'Payment failed'),
            'transactionId' => $tid,
        ];
    }
}

respond([
    'ok' => $okCount > 0,
    'partial' => $okCount > 0 && $okCount < count($results),
    'company' => $company,
    'month' => $month,
    'processed' => count($results),
    'succeeded' => $okCount,
    'failed' => count($results) - $okCount,
    'results' => $results,
]);
