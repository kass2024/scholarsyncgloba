<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers/refund_requests_schema.php';
require_once __DIR__ . '/../helpers/urls.php';
require_once __DIR__ . '/auth.php';

pcvc_ensure_refund_requests_schema($conn);

$pageTitle = 'My Refund Requests';
$accountId = (int) ($_SESSION['student_account_id'] ?? 0);
$email = strtolower(trim((string) ($_SESSION['student_email'] ?? '')));

$statusLabels = [
    'pending' => 'Pending',
    'under_review' => 'Under review',
    'approved' => 'Approved',
    'rejected' => 'Rejected',
    'paid' => 'Refund paid',
];

$requests = [];
if ($email !== '') {
    $st = $conn->prepare('SELECT * FROM refund_requests WHERE LOWER(TRIM(email)) = ? OR student_portal_account_id = ? ORDER BY id DESC');
    if ($st) {
        $st->bind_param('si', $email, $accountId);
        $st->execute();
        $requests = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();
    }
}

require_once __DIR__ . '/layout.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h1 class="h4 fw-bold mb-1">My refund requests</h1>
    <p class="text-muted small mb-0">Track status and comments from our team.</p>
  </div>
  <a href="<?= htmlspecialchars(pcvc_url('/student/refund-request.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-success btn-sm fw-semibold">+ New request</a>
</div>

<?php if ($requests === []): ?>
  <div class="card p-4 text-center text-muted">
    <p class="mb-3">You have not submitted any refund requests yet.</p>
    <a href="<?= htmlspecialchars(pcvc_url('/student/refund-request.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-success">Request a refund</a>
  </div>
<?php else: ?>
  <div class="row g-3">
    <?php foreach ($requests as $r):
      $st = (string) ($r['request_status'] ?? 'pending');
      $badgeClass = match ($st) {
          'approved', 'paid' => 'bg-success',
          'rejected' => 'bg-danger',
          'under_review' => 'bg-primary',
          default => 'bg-warning text-dark',
      };
      $cur = strtoupper(trim((string) ($r['currency'] ?? 'USD')));
      $amt = number_format((float) ($r['amount'] ?? 0), 2) . ' ' . $cur;
      ?>
      <div class="col-12 col-lg-6">
        <div class="card h-100 p-3">
          <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
            <div>
              <div class="fw-bold"><?= htmlspecialchars((string) ($r['reference_id'] ?? '')) ?></div>
              <div class="small text-muted"><?= htmlspecialchars((string) ($r['created_at'] ?? '')) ?></div>
            </div>
            <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($statusLabels[$st] ?? $st) ?></span>
          </div>
          <div class="small mb-1"><span class="text-muted">Service:</span> <?= htmlspecialchars((string) ($r['service_paid_for'] ?? '')) ?></div>
          <div class="small mb-1"><span class="text-muted">Amount:</span> <strong><?= htmlspecialchars($amt) ?></strong></div>
          <div class="small mb-2"><span class="text-muted">Reason:</span> <?= htmlspecialchars(mb_strimwidth((string) ($r['reason'] ?? ''), 0, 120, '…')) ?></div>
          <?php if (!empty($r['admin_comment'])): ?>
            <div class="alert alert-success py-2 px-3 small mb-0">
              <strong>Team comment:</strong> <?= htmlspecialchars((string) $r['admin_comment']) ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/layout_footer.php'; ?>
