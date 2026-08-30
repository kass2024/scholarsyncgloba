<?php
declare(strict_types=1);

date_default_timezone_set('UTC');
session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/role.php';
require_once __DIR__ . '/helpers/mopay_wallet_transactions.php';
require_once __DIR__ . '/includes/company_branding.php';

$adminId = (int) ($_SESSION['admin_id'] ?? $_SESSION['id'] ?? 0);
if ($adminId < 1) {
    header('Location: admin-login.php');
    exit;
}

$st = $conn->prepare('SELECT role, COALESCE(full_name, email, "") AS fn FROM admins WHERE id = ? LIMIT 1');
$st->bind_param('i', $adminId);
$st->execute();
$me = $st->get_result()->fetch_assoc();
$st->close();
$role = (string) ($me['role'] ?? '');
if (!pcvc_is_superadmin_role($role)) {
    http_response_code(403);
    echo 'Superadmin only.';
    exit;
}

pcvc_ensure_mopay_wallet_transactions_schema($conn);

$_SESSION['mopay_tx_csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['mopay_tx_csrf'];

$dTo = isset($_GET['to']) ? trim((string) $_GET['to']) : date('Y-m-d');
/** Default: last 365 days so older payouts still appear after deploy. */
$dFrom = isset($_GET['from']) ? trim((string) $_GET['from']) : date('Y-m-d', strtotime('-365 days'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dFrom)) {
    $dFrom = date('Y-m-d', strtotime('-365 days'));
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dTo)) {
    $dTo = date('Y-m-d');
}
$statusF = isset($_GET['st']) ? trim((string) $_GET['st']) : '';
$ctxF = isset($_GET['ctx']) ? trim((string) $_GET['ctx']) : '';

$fromDt = $dFrom . ' 00:00:00';
$toDt = $dTo . ' 23:59:59';

$where = ['t.created_at >= ?', 't.created_at <= ?'];
$types = 'ss';
$params = [$fromDt, $toDt];
if ($statusF !== '' && in_array($statusF, ['success', 'failed', 'pending'], true)) {
    $where[] = 't.status = ?';
    $types .= 's';
    $params[] = $statusF;
}
if ($ctxF !== '') {
    $where[] = 't.context_type = ?';
    $types .= 's';
    $params[] = $ctxF;
}

$sql = 'SELECT t.*, a.full_name AS initiator_name, a.email AS initiator_email
        FROM mopay_wallet_transactions t
        LEFT JOIN admins a ON a.id = t.initiated_by_admin_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY t.id DESC
        LIMIT 1000';

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $rows = [];
}

$sumQ = 'SELECT
  SUM(CASE WHEN t.direction = "outbound" AND t.status = "success" THEN t.amount_rwf ELSE 0 END) AS out_ok_rwf,
  SUM(CASE WHEN t.direction = "outbound" AND t.status = "failed" THEN t.amount_rwf ELSE 0 END) AS out_fail_rwf,
  SUM(CASE WHEN t.direction = "inbound" AND t.status = "success" THEN t.amount_rwf ELSE 0 END) AS in_rwf,
  SUM(CASE WHEN t.direction = "outbound" AND t.status = "success" THEN 1 ELSE 0 END) AS out_ok_n,
  SUM(CASE WHEN t.direction = "outbound" AND t.status = "failed" THEN 1 ELSE 0 END) AS out_fail_n
  FROM mopay_wallet_transactions t
  WHERE ' . implode(' AND ', $where);
$sq = $conn->prepare($sumQ);
$kpis = ['out_ok_rwf' => 0, 'out_fail_rwf' => 0, 'in_rwf' => 0, 'out_ok_n' => 0, 'out_fail_n' => 0];
if ($sq) {
    $sq->bind_param($types, ...$params);
    $sq->execute();
    $kpis = $sq->get_result()->fetch_assoc() ?: $kpis;
    $sq->close();
}

