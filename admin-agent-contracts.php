<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/agent_contract_schema.php';

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    exit('Unauthorized access');
}

agent_contract_ensure_schema($conn);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$showDeleted = isset($_GET['deleted']) && $_GET['deleted'] == '1';
$showError = isset($_GET['error']) && !empty($_GET['error']);
$filter = ($_GET['status'] ?? 'signed') === 'all' ? 'all' : 'signed';

if ($filter === 'all') {
    $sql = "
        SELECT
            c.id AS contract_id,
            c.contract_token,
            c.status,
            c.agent_type,
            c.agent_name,
            c.agent_email,
            c.effective_date,
            c.signed_at,
            c.invite_sent_at,
            c.sent_at,
            c.created_at
        FROM agent_contracts c
        ORDER BY c.id DESC
    ";
} else {
    $sql = "
        SELECT
            c.id AS contract_id,
            c.contract_token,
            c.status,
            c.agent_type,
            c.agent_name,
            c.agent_email,
            c.effective_date,
            c.signed_at,
            c.invite_sent_at,
            c.sent_at,
            c.created_at
        FROM agent_contracts c
        WHERE c.status = 'signed'
        ORDER BY c.id DESC
    ";
}

$result = $conn->query($sql);
if (!$result) {
    die('An error occurred while fetching contracts.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Agent Contracts | ScholarSync Global</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
:root { --primary:#427431; --green:#28a745; --red:#dc3545; --blue:#2563eb; --bg:#f0f4f0; }
* { box-sizing:border-box; margin:0; padding:0; }
body { font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; background:var(--bg); padding:30px 20px; }
.container { max-width:1300px; margin:0 auto; background:#fff; border-radius:14px; box-shadow:0 15px 40px rgba(66,116,49,.1); padding:25px; border-top:4px solid var(--primary); }
.header { display:flex; justify-content:space-between; align-items:center; margin-bottom:25px; flex-wrap:wrap; gap:15px; }
.header h1 { font-size:22px; color:var(--primary); }
.back-btn, .issue-btn { padding:8px 16px; border-radius:8px; font-size:13px; text-decoration:none; font-weight:600; }
.back-btn { background:#e8f5e9; color:var(--primary); }
.issue-btn { background:var(--primary); color:#fff; }
.alert { padding:14px 18px; border-radius:8px; margin-bottom:20px; font-size:14px; }
.alert-success { background:#d4edda; color:#155724; }
.alert-error { background:#f8d7da; color:#721c24; }
.filters { margin-bottom:16px; display:flex; gap:8px; }
.filters a { padding:6px 12px; border-radius:6px; text-decoration:none; font-size:13px; font-weight:600; background:#eef2f6; color:#374151; }
.filters a.active { background:var(--primary); color:#fff; }
.table-responsive { overflow-x:auto; border-radius:10px; border:1px solid #e8f5e9; }
table { width:100%; border-collapse:collapse; min-width:980px; }
th, td { padding:14px 12px; border-bottom:1px solid #eef2f6; font-size:14px; text-align:left; }
th { background:#f0f7f0; font-size:12px; font-weight:600; text-transform:uppercase; color:var(--primary); }
.actions { display:flex; gap:8px; flex-wrap:wrap; }
.btn-sm { padding:6px 12px; border-radius:6px; font-size:12px; font-weight:600; text-decoration:none; border:none; cursor:pointer; }
.btn-view { background:#eef2ff; color:#1f4fd8; }
.btn-dl { background:var(--green); color:#fff; }
.btn-send { background:var(--blue); color:#fff; }
.btn-del { background:var(--red); color:#fff; }
.badge { display:inline-block; padding:3px 8px; border-radius:999px; font-size:11px; font-weight:700; text-transform:uppercase; }
.badge-signed { background:#dcfce7; color:#166534; }
.badge-draft { background:#fef3c7; color:#92400e; }
.badge-cancelled { background:#fee2e2; color:#991b1b; }
.empty { text-align:center; padding:40px; color:#6b7280; }
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Agent Referral Contracts</h1>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a class="back-btn" href="admin-dashboard.php">← Dashboard</a>
            <a class="issue-btn" href="admin-generate-agent-contract.php">Issue New Link</a>
        </div>
    </div>

    <?php if ($showDeleted): ?>
        <div class="alert alert-success">Contract deleted successfully.</div>
    <?php endif; ?>
    <?php if ($showError): ?>
        <div class="alert alert-error"><?= htmlspecialchars((string) $_GET['error']) ?></div>
    <?php endif; ?>

    <div class="filters">
        <a class="<?= $filter === 'signed' ? 'active' : '' ?>" href="?status=signed">Signed</a>
        <a class="<?= $filter === 'all' ? 'active' : '' ?>" href="?status=all">All (incl. drafts)</a>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Agent / Staff</th>
                    <th>Email</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Signed At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($result->num_rows === 0): ?>
                <tr><td colspan="7" class="empty">No agent contracts yet.</td></tr>
            <?php else: ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <?php
                    $status = (string) ($row['status'] ?? 'draft');
                    $badgeClass = $status === 'signed' ? 'badge-signed' : ($status === 'cancelled' ? 'badge-cancelled' : 'badge-draft');
                    ?>
                    <tr>
                        <td>#<?= (int) $row['contract_id'] ?></td>
                        <td><?= htmlspecialchars((string) ($row['agent_name'] ?: '—')) ?></td>
                        <td><?= htmlspecialchars((string) ($row['agent_email'] ?: '—')) ?></td>
                        <td><?= htmlspecialchars(strtoupper((string) ($row['agent_type'] ?? ''))) ?></td>
                        <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($status) ?></span></td>
                        <td><?= htmlspecialchars((string) ($row['signed_at'] ?? '—')) ?></td>
                        <td class="actions">
                            <a class="btn-sm btn-view" target="_blank" href="<?= $basePath ?>/agent-contract.php?token=<?= urlencode((string) $row['contract_token']) ?>">View</a>
                            <?php if ($status === 'signed'): ?>
                                <a class="btn-sm btn-dl" href="<?= $basePath ?>/admin-download-agent-contract.php?id=<?= (int) $row['contract_id'] ?>">PDF</a>
                                <button type="button" class="btn-sm btn-send" onclick="sendContract(<?= (int) $row['contract_id'] ?>, this)">Email PDF</button>
                            <?php endif; ?>
                            <form method="post" action="<?= $basePath ?>/admin-delete-agent-contract.php" style="display:inline;" onsubmit="return confirm('Delete this contract permanently?');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="contract_id" value="<?= (int) $row['contract_id'] ?>">
                                <button type="submit" class="btn-sm btn-del">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
function sendContract(id, btn) {
  if (!confirm('Email the signed PDF to the agent?')) return;
  const fd = new FormData();
  fd.append('csrf_token', <?= json_encode($_SESSION['csrf_token']) ?>);
  fd.append('contract_id', String(id));
  btn.disabled = true;
  btn.textContent = 'Sending...';
  fetch(<?= json_encode($basePath . '/admin-send-agent-contract.php') ?>, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      alert(data.success ? 'Signed PDF emailed successfully.' : (data.error || 'Send failed'));
      btn.disabled = false;
      btn.textContent = 'Email PDF';
    })
    .catch(() => {
      alert('Network error while sending email.');
      btn.disabled = false;
      btn.textContent = 'Email PDF';
    });
}
</script>
</body>
</html>
