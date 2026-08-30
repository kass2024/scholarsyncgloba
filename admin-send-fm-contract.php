<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/fm_mobility_contract_schema.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

fm_contract_ensure_schema($conn);

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
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
           a.first_name, a.last_name, a.email AS app_email
    FROM fm_mobility_contracts c
    LEFT JOIN francophonie_mobility_applications a ON a.id = c.application_id
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
$clientName  = trim($row['external_client_name'] ?: trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')));

if (!filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid client email']);
    exit;
}

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = 'scholarsyncglobal.ca';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'infos@scholarsyncglobal.ca';
    $mail->Password   = getenv('SMTP_PASSWORD') ?: '';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = 465;
    $mail->CharSet    = 'UTF-8';
    $mail->Encoding   = 'base64';

    $mail->setFrom('infos@scholarsyncglobal.ca', 'ScholarSync Global');
    $mail->addAddress($clientEmail, $clientName);

    $mail->Subject = 'Your Signed Francophonie Mobility Contract - ScholarSync Global';
    $mail->isHTML(true);
    $mail->Body = "
    <div style='font-family:Arial,sans-serif;font-size:14px;color:#222;line-height:1.6'>
        <p><strong>ScholarSync Global</strong></p>
        <p>Dear {$clientName},</p>
        <p>We are pleased to inform you that your Francophonie Mobility Service Agreement has been successfully signed.</p>
        <p>Your signed contract is attached to this email for your records.</p>
        <p>If you have any questions, please reply to this email.</p>
        <p style='margin-top:30px'>Kind regards,<br><strong>ScholarSync Global</strong></p>
    </div>";
    $mail->AltBody = "Dear {$clientName},\n\nYour Francophonie Mobility contract has been signed. Please find the signed agreement attached.\n\nScholarSync Global";
    $mail->addAttachment($row['pdf_path']);
    $mail->send();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Email failed: ' . $e->getMessage()]);
    exit;
}

$update = $conn->prepare('UPDATE fm_mobility_contracts SET sent_at = NOW() WHERE id = ?');
$update->bind_param('i', $contractId);
$update->execute();
$update->close();

echo json_encode(['success' => true]);
exit;