$dailyOut = [];
foreach ($rows as $r) {
    if (($r['direction'] ?? '') !== 'outbound') {
        continue;
    }
    $d = substr((string) ($r['created_at'] ?? ''), 0, 10);
    if ($d === '') {
        continue;
    }
    if (!isset($dailyOut[$d])) {
        $dailyOut[$d] = ['paid' => 0, 'failed' => 0];
    }
    if (($r['status'] ?? '') === 'success') {
        $dailyOut[$d]['paid'] += (int) ($r['amount_rwf'] ?? 0);
    } elseif (($r['status'] ?? '') === 'failed') {
        $dailyOut[$d]['failed'] += (int) ($r['amount_rwf'] ?? 0);
    }
}
krsort($dailyOut);
$dailyOut = array_slice($dailyOut, 0, 14, true);

$colors = ['navy' => '#427431', 'blue' => '#3661B9', 'muted' => '#64748b'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MoPay Transactions | <?= htmlspecialchars(PCVC_COMPANY_DISPLAY_NAME, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    * { box-sizing: border-box; }
    body { font-family: 'Segoe UI', system-ui, sans-serif; background: #f1f5f9; margin: 0; color: #1e293b; }
    .wrap { max-width: 1280px; margin: 0 auto; padding: 20px; }
    h1 { font-size: 1.35rem; color: <?= $colors['navy'] ?>; margin: 0 0 16px; display: flex; align-items: center; gap: 10px; }
    .kpis { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; margin-bottom: 18px; }
    .kpi { background: #fff; border-radius: 12px; padding: 14px 16px; box-shadow: 0 2px 8px rgba(0,0,0,.06); border: 1px solid #e2e8f0; }
    .kpi b { display: block; font-size: 0.72rem; text-transform: uppercase; letter-spacing: .04em; color: <?= $colors['muted'] ?>; margin-bottom: 6px; }
    .kpi span { font-size: 1.25rem; font-weight: 800; color: <?= $colors['navy'] ?>; }
    .filters { background: #fff; padding: 14px 16px; border-radius: 12px; margin-bottom: 16px; display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; border: 1px solid #e2e8f0; }
    .filters label { font-size: 0.75rem; font-weight: 700; color: #64748b; display: block; margin-bottom: 4px; }
    .filters input, .filters select { padding: 8px 10px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 0.9rem; }
    .btn { background: <?= $colors['navy'] ?>; color: #fff; border: none; padding: 9px 16px; border-radius: 8px; font-weight: 700; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
    .btn-outline { background: #fff; color: <?= $colors['navy'] ?>; border: 1px solid #cbd5e1; }
    table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.06); font-size: 0.82rem; }
    th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
    th { background: #f8fafc; font-size: 0.7rem; text-transform: uppercase; letter-spacing: .04em; color: #64748b; }
    tr:hover td { background: #fafafa; }
    .pill { display: inline-block; padding: 2px 8px; border-radius: 999px; font-weight: 700; font-size: 0.72rem; }
    .pill-ok { background: #dcfce7; color: #166534; }
    .pill-fail { background: #fee2e2; color: #991b1b; }
    .pill-pend { background: #fef9c3; color: #854d0e; }
    .pill-out { background: #e0f2fe; color: #0369a1; }
    .pill-in { background: #f3e8ff; color: #6b21a8; }
    .err { color: #991b1b; max-width: 280px; word-break: break-word; }
    .daily { margin-top: 24px; }
    .daily h2 { font-size: 0.95rem; margin: 0 0 10px; color: <?= $colors['navy'] ?>; font-weight: 700; }
    .msisdn-cell { font-family: ui-monospace, 'Cascadia Code', monospace; font-size: 0.9rem; font-weight: 600; color: #0f172a; white-space: nowrap; }
    .msisdn-cell .role { font-size: 0.7rem; font-weight: 600; color: #64748b; margin-top: 2px; display: block; }
    .daily table { margin-top: 8px; }
  </style>
</head>
<body>
<div class="wrap">
  <h1><i class="fas fa-wallet"></i> MoPay Transactions</h1>

  <div class="kpis">
    <div class="kpi"><b>Outbound paid</b><span><?= number_format((float) ($kpis['out_ok_rwf'] ?? 0), 0) ?> RWF</span><small style="color:#64748b;"> <?= (int) ($kpis['out_ok_n'] ?? 0) ?> tx</small></div>
    <div class="kpi"><b>Outbound failed</b><span><?= number_format((float) ($kpis['out_fail_rwf'] ?? 0), 0) ?> RWF</span><small style="color:#64748b;"> <?= (int) ($kpis['out_fail_n'] ?? 0) ?> tx</small></div>
    <div class="kpi"><b>Inbound</b><span><?= number_format((float) ($kpis['in_rwf'] ?? 0), 0) ?> RWF</span></div>
  </div>

  <?php if ($dailyOut !== []): ?>
  <div class="daily">
    <h2>Daily outbound (paid vs failed)</h2>
    <table>
      <thead><tr><th>Date</th><th>Paid (RWF)</th><th>Failed (RWF)</th></tr></thead>
      <tbody>
        <?php foreach ($dailyOut as $dk => $dv): ?>
        <tr>
          <td><?= htmlspecialchars($dk, ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= number_format((int) ($dv['paid'] ?? 0), 0) ?></td>
          <td><?= number_format((int) ($dv['failed'] ?? 0), 0) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <form class="filters" method="get" action="">
    <div>
      <label>From</label>
      <input type="date" name="from" value="<?= htmlspecialchars($dFrom, ENT_QUOTES, 'UTF-8') ?>">
    </div>
    <div>
      <label>To</label>
      <input type="date" name="to" value="<?= htmlspecialchars($dTo, ENT_QUOTES, 'UTF-8') ?>">
    </div>
    <div>
      <label>Status</label>
      <select name="st">
        <option value="">All</option>
        <option value="success" <?= $statusF === 'success' ? 'selected' : '' ?>>Success</option>
        <option value="failed" <?= $statusF === 'failed' ? 'selected' : '' ?>>Failed</option>
        <option value="pending" <?= $statusF === 'pending' ? 'selected' : '' ?>>Pending</option>
      </select>
    </div>
    <div>
      <label>Context</label>
      <select name="ctx">
        <option value="">All</option>
        <option value="commission_momo" <?= $ctxF === 'commission_momo' ? 'selected' : '' ?>>Commission MoMo</option>
        <option value="payroll_staff" <?= $ctxF === 'payroll_staff' ? 'selected' : '' ?>>Payroll</option>
        <option value="fee_checkout" <?= $ctxF === 'fee_checkout' ? 'selected' : '' ?>>Fee checkout</option>
        <option value="mopay_checkout" <?= $ctxF === 'mopay_checkout' ? 'selected' : '' ?>>MoPay checkout</option>
      </select>
    </div>
    <button type="submit" class="btn"><i class="fas fa-filter"></i> Apply</button>
    <a href="mopay-payment-transactions.php" class="btn btn-outline">Reset</a>
  </form>

  <table>
    <thead>
      <tr>
        <th>When (UTC)</th>
        <th>Dir</th>
        <th>Payer / payee MSISDN</th>
        <th>Context</th>
        <th>By</th>
        <th>Amount</th>
        <th>Status</th>
        <th>Gateway / flow</th>
        <th>Error</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php if ($rows === []): ?>
      <tr><td colspan="10" style="text-align:center;padding:28px;color:#94a3b8;">No rows in this range.</td></tr>
      <?php else: ?>
      <?php foreach ($rows as $r):
        $init = trim((string) ($r['initiator_name'] ?? ''));
        if ($init === '') {
            $init = (string) ($r['initiator_email'] ?? '—');
        }
        $dir = (string) ($r['direction'] ?? '');
        $stt = (string) ($r['status'] ?? '');
        $ms = pcvc_mopay_format_msisdn_full($r['recipient_msisdn'] ?? null);
        $msNote = ($dir === 'inbound') ? 'Payer MSISDN' : 'Payee MSISDN';
        ?>
      <tr data-log-id="<?= (int) $r['id'] ?>">
        <td><?= htmlspecialchars(substr((string) ($r['created_at'] ?? ''), 0, 19), ENT_QUOTES, 'UTF-8') ?></td>
        <td><span class="pill <?= $dir === 'inbound' ? 'pill-in' : 'pill-out' ?>"><?= htmlspecialchars($dir, ENT_QUOTES, 'UTF-8') ?></span></td>
        <td class="msisdn-cell"><?= htmlspecialchars($ms, ENT_QUOTES, 'UTF-8') ?><span class="role"><?= htmlspecialchars($msNote, ENT_QUOTES, 'UTF-8') ?></span></td>
        <td><?= htmlspecialchars((string) ($r['context_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?><br><small style="color:#64748b;"><?= htmlspecialchars((string) ($r['context_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small></td>
        <td><?= htmlspecialchars($init, ENT_QUOTES, 'UTF-8') ?> <small style="color:#94a3b8;">#<?= (int) ($r['initiated_by_admin_id'] ?? 0) ?></small></td>
        <td><strong><?= number_format((int) ($r['amount_rwf'] ?? 0), 0) ?></strong> <?= htmlspecialchars((string) ($r['currency'] ?? 'RWF'), ENT_QUOTES, 'UTF-8') ?></td>
        <td>
          <?php
          $pc = 'pill-pend';
          if ($stt === 'success') {
              $pc = 'pill-ok';
          } elseif ($stt === 'failed') {
              $pc = 'pill-fail';
          }
            ?>
          <span class="pill <?= $pc ?>"><?= htmlspecialchars($stt, ENT_QUOTES, 'UTF-8') ?></span>
        </td>
        <td><code style="font-size:0.75rem;"><?= htmlspecialchars(substr((string) ($r['gateway_transaction_id'] ?? ''), 0, 36), ENT_QUOTES, 'UTF-8') ?></code><br><small><?= htmlspecialchars((string) ($r['mopay_flow'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small></td>
        <td class="err"><?= htmlspecialchars(substr((string) ($r['error_message'] ?? ''), 0, 200), ENT_QUOTES, 'UTF-8') ?></td>
        <td>
          <?php if ($dir === 'outbound' && $stt === 'failed' && ($r['context_type'] ?? '') === 'commission_momo'): ?>
          <button type="button" class="btn retry-btn" style="padding:6px 10px;font-size:0.75rem;" data-id="<?= (int) $r['id'] ?>">Retry</button>
          <?php elseif ($dir === 'outbound' && $stt === 'failed' && ($r['context_type'] ?? '') === 'payroll_staff'): ?>
          <a href="admin-payroll.php" class="btn btn-outline" style="padding:6px 10px;font-size:0.75rem;">Payroll</a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<script>
window.MOPAY_TX_CSRF = <?= json_encode($csrf, JSON_UNESCAPED_UNICODE) ?>;
document.querySelectorAll('.retry-btn').forEach(btn => {
  btn.addEventListener('click', async () => {
    const id = parseInt(btn.getAttribute('data-id'), 10);
    if (!id || !confirm('Retry this commission MoMo payout using the same amount as in the log (capped by remaining balance)?')) return;
    btn.disabled = true;
    try {
      const r = await fetch('api/mopay-transaction-retry.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf: window.MOPAY_TX_CSRF, log_id: id })
      });
      const j = await r.json();
      if (j.ok) {
        alert('Paid. Tx: ' + (j.transactionId || ''));
        location.reload();
      } else {
        alert(j.error || 'Failed');
        btn.disabled = false;
      }
    } catch (e) {
      alert('Network error');
      btn.disabled = false;
    }
  });
});
</script>
</body>
</html>
