<?php
declare(strict_types=1);

date_default_timezone_set('UTC');
session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/role.php';
require_once __DIR__ . '/helpers/mopay_wallet_jsonl_backfill.php';
require_once __DIR__ . '/includes/company_branding.php';

$adminId = (int) ($_SESSION['admin_id'] ?? $_SESSION['id'] ?? 0);
if ($adminId < 1) {
    header('Location: admin-login.php');
    exit;
}

$st = $conn->prepare('SELECT role FROM admins WHERE id = ? LIMIT 1');
$st->bind_param('i', $adminId);
$st->execute();
$roleRow = $st->get_result()->fetch_assoc();
$st->close();
if (!pcvc_is_superadmin_role((string) ($roleRow['role'] ?? ''))) {
    http_response_code(403);
    echo 'Superadmin only.';
    exit;
}

$_SESSION['mopay_import_csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['mopay_import_csrf'];

$result = null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $t = isset($_POST['csrf']) ? (string) $_POST['csrf'] : '';
    $sess = $_SESSION['mopay_import_csrf'] ?? '';
    if ($sess === '' || !hash_equals($sess, $t)) {
        $result = ['ok' => false, 'error' => 'Invalid security token. Reload and try again.'];
    } else {
        $result = pcvc_mopay_backfill_from_local_logs($conn);
        $result['ok'] = true;
    }
}

$txPath = __DIR__ . '/payments/mopay/storage/transactions.jsonl';
$whPath = __DIR__ . '/payments/mopay/logs/webhook.log.jsonl';
$txOk = is_readable($txPath);
$whOk = is_readable($whPath);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MoPay JSONL backfill | <?= htmlspecialchars(PCVC_COMPANY_DISPLAY_NAME, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body { font-family: 'Segoe UI', system-ui, sans-serif; background: #f1f5f9; margin: 0; padding: 24px; color: #1e293b; }
    .card { max-width: 720px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 22px; border: 1px solid #e2e8f0; }
    h1 { font-size: 1.2rem; color: #427431; margin: 0 0 10px; }
    p { font-size: 0.92rem; color: #475569; line-height: 1.5; }
    code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 0.85rem; }
    .files { margin: 14px 0; font-size: 0.88rem; }
    .ok { color: #166534; }
    .no { color: #b45309; }
    .btn { background: #427431; color: #fff; border: none; padding: 10px 18px; border-radius: 8px; font-weight: 700; cursor: pointer; }
    .msg { margin-top: 16px; padding: 12px; border-radius: 8px; background: #f0fdf4; border: 1px solid #bbf7d0; font-size: 0.9rem; }
    .err { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
    ul { font-size: 0.82rem; color: #64748b; max-height: 200px; overflow: auto; }
    a { color: #3661B9; }
  </style>
</head>
<body>
<div class="card">
  <h1><i class="fas fa-file-import"></i> JSONL backfill (maintenance)</h1>
  <p class="files" style="margin-bottom:14px;">Merges local log lines into <code>mopay_wallet_transactions</code> when the gateway id is new. Idempotent.</p>
  <div class="files">
    <div><i class="fas fa-file"></i> <code>payments/mopay/storage/transactions.jsonl</code> — <?= $txOk ? '<span class="ok">readable</span>' : '<span class="no">missing / not readable</span>' ?></div>
    <div><i class="fas fa-file"></i> <code>payments/mopay/logs/webhook.log.jsonl</code> — <?= $whOk ? '<span class="ok">readable</span>' : '<span class="no">missing / not readable</span>' ?></div>
  </div>

  <form method="post" action="">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    <button type="submit" class="btn"><i class="fas fa-download"></i> Run import</button>
  </form>

  <?php if (is_array($result)): ?>
    <?php if (!empty($result['ok']) && empty($result['error'])): ?>
    <div class="msg">
      Imported <strong><?= (int) ($result['imported'] ?? 0) ?></strong> row(s). Skipped <strong><?= (int) ($result['skipped'] ?? 0) ?></strong> (already in DB or not applicable).
      <?php if (!empty($result['errors'])): ?>
        <p class="err"><?= htmlspecialchars(implode(' ', $result['errors']), ENT_QUOTES, 'UTF-8') ?></p>
      <?php endif; ?>
      <?php if (!empty($result['detail']) && is_array($result['detail'])): ?>
        <ul>
          <?php foreach (array_slice($result['detail'], 0, 50) as $d): ?>
            <li><?= htmlspecialchars((string) $d, ENT_QUOTES, 'UTF-8') ?></li>
          <?php endforeach; ?>
          <?php if (count($result['detail']) > 50): ?>
            <li>…</li>
          <?php endif; ?>
        </ul>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="msg err"><?= htmlspecialchars((string) ($result['error'] ?? 'Failed'), ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
  <?php endif; ?>

  <p style="margin-top:20px;"><a href="mopay-payment-transactions.php">← MoPay Transactions</a></p>
</div>
</body>
</html>
