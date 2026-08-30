<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';
/* =====================================================
   1. ADMIN SECURITY
===================================================== */
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    exit('Unauthorized access.');
}

/* =====================================================
   2. DATABASE VALIDATION
===================================================== */
if (!isset($conn) || $conn->connect_error) {
    http_response_code(500);
    exit('Database connection error.');
}

/* =====================================================
   3. BUILD BASE URL
===================================================== */
$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'];
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

$contractPage = $basePath . '/student-contract.php';
$baseUrl      = "{$scheme}://{$host}{$contractPage}";

/* =====================================================
   4. INITIAL STATE
===================================================== */
$contractLink = null;
$message      = null;

/* =====================================================
   5. HANDLE REQUEST
===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $studentId = !empty($_POST['student_id']) ? (int) $_POST['student_id'] : null;

    if ($studentId) {
        $stmt = $conn->prepare(
            "SELECT contract_token
             FROM student_contracts
             WHERE student_id = ?
               AND status IN ('draft','signed')
             ORDER BY id DESC
             LIMIT 1"
        );
        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existing) {
            $contractLink = $baseUrl . '?token=' . $existing['contract_token'];
            $message = 'Existing contract found. Reusing the same link.';
        }
    }

    if (!$contractLink) {
        $contractToken = bin2hex(random_bytes(32));

        $stmt = $conn->prepare(
            "INSERT INTO student_contracts
             (contract_token, student_id, status, created_at)
             VALUES (?, ?, 'draft', NOW())"
        );
        $stmt->bind_param('si', $contractToken, $studentId);
        $stmt->execute();
        $stmt->close();

        $contractLink = $baseUrl . '?token=' . $contractToken;
        $message = 'New contract issued successfully.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Issue Student Contract</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<!-- PAGE-SPECIFIC STYLES ONLY -->
<style>
:root {
    --primary: #427431;
    --success: #28a745;
    --bg: #f8fafc;
    --card: #ffffff;
    --text-muted: #6c757d;
}

body {
    font-family: 'Inter', sans-serif;
    background: var(--bg);
}

.container {
    max-width: 620px;
    margin: 80px auto;
    background: var(--card);
    padding: 32px;
    border-radius: 14px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.08);
}

h1 {
    text-align: center;
    margin-bottom: 6px;
}

.subtitle {
    text-align: center;
    color: var(--text-muted);
    margin-bottom: 30px;
    font-size: 14px;
}

.btn {
    width: 100%;
    padding: 14px;
    font-size: 16px;
    border-radius: 8px;
    border: none;
    cursor: pointer;
}

.btn-primary {
    background: var(--primary);
    color: #fff;
}

.btn-success {
    background: var(--success);
    color: #fff;
    margin-top: 10px;
}

.btn-back {
    background: #e9ecef;
    margin-bottom: 20px;
}

.alert-success {
    background: #e6f4ea;
    padding: 12px;
    border-radius: 6px;
    text-align: center;
    margin-bottom: 18px;
}

.link-box {
    margin-top: 25px;
    padding: 16px;
    background: #f7f9fc;
    border-radius: 8px;
}
</style>
</head>

<body>

<?php include 'header.php'; ?>

<main class="container">

    <a href="admin-dashboard.php">
        <button class="btn btn-back" type="button">← Back to Dashboard</button>
    </a>

    <h1>Issue Student Contract</h1>
    <p class="subtitle">Generate or retrieve a persistent student contract link</p>

    <?php if ($message): ?>
        <div class="alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="post">
        <!-- Optional binding -->
        <!-- <input type="hidden" name="student_id" value="123"> -->
        <button class="btn btn-primary" type="submit">📄 Issue / Retrieve Contract</button>
    </form>

    <?php if ($contractLink): ?>
        <div class="link-box">
            <label>Contract Link</label>
            <input type="text" id="contractLink" value="<?= htmlspecialchars($contractLink) ?>" readonly>
            <button class="btn btn-success" type="button" onclick="copyLink()">📋 Copy Link</button>
            <div id="copyMsg" style="display:none;text-align:center;color:green;margin-top:8px;">
                ✔ Contract link copied
            </div>
        </div>
    <?php endif; ?>

</main>

<?php include 'footer.php'; ?>

<script>
function copyLink() {
    const input = document.getElementById('contractLink');
    const msg = document.getElementById('copyMsg');
    input.select();
    document.execCommand('copy');
    msg.style.display = 'block';
    setTimeout(() => msg.style.display = 'none', 2000);
}
</script>

</body>
</html>
