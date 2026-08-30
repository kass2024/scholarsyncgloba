<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/company_branding.php';
require_once __DIR__ . '/helpers/commission_requests_schema.php';

if (empty($_SESSION['username']) && empty($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? 0);
if ($userId < 1) {
    header('Location: admin-login.php');
    exit;
}

pcvc_ensure_commission_requests_schema($conn);

$uidStr = (string) $userId;
$stmt = $conn->prepare(
    'SELECT id, request_status, recruited_name, amount_usd, amount_rwf, submission_date, created_at, comments, rejection_reason
     FROM commission_requests
     WHERE user_id = ? OR CAST(user_id AS UNSIGNED) = ?
     ORDER BY id DESC'
);
$stmt->bind_param('si', $uidStr, $userId);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$labels = [
    'pending' => 'Pending',
    'under_review' => 'Under review',
    'approved' => 'Approved',
    'paid_partial' => 'Paid (partial)',
    'paid_full' => 'Paid in full',
    'rejected' => 'Rejected',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My commission requests | <?= htmlspecialchars(PCVC_COMPANY_DISPLAY_NAME, ENT_QUOTES, 'UTF-8') ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body { background: #f1f5f9; font-family: system-ui, sans-serif; }
    .pcvc-head { background: linear-gradient(135deg, #427431, #3661B9); color: #fff; padding: 1.25rem; border-radius: 12px; margin-bottom: 1.25rem; }
  </style>
</head>
<body class="p-3 p-md-4">
  <div class="container" style="max-width: 960px;">
    <div class="pcvc-head">
      <h1 class="h4 mb-1"><i class="fas fa-list me-2"></i>My commission requests</h1>
      <p class="mb-0 small opacity-90">You can open a request to edit it unless it is <strong>under review</strong>.</p>
    </div>

    <?php if ($rows === []): ?>
      <div class="alert alert-light border">No commission requests found for your account.</div>
      <a class="btn btn-primary" href="Commission-Request.php"><i class="fas fa-plus me-1"></i> New request</a>
    <?php else: ?>
      <div class="table-responsive bg-white rounded-3 shadow-sm">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Status</th>
              <th>Student</th>
              <th class="text-end">Amount</th>
              <th class="text-end">RWF</th>
              <th>Submitted</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r):
              $rid = (int) $r['id'];
              $st = trim((string) ($r['request_status'] ?? 'pending')) ?: 'pending';
              $locked = ($st === 'under_review');
              ?>
              <tr>
                <td class="font-monospace"><?= $rid ?></td>
                <td>
                  <span class="badge <?= $st === 'rejected' ? 'bg-danger' : ($locked ? 'bg-warning text-dark' : 'bg-secondary') ?>">
                    <?= htmlspecialchars($labels[$st] ?? $st, ENT_QUOTES, 'UTF-8') ?>
                  </span>
                  <?php if ($st === 'rejected' && trim((string) ($r['rejection_reason'] ?? '')) !== ''): ?>
                    <div class="small text-danger mt-1" style="max-width:280px;line-height:1.35;">
                      <strong>Reason:</strong> <?= nl2br(htmlspecialchars((string) $r['rejection_reason'], ENT_QUOTES, 'UTF-8')) ?>
                    </div>
                  <?php endif; ?>
                  <?php if ($locked): ?>
                    <span class="small text-muted d-block">Editing locked</span>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars((string) ($r['recruited_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                <td class="text-end"><?php
                  $cur = strtoupper(trim((string) ($r['commission_currency'] ?? 'USD'))) ?: 'USD';
                  echo htmlspecialchars($cur, ENT_QUOTES, 'UTF-8') . ' ' . number_format((float) ($r['amount_usd'] ?? 0), 2);
                ?></td>
                <td class="text-end"><?= number_format((float) ($r['amount_rwf'] ?? 0), 0) ?></td>
                <td class="small"><?= htmlspecialchars((string) ($r['submission_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                  <a class="btn btn-sm btn-outline-primary" href="commission-request-edit.php?id=<?= $rid ?>">
                    <?= $locked ? '<i class="fas fa-eye"></i> View' : '<i class="fas fa-edit"></i> Edit' ?>
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="mt-3">
        <a class="btn btn-success" href="Commission-Request.php"><i class="fas fa-plus me-1"></i> New commission request</a>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
