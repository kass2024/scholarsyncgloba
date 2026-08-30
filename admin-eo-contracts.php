<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/eo_contract_schema.php';

if (!isset($_SESSION['admin_id']) && empty($_SESSION['id'])) {
    http_response_code(403);
    exit('Unauthorized access');
}

eo_contract_ensure_schema($conn);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$showSuccess = isset($_GET['sent']) && $_GET['sent'] == '1';
$showDeleted = isset($_GET['deleted']) && $_GET['deleted'] == '1';
$showError = isset($_GET['error']) && !empty($_GET['error']);

$sql = "
    SELECT
        c.id AS contract_id,
        c.contract_token,
        c.status,
        c.signed_at,
        c.sent_at,
        c.external_client_name,
        c.external_client_email,
        a.reference_id,
        a.full_name,
        a.email AS app_email
    FROM eo_employment_contracts c
    LEFT JOIN employment_opportunities_applications a ON a.id = c.application_id
    WHERE c.status = 'signed'
    ORDER BY c.id DESC
";

$result = $conn->query($sql);
if (!$result) {
    die('An error occurred while fetching contracts.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Signed Employment Opportunities Contracts | ScholarSync Global</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
:root { --primary:#1e4d2b; --green:#28a745; --teal:#17a2b8; --orange:#fd7e14; --red:#dc3545; --bg:#f0f4f0; }
* { box-sizing:border-box; margin:0; padding:0; }
body { font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; background:var(--bg); padding:30px 20px; }
.container { max-width:1300px; margin:0 auto; background:#fff; border-radius:14px; box-shadow:0 15px 40px rgba(30,77,43,.1); padding:25px; border-top:4px solid var(--primary); }
.header { display:flex; justify-content:space-between; align-items:center; margin-bottom:25px; flex-wrap:wrap; gap:15px; }
.header h1 { font-size:22px; color:var(--primary); }
.back-btn { background:#e8f5e9; color:var(--primary); padding:8px 16px; border-radius:8px; font-size:13px; text-decoration:none; font-weight:600; }
.issue-btn { background:var(--primary); color:#fff; padding:8px 16px; border-radius:8px; font-size:13px; text-decoration:none; font-weight:600; }
.alert { padding:14px 18px; border-radius:8px; margin-bottom:20px; font-size:14px; }
.alert-success { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
.alert-error { background:#f8d7da; color:#721c24; }
.table-responsive { overflow-x:auto; border-radius:10px; border:1px solid #e8f5e9; }
table { width:100%; border-collapse:collapse; min-width:900px; }
th, td { padding:14px 12px; border-bottom:1px solid #eef2f6; font-size:14px; text-align:left; }
th { background:#f0f7f0; font-size:12px; font-weight:600; text-transform:uppercase; color:var(--primary); }
tr:hover { background:#fafbfe; }
.status { display:inline-block; padding:5px 12px; border-radius:20px; font-size:12px; font-weight:600; background:#e6f4ea; color:#1e7e34; }
.ref-badge { font-size:11px; background:#e8f5e9; color:var(--primary); padding:2px 8px; border-radius:4px; font-weight:600; }
.actions { display:flex; gap:8px; flex-wrap:wrap; }
.btn { border:none; padding:8px 14px; font-size:12px; font-weight:600; border-radius:6px; color:#fff; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; }
.btn-pdf { background:var(--green); }
.btn-send { background:var(--teal); }
.btn-resend { background:var(--orange); }
.btn-del { background:var(--red); }
.btn:disabled { opacity:.6; cursor:not-allowed; }
.small-text { font-size:11px; color:#6c757d; margin-left:6px; }
.empty-state { text-align:center; color:#6c757d; padding:50px 20px; }
.loading { display:inline-block; width:14px; height:14px; border:2px solid #fff; border-radius:50%; border-top-color:transparent; animation:spin .6s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <h1>🇷🇺 Signed Employment Opportunities Contracts</h1>
    <div style="display:flex;gap:10px;">
      <a class="issue-btn" href="admin-generate-eo-contract.php">+ Issue Contract</a>
      <a class="back-btn" href="admin-dashboard.php">← Dashboard</a>
    </div>
  </div>

  <?php if ($showSuccess): ?>
    <div class="alert alert-success">Contract emailed successfully.</div>
  <?php endif; ?>
  <?php if ($showDeleted): ?>
    <div class="alert alert-success">Contract deleted successfully.</div>
  <?php endif; ?>
  <?php if ($showError): ?>
    <div class="alert alert-error"><?= htmlspecialchars($_GET['error']) ?></div>
  <?php endif; ?>

  <div class="table-responsive">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Client</th>
          <th>Email</th>
          <th>Application Ref</th>
          <th>Signed At</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($result->num_rows === 0): ?>
          <tr><td colspan="7" class="empty-state">No signed contracts yet.</td></tr>
        <?php else: ?>
          <?php while ($row = $result->fetch_assoc()): ?>
            <?php
            $clientName = trim($row['external_client_name'] ?? '');
            if ($clientName === '' && ($row['full_name'] ?? '') !== '') {
                $clientName = trim((string) $row['full_name']);
            }
            $clientEmail = $row['external_client_email'] ?: ($row['app_email'] ?? '');
            ?>
            <tr>
              <td><?= (int) $row['contract_id'] ?></td>
              <td><?= htmlspecialchars($clientName ?: '—') ?></td>
              <td><?= htmlspecialchars($clientEmail) ?></td>
              <td><?php if ($row['reference_id']): ?><span class="ref-badge"><?= htmlspecialchars($row['reference_id']) ?></span><?php else: ?>—<?php endif; ?></td>
              <td><?= $row['signed_at'] ? htmlspecialchars(date('M j, Y H:i', strtotime($row['signed_at']))) : '—' ?></td>
              <td><span class="status">Signed</span><?php if ($row['sent_at']): ?><span class="small-text">Sent <?= date('M j', strtotime($row['sent_at'])) ?></span><?php endif; ?></td>
              <td>
                <div class="actions">
                  <a class="btn btn-pdf" href="admin-download-eo-contract.php?id=<?= (int) $row['contract_id'] ?>" target="_blank">PDF</a>
                  <a class="btn btn-pdf" href="eo-employment-contract.php?token=<?= urlencode($row['contract_token']) ?>" target="_blank">View</a>
                  <form class="send-form" method="post" action="admin-send-eo-contract.php" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="contract_id" value="<?= (int) $row['contract_id'] ?>">
                    <button class="btn <?= $row['sent_at'] ? 'btn-resend' : 'btn-send' ?>" type="button" onclick="sendContract(this)">
                      <?= $row['sent_at'] ? 'Resend' : 'Email' ?>
                    </button>
                  </form>
                  <form method="post" action="admin-delete-eo-contract.php" style="display:inline;" onsubmit="return confirm('Delete this contract permanently?');">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="contract_id" value="<?= (int) $row['contract_id'] ?>">
                    <button class="btn btn-del" type="submit">Delete</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function sendContract(btn) {
  const form = btn.closest('form');
  const orig = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<span class="loading"></span>';
  fetch(form.action, { method: 'POST', body: new FormData(form) })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        window.location.href = 'admin-eo-contracts.php?sent=1';
      } else {
        alert(data.error || 'Failed to send email.');
        btn.disabled = false;
        btn.innerHTML = orig;
      }
    })
    .catch(() => {
      alert('Network error.');
      btn.disabled = false;
      btn.innerHTML = orig;
    });
}
</script>
</body>
</html>
