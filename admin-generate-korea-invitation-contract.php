<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/korea_invitation_contract_schema.php';

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    exit('Unauthorized access.');
}

if (!isset($conn) || $conn->connect_error) {
    http_response_code(500);
    exit('Database connection error.');
}

kic_contract_ensure_schema($conn);

$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'];
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$contractPage = $basePath . '/korea-invitation-contract.php';
$baseUrl      = "{$scheme}://{$host}{$contractPage}";

$contractLink = null;
$message      = null;
$studentId    = !empty($_GET['student_id']) ? (int) $_GET['student_id'] : (!empty($_POST['student_id']) ? (int) $_POST['student_id'] : 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentId = !empty($_POST['student_id']) ? (int) $_POST['student_id'] : null;

    if ($studentId) {
        $stmt = $conn->prepare("
            SELECT contract_token
            FROM korea_invitation_contracts
            WHERE student_id = ?
              AND status IN ('draft','signed')
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existing) {
            $contractLink = $baseUrl . '?token=' . $existing['contract_token'];
            $message = 'Existing Korea invitation contract found. Reusing the same link.';
        }
    }

    if (!$contractLink) {
        $contractToken = bin2hex(random_bytes(32));
        $studentIdForBind = $studentId ?: null;

        $stmt = $conn->prepare("
            INSERT INTO korea_invitation_contracts
            (contract_token, student_id, status, created_at)
            VALUES (?, ?, 'draft', NOW())
        ");
        $stmt->bind_param('si', $contractToken, $studentIdForBind);
        $stmt->execute();
        $stmt->close();

        $contractLink = $baseUrl . '?token=' . $contractToken;
        $message = 'New Korea invitation contract issued successfully.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Issue Korea Invitation Contract</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
:root { --primary:#427431; --success:#28a745; --bg:#f8fafc; --card:#fff; --text-muted:#6c757d; }
body { font-family:'Inter',sans-serif; background:var(--bg); margin:0; }
.container { max-width:620px; margin:80px auto; background:var(--card); padding:32px; border-radius:14px; box-shadow:0 20px 40px rgba(0,0,0,.08); border-top:4px solid var(--primary); }
h1 { text-align:center; margin-bottom:6px; }
.subtitle { text-align:center; color:var(--text-muted); margin-bottom:24px; font-size:14px; }
.form-group { margin-bottom:18px; }
.form-group label { display:block; font-weight:600; margin-bottom:8px; font-size:13px; }
.form-group input { width:100%; padding:12px; border:2px solid #e5e7eb; border-radius:8px; box-sizing:border-box; }
.btn { width:100%; padding:14px; border:none; border-radius:8px; font-size:16px; font-weight:600; cursor:pointer; background:var(--primary); color:#fff; }
.alert-success { background:#e6f4ea; color:#1e5631; padding:14px; border-radius:8px; margin-bottom:18px; font-size:14px; }
.link-box { margin-top:24px; padding:18px; background:#f7faf7; border-radius:10px; border:1px solid #d4e4d4; }
.link-box input { width:100%; padding:12px; border:2px solid #e5e7eb; border-radius:8px; margin-bottom:10px; box-sizing:border-box; }
.btn-copy { background:var(--success); color:#fff; width:100%; padding:12px; border:none; border-radius:8px; font-weight:600; cursor:pointer; }
</style>
</head>
<body>
<?php include 'header.php'; ?>
<main class="container">
    <div style="text-align:center;margin-bottom:12px;font-size:12px;font-weight:600;color:var(--primary);">🇰🇷 Korea Invitation Contract</div>
    <h1>Issue Contract Link</h1>
    <p class="subtitle">Generate a secure signing link for the South Korea Event Attendance Service Agreement.</p>

    <?php if ($message): ?>
        <div class="alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="form-group">
            <label for="student_id">Student / Applicant ID (optional)</label>
            <input type="number" name="student_id" id="student_id" min="1" placeholder="student_applications.id for auto-fill" value="<?= $studentId > 0 ? $studentId : '' ?>">
        </div>
        <button class="btn" type="submit">Issue / Retrieve Contract Link</button>
    </form>

    <?php if ($contractLink): ?>
        <div class="link-box">
            <label for="contractLink">Contract Signing Link</label>
            <input type="text" id="contractLink" value="<?= htmlspecialchars($contractLink) ?>" readonly>
            <button class="btn-copy" type="button" onclick="copyLink()">Copy Link</button>
        </div>
    <?php endif; ?>
</main>
<?php include 'footer.php'; ?>
<script>
function copyLink() {
    const input = document.getElementById('contractLink');
    input.select();
    navigator.clipboard?.writeText(input.value).catch(() => document.execCommand('copy'));
    if (!navigator.clipboard) document.execCommand('copy');
    alert('Contract link copied.');
}
</script>
</body>
</html>
