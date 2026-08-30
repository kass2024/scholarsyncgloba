<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/eo_contract_schema.php';
require_once __DIR__ . '/helpers/mail_smtp.php';

header('Content-Type: application/json');

eo_contract_ensure_schema($conn);

if (!isset($_SESSION['admin_id']) && empty($_SESSION['id'])) {
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
    SELECT c.pdf_path, c.external_client_name, c.external_client_email,
           a.full_name, a.email AS app_email
    FROM eo_employment_contracts c
    LEFT JOIN employment_opportunities_applications a ON a.id = c.application_id
    WHERE c.id = ? AND c.status = 'signed'
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

$clientEmail = trim($row['external_client_email'] ?: ($row['app_email'] ?? ''));
$clientName  = trim($row['external_client_name'] ?: ($row['full_name'] ?? ''));

if (!filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid client email']);
    exit;
}

$safeName = htmlspecialchars($clientName, ENT_QUOTES, 'UTF-8');
$body = "
<div style='font-family:Arial,sans-serif;font-size:14px;color:#222;line-height:1.6'>
    <p><strong>ScholarSync Global</strong></p>
    <p>Dear {$safeName},</p>
    <p>We are pleased to inform you that your Employment Opportunities Service Agreement has been successfully signed.</p>
    <p>Your signed contract is attached to this email for your records.</p>
    <p>If you have any questions, please reply to this email.</p>
    <p style='margin-top:30px'>Kind regards,<br><strong>ScholarSync Global</strong></p>
</div>";

$sent = sendSMTPMail(
    $clientEmail,
    'Your Signed Employment Opportunities Contract - ScholarSync Global',
    $body,
    [['path' => $row['pdf_path'], 'name' => 'Employment_Opportunities_Contract.pdf']]
);

if (!$sent) {
    echo json_encode(['success' => false, 'error' => 'Email failed to send. Check SMTP settings in .env']);
    exit;
}

$update = $conn->prepare('UPDATE eo_employment_contracts SET sent_at = NOW() WHERE id = ?');
$update->bind_param('i', $contractId);
$update->execute();
$update->close();

echo json_encode(['success' => true]);
exit;
