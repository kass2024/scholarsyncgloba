<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/agent_contract_schema.php';
require_once __DIR__ . '/helpers/mail_smtp.php';

header('Content-Type: application/json');

agent_contract_ensure_schema($conn);

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

if (!isset($_POST['contract_id']) || !ctype_digit($_POST['contract_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid contract ID']);
    exit;
}

$contractId = (int) $_POST['contract_id'];

$stmt = $conn->prepare("
    SELECT pdf_path, agent_name, agent_email
    FROM agent_contracts
    WHERE id = ? AND status = 'signed'
    LIMIT 1
");
$stmt->bind_param('i', $contractId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['success' => false, 'error' => 'Contract not found']);
    exit;
}

if (empty($row['pdf_path']) || !file_exists($row['pdf_path'])) {
    echo json_encode(['success' => false, 'error' => 'PDF file not found']);
    exit;
}

$clientEmail = trim((string) ($row['agent_email'] ?? ''));
$clientName  = trim((string) ($row['agent_name'] ?? ''));

if (!filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid agent email']);
    exit;
}

$safeName = htmlspecialchars($clientName, ENT_QUOTES, 'UTF-8');
$body = "
<div style='font-family:Arial,sans-serif;font-size:14px;color:#222;line-height:1.6'>
    <p><strong>ScholarSync Global</strong></p>
    <p>Dear {$safeName},</p>
    <p>Your <strong>Agent Referral and Commission Agreement</strong> has been successfully signed.</p>
    <p>A copy of the signed contract is attached for your records.</p>
    <p>If you have any questions, please reply to this email.</p>
    <p style='margin-top:30px'>Kind regards,<br><strong>ScholarSync Global</strong></p>
</div>";

$sent = sendSMTPMail(
    $clientEmail,
    'Your Signed Agent Agreement - ScholarSync Global',
    $body,
    [['path' => $row['pdf_path'], 'name' => 'Agent_Referral_Commission_Agreement.pdf']]
);

if (!$sent) {
    echo json_encode(['success' => false, 'error' => 'Email failed to send. Check SMTP settings in .env']);
    exit;
}

$update = $conn->prepare('UPDATE agent_contracts SET sent_at = NOW() WHERE id = ?');
$update->bind_param('i', $contractId);
$update->execute();
$update->close();

echo json_encode(['success' => true]);
exit;
