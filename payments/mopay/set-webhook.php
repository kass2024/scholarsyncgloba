<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mopay_http.php';
require_once __DIR__ . '/auth.php';

$cfg = require __DIR__ . '/config.php';

$secret = (string)($cfg['callback_signing_key'] ?? '');
if ($secret === '') {
    echo 'Missing callback signing key. Set MOPAY_CALLBACK_SIGNING_KEY in .env.';
    exit;
}

// If you pass ?callback_url=... it will be used; otherwise we build it from the current request.
$callbackUrl = isset($_GET['callback_url']) ? trim((string)$_GET['callback_url']) : '';
if ($callbackUrl === '') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host === '') {
        echo 'Missing host to build callback_url. Pass ?callback_url=https://... .';
        exit;
    }
    $callbackUrl = $scheme . '://' . $host . '/payments/mopay/webhook.php';
}

try {
    $authValue = mopay_get_authorization_value($cfg);
} catch (Throwable $e) {
    echo 'Auth error: ' . htmlspecialchars($e->getMessage());
    exit;
}

$serverBase = $cfg['server_base_url'];
if ($serverBase === '') {
    echo 'Missing MOPAY_SERVER_BASE_URL in .env.';
    exit;
}

// PDF suggests settings endpoint: POST /api/v1/user/settings with JSON { id, value }.
$settingsUrl = $serverBase . '/api/v1/user/settings';

$headers = [
    'Authorization: ' . $authValue,
    'Content-Type: application/json; charset=UTF-8',
    'Accept: application/json',
];

// We set both callback_url and callback_signing_key.
// If the API expects different IDs, update the "id" strings below.
$res1 = mopay_http_request('POST', $settingsUrl, $headers, [
    'id' => 'callback_url',
    'value' => $callbackUrl,
]);

$res2 = mopay_http_request('POST', $settingsUrl, $headers, [
    'id' => 'callback_signing_key',
    'value' => $secret,
]);

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>MoPay - Set Webhook</title>
  <style>
    body{font-family:Arial,sans-serif;background:#f8fafc;margin:0;padding:1rem;color:#111827}
    .box{max-width:900px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:1rem}
    pre{background:#f3f4f6;border:1px solid #e5e7eb;border-radius:10px;padding:.75rem;overflow:auto}
  </style>
</head>
<body>
  <div class="box">
    <h2>Webhook setup results</h2>
    <p><b>callback_url:</b> <?= htmlspecialchars($callbackUrl) ?></p>
    <h3>1) Set callback_url</h3>
    <p>HTTP: <?= (int)$res1['status_code'] ?></p>
    <pre><?= htmlspecialchars((string)$res1['body']) ?></pre>
    <h3>2) Set callback_signing_key</h3>
    <p>HTTP: <?= (int)$res2['status_code'] ?></p>
    <pre><?= htmlspecialchars((string)$res2['body']) ?></pre>
  </div>
</body>
</html>
<?php
exit;

