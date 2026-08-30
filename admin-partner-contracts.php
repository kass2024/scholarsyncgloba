<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    exit('Unauthorized access');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
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
        c.company_name,
        c.company_email,
        c.company_phone,
        c.representative_name,
        c.representative_title
    FROM partner_contracts c
    WHERE c.status = 'signed'
    ORDER BY c.id DESC
";

$result = $conn->query($sql);

if (!$result) {
    error_log('Database query failed: ' . $conn->error);
    die('An error occurred while fetching contracts. Please try again.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Signed Partner Contracts | ScholarSync Global</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<style>
:root{
    --blue:#1f4fd8;
    --green:#28a745;
    --teal:#17a2b8;
    --orange:#fd7e14;
    --red:#dc3545;
    --gray:#6c757d;
    --bg:#f3f5f9;
    --success-bg:#d4edda;
    --success-text:#155724;
    --success-border:#c3e6cb;
    --error-bg:#f8d7da;
    --error-text:#721c24;
    --error-border:#f5c6cb;
}

*{ box-sizing:border-box; margin:0; padding:0; }

body{
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Inter', Arial, sans-serif;
    background:var(--bg);
    padding:30px 20px;
    margin:0;
    line-height:1.5;
}

.container{
    max-width:1300px;
    margin:0 auto;
    background:#fff;
    border-radius:14px;
    box-shadow:0 15px 40px rgba(0,0,0,.08);
    padding:25px;
}

.header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:25px;
    flex-wrap:wrap;
    gap:15px;
}

.header h1{
    margin:0;
    font-size:24px;
    font-weight:600;
    color:#333;
}

.back-btn{
    background:#eef2ff;
    color:var(--blue);
    padding:8px 16px;
    border-radius:8px;
    font-size:13px;
    text-decoration:none;
    font-weight:600;
    transition: background 0.2s;
}

.back-btn:hover { background: #e0e7ff; }

.alert {
    padding: 16px 20px;
    border-radius: 8px;
    margin-bottom: 25px;
    font-size: 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.alert-success {
    background-color: var(--success-bg);
    color: var(--success-text);
    border: 1px solid var(--success-border);
}

.alert-error {
    background-color: var(--error-bg);
    color: var(--error-text);
    border: 1px solid var(--error-border);
}

.alert .close-btn {
    background: none;
    border: none;
    font-size: 20px;
    cursor: pointer;
    color: inherit;
    opacity: 0.5;
    padding: 0 5px;
    transition: opacity 0.2s;
}

.alert .close-btn:hover { opacity: 1; }

.table-responsive {
    overflow-x: auto;
    border-radius: 10px;
    border: 1px solid #eef2f6;
}

table{
    width:100%;
    border-collapse:collapse;
    min-width: 900px;
}

th, td{
    padding:16px 12px;
    border-bottom:1px solid #eef2f6;
    font-size:14px;
    text-align:left;
}

th{
    background:#f8fafd;
    font-size:12px;
    font-weight:600;
    text-transform:uppercase;
    letter-spacing:0.5px;
    color:#5a6a7e;
}

tr:hover { background-color: #fafbfe; }
tr:last-child td { border-bottom: none; }

.status{
    display: inline-block;
    padding:6px 14px;
    border-radius:30px;
    font-size:12px;
    font-weight:600;
    text-align:center;
    min-width: 80px;
}

.status.signed{
    background:#e6f4ea;
    color:#1e7e34;
}

.actions{
    display:flex;
    gap:8px;
    align-items:center;
    flex-wrap:wrap;
}

.btn{
    border:none;
    padding:8px 14px;
    font-size:12px;
    font-weight:600;
    border-radius:6px;
    color:#fff;
    cursor:pointer;
    white-space:nowrap;
    text-decoration:none;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    transition: all 0.2s;
    min-width: 65px;
    border: 1px solid transparent;
}

.btn:focus-visible {
    outline: 2px solid var(--blue);
    outline-offset: 2px;
}

.btn:disabled { opacity: 0.6; cursor: not-allowed; }

.btn-pdf{ background:var(--green); }
.btn-pdf:hover:not(:disabled) { background: #218838; }

.btn-send{ background:var(--teal); }
.btn-send:hover:not(:disabled) { background: #138496; }

.btn-resend{ background:var(--orange); }
.btn-resend:hover:not(:disabled) { background: #e46a0e; }

.btn-del{ background:var(--red); }
.btn-del:hover:not(:disabled) { background: #c82333; }

.small-text{
    font-size:11px;
    color:#6c757d;
    margin-left:8px;
    white-space:nowrap;
}

.empty-state {
    text-align: center;
    color: #6c757d;
    padding: 50px 20px !important;
    font-size: 16px;
}

.empty-state p { margin-top: 10px; font-size: 14px; }

@media (max-width: 768px) {
    body { padding: 15px; }
    .container { padding: 15px; }
    .header h1 { font-size: 20px; }
}
</style>
</head>
<body>

<div class="container">

<div class="header">
    <h1> Signed Partner Contracts</h1>
    <a href="<?= $basePath ?>/admin-dashboard.php" class="back-btn">
        Back to Dashboard
    </a>
</div>

<?php if ($showSuccess): ?>
<div class="alert alert-success" id="successAlert">
    <span>Email sent successfully!</span>
    <button type="button" class="close-btn" onclick="this.parentElement.remove()" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if ($showDeleted): ?>
<div class="alert alert-success" id="deletedAlert">
    <span>Partner contract deleted successfully.</span>
    <button type="button" class="close-btn" onclick="this.parentElement.remove()" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if ($showError): ?>
<div class="alert alert-error" id="errorAlert">
    <span><?= htmlspecialchars(urldecode((string) $_GET['error']), ENT_QUOTES, 'UTF-8') ?></span>
    <button type="button" class="close-btn" onclick="this.parentElement.remove()" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="table-responsive">
<table>
<thead>
<tr>
    <th>#</th>
    <th>Company</th>
    <th>Representative</th>
    <th>Email</th>
    <th>Status</th>
    <th>Signed At</th>
    <th>Actions</th>
</tr>
</thead>
<tbody>

<?php if ($result->num_rows === 0): ?>
<tr>
    <td colspan="7" class="empty-state">
        <div> No signed partner contracts found</div>
        <p>Contracts will appear here once partners sign them</p>
    </td>
</tr>
<?php endif; ?>

<?php $i = 1; while ($row = $result->fetch_assoc()): ?>
<?php $hasBeenSent = !empty($row['sent_at']); ?>
<tr>
    <td><?= $i++ ?></td>
    <td><?= htmlspecialchars($row['company_name'] ?? '') ?></td>
    <td><?= htmlspecialchars($row['representative_name'] ?? '') ?></td>
    <td><?= htmlspecialchars($row['company_email'] ?? '') ?></td>
    <td><span class="status signed">SIGNED</span></td>
    <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($row['signed_at'] ?? 'now'))) ?></td>
    <td class="actions">
        <a class="btn btn-pdf"
           href="<?= $basePath ?>/admin-download-partner-contract.php?id=<?= (int)$row['contract_id'] ?>"
           target="_blank"
           rel="noopener noreferrer"
           title="Download PDF">
            PDF
        </a>
        <form method="post"
              action="<?= $basePath ?>/admin-delete-partner-contract.php"
              style="display:inline;"
              onsubmit="return confirm('Delete this partner contract permanently? This cannot be undone.');">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="contract_id" value="<?= (int) $row['contract_id'] ?>">
            <button class="btn btn-del" type="submit" title="Delete contract">Delete</button>
        </form>
    </td>
</tr>
<?php endwhile; ?>

</tbody>
</table>
</div>

</div>

</body>
</html>
