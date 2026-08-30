<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mopay_http.php';
require_once __DIR__ . '/auth.php';

$cfg = require __DIR__ . '/config.php';

function html_escape($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$trxId = isset($_GET['trxId']) ? trim((string)$_GET['trxId']) : '';
if ($trxId === '') {
    $trxId = isset($_GET['transactionId']) ? trim((string)$_GET['transactionId']) : '';
}
if ($trxId === '') {
    http_response_code(400);
    header('Content-Type: text/html; charset=UTF-8');
    echo 'Missing trxId. Use: status.php?trxId=...';
    exit;
}

try {
    $authValue = mopay_get_authorization_value($cfg);
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Auth error: ' . htmlspecialchars($e->getMessage());
    exit;
}

$serverBase = $cfg['server_base_url'];
if ($serverBase === '') {
    http_response_code(500);
    echo 'Mopay server base URL not configured (MOPAY_SERVER_BASE_URL).';
    exit;
}

// PDF: GET /api/v1/momo/transactionstatus/{trxId}
$url = $serverBase . '/api/v1/momo/transactionstatus/' . rawurlencode($trxId);

$headers = [
    'Authorization: ' . $authValue,
    'Accept: application/json',
];

$res = mopay_http_request('GET', $url, $headers, null);
$rawBody = is_string($res['body']) ? $res['body'] : '';
$json = null;
if ($rawBody !== '') {
    $json = json_decode($rawBody, true);
}

$logLine = [
    'type' => 'status_check',
    'time' => date('c'),
    'trxId' => $trxId,
    'response_http' => $res['status_code'],
    'response_json' => is_array($json) ? $json : null,
    'response_raw' => $rawBody,
];
file_put_contents(__DIR__ . '/storage/transactions.jsonl', json_encode($logLine) . PHP_EOL, FILE_APPEND | LOCK_EX);

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>MoPay - Status</title>
  <style>
    body{font-family:Arial,sans-serif;background:#f8fafc;margin:0;padding:1rem;color:#111827}
    .box{max-width:900px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:1rem}
    .mono{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace;word-break:break-all}
    .ok{color:#059669;font-weight:700}
    .err{color:#dc2626;font-weight:700}
    a{color:#2563eb}
  </style>
</head>
<body>
  <div class="box">
    <h2>MoPay status</h2>
    <p class="mono"><b>trxId:</b> <?php echo html_escape($trxId); ?></p>
    <p><b>HTTP:</b> <?php echo html_escape($res['status_code']); ?></p>
    <hr />
    <?php if (is_array($json)): ?>
      <pre class="mono" style="white-space:pre-wrap;background:#f3f4f6;border-radius:10px;padding:.75rem;border:1px solid #e5e7eb;"><?php echo html_escape(json_encode($json, JSON_PRETTY_PRINT)); ?></pre>
    <?php else: ?>
      <pre class="mono" style="white-space:pre-wrap;background:#f3f4f6;border-radius:10px;padding:.75rem;border:1px solid #e5e7eb;"><?php echo html_escape($rawBody); ?></pre>
    <?php endif; ?>
    <p style="color:#6b7280;">
      Note: payment is confirmed when your webhook endpoint receives the JWT callback.
    </p>
    <p>
      Back to <a href="index.php">MoPay Payments</a>
    </p>
  </div>
</body>
</html>
<?php
exit;

