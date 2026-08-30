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
$showSuccess = isset($_GET['generated']) && $_GET['generated'] == '1';
$showError = isset($_GET['error']) && !empty($_GET['error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = bin2hex(random_bytes(16));
    
    $stmt = $conn->prepare("
        INSERT INTO partner_contracts
        (contract_token, created_at)
        VALUES (?, NOW())
    ");
    $stmt->bind_param("s", $token);
    
    if ($stmt->execute()) {
        // Detect if running on localhost or production
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $fullPath = $protocol . '://' . $host . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . '/partner-contract.php?token=' . $token;
        
        header("Location: ?generated=1&link=" . urlencode($fullPath));
        exit;
    } else {
        header("Location: ?error=" . urlencode("Failed to generate contract"));
        exit;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Generate Partner Contract | ScholarSync Global</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<style>
:root{
    --blue:#1f4fd8;
    --green:#28a745;
    --bg:#f3f5f9;
    --border:#e5e7eb;
    --text:#374151;
}

*{ box-sizing:border-box; margin:0; padding:0; }

body{
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
    background:var(--bg);
    padding:30px 20px;
    line-height:1.6;
    color:var(--text);
}

.container{
    max-width:800px;
    margin:0 auto;
    background:#fff;
    border-radius:12px;
    box-shadow:0 10px 30px rgba(0,0,0,.08);
    padding:30px;
}

.header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:30px;
    padding-bottom:20px;
    border-bottom:1px solid var(--border);
}

.header h1{
    font-size:24px;
    font-weight:600;
    color:#1f2937;
}

.back-btn{
    background:#eef2ff;
    color:var(--blue);
    padding:8px 16px;
    border-radius:6px;
    text-decoration:none;
    font-weight:500;
    font-size:14px;
    transition: background 0.2s;
}

.back-btn:hover{ background:#e0e7ff; }

.form-group{
    margin-bottom:20px;
}

.form-group label{
    display:block;
    margin-bottom:6px;
    font-weight:500;
    color:#374151;
}

.form-group input{
    width:100%;
    padding:10px 12px;
    border:1px solid var(--border);
    border-radius:6px;
    font-size:14px;
    transition: border-color 0.2s;
}

.form-group input:focus{
    outline:none;
    border-color:var(--blue);
    box-shadow:0 0 0 3px rgba(31, 79, 216, 0.1);
}

.btn{
    padding:10px 20px;
    border:none;
    border-radius:6px;
    font-size:14px;
    font-weight:500;
    cursor:pointer;
    transition: all 0.2s;
}

.btn-primary{
    background:var(--blue);
    color:#fff;
}

.btn-primary:hover{ background:#1e40af; }

.alert{
    padding:12px 16px;
    border-radius:6px;
    margin-bottom:20px;
    font-size:14px;
}

.alert-success{
    background:#d1fae5;
    color:#065f46;
    border:1px solid #a7f3d0;
}

.alert-error{
    background:#fee2e2;
    color:#991b1b;
    border:1px solid #fecaca;
}

.link-result{
    background:#f8fafc;
    border:1px solid #e2e8f0;
    border-radius:6px;
    padding:16px;
    margin-bottom:20px;
}

.link-result p{
    margin:0 0 8px 0;
    font-weight:500;
}

.link-result input{
    width:100%;
    padding:8px 12px;
    border:1px solid #d1d5db;
    border-radius:4px;
    font-family:monospace;
    font-size:13px;
    background:#fff;
}

@media (max-width: 768px) {
    body{ padding: 15px; }
    .container{ padding: 20px; }
    .header{ flex-direction: column; gap:15px; align-items:flex-start; }
}
</style>
</head>
<body>

<script>
function copyLink() {
    const linkInput = document.getElementById('contractLink');
    linkInput.select();
    document.execCommand('copy');
    
    // Show feedback
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="bi bi-check" style="margin-right: 5px;"></i> Copied!';
    button.style.backgroundColor = '#28a745';
    
    setTimeout(() => {
        button.innerHTML = originalText;
        button.style.backgroundColor = '';
    }, 2000);
}
</script>

<div class="container">

<div class="header">
    <h1> Generate Partner Contract</h1>
    <a href="<?= $basePath ?>/admin-dashboard.php" class="back-btn">
        Back to Dashboard
    </a>
</div>

<?php if ($showSuccess && isset($_GET['link'])): ?>
<div class="alert alert-success">
     Contract link generated successfully!
</div>
<div class="link-result">
    <p> Contract Link (share with partner):</p>
    <div style="display: flex; gap: 10px; align-items: center;">
        <input type="text" id="contractLink" value="<?= htmlspecialchars($_GET['link']) ?>" readonly onclick="this.select()" style="flex: 1;">
        <button type="button" onclick="copyLink()" class="btn btn-primary" style="padding: 8px 16px; font-size: 13px;">
            <i class="bi bi-clipboard" style="margin-right: 5px;"></i> Copy
        </button>
    </div>
</div>
<?php endif; ?>

<?php if ($showError): ?>
<div class="alert alert-error">
     <?= htmlspecialchars(urldecode($_GET['error'])) ?>
</div>
<?php endif; ?>

<form method="post">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <button type="submit" class="btn btn-primary">Generate Partner Contract Link</button>
</form>

</div>

</body>
</html>
