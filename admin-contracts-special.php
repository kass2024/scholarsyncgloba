<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';
/* =====================================================
   1. ADMIN AUTH
===================================================== */
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    exit('Unauthorized access');
}

/* =====================================================
   2. CSRF TOKEN
===================================================== */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* =====================================================
   3. BASE PATH (LIVE + LOCAL SAFE)
===================================================== */
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

/* =====================================================
   4. FETCH SIGNED CONTRACTS (LOGIC UNCHANGED)
===================================================== */
$sql = "
    SELECT
        c.id AS contract_id,
        c.contract_token,
        c.status,
        c.signed_at,
        c.sent_at,
        s.first_name,
        s.last_name,
        s.email
    FROM student_contracts_special c
    LEFT JOIN student_applications s
        ON s.id = c.student_id
    WHERE c.status = 'signed'
    ORDER BY c.id DESC
";

$result = $conn->query($sql);

if (!$result) {
    die('Database query failed: ' . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Signed Student Contracts</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
:root{
    --blue:#1f4fd8;
    --green:#28a745;
    --teal:#17a2b8;
    --orange:#fd7e14;
    --red:#dc3545;
    --gray:#6c757d;
    --bg:#f3f5f9;
}

*{
    box-sizing:border-box;
}

body{
    font-family:Inter,Segoe UI,Arial,sans-serif;
    background:var(--bg);
    padding:30px;
    margin:0;
}

.container{
    max-width:1300px;
    margin:auto;
    background:#fff;
    border-radius:14px;
    box-shadow:0 15px 40px rgba(0,0,0,.08);
    padding:25px;
}

.header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:20px;
}

.header h1{
    margin:0;
    font-size:22px;
}

.back-btn{
    background:#eef2ff;
    color:var(--blue);
    padding:8px 14px;
    border-radius:8px;
    font-size:13px;
    text-decoration:none;
    font-weight:600;
}

table{
    width:100%;
    border-collapse:collapse;
}

th,td{
    padding:14px 12px;
    border-bottom:1px solid #e6e9ef;
    font-size:14px;
}

th{
    background:#f9fafc;
    font-size:12px;
    text-transform:uppercase;
    letter-spacing:.04em;
}

.status{
    padding:5px 12px;
    border-radius:20px;
    font-size:11px;
    font-weight:700;
}

.status.signed{
    background:#e6f4ea;
    color:#1e7e34;
}

.actions{
    display:flex;
    gap:6px;
    align-items:center;
    flex-wrap:nowrap;
}

.btn{
    border:none;
    padding:7px 12px;
    font-size:12px;
    font-weight:600;
    border-radius:6px;
    color:#fff;
    cursor:pointer;
    white-space:nowrap;
}

.btn-view{ background:var(--blue); }
.btn-pdf{ background:var(--green); }
.btn-send{ background:var(--teal); }
.btn-resend{ background:var(--orange); }
.btn-del{ background:var(--red); }

.small-text{
    font-size:11px;
    color:#666;
    margin-left:6px;
}
</style>

<script>
function sendContract(form, btn){
    if(!confirm("Send contract email to student?")) return false;

    btn.disabled = true;
    btn.innerText = "Sending...";

    fetch(form.action, {
        method: "POST",
        body: new FormData(form)
    })
    .then(() => location.reload())
    .catch(() => {
        alert("Failed to send email");
        btn.disabled = false;
        btn.innerText = "Send";
    });

    return false;
}
</script>
</head>

<body>

<div class="container">

<div class="header">
    <h1>📄 Signed Student Contracts</h1>
    <a href="<?= $basePath ?>/admin-dashboard.php" class="back-btn">
        ← Back to Dashboard
    </a>
</div>

<table>
<thead>
<tr>
    <th>#</th>
    <th>Student</th>
    <th>Email</th>
    <th>Status</th>
    <th>Signed At</th>
    <th>Actions</th>
</tr>
</thead>
<tbody>

<?php if ($result->num_rows === 0): ?>
<tr>
    <td colspan="6" style="text-align:center;color:#777;padding:30px;">
        No signed contracts found.
    </td>
</tr>
<?php endif; ?>

<?php $i = 1; while ($row = $result->fetch_assoc()): ?>
<tr>

<td><?= $i++ ?></td>

<td>
<?= htmlspecialchars(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?>
</td>

<td><?= htmlspecialchars($row['email'] ?? '—') ?></td>

<td>
    <span class="status signed">SIGNED</span>
</td>

<td><?= htmlspecialchars($row['signed_at'] ?? '—') ?></td>

<td class="actions">

<a class="btn btn-view" target="_blank"
   href="<?= $basePath ?>/student-contract-special.php?token=<?= urlencode($row['contract_token']) ?>">
View
</a>

<a class="btn btn-pdf"
   href="<?= $basePath ?>/admin-download-contract-special.php?id=<?= (int)$row['contract_id'] ?>">
PDF
</a>

<form action="<?= $basePath ?>/admin-send-contract-special.php"
      method="post"
      onsubmit="return sendContract(this,this.querySelector('button'))">

<input type="hidden" name="contract_id" value="<?= (int)$row['contract_id'] ?>">
<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

<button class="btn <?= empty($row['sent_at']) ? 'btn-send' : 'btn-resend' ?>">
<?= empty($row['sent_at']) ? 'Send' : 'Resend' ?>
</button>
</form>

<form action="<?= $basePath ?>/admin-delete-contract-special.php"
      method="post"
      onsubmit="return confirm('Delete this contract permanently?')">

<input type="hidden" name="contract_id" value="<?= (int)$row['contract_id'] ?>">
<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

<button class="btn btn-del">Delete</button>
</form>

<?php if (!empty($row['sent_at'])): ?>
<span class="small-text">
    Last sent <?= htmlspecialchars($row['sent_at']) ?>
</span>
<?php endif; ?>

</td>
</tr>
<?php endwhile; ?>

</tbody>
</table>

</div>

</body>
</html>
