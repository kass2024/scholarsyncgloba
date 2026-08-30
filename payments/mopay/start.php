<?php
require_once __DIR__ . '/../../includes/brand_logo.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mopay_http.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/trace.php';

$cfg = require __DIR__ . '/config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../lib/fx.php';

function respond_json(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$input = $method === 'POST' ? $_POST : $_GET;

$phone = isset($input['phone']) ? trim((string)$input['phone']) : '';
$amount = isset($input['amount']) ? $input['amount'] : '';
$reference = isset($_GET['reference']) ? trim((string)$_GET['reference']) : '';
$mno = isset($input['mno']) ? trim((string)$input['mno']) : ($cfg['default_mno'] ?? 'mtn');

// Optional DB-bound checkout metadata.
$applicationId = isset($input['student_id']) ? (int)$input['student_id'] : 0;
$packageId = isset($input['package_id']) ? (int)$input['package_id'] : 0;
$itemsJson = isset($input['items']) ? (string)$input['items'] : '';
$requestedItemsMap = []; // fee_item_id => requested amount in package currency (null means "pay full remaining")
$requestedItemIds = [];
if ($itemsJson !== '') {
    $decoded = json_decode($itemsJson, true);
    if (is_array($decoded)) {
        foreach ($decoded as $row) {
            // Backward compatible: allow [1,2,3] (ids only)
            // New format: [{ "id": 1, "amount": 50.00 }, ...]
            if (is_array($row) && array_key_exists('id', $row)) {
                $id = (int)($row['id'] ?? 0);
                if ($id > 0) {
                    if (array_key_exists('amount', $row)) {
                        $amt = $row['amount'];
                        $requestedAmount = is_numeric($amt) ? (float)$amt : null;
                        $requestedItemsMap[$id] = $requestedAmount; // null => will be treated as full remaining below
                    } else {
                        $requestedItemsMap[$id] = null; // full remaining
                    }
                    $requestedItemIds[] = $id;
                }
                continue;
            }

            if (is_scalar($row)) {
                $id = (int)$row;
                if ($id > 0) {
                    $requestedItemsMap[$id] = null; // full remaining
                    $requestedItemIds[] = $id;
                }
            }
        }
    }
    $requestedItemIds = array_values(array_unique($requestedItemIds));
}

if ($phone === '' || $amount === '' || !is_numeric($amount)) {
    respond_json(['ok' => false, 'error' => 'Missing/invalid query params. Use ?phone=...&amount=...'], 400);
}

$amountInt = (int)floor((float)$amount);
if ($amountInt <= 0) {
    respond_json(['ok' => false, 'error' => 'Amount must be >= 1'], 400);
}

// If called from fee checkout: compute the amount (in RWF) server-side from selected items.
// We still accept phone from the user, but we do NOT trust the provided amount.
if ($applicationId > 0 && $packageId > 0 && !empty($requestedItemIds)) {
    $stmt = $conn->prepare("SELECT currency FROM fee_packages WHERE id = ? LIMIT 1");
    if (!$stmt) {
        respond_json(['ok' => false, 'error' => 'DB error: fee_packages'], 500);
    }
    $stmt->bind_param('i', $packageId);
    $stmt->execute();
    $pkg = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $pkgCurrency = (string)($pkg['currency'] ?? 'RWF');

    // Load remaining per selected item for this student/package.
    $placeholders = implode(',', array_fill(0, count($requestedItemIds), '?'));
    $types = str_repeat('i', count($requestedItemIds) + 2);
    $params = array_merge([$applicationId, $packageId], $requestedItemIds);

    $sql = "
      SELECT
        fi.id,
        fi.amount,
        COALESCE(SUM(ap.amount_paid), 0) AS paid
      FROM fee_items fi
      LEFT JOIN application_payments ap
        ON ap.fee_item_id = fi.id
       AND ap.application_id = ?
       AND ap.status = 'PAID'
      WHERE fi.package_id = ?
        AND fi.id IN ($placeholders)
      GROUP BY fi.id, fi.amount
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        respond_json(['ok' => false, 'error' => 'DB error: fee_items'], 500);
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    $selected = [];
    $totalBase = 0.0;
    while ($row = $res->fetch_assoc()) {
        $feeItemId = (int)$row['id'];
        $itemTotal = (float)$row['amount'];
        $itemPaid = min((float)$row['paid'], $itemTotal);
        $remaining = max(0.0, $itemTotal - $itemPaid);
        if ($remaining <= 0) continue;

        $requestedAmount = $requestedItemsMap[$feeItemId] ?? null;
        if ($requestedAmount === null) {
            // Full remaining
            $payAmount = $remaining;
        } else {
            // Partial payment requested
            if (!is_finite($requestedAmount)) {
                continue;
            }
            $payAmount = (float)$requestedAmount;
            if ($payAmount <= 0) {
                continue;
            }
            if ($payAmount > $remaining + 1e-9) {
                respond_json([
                    'ok' => false,
                    'error' => 'Requested amount exceeds remaining for fee item ' . $feeItemId,
                ], 422);
            }
        }

        if ($payAmount > 0) {
            $selected[] = [
                'id' => $feeItemId,
                'amount' => $payAmount,
            ];
            $totalBase += $payAmount;
        }
    }
    $stmt->close();

    if ($totalBase <= 0 || empty($selected)) {
        respond_json(['ok' => false, 'error' => 'Selected items are already fully paid or invalid.'], 422);
    }

    $rate = payments_fx_get_rate_to_rwf($pkgCurrency);
    $amountInt = (int)ceil($totalBase * $rate);
    if ($amountInt <= 0) {
        respond_json(['ok' => false, 'error' => 'Failed to compute amount in RWF.'], 500);
    }

    // Create a friendly reference if not provided.
    if ($reference === '') {
        $reference = 'SCHOLARSYNC_FEE_' . $applicationId . '_' . date('Ymd_His');
    }

    // Attach metadata for later webhook finalization.
    $_POST['__fee_checkout_meta'] = json_encode([
        'student_id' => $applicationId,
        'package_id' => $packageId,
        'package_currency' => $pkgCurrency,
        'rate_to_rwf' => $rate,
        'items' => $selected,
        'base_total' => $totalBase,
        'rwf_total' => $amountInt,
    ], JSON_UNESCAPED_SLASHES);
}

if ($reference === '') {
    // Match the merchant's expected reference naming style (no "TEST").
    // Example from your ET Id: "SCHOLARSYNCGLOBAL VISA 20260326 081709 ..."
    $reference = 'SCHOLARSYNCGLOBAL VISA ' . date('Ymd') . ' ' . date('His');
}

// Must be unique per attempt (as described in the PDF).
$transactionId = strtoupper(trim($reference));
// For /api/v1/payment: avoid spaces/special chars in transactionId.
$transactionId = preg_replace('/[^A-Z0-9]+/', '_', $transactionId);
$transactionId = trim($transactionId, '_') . '_' . time() . '_' . random_int(1000, 9999);

$serverBase = $cfg['server_base_url'];
if ($serverBase === '') {
    respond_json(['ok' => false, 'error' => 'Mopay server base URL not configured (MOPAY_SERVER_BASE_URL)'], 500);
}

// PDF: POST {server}/api/v2/momo/debit
$useTransferFlow = isset($_GET['use_transfer']) ? ((string)$_GET['use_transfer'] === '1') : true;
$receiverAccountNo = trim((string)($cfg['receiver_account_no'] ?? ''));
$shouldUsePaymentEndpoint = $useTransferFlow && $receiverAccountNo !== '';

// Transfer fees/constraints vary per merchant config; the PDF warns about transfer charges.
// To avoid "debit happened but transfer failed" for tiny amounts, enforce a minimum when using transfer flow.
$minTransferAmount = (int)($cfg['min_transfer_amount'] ?? 1);
$transferFee = (int)($cfg['transfer_fee'] ?? 0);
// Let gateway decide constraints; we only require amount >= 1 above.

$url = $shouldUsePaymentEndpoint
    ? $serverBase . '/api/v1/payment'
    : $serverBase . '/api/v2/momo/debit';

$countryCode = strtolower((string)($cfg['default_country_code'] ?? 'rw'));
$currency = (string)($cfg['default_currency'] ?? 'RWF');

$payload = [
    'account_no' => $phone,
    'payment_type' => 'momo',
    'message' => 'SCHOLARSYNCGLOBAL_VISA',
    'transactionId' => $transactionId,
    'currency' => $currency,
    'amount' => $amountInt,
    'country_code' => $countryCode,
    'mno' => $mno,
];

$transferTransactionId = null;
$transferAmount = null;
if ($shouldUsePaymentEndpoint) {
    $transferTransactionId = $transactionId . '_T';
    // Start with transfer amount == paid amount.
    // If your merchant has hidden charges, we can later adjust via allow_transfer_cap retry logic.
    $transferAmount = $amountInt;
    if ($transferAmount <= 0) {
        respond_json([
            'ok' => false,
            'error' => "Transfer amount invalid: {$transferAmount}. Increase amount or set use_transfer=0.",
        ], 400);
    }

    $payload = [
        'transactionId' => $transactionId,
        'account_no' => $phone,
        'title' => (string)($cfg['payment_title'] ?? 'ScholarSync Service Payment'),
        'details' => (string)($cfg['payment_details'] ?? 'Service payment from customer'),
        'payment_type' => 'momo',
        'amount' => $amountInt,
        'currency' => $currency,
        'message' => 'SCHOLARSYNCGLOBAL_VISA_PAYMENT',
        'transfers' => [
            [
                'transactionId' => $transferTransactionId,
                'account_no' => $receiverAccountNo,
                'payment_type' => 'momo',
                'amount' => $transferAmount,
                'currency' => $currency,
                'message' => 'SCHOLARSYNCGLOBAL_RECEIVER_TRANSFER',
            ],
        ],
    ];
}

$dryRun = isset($_GET['dry_run']) && (string)$_GET['dry_run'] === '1';
if ($dryRun) {
    $logLine = [
        'type' => 'debit_dry_run',
        'time' => date('c'),
        'transactionId' => $transactionId,
        'flow' => $shouldUsePaymentEndpoint ? 'payment_with_transfer' : 'debit_only',
        'request' => $payload,
    ];
    file_put_contents(__DIR__ . '/storage/transactions.jsonl', json_encode($logLine) . PHP_EOL, FILE_APPEND | LOCK_EX);

    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/><title>MoPay dry run</title></head><body style="font-family:Arial,sans-serif;background:#f8fafc;margin:0;padding:1rem;color:#111827"><div style="max-width:900px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:1rem"><h2>MoPay dry run (no request sent)</h2><p><b>transactionId:</b> ' . htmlspecialchars($transactionId) . '</p><p><b>flow:</b> ' . htmlspecialchars($shouldUsePaymentEndpoint ? 'payment_with_transfer' : 'debit_only') . '</p><pre style="background:#f3f4f6;border:1px solid #e5e7eb;border-radius:10px;padding:.75rem;white-space:pre-wrap">' . htmlspecialchars(json_encode($payload, JSON_PRETTY_PRINT)) . '</pre><p>Now you can test the real call by removing <span style="font-family:monospace;">dry_run=1</span>.</p></div></body></html>';
    exit;
}

 $authValue = '';
try {
    $authValue = mopay_get_authorization_value($cfg);
} catch (Throwable $e) {
    respond_json(['ok' => false, 'error' => 'Auth error: ' . $e->getMessage()], 500);
}

$headers = [
    'Authorization: ' . $authValue,
    'Content-Type: application/json; charset=UTF-8',
    'Accept: application/json',
    'category: BIZAO',
];

mopay_trace_log([
    'type' => 'request_prepare',
    'transactionId' => $transactionId,
    'transferTransactionId' => $transferTransactionId,
    'flow' => $shouldUsePaymentEndpoint ? 'payment_with_transfer' : 'debit_only',
    'endpoint' => $url,
    'auth_mode' => mopay_trace_auth_mode($authValue),
    'receiver_account_no' => $receiverAccountNo !== '' ? $receiverAccountNo : null,
    'amount' => $amountInt,
    'transfer_fee' => $transferFee,
    'transfer_amount' => $transferAmount,
    'payload' => $payload,
]);

$res = mopay_http_request('POST', $url, $headers, $payload);

$rawBody = is_string($res['body']) ? $res['body'] : '';
$json = null;
if ($rawBody !== '') {
    $json = json_decode($rawBody, true);
}

// If the gateway enforces "total transfer amount must match paid amount",
// and merchant fee is effectively 0, retry once using transfer_amount == paid amount.
if (
    $shouldUsePaymentEndpoint &&
    $res['status_code'] >= 400 &&
    is_array($json) &&
    isset($json['message']) &&
    is_string($json['message']) &&
    stripos($json['message'], 'Total Transfer amount not match with paid amount') !== false
) {
    // If the gateway rejects transfer amount mismatch, allow transfer cap can disable
    // that constraint (per PDF note).
    $settingsUrl = $serverBase . '/api/v1/user/settings';
    $setAllowTransferCap = mopay_http_request('POST', $settingsUrl, $headers, [
        'id' => 'allow_transfer_cap',
        'value' => true,
    ]);

    mopay_trace_log([
        'type' => 'allow_transfer_cap_attempt',
        'transactionId' => $transactionId,
        'settings_http' => $setAllowTransferCap['status_code'],
        'settings_raw' => (string)($setAllowTransferCap['body'] ?? ''),
    ]);

    $retryPayload = $payload;
    $retryPayload['transfers'][0]['amount'] = $amountInt;
    mopay_trace_log([
        'type' => 'request_retry',
        'transactionId' => $transactionId,
        'reason' => 'transfer_amount_mismatch_retry_equal_paid',
        'original_transfer_amount' => $transferAmount,
        'retry_transfer_amount' => $amountInt,
        'endpoint' => $url,
        'payload' => $retryPayload,
    ]);

    $res = mopay_http_request('POST', $url, $headers, $retryPayload);
    $rawBody = is_string($res['body']) ? $res['body'] : '';
    $json = null;
    if ($rawBody !== '') {
        $json = json_decode($rawBody, true);
    }
    mopay_trace_log([
        'type' => 'request_retry_response',
        'transactionId' => $transactionId,
        'response_http' => $res['status_code'],
        'response_json' => is_array($json) ? $json : null,
        'response_raw' => $rawBody,
    ]);
}

mopay_trace_log([
    'type' => 'request_response',
    'transactionId' => $transactionId,
    'transferTransactionId' => $transferTransactionId,
    'response_http' => $res['status_code'],
    'response_json' => is_array($json) ? $json : null,
    'response_raw' => $rawBody,
]);

// Store the request/response for testing.
$logLine = [
    'type' => 'debit_initiate',
    'time' => date('c'),
    'transactionId' => $transactionId,
    'transferTransactionId' => $transferTransactionId,
    'flow' => $shouldUsePaymentEndpoint ? 'payment_with_transfer' : 'debit_only',
    'fee_checkout_meta' => isset($_POST['__fee_checkout_meta']) ? json_decode((string)$_POST['__fee_checkout_meta'], true) : null,
    'request' => $payload,
    'response_http' => $res['status_code'],
    'response_json' => is_array($json) ? $json : null,
    'response_raw' => $rawBody,
];
$logFile = __DIR__ . '/storage/transactions.jsonl';
file_put_contents($logFile, json_encode($logLine) . PHP_EOL, FILE_APPEND | LOCK_EX);

if ($res['status_code'] < 200 || $res['status_code'] >= 300) {
    // Still return JSON so you can debug quickly.
    respond_json([
        'ok' => false,
        'http_status' => $res['status_code'],
        'response' => is_array($json) ? $json : $rawBody,
        'transactionId' => $transactionId,
    ], 502);
}

$momoRef = is_array($json) && isset($json['momoRef']) ? $json['momoRef'] : null;
$statusDesc = is_array($json) && isset($json['statusDesc']) ? $json['statusDesc'] : null;
$flowLabel = $shouldUsePaymentEndpoint ? 'payment_with_transfer' : 'debit_only';
$receiverConfigured = $shouldUsePaymentEndpoint;

// Simple HTML response (because start is usually called via click/URL).
$trxToQuery = $transactionId;
$queryUrl = 'status.php?trxId=' . urlencode($trxToQuery);

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>MoPay - Start</title>
  <style>
    :root{
      --brand-green:#1f8a4c;
      --brand-red:#e11d48;
      --brand-dark:#0f172a;
      --brand-muted:#6b7280;
      --bg:#f8fafc;
      --card:#ffffff;
      --border:rgba(15,23,42,.12);
      --shadow: 0 18px 50px rgba(2, 6, 23, .10);
      --radius: 18px;
    }
    *{ box-sizing:border-box; }
    body{
      font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, "Noto Sans", "Liberation Sans", sans-serif;
      background:
        radial-gradient(1100px 520px at 15% -10%, rgba(31,138,76,.14), transparent 60%),
        radial-gradient(1000px 520px at 95% 0%, rgba(225,29,72,.12), transparent 55%),
        var(--bg);
      margin:0;
      min-height:100vh;
      display:flex;
      align-items:center;
      justify-content:center;
      padding: 1.25rem;
      color: var(--brand-dark);
    }
    .wrap{ width:100%; max-width: 720px; margin:0 auto; }
    .card{
      background: var(--card);
      border:1px solid var(--border);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 1.25rem;
    }
    @media (min-width: 720px){
      body{ padding:2rem; }
      .card{ padding: 1.6rem 1.7rem; }
    }
    .header{
      display:flex;
      flex-direction:column;
      align-items:center;
      text-align:center;
      gap:.5rem;
      padding-bottom: .9rem;
      border-bottom: 1px solid rgba(15,23,42,.08);
    }
    .logo{ width:72px; height:72px; object-fit:contain; }
    h1{ margin:0; font-size: 1.2rem; letter-spacing:-.02em; }
    .sub{ color: var(--brand-muted); font-size:.95rem; margin-top:.05rem; }
    .pill{
      display:inline-flex; align-items:center; gap:.5rem;
      padding:.45rem .7rem;
      border-radius:999px;
      border:1px solid rgba(15,23,42,.10);
      background: rgba(15,23,42,.03);
      color: rgba(15,23,42,.75);
      font-size:.85rem;
      margin-top:.15rem;
    }
    .mono{font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; word-break: break-word;}
    .content{ padding-top: 1rem; }
    .grid{
      display:grid;
      grid-template-columns: 1fr;
      gap: .9rem;
      margin-top: 1rem;
    }
    @media (min-width: 720px){
      .grid{ grid-template-columns: 1fr 1fr; }
    }
    .kv{
      border:1px solid rgba(15,23,42,.10);
      background: rgba(15,23,42,.02);
      border-radius: 16px;
      padding: .85rem .95rem;
    }
    .k{ font-size:.82rem; color: rgba(15,23,42,.55); font-weight:800; letter-spacing:.01em; text-transform:uppercase; }
    .v{ margin-top:.25rem; font-weight:900; color: rgba(15,23,42,.90); }
    .muted{ color: var(--brand-muted); }
    .actions{ display:flex; gap:.75rem; flex-wrap:wrap; margin-top: 1.1rem; }
    a{ color: inherit; text-decoration:none; }
    .btn{
      flex: 1 1 220px;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      gap:.55rem;
      padding: .95rem 1rem;
      border-radius: 16px;
      border:0;
      background: linear-gradient(90deg, var(--brand-green), var(--brand-red));
      color:#fff;
      font-weight:900;
      cursor:pointer;
      box-shadow: 0 12px 26px rgba(2,6,23,.14);
      transition: transform .08s ease, filter .15s ease;
    }
    .btn:hover{ filter: brightness(1.02); }
    .btn:active{ transform: translateY(1px); }
    .btn.ghost{
      background: transparent;
      color: rgba(15,23,42,.90);
      border: 1px solid rgba(15,23,42,.14);
      box-shadow: none;
      font-weight:900;
    }
    .note{
      margin-top: 1rem;
      border-radius: 16px;
      border: 1px solid rgba(225,29,72,.22);
      background: rgba(225,29,72,.06);
      padding: .9rem 1rem;
    }
    .note strong{ color: rgba(15,23,42,.92); }
  </style>
</head>
<body>
  <?php $logoSrc = scholarsync_brand_logo_href(__DIR__); ?>
  <div class="wrap">
    <div class="card">
      <div class="header">
        <img class="logo" src="<?php echo htmlspecialchars($logoSrc); ?>" alt="ScholarSync Global" width="88" height="88" decoding="async" />
        <h1>Payment request sent</h1>
        <div class="sub">Approve the MoMo prompt on your phone to complete the payment.</div>
        <?php if ($statusDesc !== null): ?>
          <div class="pill">
            <i class="fas fa-clock" style="color:var(--brand-red)"></i>
            Status: <?php echo htmlspecialchars((string)$statusDesc); ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="content">
        <div class="grid">
          <div class="kv">
            <div class="k">Transaction</div>
            <div class="v mono"><?php echo htmlspecialchars($transactionId); ?></div>
          </div>
          <div class="kv">
            <div class="k">Amount</div>
            <div class="v"><?php echo htmlspecialchars((string)$amountInt); ?> <?php echo htmlspecialchars($currency); ?></div>
          </div>
          <div class="kv">
            <div class="k">Phone</div>
            <div class="v mono"><?php echo htmlspecialchars($phone); ?></div>
          </div>
          <?php if ($momoRef !== null): ?>
          <div class="kv">
            <div class="k">MoMo Ref</div>
            <div class="v mono"><?php echo htmlspecialchars((string)$momoRef); ?></div>
          </div>
          <?php endif; ?>
        </div>

        <div class="actions">
          <a class="btn" href="<?php echo htmlspecialchars($queryUrl); ?>">
            <i class="fas fa-sync-alt"></i> Check status
          </a>
          <a class="btn ghost" href="../checkout.php">
            <i class="fas fa-arrow-left"></i> Back
          </a>
        </div>

        <div class="note">
          <strong>No prompt?</strong>
          <div class="muted" style="margin-top:.35rem;">
            If it doesn’t arrive within about 60 seconds, dial
            <span class="mono">*182*7*1momo PIN#</span> (replace <span class="mono">PIN</span> with your MoMo PIN).
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>

<?php
exit;

