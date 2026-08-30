<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/korea_invitation_contract_schema.php';

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    exit('Unauthorized access');
}

kic_contract_ensure_schema($conn);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
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
        s.first_name,
        s.last_name,
        s.email AS student_email
    FROM korea_invitation_contracts c
    LEFT JOIN student_applications s ON s.id = c.student_id
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
<title>Signed Korea Invitation Contracts | ScholarSync Global</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
:root { --primary:#427431; --green:#28a745; --red:#dc3545; --bg:#f0f4f0; }
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
.table-responsive { overflow-x:auto; border-radius:10px; border:1px solid #e8f5e9; }
table { width:100%; border-collapse:collapse; min-width:900px; }
th, td { padding:14px 12px; border-bottom:1px solid #eef2f6; font-size:14px; text-align:left; }
th { background:#f0f7f0; font-size:12px; font-weight:600; text-transform:uppercase; color:var(--primary); }
.actions { display:flex; gap:8px; flex-wrap:wrap; }
.btn-sm { padding:6px 12px; border-radius:6px; font-size:12px; font-weight:600; text-decoration:none; border:none; cursor:pointer; }
.btn-view { background:#eef2ff; color:#1f4fd8; }
.btn-dl { background:var(--green); color:#fff; }
.btn-del { background:var(--red); color:#fff; }
.empty { text-align:center; padding:40px; color:#6b7280; }
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🇰🇷 Signed Korea Invitation Contracts</h1>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a class="back-btn" href="admin-dashboard.php">← Dashboard</a>
            <a class="issue-btn" href="admin-generate-korea-invitation-contract.php">Issue New Link</a>
        </div>
    </div>

    <?php if ($showDeleted): ?>
        <div class="alert alert-success">Contract deleted successfully.</div>
    <?php endif; ?>
    <?php if ($showError): ?>
        <div class="alert alert-error"><?= htmlspecialchars((string) $_GET['error']) ?></div>
    <?php endif; ?>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Client</th>
                    <th>Email</th>
                    <th>Signed At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($result->num_rows === 0): ?>
                <tr><td colspan="5" class="empty">No signed Korea invitation contracts yet.</td></tr>
            <?php else: ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <?php
                    $clientName = trim((string) ($row['external_client_name'] ?? ''));
                    if ($clientName === '') {
                        $clientName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                    }
                    $email = $row['external_client_email'] ?: ($row['student_email'] ?? '');
                    ?>
                    <tr>
                        <td>#<?= (int) $row['contract_id'] ?></td>
                        <td><?= htmlspecialchars($clientName !== '' ? $clientName : '—') ?></td>
                        <td><?= htmlspecialchars($email !== '' ? $email : '—') ?></td>
                        <td><?= htmlspecialchars((string) ($row['signed_at'] ?? '—')) ?></td>
                        <td class="actions">
                            <a class="btn-sm btn-view" target="_blank" href="<?= $basePath ?>/korea-invitation-contract.php?token=<?= urlencode((string) $row['contract_token']) ?>">View</a>
                            <a class="btn-sm btn-dl" href="<?= $basePath ?>/admin-download-korea-invitation-contract.php?id=<?= (int) $row['contract_id'] ?>">PDF</a>
                            <form method="post" action="<?= $basePath ?>/admin-delete-korea-invitation-contract.php" style="display:inline;" onsubmit="return confirm('Delete this contract permanently?');">
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
</body>
</html>
